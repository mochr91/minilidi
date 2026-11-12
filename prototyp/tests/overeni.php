<?php
declare(strict_types=1);

require dirname(__DIR__).'/world.php';
require dirname(__DIR__).'/ai.php';
require dirname(__DIR__).'/auto.php';

function check(bool $ok,string $test): void {
    if (!$ok) throw new RuntimeException($test);
}

function fixture(): array {
    $db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    init($db);
    $world=readWorld($db);
    $world['minute']=720.0;
    return [$db,$world];
}

$stub=(int)($argv[1]??0);
$unused=(int)($argv[2]??0);
check($stub>0 && $unused>0 && $stub!==$unused,'Předej port testovacího modelu a volný port.');
putenv('MINILIDI_NO_AI=0');
putenv('MINILIDI_AI=ollama');
putenv('MINILIDI_BATCH_TIMEOUT_MS=220');
foreach (['valid','mixed','invalid','timeout','offline','openai'] as $mode) {
    [$db,$world]=fixture();
    putenv('MINILIDI_AI_PORT='.($mode==='offline'?$unused:$stub));
    putenv('MINILIDI_AI='.($mode==='openai'?'openai':'ollama'));
    putenv('MINILIDI_MODEL='.($mode==='openai'?'valid':$mode));
    $batch=prepareVillagePlan($db,$world,true);
    applyVillagePlan($db,$world,$batch,true);
    $source=$db->query("SELECT DISTINCT source FROM journal WHERE title LIKE '%začíná úkol'")->fetchAll(PDO::FETCH_COLUMN);
    check(in_array('AI',$source,true)===in_array($mode,['valid','mixed','openai'],true), 'Zdroj AI: '.$mode);
    check(!in_array($mode,['mixed','invalid','timeout','offline'],true)||in_array('Hra',$source,true),'Zdroj Hra: '.$mode);
    check((int)$db->query('SELECT COUNT(*) FROM ai_metrics')->fetchColumn()===1,'Metriky: '.$mode);
    if ($mode==='valid') check((float)$db->query('SELECT load_ms FROM ai_metrics')->fetchColumn()===2.5,'Ollama load_duration v ms');
    if ($mode==='timeout') {
        check(str_contains($batch['detail'],'Vypršel'),'Vypršení limitu');
        usleep(650000);
    }
    $before=$world['minute'];
    advance($db,$world,180);
    check($world['minute']>$before,'Pokračování času: '.$mode);
    echo "Model $mode OK\n";
}

function simulate(PDO $db,array &$world,int $days,bool $withAi): array {
    $world['nextAutoMinute']=$world['minute'];
    $world['nextSocialMinute']=$world['minute'];
    $pits=[]; $longest=[]; $weather=[];
    for ($i=0;$i<$days*96;$i++) {
        if ($world['minute'] >= $world['nextAutoMinute']) {
            applyVillagePlan($db,$world,prepareVillagePlan($db,$world,$withAi),$withAi);
        } else {
            if ($world['minute'] >= $world['nextSocialMinute']) {
                $scene=socialOptions($world);
                if ($scene) applyMeeting($db,$world,$scene,null);
                $world['nextSocialMinute']=(int)$world['minute']+SOCIAL_INTERVAL;
            }
            startPlannedWork($db,$world);
        }
        advance($db,$world,15);
        $weather[villageWeather($world)['precip']]=true;
        foreach ($world['people'] as $id=>$p) {
            $pits[$id]=($p['mood']<20 || $p['fun']<15) ? ($pits[$id]??0)+15 : 0;
            $longest[$id]=max($pits[$id],$longest[$id]??0);
        }
    }
    return [$longest,array_keys($weather)];
}

putenv('MINILIDI_AI_PORT='.$stub);
putenv('MINILIDI_AI=ollama');
putenv('MINILIDI_MODEL=valid');
putenv('MINILIDI_BATCH_TIMEOUT_MS=1500');
[$db,$world]=fixture();
for ($day=270;$day<359;$day++) {
    $conditions=[];
    for ($slot=0;$slot<18;$slot++) {
        $probe=$world;
        $probe['minute']=$day*1440+$slot*240;
        $conditions[]=villageWeather($probe)['precip'];
    }
    if (in_array('rain',$conditions,true) && in_array('snow',$conditions,true)) break;
}
check($day<359,'Výběr úseku s deštěm a sněhem');
$world['minute']=(float)($day*1440);
$world['food']=65;
$world['wood']=55;
foreach ($world['people'] as &$p) { $p['mood']=0; $p['fun']=.3; }
unset($p);
[$pits,$weather]=simulate($db,$world,3,true);
check(max($pits)<=1440,'Nálada a zábava se mají vzpamatovat do dne: '.encode($pits));
check(in_array('rain',$weather,true)&&in_array('snow',$weather,true),'Déšť i sníh v simulaci');
echo 'Pojistky: maximálně '.max($pits)." herních minut pod hranicí\n";

[$db,$world]=fixture();
$before=$world['people'];
simulate($db,$world,5,false);
$counts=array_count_values(array_column($world['pairHistory'],0));
check(count($counts)>=6 && max($counts)/array_sum($counts)<=.40,'Střídání dvojic: '.encode($counts));
check($before['anna']['bonds']!==$world['people']['anna']['bonds'],'Změna vztahů');
check((int)$db->query("SELECT MAX(n) FROM (SELECT COUNT(*) n FROM memories GROUP BY person,minute)")->fetchColumn()<=5,'Limit vzpomínek');
check((int)$db->query('SELECT COUNT(*) FROM memories')->fetchColumn()>0,'Vzpomínky vznikly');
echo 'Setkání: '.count($counts).' dvojic, maximum '.round(100*max($counts)/array_sum($counts),1)." %\n";

[$db,$world]=fixture();
$base=$world['people']['anna'];
$faithful=array_replace($base,['faith'=>92]);
$terrified=array_replace($base,['fear'=>92,'trust'=>20]);
check(religiousInfluence($faithful)>religiousInfluence($base),'Vysoká víra zvyšuje ochotu');
check(inspirationChoices($terrified,'market',(int)$world['minute'])===['odmitnout'],'Strach vynucuje odmítnutí');
$world['people']['anna']=$terrified;
faithHour($db,$world);
check($world['people']['anna']['trust']<20 && productivity($terrified)<productivity($base),'Důvěra a výkon klesají');
check(interpretation($faithful)!==interpretation($terrified),'Odlišné výklady');
$world['people']['karel']['faith']=80;
$world['people']['katerina']['faith']=63;
check(religionCandidate($world)===null,'Vůdce bez znamení nezakládá hnutí');
applyMiracle($db,$world,'harvest');
check(religionCandidate($world)===null,'Hnutí čeká na přívržence');
$world['people']['jan']['faith']=70;
$world['sign']['witnesses'][]='jan';
foundReligion($db,$world,null);
check(count($world['religions'])===1 && count($world['religions'][0]['members'])>=3,'Vznik hnutí');
check(!miracleAvailable($world,'heal'),'Prodleva zázraku');
echo "Víra, strach, zázrak a vznik hnutí OK\n";
