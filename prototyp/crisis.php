<?php
declare(strict_types=1);

const CRISES = [
    'fire'=>['label'=>'Požár','action'=>'firefight','minutes'=>180,'base'=>6],
    'flood'=>['label'=>'Povodeň','action'=>'flood_defense','minutes'=>480,'base'=>10],
];

function crisisAction(array $world): ?string {
    return empty($world['crisis']) ? null : CRISES[$world['crisis']['type']]['action'];
}

function ableForCrisis(array $world,array $p): bool {
    return $p['age']>=14 && ($p['awayUntil']??0)<=$world['minute'] && (empty($p['illUntil']) || $p['illUntil']<=$world['minute'])
        && empty($p['pregnant']);
}

function startCrisis(PDO $db,array &$world,string $type,int $severity): void {
    $now=(int)$world['minute'];
    $def=CRISES[$type];
    $world['crisis']=['type'=>$type,'label'=>$def['label'],'start'=>$now,'end'=>$now+$def['minutes'],
        'need'=>$def['base']+$severity*(int)($def['base']/3),'done'=>0.0,'severity'=>$severity];
    $text=$type==='fire'
        ? ['Hoří stodola na kraji vsi','Plameny se šíří. Všichni schopní lidé běží s vodou.']
        : ['Řeka se rozlévá, voda stoupá','Voda se blíží k polím a sýpce. Lidé zpevňují břeh.'];
    faithEvent($db,$world,$def['label'].'! '.$text[0],$text[1],'krize','Hra');
    $world['counters']['crises'][]=['type'=>$type,'minute'=>$now];
    foreach ($world['people'] as $id=>&$p) {
        if (!ableForCrisis($world,$p)) continue;
        $p['task']=null;
        unset($world['plan'][$id]);
        assignWork($world,$id,$def['action'],true);
    }
    unset($p);
    $world['nextAutoMinute']=min($world['nextAutoMinute'],$now+30);
}

function resolveCrisis(PDO $db,array &$world): void {
    $crisis=$world['crisis'];
    $now=(int)$world['minute'];
    $ratio=min(1.0,$crisis['done']/max(1,$crisis['need']));
    $lost=1-$ratio;
    $text=[];
    $shave=function(string $key,float $share) use (&$world,&$text) {
        $amount=$world[$key]*$share;
        if ($amount<.3) return;
        consumeStock($world,$key,$amount);
        $text[]=(RESOURCES[$key]??'Kámen u lomu').' −'.fmtAmount($amount);
    };
    if ($crisis['type']==='fire') {
        $mitigate=hasBuilding($world,'studna')?.7:1;
        $shave('wood',(.05+.30*$lost)*$mitigate);
        $shave('food',(.03+.22*$lost)*$mitigate);
        $shave('hay',(.10+.70*$lost)*$mitigate);
        $shave('planks',(.05+.25*$lost)*$mitigate);
        $targets=array_values(array_diff(array_keys($world['buildings']),['studna']));
        if ($lost>.75 && $targets) {
            $key=$targets[crc32((string)$now)%count($targets)];
            unset($world['buildings'][$key]);
            $text[]='shořel(a) '.BUILDINGS[$key]['label'];
            if (($world['project']['key']??null)===$key) $world['project']=null;
        }
    } else {
        $shave('grain',.04+.26*$lost);
        $shave('flour',.04+.26*$lost);
        $shave('food',.04+.22*$lost);
        $shave('quarryStone',.05+.20*$lost);
        $world['fieldBlockedUntil']=$now+(int)((1.5+2.5*$lost)*1440);
        $sick=0;
        foreach ($world['people'] as $id=>&$p) {
            $risk=(.04+.20*$lost)*(hasBuilding($world,'studna')?1:1.6);
            if (weatherNoise($now,'flood-ill:'.$id)<$risk && empty($p['illUntil'])) { $p['illUntil']=$now+3*1440; ++$sick; }
        }
        unset($p);
        if ($sick) $text[]=$sick.' nemocných po záplavě';
    }
    foreach ($world['people'] as &$p) {
        $p['mood']=clamp($p['mood']-($lost>.6?10:4));
        $p['fear']=clamp($p['fear']+($lost>.6?8:3));
        if ($ratio>=.8) $p['faith']=clamp($p['faith']+2);
    }
    unset($p);
    $title=$ratio>=.8 ? $crisis['label'].' zvládnut' : $crisis['label'].' způsobil škody';
    faithEvent($db,$world,$title,($text?implode(', ',$text).'. ':'Žádné velké škody. ').'Obrana '.round($ratio*100).' %.','krize','Hra');
    $world['counters']['crises'][count($world['counters']['crises'])-1]['ratio']=round($ratio,2);
    $world['crisis']=null;
    $world['crisisCooldown']=$now+12*1440;
}

function crisisTick(PDO $db,array &$world): void {
    $crisis=$world['crisis']??null;
    if (!$crisis) return;
    if ((int)$world['minute']>=$crisis['end'] || $crisis['done']>=$crisis['need']) resolveCrisis($db,$world);
}

function crisisChain(array &$world,string $id): void {
    $action=crisisAction($world);
    if ($action===null) return;
    $p=$world['people'][$id];
    if (!ableForCrisis($world,$p) || $p['fatigue']>92) return;
    assignWork($world,$id,$action,true);
}

function crisisHour(PDO $db,array &$world): void {
    $now=(int)$world['minute'];
    $hour=intdiv($now%1440,60);
    $calendar=calendarAt($now,$world['calendarOriginDay']);
    $day=intdiv($now,1440);
    if ($hour===6 && $calendar['monthIndex']===5 && $calendar['day']===1 && weatherNoise($calendar['year'],'harvest')<.17) {
        $world['harvestFactor']=.55;
        $world['harvestUntil']=$now+40*1440;
        faithEvent($db,$world,'Špatná úroda','Obilí zasáhla plíseň a sucho. Žně přinesou jen polovinu, hrozí tvrdší zima.','krize','Hra');
        $world['counters']['crises'][]=['type'=>'harvest','minute'=>$now,'ratio'=>0];
    }
    if (!empty($world['crisis']) || $now<($world['crisisCooldown']??0) || !$world['people']) return;
    $weather=villageWeather($world);
    if ($hour===15) {
        $risk=.0014*(($weather['wind']>=26)?2:1)*($weather['heat']?2:1)*($weather['precip']==='none'?1:.3);
        if (weatherNoise($day,'fire')<$risk) startCrisis($db,$world,'fire',crc32('fire'.$day)%3);
    } elseif ($hour===5) {
        $wet=in_array($weather['precip'],['rain','sleet'],true) || $weather['ground']==='mud';
        $risk=in_array($calendar['monthIndex'],[0,1,2,3,4],true) ? ($wet?.004:.0010) : .0003;
        if (weatherNoise($day,'flood')<$risk) startCrisis($db,$world,'flood',crc32('flood'.$day)%3);
    }
}
