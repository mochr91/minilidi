<?php
declare(strict_types=1);

const GOALS = [
    'bread'=>['label'=>'Chléb: mlýn a pec','tiers'=>[1,2],'unit'=>'budov'],
    'timber'=>['label'=>'Dřevo: pila a dílna','tiers'=>[1,2],'unit'=>'budov'],
    'farm'=>['label'=>'Hospodářství: studna a statek','tiers'=>[1,2],'unit'=>'budov'],
    'tavern'=>['label'=>'Hospoda na návsi','tiers'=>[1],'unit'=>'budov'],
    'livestock'=>['label'=>'Chov: kurník, chlív, ohrada a stáje','tiers'=>[2,4],'unit'=>'budov'],
    'faith_house'=>['label'=>'Hřbitov a kostel','tiers'=>[1,2],'unit'=>'budov'],
    'garden_cellar'=>['label'=>'Zahrada a sklep na zimu','tiers'=>[1,2],'unit'=>'budov'],
    'winter_prep'=>['label'=>'Příprava na zimu: jídlo, topivo a seno','tiers'=>[60,100],'unit'=>'%'],
    'plant_stock'=>['label'=>'Zásoba zeleniny, zelí a luštěnin','tiers'=>[60,150],'unit'=>'jídel'],
    'carts'=>['label'=>'Vozy na svoz dřeva a kamene','tiers'=>[1,2],'unit'=>'vozů'],
    'charcoal_stock'=>['label'=>'Zásoba dřevěného uhlí','tiers'=>[10,30],'unit'=>'uhlí'],
    'smoked_stock'=>['label'=>'Uzené maso na zimu','tiers'=>[10,40],'unit'=>'masa'],
    'smithy'=>['label'=>'Kovárna a důl','tiers'=>[1,2],'unit'=>'budov'],
    'iron_tools'=>['label'=>'Železné nářadí','tiers'=>[2,6],'unit'=>'kusů'],
    'vineyard_house'=>['label'=>'Vinice a lis','tiers'=>[1,2],'unit'=>'budov'],
    'wine_stock'=>['label'=>'Zásoba vína','tiers'=>[10,40],'unit'=>'vína'],
    'larder_house'=>['label'=>'Špýchar, mlékárna a rybník','tiers'=>[1,3],'unit'=>'budov'],
    'crafts_house'=>['label'=>'Koželužna a tkalcovna','tiers'=>[1,2],'unit'=>'budov'],
    'school_house'=>['label'=>'Fara a škola','tiers'=>[1],'unit'=>'budov'],
    'boots_stock'=>['label'=>'Boty na zimu','tiers'=>[4,9],'unit'=>'párů'],
    'brewing_house'=>['label'=>'Chmelnice, sladovna a pivovar','tiers'=>[1,3],'unit'=>'budov'],
    'beer_stock'=>['label'=>'Zásoba piva pro hospodu','tiers'=>[20,60],'unit'=>'piv'],
    'bees_house'=>['label'=>'Včelín s úly','tiers'=>[1],'unit'=>'budov'],
    'roofs_tiled'=>['label'=>'Tašky místo došků na střechách','tiers'=>[1,3],'unit'=>'střech'],
    'bath_house'=>['label'=>'Lázeň na sobotní koupel','tiers'=>[1],'unit'=>'budov'],
    'pigs_herd'=>['label'=>'Prasata v chlívě','tiers'=>[4,8],'unit'=>'prasat'],
    'sheep_house'=>['label'=>'Ovčín s ovcemi','tiers'=>[1],'unit'=>'budov'],
    'wool_cloth'=>['label'=>'Zásoba teplé látky','tiers'=>[10,25],'unit'=>'látky'],
    'mayor_elected'=>['label'=>'Zvolený starosta','tiers'=>[1],'unit'=>'starosta'],
    'public_buildings'=>['label'=>'Provozní budovy','tiers'=>[3,5,7,10,13,15,17,20,24,27,30,33,34],'unit'=>'budov'],
    'plank_stock'=>['label'=>'Zásoba prken na další stavby','tiers'=>[10,25],'unit'=>'prken'],
    'tool_stock'=>['label'=>'Vybavení nářadím','tiers'=>[5,10],'unit'=>'nářadí'],
    'homes'=>['label'=>'Nové domy','tiers'=>[1,2,3,4,6,8,10],'unit'=>'domů'],
    'pop'=>['label'=>'Obyvatelé','tiers'=>[12,16,20,30,40,50,64],'unit'=>'lidí'],
    'settlers'=>['label'=>'Přistěhované rodiny','tiers'=>[3,6,10],'unit'=>'skupin'],
    'stocks'=>['label'=>'Zásoby jídla na 15 dní','tiers'=>[15],'unit'=>'dní'],
    'winters'=>['label'=>'Přežité zimy','tiers'=>[1,2,3,5],'unit'=>'zim'],
];

function goalValue(array $world,string $key): float {
    return match($key) {
        'bread'=>(float)count(array_filter(['mlyn','pec'],fn($b)=>hasBuilding($world,$b))),
        'timber'=>(float)count(array_filter(['pila','dilna'],fn($b)=>hasBuilding($world,$b))),
        'farm'=>(float)count(array_filter(['studna','statek'],fn($b)=>hasBuilding($world,$b))),
        'tavern'=>hasBuilding($world,'hospoda')?1.0:0.0,
        'livestock'=>(float)count(array_filter(['kurnik','chliv','ohrada','staje'],fn($b)=>hasBuilding($world,$b))),
        'faith_house'=>(float)count(array_filter(['hrbitov','kostel'],fn($b)=>hasBuilding($world,$b))),
        'garden_cellar'=>(float)count(array_filter(['zahrada','sklep'],fn($b)=>hasBuilding($world,$b))),
        'winter_prep'=>(float)round(100*winterPrep($world)['min']),
        'plant_stock'=>(float)round(plantStock($world)),
        'carts'=>(float)($world['carts']??0),
        'charcoal_stock'=>(float)round($world['charcoal']??0),
        'smoked_stock'=>(float)round($world['meatSmoked']??0),
        'smithy'=>(float)count(array_filter(['kovarna','dul'],fn($b)=>hasBuilding($world,$b))),
        'iron_tools'=>(float)round($world['ironTools']??0),
        'vineyard_house'=>(float)count(array_filter(['vinice','lis'],fn($b)=>hasBuilding($world,$b))),
        'wine_stock'=>(float)round(wineStock($world)),
        'larder_house'=>(float)count(array_filter(['spychar','mlekarna','rybnik'],fn($b)=>hasBuilding($world,$b))),
        'crafts_house'=>(float)count(array_filter(['kozeluzna','tkalcovna'],fn($b)=>hasBuilding($world,$b))),
        'school_house'=>hasBuilding($world,'fara')?1.0:0.0,
        'boots_stock'=>(float)round($world['boots']??0),
        'brewing_house'=>(float)count(array_filter(['chmelnice','sladovna','pivovar'],fn($b)=>hasBuilding($world,$b))),
        'beer_stock'=>(float)round(beerStock($world)),
        'bees_house'=>hasBuilding($world,'vcelin')?1.0:0.0,
        'roofs_tiled'=>(float)count(array_filter($world['roofs']??[],fn($r)=>$r==='tile')),
        'bath_house'=>hasBuilding($world,'lazen')?1.0:0.0,
        'pigs_herd'=>(float)floor(pigsOf($world)),
        'sheep_house'=>hasBuilding($world,'ovcin')?1.0:0.0,
        'wool_cloth'=>(float)round($world['cloth']??0),
        'mayor_elected'=>mayorPerson($world)!==null?1.0:0.0,
        'public_buildings'=>(float)count($world['buildings']),
        'plank_stock'=>(float)$world['planks'],
        'tool_stock'=>(float)$world['tools'],
        'homes'=>(float)count($world['homesBuilt']),
        'pop'=>(float)count($world['people']),
        'settlers'=>(float)($world['settlers']['arrived']??0),
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
