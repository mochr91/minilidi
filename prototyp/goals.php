<?php
declare(strict_types=1);

const GOALS = [
    'bread'=>['label'=>'Chléb: mlýn a pec','tiers'=>[1,2],'unit'=>'budov'],
    'timber'=>['label'=>'Dřevo: pila a dílna','tiers'=>[1,2],'unit'=>'budov'],
    'farm'=>['label'=>'Hospodářství: studna a statek','tiers'=>[1,2],'unit'=>'budov'],
    'homes'=>['label'=>'Nové domy','tiers'=>[1,2,3,4],'unit'=>'domů'],
    'pop'=>['label'=>'Obyvatelé','tiers'=>[12,16,20,30],'unit'=>'lidí'],
    'stocks'=>['label'=>'Zásoby jídla na 15 dní','tiers'=>[15],'unit'=>'dní'],
    'winters'=>['label'=>'Přežité zimy','tiers'=>[1,2,3,5],'unit'=>'zim'],
];

function goalValue(array $world,string $key): float {
    return match($key) {
        'bread'=>(float)count(array_filter(['mlyn','pec'],fn($b)=>hasBuilding($world,$b))),
        'timber'=>(float)count(array_filter(['pila','dilna'],fn($b)=>hasBuilding($world,$b))),
        'farm'=>(float)count(array_filter(['studna','statek'],fn($b)=>hasBuilding($world,$b))),
        'homes'=>(float)count($world['homesBuilt']),
        'pop'=>(float)count($world['people']),
        'stocks'=>round(foodReserve($world)/max(1,dailyFoodNeed($world)),1),
        'winters'=>(float)($world['winters']??0),
        default=>0.0,
    };
}

function goalState(array $world): array {
    $list=[];
    foreach (GOALS as $key=>$def) {
        $value=goalValue($world,$key);
        $tier=null;
        foreach ($def['tiers'] as $i=>$target) if (!isset($world['goals']['done'][$key.':'.$target])) { $tier=$i; break; }
        $target=$tier===null ? end($def['tiers']) : $def['tiers'][$tier];
        $list[]=['key'=>$key,'label'=>$def['label'],'current'=>$value,'target'=>$target,'tier'=>$tier,'unit'=>$def['unit'],
            'complete'=>$tier===null,'doneTiers'=>count(array_filter($def['tiers'],fn($t)=>isset($world['goals']['done'][$key.':'.$t])))];
    }
    return $list;
}

function goalMain(array $world): ?array {
    foreach (goalState($world) as $goal) if (!$goal['complete']) return $goal;
    return null;
}

function goalsDone(array $world): int {
    return count($world['goals']['done']??[]);
}

function villageScore(array $world): int {
    $people=$world['people'];
    $avgMood=$people?array_sum(array_column($people,'mood'))/count($people):0;
    $reserveDays=min(20,foodReserve($world)/max(1,dailyFoodNeed($world)));
    return (int)round(count($people)*12+count($world['homesBuilt'])*14+count($world['buildings'])*10+($world['winters']??0)*40
        +goalsDone($world)*8+$reserveDays+$avgMood*.2-($world['counters']['hungryMeals']??0)*.5);
}

function goalsDaily(PDO $db,array &$world): void {
    $now=(int)$world['minute'];
    $calendar=calendarAt($now,$world['calendarOriginDay']);
    if ($calendar['season']==='Zima' && ($world['counters']['hungryMealsToday']??0)>0) $world['winterBad']=true;
    $world['counters']['hungryMealsToday']=0;
    if ($calendar['monthIndex']===0 && $calendar['day']===1 && $calendar['year']>1 && ($world['winterChecked']??0)<$calendar['year']) {
        $world['winterChecked']=$calendar['year'];
        if (empty($world['winterBad'])) {
            $world['winters']=($world['winters']??0)+1;
            journal($db,$world,'','Zima je za námi','Vesnice přežila zimu bez hladovění a mrazu. Přežité zimy: '.$world['winters'].'.','Příběh');
        } else {
            journal($db,$world,'','Zima byla krutá','V zimě se hladovělo, zima se do počtu nezapočítá.','Příběh');
        }
        $world['winterBad']=false;
    }
    foreach (GOALS as $key=>$def) {
        $value=goalValue($world,$key);
        foreach ($def['tiers'] as $target) {
            $slot=$key.':'.$target;
            if ($value>=$target && !isset($world['goals']['done'][$slot])) {
                $world['goals']['done'][$slot]=$now;
                journal($db,$world,'','Splněn cíl: '.$def['label'],'Dosaženo '.$target.' '.$def['unit'].'.','Příběh');
            }
        }
    }
}
