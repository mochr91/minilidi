<?php
declare(strict_types=1);

const DOCTRINES = [
    'holiday'=>'Sváteční klid', 'tithe'=>'Desátek', 'fast'=>'Půst',
    'chapel'=>'Obřad v kapli', 'tree'=>'Uctívání stromu', 'peace'=>'Zákaz násilí',
];
const MIRACLES = [
    'heal'=>['label'=>'Ulevit nemocným','cost'=>18],
    'harvest'=>['label'=>'Požehnat úrodě','cost'=>26],
    'weather'=>['label'=>'Na tři hodiny utišit počasí','cost'=>30],
    'protect'=>['label'=>'Zachránit zásoby','cost'=>22],
];
const MIRACLE_COOLDOWN = 360;

function religiousInfluence(array $p): float {
    return ($p['trust'] - 50) * .75 + ($p['faith'] - 40) * .30
        + min($p['fear'],45) * .23 - max(0,$p['fear']-55) * 1.65;
}

function interpretation(array $p,array $neighbors=[]): string {
    $temperament = match($p['id']) {'karel'=>5,'matyas'=>4,'katerina'=>3,'anna'=>-4,'tereza'=>-3,default=>0};
    $influence=0.0;
    foreach ($p['bonds'] as $id=>$bond) {
        if ($bond>=65 && isset($neighbors[$id])) $influence+=($neighbors[$id]['faith']-50)*.04;
    }
    $belief = $p['faith'] + ($p['trust']-50)*.45 + ($p['charisma']-50)*.12 + $temperament + max(-6,min(6,$influence));
    if ($p['fear'] >= 68 || ($belief >= 60 && $p['trust'] < 34)) return 'jiné znamení';
    return $belief >= 56 ? 'zázrak' : 'náhoda';
}

function faithEvent(PDO $db,array &$world,string $title,string $detail,string $type,string $source='Hra'): void {
    $world['faithEvents'][] = ['minute'=>(int)$world['minute'],'title'=>$title,'detail'=>$detail,'type'=>$type,'source'=>$source];
    $world['faithEvents'] = array_slice($world['faithEvents'],-18);
    journal($db,$world,'',$title,$detail,$source);
}

function miracleAvailable(array $world,string $key): bool {
    return isset(MIRACLES[$key]) && $world['grace'] >= MIRACLES[$key]['cost'] && $world['minute'] >= $world['miracleAfter'];
}

function applyMiracle(PDO $db,array &$world,string $key): void {
    if (!miracleAvailable($world,$key)) return;
    $minute = (int)$world['minute'];
    $world['grace'] -= MIRACLES[$key]['cost'];
    $world['miracleAfter'] = $minute + MIRACLE_COOLDOWN;
    switch ($key) {
        case 'heal':
            foreach ($world['people'] as &$p) {
                $p['illUntil'] = 0;
                $p['fatigue'] = clamp($p['fatigue']-16);
            }
            unset($p);
            break;
        case 'harvest':
            $world['fieldCare'] = 2;
            $world['food'] += 8;
            break;
        case 'weather':
            $world['calmUntil'] = $minute + 180;
            break;
        case 'protect':
            $world['food'] += 5;
            $world['wood'] += 5;
            $world['protectedUntil'] = $minute + 1440;
            break;
    }
    $witnesses = [];
    $interpretations = [];
    $views = [];
    $observers = $world['people'];
    foreach ($world['people'] as $id=>&$p) {
        $view = interpretation($p,$observers);
        $views[$id] = $view;
        $p['faith'] = clamp($p['faith'] + match($view) {'zázrak'=>9,'jiné znamení'=>5,default=>0});
        $p['trust'] = clamp($p['trust'] + match($view) {'zázrak'=>5,'jiné znamení'=>-3,default=>0});
        if ($view !== 'náhoda') $witnesses[] = $id;
        $interpretations[] = $p['name'].' – '.$view;
        rememberEvent($db,$world,$id,'znamení',MIRACLES[$key]['label'].': '.$view,5);
    }
    unset($p);
    $world['sign'] = ['minute'=>$minute,'label'=>MIRACLES[$key]['label'],'witnesses'=>$witnesses,'views'=>$views,'used'=>false,'told'=>false];
    faithEvent($db,$world,MIRACLES[$key]['label'],implode(', ',$interpretations),'znamení','Hráč');
}

function religionCandidate(array $world): ?array {
    $sign = $world['sign'];
    if (!$sign || $sign['used'] || $world['minute']-$sign['minute'] > 2*1440 || count($world['religions'])>=4) return null;
    $taken = array_merge([], ...array_column($world['religions'],'members'));
    foreach ($sign['witnesses'] as $id) {
        $p = $world['people'][$id] ?? null;
        if (!$p || $p['age']<18 || $p['faith']<72 || $p['charisma']<65 || in_array($id,$taken,true)) continue;
        $members = [$id];
        foreach ($sign['witnesses'] as $other) {
            if ($other!==$id && isset($world['people'][$other]) && !in_array($other,$taken,true) &&
                $world['people'][$other]['faith']>=55 && ($p['bonds'][$other]??48)>=46) $members[] = $other;
        }
        if (count($members)>=3) return ['leader'=>$id,'members'=>$members];
    }
    return null;
}

function foundReligion(PDO $db,array &$world,?array $model): void {
    $candidate = religionCandidate($world);
    if (!$candidate) return;
    $proposed = $model['doctrine'] ?? '';
    $valid = is_string($proposed) && isset(DOCTRINES[$proposed]) && narrativeText($model['name']??null,45)
        && narrativeText($model['text']??null,140);
    $leader = $world['people'][$candidate['leader']];
    $relation = $leader['trust']>=58 ? 'uctívají hráče' : ($leader['trust']<30 ? 'odpadlictví' : 'vlastní víra');
    $doctrine = $valid ? $proposed : ($relation==='uctívají hráče'?'chapel':'tree');
    $name = $valid ? trim($model['name']) : ($doctrine==='chapel'?'Strážci kaple':'Strážci lípy');
    if (in_array($name,array_column($world['religions'],'name'),true)) $name .= ' ('.$leader['name'].')';
    $world['religions'][] = $candidate + [
        'name'=>$name,'doctrine'=>$doctrine,'relationToPlayer'=>$relation,'strength'=>count($candidate['members'])*10,
    ];
    $world['sign']['used'] = true;
    faithEvent($db,$world,$leader['name'].' zakládá hnutí '.$name,
        $valid ? trim($model['text']) : 'Sousedé se shodli na výkladu znamení. Obřad vede '.$leader['name'].'.','vznik',$valid?'AI':'Hra');
}

function faithHour(PDO $db,array &$world): void {
    $now = (int)$world['minute'];
    $hour = intdiv($now%1440,60);
    $world['grace'] = min(100,$world['grace']+1);
    $cold = villageWeather($world)['frozen'] && $world['wood'] < 1;
    foreach ($world['people'] as $id=>&$p) {
        if ($p['fear']>60) $p['trust'] = clamp($p['trust']-($p['fear']>=80?1.8:.6));
        if ($p['age']>=18 && $p['fear']>=97 && $p['trust']<18 && $p['awayUntil']<=$now) {
            $p['awayUntil']=$now+720;
            $p['resistUntil']=$now+720;
            $p['fear']=clamp($p['fear']-16);
            $p['task']=null;
            $p['place']='away';
            $p['activity']='Uchýlil/a se mimo vesnici';
            unset($world['plan'][$id]);
            faithEvent($db,$world,$p['name'].' prchá ze vsi','Na dvanáct hodin se ukryje mimo vesnici.','útěk');
            rememberEvent($db,$world,$id,'krize','Ze strachu odešel/odešla z vesnice.',5);
        } elseif ($p['fear']>=88 && $p['trust']<24 && $p['resistUntil']<=$now) {
            $p['resistUntil'] = $now+720;
            $p['fear'] = clamp($p['fear']-8);
            if (!empty($p['task']['automatic'])) $p['task'] = null;
            faithEvent($db,$world,$p['name'].' se bouří','Na půl dne odmítá vnuknutí.','odpor');
        }
        if ($hour===6 && $p['illUntil']<=$now && ($p['hunger']>75 || $cold) && $p['fatigue']>64 &&
            weatherNoise(intdiv($now,1440), 'illness:'.$id)<.30) {
            $p['illUntil'] = $now+1440;
            journal($db,$world,$id,$p['name'].' onemocněl/a','Nemoc snižuje výkon. Pomůže odpočinek nebo uzdravení.','Hra');
            rememberEvent($db,$world,$id,'krize','Nemoc po vyčerpání.',5);
        }
        $p['fear'] = clamp($p['fear']-.15);
    }
    unset($p);
    if ($hour===6 && $world['protectedUntil']<=$now && villageWeather($world)['wind']>=32 &&
        weatherNoise(intdiv($now,1440),'storm-loss')<.35) {
        $lost = min(4,$world['wood']);
        consumeStock($world,'wood',$lost);
        faithEvent($db,$world,'Vítr poničil hranici dříví','Zásoba dřeva klesla o '.round($lost,1).'.','krize');
    }
    if ($hour!==18) return;
    $day = intdiv($now,1440)-$world['calendarOriginDay'];
    foreach ($world['religions'] as $i=>&$group) {
        $members = $group['members'];
        foreach ($members as $id) {
            $p = &$world['people'][$id];
            if ($p['lastPrayer'] < $now-1440) $p['mood'] = clamp($p['mood']-5);
            if ($group['doctrine']==='tithe' && $world['food'] >= count($world['people'])) consumeStock($world,'food',.25);
            if ($group['doctrine']==='fast') $p['hunger'] = clamp($p['hunger']+9);
            if ($group['doctrine']==='peace') $p['fear'] = clamp($p['fear']-3);
            unset($p);
        }
        $outsiders = array_diff(array_keys($world['people']),array_merge([], ...array_column($world['religions'],'members')));
        foreach ($outsiders as $id) {
            $p = $world['people'][$id];
            if ($p['faith']<66 || ($p['bonds'][$group['leader']]??48)<63) continue;
            $group['members'][] = $id;
            faithEvent($db,$world,$p['name'].' přistupuje k hnutí '.$group['name'],'Vztah k vůdci a víra vedly ke konverzi.','konverze');
            break;
        }
        $group['strength'] = min(100,10*count($group['members']));
        if ($day%7===0) faithEvent($db,$world,'Svátek hnutí '.$group['name'],DOCTRINES[$group['doctrine']].'.','svátek');
        if ($day%7===3 && count($members)>=6 && count($world['religions'])<4) {
            $dissent = array_values(array_filter($members,fn($id)=>$id!==$group['leader'] &&
                ($world['people'][$id]['bonds'][$group['leader']]??48)<44 && $world['people'][$id]['faith']>=65));
            if (count($dissent)>=3) {
                $group['members'] = array_values(array_diff($group['members'],$dissent));
                $world['religions'][] = ['name'=>'Obnova – '.$group['name'],'doctrine'=>$group['doctrine']==='peace'?'tree':'peace',
                    'leader'=>$dissent[0],'members'=>$dissent,'relationToPlayer'=>'vlastní víra','strength'=>count($dissent)*10];
                $group['strength'] = 10*count($group['members']);
                faithEvent($db,$world,'Rozkol v hnutí '.$group['name'],'Část členů následuje vlastní výklad.','schizma');
            }
        }
    }
    unset($group);
    if ($day%7===5 && count($world['religions'])>=2) {
        [$a,$b] = $world['religions'];
        if ($a['doctrine']!==$b['doctrine'] && !in_array('peace',[$a['doctrine'],$b['doctrine']],true)) {
            $id=$a['leader']; $other=$b['leader'];
            $world['people'][$id]['bonds'][$other]=clamp(($world['people'][$id]['bonds'][$other]??48)-4);
            $world['people'][$other]['bonds'][$id]=clamp(($world['people'][$other]['bonds'][$id]??48)-4);
            faithEvent($db,$world,'Spor o výklad znamení',$a['name'].' a '.$b['name'].' se neshodly na obřadu.','spor');
        }
    }
}

function doctrineFor(array $world,string $id): ?string {
    foreach ($world['religions'] as $group) if (in_array($id,$group['members'],true)) return $group['doctrine'];
    return null;
}
