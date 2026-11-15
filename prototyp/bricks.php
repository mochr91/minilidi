<?php
declare(strict_types=1);

// Cihelna (fáze G rozšíření ekonomiky, návrh v ROZSIRENI-EKONOMIKY.md bod 12):
// hlína z hliniště (březen až září), pálení cihel a tašek (květen až září, dřevo nebo uhlí z přebytku),
// přestavba doškových střech na tašky. Užitek: menší riziko a škody při požáru.
// Střechy: šest pevných domů má na začátku tašky (Kovářovi, Doležalovi, Královi) nebo došky (Hruškovi, Novákovi, Veselí),
// postavený dům má tašky, domek došky. Do `roofs` se zapisují jen přestavěné střechy ('tile').

const BRICK_TASKS = ['dig_clay','fire_bricks','reroof'];
const THATCH_STATIC = ['Hruškovi','Novákovi','Veselí'];
const CLAY_CAP = 80.0;
const CLAY_DIG = 6.0;
const BRICK_CLAY = 30.0;
const BRICK_BATCH = 20.0;
const BRICK_WOOD = 8.0;
const BRICK_COAL = 3.0;
const BRICKS_TARGET = 40.0;
const REROOF_BRICKS = 30.0;
const REROOF_HOURS = 14.0;
const ROOF_FIRE_RISK = .5;            // riziko požáru × (1 − tato hodnota × podíl přestavěných střech)
const ROOF_FIRE_DAMAGE = .35;         // škody při požáru × (1 − tato hodnota × podíl)
const BRICKS_SELL_KEEP = 40.0;

// Původní střecha domu podle `houseList` (bez přestaveb).
function roofDefault(array $house): string {
    if ($house['kind']==='static') return in_array($house['id'],THATCH_STATIC,true) ? 'thatch' : 'tile';
    return $house['kind']==='domek' ? 'thatch' : 'tile';
}

function roofMap(array $world): array {
    $map=[];
    foreach (houseList($world) as $id=>$house) $map[$id]=($world['roofs'][$id]??null)==='tile' ? 'tile' : roofDefault($house);
    return $map;
}

// Doškové domy, které jde přestavět (pevné domy první).
function thatchHouses(array $world): array {
    return array_keys(array_filter(roofMap($world),fn($r)=>$r==='thatch'));
}

// Podíl původně doškových střech, které už jsou přestavěné na tašky (0 až 1).
function roofShare(array $world): float {
    $original=0; $done=0;
    foreach (houseList($world) as $id=>$house) {
        if (roofDefault($house)!=='thatch') continue;
        ++$original;
        if (($world['roofs'][$id]??null)==='tile') ++$done;
    }
    return $original>0 ? $done/$original : 0.0;
}

function fireRiskFactor(array $world): float {
    return 1-ROOF_FIRE_RISK*roofShare($world);
}

function fireDamageFactor(array $world): float {
    return 1-ROOF_FIRE_DAMAGE*roofShare($world);
}

function kilnFuel(array $world): ?string {
    if (($world['charcoal']??0)>=BRICK_COAL+charcoalTarget($world)) return 'charcoal';
    if ($world['wood']>=BRICK_WOOD+20) return 'wood';
    return null;
}

function brickOpenDay(array $world): bool {
    $weather=villageWeather($world);
    return !$weather['frozen'] && $weather['ground']!=='snow' && $weather['precip']==='none';
}

// null = činnost tento modul nezná, jinak jestli je teď dostupná.
function bricksWorkAvailable(array $world,string $id,string $key): ?bool {
    if (!in_array($key,BRICK_TASKS,true)) return null;
    if (!hasBuilding($world,'cihelna')) return false;
    $m=calendarMonth($world);
    $project=$world['roofProject']??null;
    return match ($key) {
        'dig_clay'=>$m<=6 && ($world['clay']??0)<CLAY_CAP-CLAY_DIG && brickOpenDay($world) && taskCount($world,'dig_clay')<2,
        'fire_bricks'=>$m>=2 && $m<=6 && brickOpenDay($world) && ($world['clay']??0)>=BRICK_CLAY && ($world['bricks']??0)<BRICKS_TARGET
            && kilnFuel($world)!==null && taskCount($world,'fire_bricks')<1,
        'reroof'=>$m>=1 && $m<=8 && brickOpenDay($world) && taskCount($world,'reroof')<2
            && (($project!==null && ($project['paid'] || ($world['bricks']??0)>=REROOF_BRICKS)) || ($project===null && ($world['bricks']??0)>=REROOF_BRICKS && thatchHouses($world))),
    };
}

function bricksScore(array $world,string $key): int {
    return match ($key) {
        'dig_clay'=>($world['clay']??0)<BRICK_CLAY*1.5 ? 14 : 5,
        'fire_bricks'=>24,
        'reroof'=>28,
        default=>0,
    };
}

function completeBricksWork(PDO $db,array &$world,string $id,string $key): ?string {
    if (!in_array($key,BRICK_TASKS,true)) return null;
    $person=$world['people'][$id];
    $skill=taskSkillFactor($world,$id,$key);
    switch ($key) {
        case 'dig_clay':
            $n=min(CLAY_CAP-($world['clay']??0),CLAY_DIG*$skill);
            if ($n<=0 || !hasBuilding($world,'cihelna')) return 'Hliniště je prázdné.';
            $world['clay']=($world['clay']??0)+$n;
            countProduced($world,'clay',$n);
            maybeInjure($db,$world,$id,'dig_clay');
            return 'Nakopáno '.fmtAmount($n).' hlíny.';
        case 'fire_bricks':
            $fuel=kilnFuel($world);
            if (($world['clay']??0)<BRICK_CLAY || $fuel===null || !hasBuilding($world,'cihelna')) return 'Na pálení chybí hlína nebo palivo.';
            $world['clay']-=BRICK_CLAY;
            if ($fuel==='charcoal') $world['charcoal']-=BRICK_COAL; else consumeStock($world,'wood',BRICK_WOOD);
            $n=round(BRICK_BATCH*min(1.15,$skill),1);
            $world['bricks']=($world['bricks']??0)+$n;
            countProduced($world,'bricks',$n);
            return 'Vypáleno '.fmtAmount($n).' cihel a tašek ('.($fuel==='charcoal'?'uhlím':'dřevem').').';
        case 'reroof':
            $project=$world['roofProject']??null;
            if ($project===null) {
                $targets=thatchHouses($world);
                if (!$targets) return 'Všechny střechy už jsou z tašek.';
                $project=['home'=>$targets[0],'progress'=>0.0,'paid'=>false];
            }
            if (!$project['paid']) {
                if (($world['bricks']??0)<REROOF_BRICKS) return 'Na střechu chybí tašky.';
                $world['bricks']-=REROOF_BRICKS;
                $project['paid']=true;
            }
            $hours=max(0,(int)($person['task']['end']??0)-(int)($person['task']['start']??0))/60;
            $power=max(.5,productivity($person)/70)*$skill;
            $project['progress']+=$hours*min(1.5,$power/BUILD_NORM_POWER);
            if ($project['progress']>=REROOF_HOURS) {
                $home=(string)$project['home'];
                $world['roofs'][$home]='tile';
                $world['roofProject']=null;
                $houses=houseList($world);
                journal($db,$world,$id,'Střecha z došek na tašky','Dům se přestavěl na taškovou střechu ('.houseLabel($home,$houses).'). Hrozí menší škody při požáru.','Hra');
                maybeInjure($db,$world,$id,'reroof');
                return 'Střecha je přestavěná na tašky.';
            }
            $world['roofProject']=$project;
            maybeInjure($db,$world,$id,'reroof');
            return 'Střecha postoupila ('.fmtAmount($project['progress']).' z '.fmtAmount(REROOF_HOURS).' člověkohodin).';
    }
    return null;
}
