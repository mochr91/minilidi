<?php
declare(strict_types=1);

const HOME_LIMIT = 4;
const HOUSE_WOOD = 12;
const HOUSE_STONE = 6;

function neighborFor(array $world, string $id, string $action): ?string {
    $person = $world['people'][$id];
    if ($action === 'withhold_tools') {
        foreach ($person['grudges'] as $other=>$expiry) {
            if ($expiry > $world['minute'] && isset($world['people'][$other]) &&
                empty($world['people'][$other]['toolPenaltyUntil'])) return $other;
        }
        return null;
    }
    $selected = null;
    $need = -INF;
    foreach ($world['people'] as $other=>$candidate) {
        if ($other === $id || $candidate['family'] === $person['family'] || ($candidate['awayUntil']??0)>$world['minute']) continue;
        $score = $candidate['hunger'] + $candidate['fatigue'] * .35 + (($person['bonds'][$other]??48)-48)*.65;
        if ($score > $need) { $selected = $other; $need = $score; }
    }
    return $selected;
}

function workAvailable(array $world, string $id, string $key): bool {
    if (($world['people'][$id]['awayUntil']??0)>$world['minute']) return false;
    if ($world['people'][$id]['age'] < 12 && !in_array($key,['rest','gathering','bring_water','deliver_meal','market','gather_neighbors'],true)) return false;
    if (!weatherAllows($world,$key)) return false;
    $person = $world['people'][$id];
    if (in_array($key,HEAVY_WORK,true) && isHeavyBlocked($world,$person)) return false;
    if ($person['age'] < 14 && in_array($key,['construct','saw','firefight','flood_defense','quarrying','felling'],true)) return false;
    $active = array_column(array_filter(array_column($world['people'],'task')),'action');
    return match ($key) {
        'haul_stone' => $world['quarryStone'] >= 1 && !in_array($key,$active,true),
        'fertilize' => $world['fieldCare'] < 2 && !in_array($key,$active,true),
        'fix_fence' => $world['fenceUntil'] <= $world['minute'] && $world['wood'] >= 2 && !in_array($key,$active,true),
        'deliver_meal' => $world['food'] >= 1 && neighborFor($world,$id,$key) !== null,
        'gather_neighbors' => $world['food'] >= 3 && !in_array($key,$active,true),
        'build_house' => count($world['homesBuilt']) < HOME_LIMIT && canPay($world,homeCost(count($world['homesBuilt']))) &&
            count($world['people']) >= housingCapacity($world)-4 && !in_array($key,$active,true),
        'withhold_tools' => neighborFor($world,$id,$key) !== null,
        'mill' => hasBuilding($world,'mlyn') && $world['grain'] >= 2 && !in_array($key,$active,true),
        'bake' => hasBuilding($world,'pec') && $world['flour'] >= 2 && !in_array($key,$active,true),
        'saw' => hasBuilding($world,'pila') && $world['wood'] >= 18 && !in_array($key,$active,true),
        'craft' => hasBuilding($world,'dilna') && $world['planks'] >= 2 && $world['stone'] >= 1 && !in_array($key,$active,true),
        'mow' => $world['hay'] < 70,
        'construct' => projectReady($world) && count(array_keys($active,'construct',true)) < 3,
        'firefight' => crisisAction($world)==='firefight' && ableForCrisis($world,$person),
        'flood_defense' => crisisAction($world)==='flood_defense' && ableForCrisis($world,$person),
        default => true,
    };
}

function assignWork(array &$world, string $id, string $key, bool $automatic, int $delay=0): void {
    $start = (int)$world['minute'] + $delay;
    $atStart = $world;
    $atStart['minute'] = $start;
    $duration = weatherWorkMinutes($atStart,$key,ACTIONS[$key]['minutes']??60);
    $task = ['action'=>$key,'start'=>$start,'end'=>$start+$duration];
    if ($automatic) $task['automatic'] = true;
    if (in_array($key,['help','deliver_meal','withhold_tools','bring_water'],true)) {
        $task['other'] = neighborFor($world,$id,$key);
    }
    if ($key === 'build_house') $task['plot'] = count($world['homesBuilt']);
    $world['people'][$id]['task'] = $task;
}

function rememberHelp(array &$world, string $id, string $other, float $amount): void {
    $a = &$world['people'][$id];
    $b = &$world['people'][$other];
    $a['bonds'][$other] = clamp(($a['bonds'][$other]??48)+$amount*.5);
    $b['bonds'][$id] = clamp(($b['bonds'][$id]??48)+$amount);
    unset($b['grudges'][$id]);
}

function rememberHarm(PDO $db, array &$world, string $actor, string $other, string $reason): void {
    $person = &$world['people'][$other];
    $person['bonds'][$actor] = clamp(($person['bonds'][$actor]??48)-8);
    $person['grudges'][$actor] = (int)$world['minute'] + 3*1440;
    journal($db,$world,$other,$person['name'].' si pamatuje křivdu',$reason,'Hra');
}

function consumeStock(array &$world, string $key, float $quantity): void {
    $world[$key] -= $quantity;
    if (isset($world['usedToday'])) $world['usedToday'][$key] = ($world['usedToday'][$key]??0) + $quantity;
}

function completeWork(PDO $db, array &$world, string $id): string {
    $p = &$world['people'][$id];
    $task = $p['task'];
    $key = $task['action'];
    $other = $task['other'] ?? null;
    $economy = completeEconomyWork($db,$world,$id,$key);
    if ($economy !== null) {
        maybeInjure($db,$world,$id,$key);
        return $economy;
    }
    switch ($key) {
        case 'prayer':
            $p['lastPrayer'] = (int)$world['minute'];
            $p['faith'] = clamp($p['faith']+2);
            $p['fear'] = clamp($p['fear']-2);
            $p['mood'] = clamp($p['mood']+5);
            faithEvent($db,$world,$p['name'].' končí obřad','Modlitba posílila víru a zmírnila strach.','obřad');
            return 'Obřad skončil.';

        case 'haul_stone':
            $qty = min(4.0,$world['quarryStone']);
            $world['quarryStone'] -= $qty;
            $world['stone'] += $qty;
            return 'Trakař dovezl do vesnice ' . number_format($qty,1,',',' ') . ' jednotek kamene.';
        case 'fertilize':
            $world['fieldCare'] = min(2,$world['fieldCare']+1);
            return 'Pohnojené pole zvýší výnos příští sklizně.';
        case 'fix_fence':
            if ($world['wood'] < 2) return 'Na dokončení opravy chybí dřevo.';
            consumeStock($world,'wood',2);
            $world['fenceUntil'] = (int)$world['minute'] + 3*1440;
            return 'Oprava spotřebovala 2 dřeva. Zvířata mají tři dny bezpečnější ohradu.';
        case 'deliver_meal':
            if (!$other || $world['food'] < 1) return 'Jídlo se nepodařilo doručit.';
            consumeStock($world,'food',1);
            $world['people'][$other]['hunger'] = clamp($world['people'][$other]['hunger']-34);
            rememberHelp($world,$id,$other,8);
            journal($db,$world,$other,$world['people'][$other]['name'].' dostává jídlo',$p['name'].' přinesl/a porci.','Hra');
            return 'Porci dostal/a ' . $world['people'][$other]['name'] . '.';
        case 'bring_water':
            if (!$other) return 'Voda je připravená u studny.';
            $world['people'][$other]['mood'] = clamp($world['people'][$other]['mood']+5);
            rememberHelp($world,$id,$other,4);
            return 'Vodu dostal/a ' . $world['people'][$other]['name'] . '.';
        case 'gather_neighbors':
            $guests=array_filter($world['people'],fn($person)=>commonPlace($person)===commonPlace($p));
            if (count($guests)<2) return 'Na místo nikdo další nedorazil.';
            if ($world['food'] < 3) return 'Na posezení nezbylo jídlo.';
            consumeStock($world,'food',3);
            foreach ($guests as $guest=>$person) {
                $world['people'][$guest]['fun'] = clamp($person['fun']+12);
                if ($guest !== $id) rememberHelp($world,$id,$guest,3);
            }
            return 'Posezení spotřebovalo 3 jídla a vesničané se lépe poznali.';
        case 'withhold_tools':
            if (!$other) return 'Nářadí zůstalo na svém místě.';
            $world['people'][$other]['toolPenaltyUntil'] = (int)$world['minute']+180;
            unset($p['grudges'][$other]);
            rememberHarm($db,$world,$id,$other,$p['name'].' odmítl/a půjčit nářadí.');
            return $world['people'][$other]['name'].' bude tři herní hodiny pracovat pomaleji.';
        case 'build_house':
            $cost = homeCost(count($world['homesBuilt']));
            if (!canPay($world,$cost) || count($world['homesBuilt']) >= HOME_LIMIT) {
                return 'Na dokončení domu chybí materiál nebo volné místo.';
            }
            pay($world,$cost);
            $world['homesBuilt'][] = (int)$world['minute'];
            maybeInjure($db,$world,$id,$key);
            return 'Nový dům spotřeboval '.implode(', ',array_map(fn($k,$v)=>$v.' '.RESOURCE_WORDS[$k],array_keys($cost),$cost)).'.';
    }
    if ($key === 'help' && $other) rememberHelp($world,$id,$other,6);
    $action = ACTIONS[$key];
    if (!isset($action['resource'])) return '';
    $gain = $action['yield'] * productivity($p)/100 * weatherYield($world,$key);
    if ($key==='fieldwork' && $world['fieldCare'] > 0) { $gain *= 1.4; --$world['fieldCare']; }
    if ($key==='livestock' && $world['fenceUntil'] > $world['minute']) $gain *= 1.25;
    if ($key==='livestock' && hasBuilding($world,'statek')) $gain *= 1.35;
    if ($key==='livestock' && !empty($world['hayShort'])) $gain *= .5;
    $gain = round($gain,1);
    $resource = $action['resource'];
    countProduced($world,$resource,$gain);
    maybeInjure($db,$world,$id,$key);
    if ($key==='quarrying') {
        $world['quarryStone'] += $gain;
        return 'U lomu je dalších ' . number_format($gain,1,',',' ') . ' jednotek kamene k odvozu.';
    }
    $world[$resource] += $gain;
    return 'Do zásob přidáno ' . number_format($gain,1,',',' ') . ' jednotek ' .
        (['wood'=>'dřeva.','grain'=>'obilí.'][$resource] ?? 'jídla.');
}

const NEWCOMERS = [
    ['Ondřej Novák','Novákovi','Hospodář','field','#856146'],
    ['Tereza Veselá','Veselí','Pomocnice','yard','#76425d'],
    ['Jakub Hrubý','Hrubí','Tesař','forest','#5d6b4a'],
    ['Alena Sedláčková','Sedláčkovi','Tkadlena','yard','#7a5a76'],
];

function welcomeNewcomer(PDO $db, array &$world): void {
    $number = $world['newcomers'];
    if ($number >= count($world['homesBuilt']) || $number >= count(NEWCOMERS) || $world['food'] < 2*(count($world['people'])+1) ||
        $world['wood'] < 3*villageWeather($world)['woodNeed'] || $world['minute']-$world['homesBuilt'][$number] < 120 ||
        count($world['people']) >= housingCapacity($world)) return;
    $id = 'novy'.($number+1);
    [$name,$family,$role,$work,$color] = NEWCOMERS[$number];
    $p = ['id'=>$id,'name'=>$name,'family'=>$family,'age'=>29,'role'=>$role,'work'=>$work,'color'=>$color,
        'charisma'=>54,'lastPrayer'=>-1440,'resistUntil'=>0,'illUntil'=>0,'awayUntil'=>0,
        'trait'=>'Do vsi přišla za prací a hledá dobré sousedy.','skill'=>66,'fatigue'=>30,'mood'=>65,'trust'=>45,'faith'=>42,
        'fear'=>12,'hunger'=>28,'fun'=>60,'place'=>'home','activity'=>'Zabydluje se','task'=>null,'bonds'=>[],'grudges'=>[]];
    $p += lifeDefaults($p,intdiv((int)$world['minute'],1440));
    foreach ($world['people'] as $other=>&$neighbor) {
        $p['bonds'][$other] = 48;
        $neighbor['bonds'][$id] = 48;
    }
    unset($neighbor);
    $world['people'][$id] = $p;
    ++$world['newcomers'];
    journal($db,$world,$id,$name.' se přistěhoval/a','Vesnice měla volný dům a zásoby na přijetí nového obyvatele.','Hra');
}
