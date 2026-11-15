<?php
declare(strict_types=1);

// Lázeň (fáze G rozšíření ekonomiky, návrh v ROZSIRENI-EKONOMIKY.md bod 12):
// sobotní koupel (12 až 19 h) spotřebuje dřevo, zvedne náladu a dá hygienu, která v zimě snižuje šanci nemoci.
// Lázeň se zavře, když dřevo klesne pod rezervu (zimní příprava topiva má přednost). Před postavením se nic nemění.

const BATH_TASK = 'bathe';
const BATH_WOOD = .35;                // dřeva na osobu a koupel
const BATH_SATURDAY = 5;              // den v týdnu (neděle je 6, viz isSunday)
const BATH_FROM = 12;
const BATH_TO = 19;
const BATH_RESERVE = 25.0;            // mimo zimu: pod tímto množstvím dřeva se nelázní
const BATH_RESERVE_WINTER = 70.0;     // v zimě (topivo na dvacet dní)
const BATH_MOOD = 5.0;
const BATH_MOOD_WINTER = 8.0;
const HYGIENE_DAYS = 14;              // kdo se koupal za posledních čtrnáct dní, je čistý
const HYGIENE_SICK_CUT = .4;          // šance zimní nemoci × (1 − tato hodnota × hygiena)

function isSaturday(array $world): bool {
    return intdiv((int)$world['minute'],1440)%7===BATH_SATURDAY;
}

function bathReserve(array $world): float {
    return isWinterMonth(calendarMonth($world)) ? BATH_RESERVE_WINTER : BATH_RESERVE;
}

// Podíl lidí od 12 let, kteří se koupali v posledních dnech (0 až 1); bez lázně 0.
function hygieneShare(array $world): float {
    if (!hasBuilding($world,'lazen')) return 0.0;
    $today=intdiv((int)$world['minute'],1440);
    $adults=0; $clean=0;
    foreach ($world['people'] as $p) {
        if ($p['age']<12) continue;
        ++$adults;
        if ($today-(int)($p['bathDay']??-1000)<=HYGIENE_DAYS) ++$clean;
    }
    return $adults>0 ? $clean/$adults : 0.0;
}

// null = činnost tento modul nezná, jinak jestli je teď dostupná.
function bathWorkAvailable(array $world,string $id,string $key): ?bool {
    if ($key!==BATH_TASK) return null;
    if (!hasBuilding($world,'lazen') || !isSaturday($world)) return false;
    $hour=intdiv((int)$world['minute']%1440,60);
    $p=$world['people'][$id];
    return $hour>=BATH_FROM && $hour<BATH_TO && (int)($p['bathDay']??-1000)!==intdiv((int)$world['minute'],1440)
        && $world['wood']>=bathReserve($world)+BATH_WOOD && empty($world['crisis']);
}

function bathScore(array $world,string $key): int {
    return $key===BATH_TASK ? 46 : 0;
}

function completeBathWork(PDO $db,array &$world,string $id,string $key): ?string {
    if ($key!==BATH_TASK) return null;
    if (!hasBuilding($world,'lazen')) return 'Lázeň nestojí.';
    $p=&$world['people'][$id];
    $p['bathDay']=intdiv((int)$world['minute'],1440);
    $wood=min(BATH_WOOD,max(0.0,(float)$world['wood']));
    consumeStock($world,'wood',$wood);
    $p['mood']=clamp($p['mood']+(isWinterMonth(calendarMonth($world)) ? BATH_MOOD_WINTER : BATH_MOOD));
    $world['counters']['baths']=($world['counters']['baths']??0)+1;
    unset($p);
    return 'Koupel v lázni zahřála a osvěžila.';
}

function bathDaily(PDO $db,array &$world): void {
    $world['hygiene']=round(hygieneShare($world),3);
}
