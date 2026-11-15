<?php
declare(strict_types=1);

require_once __DIR__.'/skills.php';

const ACTIVITY_PACK_INTERVAL = 300;
const PACK_EFFECTS = ['food','harvest','visit_sick','mediate','games','leisure','walk','study'];
const PACK_PLACES = ['yard','forest','field','home','market','tavern'];

// Jak často worker kontroluje balíček (sekundy skutečného času). Pro test lze zkrátit proměnnou MINILIDI_PACK_INTERVAL.
function activityPackInterval(): int {
    $value=(int)(getenv('MINILIDI_PACK_INTERVAL')?:ACTIVITY_PACK_INTERVAL);
    return max(2,min(3600,$value));
}

function activityPackPath(): string {
    $path=getenv('MINILIDI_PACK_FILE');
    return is_string($path) && $path!=='' ? $path : __DIR__.'/balicky/cinnosti.json';
}

function readActivityPack(string $path): array {
    $raw = @file_get_contents($path,false,null,0,32769);
    if ($raw === false || strlen($raw)>32768) throw new RuntimeException('Soubor chybí nebo je příliš velký.');
    if (str_starts_with($raw,"\xEF\xBB\xBF")) $raw=substr($raw,3);   // Windows PowerShell přidává BOM
    try {
        $pack = json_decode($raw,true,12,JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new RuntimeException('Neplatný JSON ('.$error->getMessage().').');
    }
    if (!is_array($pack) || ($pack['version']??null)!==1 || !is_array($pack['activities']??null)) {
        throw new RuntimeException('Očekávám katalog verze 1 s nejvýše 24 činnostmi.');
    }
    // Starší worker nezná nové účinky a načítá pouze activities.
    $extensions=array_key_exists('extensions',$pack) ? $pack['extensions'] : [];
    if (!is_array($extensions) || array_intersect_key($pack['activities'],$extensions) ||
        count($pack['activities'])+count($extensions)>24) {
        throw new RuntimeException('Katalog musí mít nejvýše 24 činností a žádné duplicitní klíče.');
    }
    $actions=[];
    foreach ($pack['activities']+$extensions as $key=>$def) {
        if (!is_string($key) || !preg_match('/^pack_[a-z0-9_]{1,32}$/D',$key) || !is_array($def)) {
            throw new RuntimeException('Neplatný identifikátor činnosti'.(is_string($key)?' '.$key:'').'.');
        }
        foreach (['label','work','result'] as $field) {
            if (!is_string($def[$field]??null) || !preg_match('/\A[^\p{C}]{1,120}\z/u',$def[$field])) {
                throw new RuntimeException('Neplatný popis '.$field.' u '.$key.'.');
            }
        }
        if (!in_array($def['place']??null,PACK_PLACES,true) || !in_array($def['effect']??null,PACK_EFFECTS,true)) {
            throw new RuntimeException('Neznámé místo nebo účinek u '.$key.'.');
        }
        foreach (['minutes'=>[15,180],'age'=>[12,90],'from'=>[6,21],'until'=>[7,22],'score'=>[0,30]] as $field=>[$min,$max]) {
            if (!is_int($def[$field]??null) || $def[$field]<$min || $def[$field]>$max) {
                throw new RuntimeException('Neplatná hodnota '.$field.' u '.$key.' (celé číslo '.$min.' až '.$max.').');
            }
        }
        if ($def['until']<=$def['from'] || !in_array($def['season']??null,['any','Léto','Podzim','Jaro','Zima'],true)) {
            throw new RuntimeException('Neplatná sezóna nebo čas u '.$key.'.');
        }
        if (isset($def['building']) && (!is_string($def['building']) || !isset(BUILDINGS[$def['building']]))) {
            throw new RuntimeException('Neznámá budova u '.$key.' (povolené: '.implode(', ',array_keys(BUILDINGS)).').');
        }
        if (isset($def['category']) && (!is_string($def['category']) || !isset(CATEGORIES[$def['category']]))) {
            throw new RuntimeException('Neznámá kategorie u '.$key.' (povolené: '.implode(', ',array_keys(CATEGORIES)).').');
        }
        $action=array_intersect_key($def,array_flip(['label','work','result','place','effect','minutes','age','from','until','score','season','building']));
        $action['category']=packCategory($def);
        $action['group']=CATEGORIES[$action['category']];
        $action['fatigue']=in_array($action['effect'],['food','harvest'],true)?1.2:0.2;
        if ($action['effect']==='leisure') $action['fatigue']=-2.5;
        if ($action['effect']==='food') $action['resource']='food';
        if ($action['effect']==='harvest') $action['resource']='grain';
        $actions[$key]=$action;
    }
    return $actions;
}

// Načte balíček, při chybě ponechá poslední funkční katalog. Výsledek zapíše do $world['activityPackStatus'],
// aby ho viděl uživatel (API, stránka Rozvoj). Stejná chyba se do logu zapíše jen jednou.
function refreshActivityPack(array &$world,array &$control,?string $path=null): void {
    $path??=activityPackPath();
    $previous=$world['activityPack']??$control['activityPack']??[];
    $status=$world['activityPackStatus']??[];
    $hash=is_file($path)?substr((string)@sha1_file($path),0,12):'';
    $status['checkedAt']=time();
    $status['file']=basename($path);
    try {
        $current=readActivityPack($path);
        $change=[];
        foreach (array_diff_key($current,$previous) as $key=>$def) $change[]='+'.$key;
        foreach (array_diff_key($previous,$current) as $key=>$def) $change[]='−'.$key;
        foreach (array_intersect_key($current,$previous) as $key=>$def) if ($def!==$previous[$key]) $change[]='~'.$key;
        if (!empty($status['error'])) error_log('Minilidi – balíček činností: znovu načten.');
        $status=['ok'=>true,'count'=>count($current),'hash'=>$hash,'error'=>null,'errorHash'=>null,
            'checkedAt'=>$status['checkedAt'],'file'=>$status['file'],
            'loadedAt'=>$change || !isset($status['loadedAt']) ? time() : $status['loadedAt'],
            'change'=>$change ? implode(' ',$change) : ($status['change']??'')];
        $previous=$current;
    } catch (RuntimeException $error) {
        $errorHash=$hash.'/'.substr(sha1($error->getMessage()),0,8);
        if (($status['errorHash']??null)!==$errorHash) error_log('Minilidi – balíček činností: '.$error->getMessage());
        $status=array_merge($status,['ok'=>false,'error'=>$error->getMessage(),'errorHash'=>$errorHash,
            'hash'=>$hash,'count'=>count($previous)]);
    }
    $world['activityPack']=$control['activityPack']=$previous;
    $world['activityPackStatus']=$status;
}

// Kořeny slov z názvu a popisu činnosti, aby text od modelu k činnosti z balíčku prošel kontrolou souladu.
function packStems(array $def): ?string {
    preg_match_all('/\p{L}{5,}/u',($def['label']??'').' '.($def['work']??''),$words);
    $stems=[];
    foreach ($words[0] as $word) if (preg_match('/^\p{L}{5}/u',$word,$m)) $stems[preg_quote($m[0],'/')]=true;
    return $stems ? implode('|',array_keys($stems)) : null;
}

function activityCatalog(array $world): array {
    $catalog=[];
    foreach (ACTIONS+($world['activityPack']??[]) as $key=>$def) {
        $category=actionCategory($world,$key);
        $catalog[$key]=['category'=>$category,'group'=>CATEGORIES[$category]]+$def;
    }
    return $catalog;
}

function activityDefinition(array $world,string $key,?array $task=null): array {
    return $task['activity']??ACTIONS[$key]??$world['activityPack'][$key]??[];
}

function activityTarget(array $world,string $id,string $effect): ?string {
    if (!in_array($effect,['harvest','visit_sick'],true)) return null;
    $person=$world['people'][$id];
    foreach ($world['people'] as $other=>$candidate) {
        if ($id===$other || ($candidate['awayUntil']??0)>$world['minute']) continue;
        $eligible=match ($effect) {
            'harvest'=>$candidate['work']==='field' && $candidate['family']!==$person['family'],
            'visit_sick'=>($candidate['illUntil']??0)>$world['minute'],
            default=>false,
        };
        if ($eligible) return $other;
    }
    return null;
}

function activityDispute(array $world,string $id): ?array {
    foreach ($world['people'] as $a=>$person) {
        if ($a===$id || ($person['awayUntil']??0)>$world['minute'] || ($person['bonds'][$id]??0)<55) continue;
        foreach ($person['grudges'] as $b=>$until) {
            $other=$world['people'][$b]??null;
            if ($b!==$id && $b!==$a && $until>$world['minute'] && $other &&
                ($other['awayUntil']??0)<=$world['minute'] && ($other['bonds'][$id]??0)>=55) {
                return [$a,$b];
            }
        }
    }
    return null;
}

function packActivityAvailable(array $world,string $id,array $def): bool {
    $person=$world['people'][$id];
    $calendar=calendarAt($world['minute'],$world['calendarOriginDay']);
    $hour=$calendar['hour'];
    if ($person['age']<$def['age'] || $hour<$def['from'] || $hour>=$def['until'] ||
        ($def['season']!=='any' && $calendar['season']!==$def['season']) || !empty($world['crisis'])) return false;
    // Volitelné pole building: činnost je k dispozici až po dokončení této budovy (např. sběr vajec u kurníku).
    if (!empty($def['building']) && !hasBuilding($world,$def['building'])) return false;
    if ((int)$world['minute']%1440+$def['minutes']>$def['until']*60 &&
        in_array($def['effect'],['leisure','walk','study'],true)) return false;
    if (($person['illUntil']??0)>$world['minute'] || $person['fatigue']>=80) return false;
    $weather=villageWeather($world);
    return match ($def['effect']) {
        'food'=>!$weather['frozen'] && $weather['ground']!=='snow' &&
            !in_array($weather['precip'],['rain','sleet'],true) && $hour<$calendar['sunset'],
        'harvest'=>weatherAllows($world,'fieldwork') && activityTarget($world,$id,'harvest')!==null,
        'visit_sick'=>activityTarget($world,$id,'visit_sick')!==null,
        'mediate'=>activityDispute($world,$id)!==null,
        'leisure'=>$person['fatigue']>=25 || $person['fun']<=65,
        'walk'=>$weather['precip']==='none' && !$weather['heat'] && $weather['wind']<30 &&
            (int)$world['minute']%1440+$def['minutes']<=$calendar['sunset']*60,
        'study'=>$person['skill']<85 && $person['fatigue']<65 && $person['hunger']<65,
        'games'=>hasBuilding($world,'hospoda') && $hour>=18 && (int)$world['minute']%1440+$def['minutes']<=22*60 &&
            $person['age']>=18,
    };
}

function completePackActivity(PDO $db,array &$world,string $id,array $def,array $task): string {
    $effect=$def['effect'];
    $other=$task['other']??null;
    if (in_array($effect,['harvest','visit_sick'],true) &&
        (!isset($world['people'][$other]) || ($world['people'][$other]['awayUntil']??0)>$world['minute'])) {
        return 'Soused už není k zastižení.';
    }
    $person=&$world['people'][$id];
    if (isset($def['resource'])) {
        $amount=round(($effect==='harvest'?2.8:2.4)*productivity($person)/100*skillFactor($person,packCategory($def)),1);
        $world[$def['resource']]+=$amount;
        countProduced($world,$def['resource'],$amount);
    }
    if ($effect==='harvest' || $effect==='visit_sick') {
        rememberHelp($world,$id,$other,5);
        rememberEvent($db,$world,$other,'pomoc',$person['name'].' · '.$def['result'],3);
        if ($effect==='visit_sick') {
            $neighbor=&$world['people'][$other];
            $neighbor['mood']=clamp($neighbor['mood']+7);
        }
    }
    if ($effect==='mediate') {
        [$a,$b]=$task['dispute']??[null,null];
        if (!isset($world['people'][$a],$world['people'][$b]) ||
            max($world['people'][$a]['awayUntil']??0,$world['people'][$b]['awayUntil']??0)>$world['minute'] ||
            ($world['people'][$a]['grudges'][$b]??0)<=$world['minute']) return 'Spor už není potřeba řešit.';
        rememberHelp($world,$a,$b,6);
        unset($world['people'][$a]['grudges'][$b]);
        foreach ([$a,$b] as $neighbor) {
            rememberEvent($db,$world,$neighbor,'smír',$person['name'].' · '.$def['result'],3);
        }
    }
    if ($effect==='leisure') {
        $person['fatigue']=clamp($person['fatigue']-6);
        $person['fun']=clamp($person['fun']+5);
    } elseif ($effect==='walk') {
        $person['fun']=clamp($person['fun']+9);
        $person['mood']=clamp($person['mood']+5);
    } elseif ($effect==='study' && $person['skill']<85) {
        $person['skill']=min(85,$person['skill']+.5);
    }
    if ($effect==='games') {
        $guests=array_filter($world['people'],fn($guest)=>$guest['id']!==$id && $guest['place']==='tavern' &&
            $guest['age']>=18 && ($guest['awayUntil']??0)<=$world['minute']);
        if (!$guests) return 'Ke stolu dnes nikdo další nepřišel.';
        $person['fun']=clamp($person['fun']+10);
        $other=array_key_first($guests);
        $world['people'][$other]['fun']=clamp($world['people'][$other]['fun']+6);
        rememberHelp($world,$id,$other,3);
    }
    return $def['result'];
}
