<?php
declare(strict_types=1);

const HOME_LIMIT = 10;
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
    if (!isset($world['people'][$id])) return false;
    if (($world['people'][$id]['awayUntil']??0)>$world['minute']) return false;
    if (!isset(ACTIONS[$key])) {
        $definition=$world['activityPack'][$key]??null;
        return $definition!==null && packActivityAvailable($world,$id,$definition);
    }
    if ($world['people'][$id]['age'] < 12 && !in_array($key,['rest','gathering','bring_water','deliver_meal','market','gather_neighbors'],true)) return false;
    if (!weatherAllows($world,$key)) return false;
    $person = $world['people'][$id];
    if (in_array($key,HEAVY_WORK,true) && isHeavyBlocked($world,$person)) return false;
    if ($person['age'] < 14 && in_array($key,['construct','saw','firefight','flood_defense','quarrying','felling','haul_wood','load_kiln','build_cart','brew_beer','make_malt','mine_ore','smelt_iron','forge_tools','press_wine','make_barrel','tan_hides','make_boots','dig_clay','fire_bricks','reroof','slaughter_pig','shear_sheep'],true)) return false;
    $active = array_column(array_filter(array_column($world['people'],'task')),'action');
    $forest = forestWorkAvailable($world,$id,$key);
    if ($forest !== null) return $forest;
    $forge = forgeWorkAvailable($world,$id,$key);
    if ($forge !== null) return $forge;
    $craft = craftWorkAvailable($world,$id,$key);
    if ($craft !== null) return $craft;
    $bees = beesWorkAvailable($world,$id,$key);
    if ($bees !== null) return $bees;
    $husbandry = husbandryWorkAvailable($world,$id,$key);
    if ($husbandry !== null) return $husbandry;
    $mayorWork = mayorWorkAvailable($world,$id,$key);
    if ($mayorWork !== null) return $mayorWork;
    $bricks = bricksWorkAvailable($world,$id,$key);
    if ($bricks !== null) return $bricks;
    $bath = bathWorkAvailable($world,$id,$key);
    if ($bath !== null) return $bath;
    $wine = wineWorkAvailable($world,$id,$key);
    if ($wine !== null) return $wine;
    $beer = beerWorkAvailable($world,$id,$key);
    if ($beer !== null) return $beer;
    $winter = winterWorkAvailable($world,$id,$key);
    if ($winter !== null) return $winter && !($key==='nurse' && in_array($key,$active,true));
    return match ($key) {
        'haul_stone' => $world['quarryStone'] >= 1 && count(array_keys($active,'haul_stone',true)) < 1+($world['carts']??0),
        'felling' => ($world['forestWood']??0) < FOREST_PILE_MAX,
        'fertilize' => $world['fieldCare'] < 2 && !in_array($key,$active,true),
        'fix_fence' => $world['fenceUntil'] <= $world['minute'] && $world['wood'] >= 2 && !in_array($key,$active,true),
        'deliver_meal' => $world['food'] >= 1 && neighborFor($world,$id,$key) !== null,
        'gather_neighbors' => $world['food'] >= 3 && !in_array($key,$active,true),
        'wedding' => false,
        'build_house' => houseWorkAvailable($world) && count(array_keys($active,'build_house',true)) < HOUSE_BUILDERS_MAX,
        'withhold_tools' => neighborFor($world,$id,$key) !== null,
        'mill' => hasBuilding($world,'mlyn') && $world['grain'] >= 2+henGrainReserve($world) &&!in_array($key,$active,true),
        'bake' => hasBuilding($world,'pec') && $world['flour'] >= 2 && !in_array($key,$active,true),
        'saw' => hasBuilding($world,'pila') && $world['wood'] >= 18 && !in_array($key,$active,true),
        'craft' => hasBuilding($world,'dilna') && $world['planks'] >= 2 && $world['stone'] >= 1 && !in_array($key,$active,true),
        'mow' => $world['hay'] + 9.0*count(array_keys($active,'mow',true)) < hayTarget($world),
        'construct' => projectReady($world) && count(array_keys($active,'construct',true)) < BUILDERS_MAX,
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
    if (!isset(ACTIONS[$key]) && isset($world['activityPack'][$key])) {
        $definition=$world['activityPack'][$key];
        $task['activity']=$definition;
        $task['end']=$start+$definition['minutes'];
        $task['other']=activityTarget($world,$id,$definition['effect']);
        if ($definition['effect']==='mediate') $task['dispute']=activityDispute($world,$id);
    }
    if ($automatic) $task['automatic'] = true;
    if (in_array($key,['help','deliver_meal','withhold_tools','bring_water'],true)) {
        $task['other'] = neighborFor($world,$id,$key);
    }
    if ($key === 'nurse') $task['other'] = nurseTarget($world,$id);
    // Svoz kamene a dřeva jede vozem, dokud je nějaký volný; jinak se nosí a vozí trakařem.
    if (in_array($key,['haul_stone','haul_wood'],true) && cartFree($world)) $task['cart'] = true;
    if ($key === 'build_house') $task['plot'] = (int)($world['houseProject']['index']??count($world['homesBuilt']));
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
    $task = $world['people'][$id]['task'] ?? null;
    $result = completeWorkCore($db,$world,$id);
    if ($task !== null) {
        trainSkill($db,$world,$id,actionCategory($world,$task['action'],$task),(float)max(0,$task['end']-$task['start']));
    }
    return $result;
}

function completeWorkCore(PDO $db, array &$world, string $id): string {
    $p = &$world['people'][$id];
    $task = $p['task'];
    $key = $task['action'];
    $other = $task['other'] ?? null;
    if (isset($task['activity'])) return completePackActivity($db,$world,$id,$task['activity'],$task);
    if ($key === 'wedding') return completeWedding($db,$world,$id);
    $forest = completeForestWork($db,$world,$id,$key);
    if ($forest !== null) return $forest;
    $forge = completeForgeWork($db,$world,$id,$key);
    if ($forge !== null) { maybeInjure($db,$world,$id,$key); return $forge; }
    $craft = completeCraftWork($db,$world,$id,$key);
    if ($craft !== null) return $craft;
    $bees = completeBeesWork($db,$world,$id,$key);
    if ($bees !== null) return $bees;
    $husbandry = completeHusbandryWork($db,$world,$id,$key);
    if ($husbandry !== null) return $husbandry;
    $mayorWork = completeMayorWork($db,$world,$id,$key);
    if ($mayorWork !== null) return $mayorWork;
    $bricks = completeBricksWork($db,$world,$id,$key);
    if ($bricks !== null) return $bricks;
    $bath = completeBathWork($db,$world,$id,$key);
    if ($bath !== null) return $bath;
    $wine = completeWineWork($db,$world,$id,$key);
    if ($wine !== null) return $wine;
    $beer = completeBeerWork($db,$world,$id,$key);
    if ($beer !== null) return $beer;
    $winter = completeWinterWork($db,$world,$id,$key);
    if ($winter !== null) return $winter;
    $economy = completeEconomyWork($db,$world,$id,$key);
    if ($economy !== null) {
        maybeInjure($db,$world,$id,$key);
        return $economy;
    }
    switch ($key) {
        case 'prayer':
            $p['lastPrayer'] = (int)$world['minute'];
            $church = hasBuilding($world,'kostel');
            $soft = softSkill($p,'faith');
            $p['faith'] = clamp($p['faith']+($church?3:2)*$soft);
            $p['fear'] = clamp($p['fear']-($church?3:2)*$soft);
            $p['mood'] = clamp($p['mood']+($church?7:5)*$soft);
            // Víno při nedělním obřadu v kostele posílí víru navíc.
            if ($church && intdiv((int)$world['minute'],1440)%7===6 && ($world['wine']??0)>=WINE_MASS) {
                $world['wine']-=WINE_MASS;
                $p['faith'] = clamp($p['faith']+1.5*$soft);
                $p['mood'] = clamp($p['mood']+1);
            }
            faithEvent($db,$world,$p['name'].' končí obřad','Modlitba posílila víru a zmírnila strach.','obřad');
            return 'Obřad skončil.';

        case 'haul_stone':
            $capacity = !empty($task['cart']) ? cartCapacity($world) : 4.0*(hasBuilding($world,'staje')?1.3:1.0);
            $qty = min($capacity*taskSkillFactor($world,$id,$key,$task),$world['quarryStone']);
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
            $soft = softSkill($p,'neighbors');
            $world['people'][$other]['hunger'] = clamp($world['people'][$other]['hunger']-34*$soft);
            rememberHelp($world,$id,$other,8*$soft);
            journal($db,$world,$other,$world['people'][$other]['name'].' dostává jídlo',$p['name'].' přinesl/a porci.','Hra');
            return 'Porci dostal/a ' . $world['people'][$other]['name'] . '.';
        case 'bring_water':
            if (!$other) return 'Voda je připravená u studny.';
            $soft = softSkill($p,'neighbors');
            $world['people'][$other]['mood'] = clamp($world['people'][$other]['mood']+5*$soft);
            rememberHelp($world,$id,$other,4*$soft);
            return 'Vodu dostal/a ' . $world['people'][$other]['name'] . '.';
        case 'gather_neighbors':
            $guests=array_filter($world['people'],fn($person)=>commonPlace($person)===commonPlace($p));
            if (count($guests)<2) return 'Na místo nikdo další nedorazil.';
            if ($world['food'] < 3) return 'Na posezení nezbylo jídlo.';
            consumeStock($world,'food',3);
            $soft = softSkill($p,'home');
            foreach ($guests as $guest=>$person) {
                $world['people'][$guest]['fun'] = clamp($person['fun']+12*$soft);
                if ($guest !== $id) rememberHelp($world,$id,$guest,3*$soft);
            }
            return 'Posezení spotřebovalo 3 jídla a vesničané se lépe poznali.';
        case 'withhold_tools':
            if (!$other) return 'Nářadí zůstalo na svém místě.';
            $world['people'][$other]['toolPenaltyUntil'] = (int)$world['minute']+180;
            unset($p['grudges'][$other]);
            rememberHarm($db,$world,$id,$other,$p['name'].' odmítl/a půjčit nářadí.');
            return $world['people'][$other]['name'].' bude tři herní hodiny pracovat pomaleji.';
        case 'build_house':
            return completeHouseWork($db,$world,$id);

    }
    if ($key === 'help' && $other) rememberHelp($world,$id,$other,6*softSkill($p,'neighbors'));
    $action = ACTIONS[$key]??[];
    if (!isset($action['resource'])) return '';
    $gain = $action['yield'] * productivity($p)/100 * weatherYield($world,$key) * taskSkillFactor($world,$id,$key,$task);
    if ($key==='fieldwork' && $world['fieldCare'] > 0) { $gain *= 1.4; --$world['fieldCare']; }
    if ($key==='livestock' && $world['fenceUntil'] > $world['minute']) $gain *= 1.25;
    if ($key==='livestock' && hasBuilding($world,'statek')) $gain *= 1.35;
    if ($key==='livestock') $gain *= livestockBuildingBoost($world);
    if ($key==='livestock' && !empty($world['hayShort'])) $gain *= .5;
    $gain = round($gain,1);
    $eggNote = '';
    if ($key==='livestock') {
        $eggs = collectEggs($world);
        if ($eggs > 0) $eggNote = ' Ve kurníku sebráno ' . number_format($eggs,1,',',' ') . ' vajec.';
    }
    $resource = $action['resource'];
    if ($key!=='fieldwork') countProduced($world,$resource,$gain);
    maybeInjure($db,$world,$id,$key);
    if ($key==='quarrying') {
        $world['quarryStone'] += $gain;
        return 'U lomu je dalších ' . number_format($gain,1,',',' ') . ' jednotek kamene k odvozu.';
    }
    if ($key==='felling') {
        $world['forestWood'] += $gain;
        return 'U lesa leží dalších ' . number_format($gain,1,',',' ') . ' dřeva k odvozu.';
    }
    if ($key==='fieldwork') {
        addFieldGrain($world,$gain);
        return 'Do zásob přidáno ' . number_format($gain,1,',',' ') . ' jednotek obilí.';
    }
    $world[$resource] += $gain;
    return 'Do zásob přidáno ' . number_format($gain,1,',',' ') . ' jednotek ' .
        (['wood'=>'dřeva.','grain'=>'obilí.'][$resource] ?? 'jídla.') . $eggNote;
}

const NEWCOMERS = [
    ['Ondřej Novák','Novákovi','Hospodář','field','#856146'],
    ['Tereza Veselá','Veselí','Pomocnice','yard','#76425d'],
    ['Jakub Hrubý','Hrubí','Tesař','forest','#5d6b4a'],
    ['Alena Sedláčková','Sedláčkovi','Tkadlena','yard','#7a5a76'],
];

function welcomeNewcomer(PDO $db, array &$world): void {
    $number = $world['newcomers'];
    if ($number >= count(NEWCOMERS)) return;
    // Příchozí se nastěhuje do volného domu (pevný dům jeho příjmení, jinak nejnovější volný) a až po NEWCOMER_FIRST_DAY dnech.
    $home = homeForNewFamily($world,NEWCOMERS[$number][1],1);
    if ($home === null || intdiv((int)$world['minute'],1440) < NEWCOMER_FIRST_DAY+NEWCOMER_GAP_DAYS*$number ||
        $world['food'] < 2*(count($world['people'])+1) || $world['wood'] < 3*villageWeather($world)['woodNeed']) return;
    $id = 'novy'.($number+1);
    [$name,$family,$role,$work,$color] = NEWCOMERS[$number];
    $p = ['id'=>$id,'name'=>$name,'family'=>$family,'home'=>$home,'age'=>29,'role'=>$role,'work'=>$work,'color'=>$color,
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
