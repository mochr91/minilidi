<?php
declare(strict_types=1);

const BREAD_FOOD = 2.0;
const HEARTH_FLOUR_FOOD = 1.3;
const SAW_RATIO = 4/6;

const RESOURCES = [
    'food'=>'Jídlo','wood'=>'Dřevo','stone'=>'Kámen','grain'=>'Obilí','flour'=>'Mouka',
    'planks'=>'Prkna','hay'=>'Seno','tools'=>'Nářadí',
];

const RESOURCE_WORDS = [
    'food'=>'jídla','wood'=>'dřeva','stone'=>'kamene','grain'=>'obilí','flour'=>'mouky',
    'planks'=>'prken','hay'=>'sena','tools'=>'nářadí',
];

const BUILDINGS = [
    'mlyn'=>['label'=>'Mlýn','cost'=>['wood'=>14,'stone'=>8],'work'=>5,'needs'=>[],
        'text'=>'Mele obilí na mouku.'],
    'pila'=>['label'=>'Pila','cost'=>['wood'=>16,'stone'=>6],'work'=>5,'needs'=>[],
        'text'=>'Řeže dřevo na prkna.'],
    'pec'=>['label'=>'Chlebová pec','cost'=>['wood'=>6,'stone'=>10],'work'=>4,'needs'=>['mlyn'],
        'text'=>'Z mouky peče chléb, který zasytí dvakrát víc než kaše.'],
    'studna'=>['label'=>'Obecní studna','cost'=>['wood'=>4,'stone'=>10],'work'=>4,'needs'=>[],
        'text'=>'Čistá voda: méně nemocí a menší škody při požáru.'],
    'dilna'=>['label'=>'Dílna','cost'=>['planks'=>8,'stone'=>6],'work'=>5,'needs'=>['pila'],
        'text'=>'Vyrábí nářadí, se kterým se pracuje rychleji.'],
    'statek'=>['label'=>'Statek','cost'=>['planks'=>10,'stone'=>8,'wood'=>6],'work'=>6,'needs'=>['pila'],
        'text'=>'Větší stádo, víc jídla a zásoba sena na zimu.'],
];
const BUILD_ORDER = ['mlyn','pila','pec','studna','dilna','statek'];

function economyDefaults(): array {
    return ['grain'=>4.0,'flour'=>0.0,'planks'=>0.0,'hay'=>8.0,'tools'=>2.0,'buildings'=>[],'project'=>null,
        'harvestFactor'=>1.0,'harvestUntil'=>0,'fieldBlockedUntil'=>0,'hayShort'=>false];
}

function hasBuilding(array $world,string $key): bool {
    return isset($world['buildings'][$key]);
}

function mealCost(array $p): float {
    return $p['age']<2 ? 0.0 : ($p['age']<6 ? .5 : 1.0);
}

function dailyFoodNeed(array $world): float {
    $need=0.0;
    foreach ($world['people'] as $p) $need+=mealCost($p)*count(MEAL_HOURS);
    return $need;
}

function foodReserve(array $world): float {
    return $world['food']+$world['grain']+$world['flour']*HEARTH_FLOUR_FOOD;
}

function drawFood(array &$world,float $cost): bool {
    if (foodReserve($world)<$cost) return false;
    $missing=$cost;
    $fromFood=min($world['food'],$missing);
    $world['food']-=$fromFood;
    $missing-=$fromFood;
    if ($missing>0 && $world['flour']>0) {
        $flour=min($world['flour'],$missing/HEARTH_FLOUR_FOOD);
        $world['flour']-=$flour;
        $missing-=$flour*HEARTH_FLOUR_FOOD;
    }
    if ($missing>0.0001) $world['grain']-=min($world['grain'],$missing);
    return true;
}

function adultCount(array $world): int {
    return count(array_filter($world['people'],fn($p)=>$p['age']>=14));
}

function toolFactor(array $world): float {
    $need=max(1,(int)ceil(adultCount($world)/3));
    return .86+.2*min(1,$world['tools']/$need);
}

function housingCapacity(array $world): int {
    return 14+4*count($world['homesBuilt']);
}

function homeCost(int $built): array {
    return $built===0 ? ['wood'=>HOUSE_WOOD,'stone'=>HOUSE_STONE] : ['wood'=>6,'planks'=>6,'stone'=>HOUSE_STONE];
}

function canPay(array $world,array $cost): bool {
    foreach ($cost as $key=>$amount) if ($world[$key]<$amount) return false;
    return true;
}

function pay(array &$world,array $cost): void {
    foreach ($cost as $key=>$amount) consumeStock($world,$key,(float)$amount);
}

function projectOptions(array $world): array {
    $options=[];
    foreach (BUILD_ORDER as $key) {
        if (hasBuilding($world,$key)) continue;
        $ok=true;
        foreach (BUILDINGS[$key]['needs'] as $need) if (!hasBuilding($world,$need)) $ok=false;
        if ($ok) $options[]=$key;
    }
    return $options;
}

function chooseProject(PDO $db,array &$world,?string $suggest=null): void {
    if ($world['project']!==null) return;
    $options=projectOptions($world);
    if (!$options) return;
    $key=in_array($suggest,$options,true)?$suggest:$options[0];
    $world['project']=['key'=>$key,'label'=>BUILDINGS[$key]['label'],'progress'=>0,'work'=>BUILDINGS[$key]['work'],
        'paid'=>false,'since'=>(int)$world['minute']];
    journal($db,$world,'','Obec plánuje stavbu: '.BUILDINGS[$key]['label'],BUILDINGS[$key]['text'],'Hra');
}

function materialNeeds(array $world): array {
    $needs=[];
    $project=$world['project'];
    if ($project!==null && !$project['paid']) {
        foreach (BUILDINGS[$project['key']]['cost'] as $key=>$amount) {
            if ($world[$key]<$amount) $needs[$key]=($needs[$key]??0)+$amount-$world[$key];
        }
    }
    if (count($world['homesBuilt'])<HOME_LIMIT && count($world['people'])>=housingCapacity($world)-2) {
        foreach (homeCost(count($world['homesBuilt'])) as $key=>$amount) {
            if ($world[$key]<$amount) $needs[$key]=max($needs[$key]??0,$amount-$world[$key]);
        }
    }
    return $needs;
}

function projectReady(array $world): bool {
    $project=$world['project'];
    return $project!==null && ($project['paid'] || canPay($world,BUILDINGS[$project['key']]['cost']));
}

function countProduced(array &$world,string $resource,float $amount): void {
    $world['counters']['produced'][$resource]=($world['counters']['produced'][$resource]??0)+$amount;
}

function fmtAmount(float $value): string {
    return number_format($value,1,',',' ');
}

function economyDaily(PDO $db,array &$world): void {
    $now=(int)$world['minute'];
    $calendar=calendarAt($now,$world['calendarOriginDay']);
    $weather=villageWeather($world);
    $winter=$calendar['season']==='Zima' || $weather['ground']==='snow';
    $world['hayShort']=false;
    if ($winter) {
        $need=hasBuilding($world,'statek') ? 1.2 : .6;
        if ($world['hay']<$need) {
            $world['hayShort']=true;
            $world['counters']['hayShort']=($world['counters']['hayShort']??0)+1;
        }
        $world['hay']-=min($world['hay'],$need);
    }
    $world['grain']*=hasBuilding($world,'statek')?.9985:.998;
    $world['flour']*=.994;
    $world['hay']*=.997;
    $world['tools']*=.996;
    $reserve=dailyFoodNeed($world)*10;
    if ($world['food']>$reserve) $world['food']-=($world['food']-$reserve)*.02;
    if ($world['project']===null) chooseProject($db,$world);
}

function completeEconomyWork(PDO $db,array &$world,string $id,string $key): ?string {
    $p=$world['people'][$id];
    $power=max(.5,productivity($p)/70);
    switch ($key) {
        case 'mill':
            $n=min(6.0,$world['grain']);
            if ($n<1 || !hasBuilding($world,'mlyn')) return 'Nebylo co mlít.';
            $world['grain']-=$n;
            $world['flour']+=$n;
            countProduced($world,'flour',$n);
            return 'Z '.fmtAmount($n).' obilí je mouka.';
        case 'bake':
            $n=min(6.0,$world['flour']);
            if ($n<1 || !hasBuilding($world,'pec')) return 'Nebylo co péct.';
            $world['flour']-=$n;
            $world['food']+=$n*BREAD_FOOD;
            countProduced($world,'bread',$n*BREAD_FOOD);
            return 'Upečeno chleba za '.fmtAmount($n*BREAD_FOOD).' jídla.';
        case 'saw':
            $n=min(6.0,max(0.0,$world['wood']-8));
            if ($n<3 || !hasBuilding($world,'pila')) return 'Na prkna chybí dřevo.';
            consumeStock($world,'wood',$n);
            $world['planks']+=$n*SAW_RATIO;
            countProduced($world,'planks',$n*SAW_RATIO);
            return 'Z '.fmtAmount($n).' dřeva je '.fmtAmount($n*SAW_RATIO).' prken.';
        case 'craft':
            if ($world['planks']<2 || $world['stone']<1 || !hasBuilding($world,'dilna')) return 'Na nářadí chybí materiál.';
            consumeStock($world,'planks',2);
            consumeStock($world,'stone',1);
            $world['tools']+=1;
            countProduced($world,'tools',1);
            return 'Dílna vyrobila kus nářadí.';
        case 'mow':
            $gain=round(6.0*$power*($world['harvestUntil']>$world['minute']?$world['harvestFactor']:1),1);
            $world['hay']+=$gain;
            countProduced($world,'hay',$gain);
            return 'Do stodoly přibylo '.fmtAmount($gain).' sena.';
        case 'construct':
            $project=$world['project'];
            if ($project===null) return 'Stavba už není potřeba.';
            $cost=BUILDINGS[$project['key']]['cost'];
            if (!$project['paid']) {
                if (!canPay($world,$cost)) return 'Na stavbu zatím chybí materiál.';
                pay($world,$cost);
                $world['project']['paid']=true;
            }
            $world['project']['progress']+=min(1.5,$power);
            if ($world['project']['progress']>=$world['project']['work']) {
                $world['buildings'][$project['key']]=(int)$world['minute'];
                $world['project']=null;
                $world['counters']['built'][]=$project['key'];
                journal($db,$world,'','Dokončena stavba: '.BUILDINGS[$project['key']]['label'],BUILDINGS[$project['key']]['text'],'Hra');
                chooseProject($db,$world);
                return 'Stavba je hotová.';
            }
            return 'Stavba postoupila ('.fmtAmount($world['project']['progress']).' z '.$world['project']['work'].').';
        case 'firefight':
        case 'flood_defense':
            if (empty($world['crisis'])) return 'Nebezpečí už pominulo.';
            $world['crisis']['done']+=$power;
            return 'Obrana pokročila ('.fmtAmount($world['crisis']['done']).' z '.$world['crisis']['need'].').';
    }
    return null;
}
