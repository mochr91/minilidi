<?php
declare(strict_types=1);

function newCounters(): array {
    return ['tasks'=>[],'produced'=>[],'eaten'=>0.0,'hungryMeals'=>0,'hungryMealsToday'=>0,'births'=>0,'deaths'=>[],'weddings'=>0,
        'crises'=>[],'built'=>[],'injuries'=>0,'planValid'=>0,'planFallback'=>0,'moodSum'=>0.0,'funSum'=>0.0,'energySum'=>0.0,
        'samples'=>0,'minMood'=>100.0,'hayShort'=>0];
}

function countTask(array &$world,string $key): void {
    $world['counters']['tasks'][$key]=($world['counters']['tasks'][$key]??0)+1;
}

function hourlySample(array &$world): void {
    $people=array_values($world['people']);
    if (!$people) return;
    $count=count($people);
    $c=&$world['counters'];
    $c['moodSum']+=array_sum(array_column($people,'mood'))/$count;
    $c['funSum']+=array_sum(array_column($people,'fun'))/$count;
    $c['energySum']+=100-array_sum(array_column($people,'fatigue'))/$count;
    $c['minMood']=min($c['minMood'],min(array_column($people,'mood')));
    ++$c['samples'];
}

function weeklyRecord(array $world,int $week): array {
    $c=$world['counters'];
    $n=max(1,$c['samples']);
    $people=array_values($world['people']);
    $pregnant=count(array_filter($people,fn($p)=>!empty($p['pregnant'])));
    $tasks=$c['tasks'];
    arsort($tasks);
    $stocks=[];
    foreach (array_keys(RESOURCES) as $key) $stocks[$key]=round($world[$key],1);
    return ['week'=>$week,'to'=>(int)$world['minute'],'population'=>count($people),
        'children'=>count(array_filter($people,fn($p)=>$p['age']<14)),
        'elders'=>count(array_filter($people,fn($p)=>$p['age']>=60)),
        'pregnant'=>$pregnant,'births'=>$c['births'],'deaths'=>$c['deaths'],'deathsTotal'=>array_sum($c['deaths']),
        'weddings'=>$c['weddings'],'injuries'=>$c['injuries'],'buildings'=>count($world['buildings']),'built'=>$c['built'],
        'homes'=>count($world['homesBuilt']),'stocks'=>$stocks,
        'reserveDays'=>round(foodReserve($world)/max(1,dailyFoodNeed($world)),1),
        'avgMood'=>round($c['moodSum']/$n,1),'avgFun'=>round($c['funSum']/$n,1),'avgEnergy'=>round($c['energySum']/$n,1),
        'minMood'=>round($c['minMood'],1),'tasks'=>$tasks,'tasksTotal'=>array_sum($tasks),'produced'=>array_map(fn($v)=>round($v,1),$c['produced']),
        'hungryMeals'=>$c['hungryMeals'],'crises'=>$c['crises'],'goalsDone'=>goalsDone($world),'winters'=>$world['winters']??0,
        'score'=>villageScore($world),'planValid'=>$c['planValid'],'planFallback'=>$c['planFallback'],'hayShort'=>$c['hayShort']];
}

function summaryText(array $r): string {
    $parts=[];
    $parts[]='Týden '.$r['week'].': '.$r['population'].' obyvatel';
    if ($r['births']) $parts[]=$r['births'].' narozených';
    if ($r['deathsTotal']) $parts[]=$r['deathsTotal'].' zemřelých ('.implode(', ',array_map(fn($k,$v)=>$k.' '.$v,array_keys($r['deaths']),$r['deaths'])).')';
    if ($r['weddings']) $parts[]=$r['weddings'].' svateb';
    if ($r['built']) $parts[]='postaveno: '.implode(', ',array_map(fn($k)=>BUILDINGS[$k]['label']??$k,$r['built']));
    foreach ($r['crises'] as $crisis) $parts[]=(CRISES[$crisis['type']]['label']??'Špatná úroda');
    $parts[]='nálada '.$r['avgMood'].', zábava '.$r['avgFun'];
    $parts[]='zásoby jídla na '.$r['reserveDays'].' dní';
    $parts[]='skóre '.$r['score'];
    return implode('; ',$parts).'.';
}

function weeklyClose(PDO $db,array &$world): void {
    $day=intdiv((int)$world['minute'],1440)-$world['calendarOriginDay'];
    if ($day<7 || $day%7!==0) return;
    $week=intdiv($day,7);
    if (($world['lastWeek']??0)>=$week) return;
    $record=weeklyRecord($world,$week);
    $record['text']=summaryText($record);
    $db->prepare('INSERT OR REPLACE INTO weekly (week,data) VALUES (?,?)')->execute([$week,encode($record)]);
    $db->exec('DELETE FROM weekly WHERE week <= '.($week-520));
    $world['lastWeek']=$week;
    $keep=['moodSum'=>0.0];
    $world['counters']=newCounters();
}
