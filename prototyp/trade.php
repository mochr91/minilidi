<?php
declare(strict_types=1);

// Obchodník a úrok vrchnosti (fáze D rozšíření ekonomiky).
// Obchodník přijíždí jednou za měsíc a vymění přebytek za sůl, železo, látku a (jednou) měděný pivovarský kotel.
// Dvakrát ročně (sv. Michal 29. 9., sv. Martin 11. 11.) vesnice odevzdá vrchnosti obilí, vejce a slepici.

const TRADE_DAY = 15;                // v kterém dni měsíce obchodník přijede
const TRADE_FIRST_DAY = 45;          // nejdřív od tohoto dne hry (a když stojí hospoda)
const KETTLE_PRICE = 30.0;
const KETTLE_YIELD = 1.25;           // měděný kotel zvýší výtěžnost várky o čtvrtinu
const SALT_YIELD = .9;               // kyselé zelí se solí vyjde z 0,9 zeleniny, bez soli jen z 0,72
const SALT_YIELD_NONE = .72;
const CLOTH_KEEP = .998;
const CLOTH_WINTER_USE = .004;       // látky na osobu a den v zimě
const TRIBUTE_DUES = [
    'michal'=>['month'=>6,'day'=>29,'label'=>'sv. Michala','grain'=>2.5,'eggs'=>4,'hens'=>0],
    'martin'=>['month'=>8,'day'=>11,'label'=>'sv. Martina','grain'=>2.0,'eggs'=>6,'hens'=>1],
];

// Co se prodává (keep = kolik se nechá, price = cena za kus) a co se kupuje (cap = kolik si vesnice chce držet).
function tradeSellRules(array $world): array {
    return [
        'charcoal'=>[charcoalTarget($world),2.0],'eggs'=>[8.0,.25],'beer'=>[60.0,.05],'wine'=>[15.0,1.2],
        'hides'=>[0.0,1.5],'meatSmoked'=>[20.0,1.0],
        'cheese'=>[12.0,2.0],'leather'=>[LEATHER_TARGET,2.5],'boots'=>[bootsTarget($world)+2,5.0],
        'honey'=>[HONEY_SELL_KEEP,3.0],'wax'=>[WAX_SELL_KEEP,4.0],'bricks'=>[BRICKS_SELL_KEEP,.6],
        'lard'=>[LARD_SELL_KEEP,1.5],'wool'=>[WOOL_SELL_KEEP,2.5],
    ];
}

function tradeBuyRules(array $world): array {
    $people=max(1,count($world['people']));
    $rules=[];
    if (hasBuilding($world,'pivovar') && empty($world['trade']['kettle'])) $rules['kettle']=[1.0,KETTLE_PRICE];
    $rules['salt']=[12.0,1.2];
    if (hasBuilding($world,'kovarna')) $rules['iron']=[IRON_STOCK_MAX,5.0];
    // Vlastní tkalcovna látku vyrábí; do té doby se kupuje.
    if (!hasBuilding($world,'tkalcovna')) $rules['cloth']=[round($people*.5,1),3.0];
    return $rules;
}

// Jedna návštěva: přebytek se prodá, za tržbu (a uložený zůstatek) se koupí potřebné v pořadí kotel, sůl, železo, látka.
function tradeVisit(PDO $db,array &$world): void {
    $trade=$world['trade'];
    $credit=(float)($trade['credit']??0);
    $sold=[]; $bought=[];
    // Kolik stojí všechno, co vesnice chce koupit: prodává se jen tolik přebytku, aby to pokryl (směna, ne hromadění grošů).
    $wanted=0.0;
    foreach (tradeBuyRules($world) as $key=>[$cap,$price]) $wanted+=$key==='kettle' ? $price : max(0.0,floor($cap-(float)($world[$key]??0)))*$price;
    foreach (tradeSellRules($world) as $key=>[$keep,$price]) {
        if ($credit>=$wanted) break;
        $n=min(floor(max(0.0,($world[$key]??0)-$keep)),ceil(($wanted-$credit)/$price));
        if ($n<1) continue;
        $world[$key]-=$n;
        $credit+=$n*$price;
        $sold[]=fmtAmount((float)$n).' '.(RESOURCE_WORDS[$key]??$key);
    }
    foreach (tradeBuyRules($world) as $key=>[$cap,$price]) {
        if ($key==='kettle') {
            if ($credit>=$price) { $credit-=$price; $world['trade']['kettle']=true; $bought[]='měděný kotel pro pivovar'; }
            continue;
        }
        $have=(float)($world[$key]??0);
        $n=floor(min($cap-$have,$credit/$price));
        if ($n<1) continue;
        $credit-=$n*$price;
        $world[$key]=$have+$n;
        $bought[]=fmtAmount((float)$n).' '.(RESOURCE_WORDS[$key]??$key);
    }
    $world['trade']['credit']=round($credit,2);
    $world['trade']['visits']=($trade['visits']??0)+1;
    journal($db,$world,'','Přijel obchodník',
        ($sold?'Prodali jsme: '.implode(', ',$sold).'. ':'Nebylo co prodat. ').($bought?'Koupili jsme: '.implode(', ',$bought).'.':'Nic jsme nekoupili.').
        ' Obchodníkovi zůstalo na účtu '.fmtAmount($world['trade']['credit']).' grošů.','Hra');
}

function tradeDaily(PDO $db,array &$world): void {
    $now=(int)$world['minute'];
    $calendar=calendarAt($now,$world['calendarOriginDay']);
    $monthKey=$calendar['year']*12+$calendar['monthIndex'];
    $day=intdiv($now,1440);
    if ($day<TRADE_FIRST_DAY || !hasBuilding($world,'hospoda')) return;
    // Osada: jednou měsíčně 15. dne; větší vesnice se obchodníkovi vyplatí, takže jezdí po třech, dvou a týdnech (TRADE_INTERVALS).
    $rank=villageRank($world);
    if ($rank==='osada') {
        if ($calendar['day']!==TRADE_DAY || ($world['trade']['lastMonth']??-1)===$monthKey) return;
    } elseif ($day-(int)($world['trade']['lastDay']??-999)<TRADE_INTERVALS[$rank]) return;
    $world['trade']['lastMonth']=$monthKey;
    $world['trade']['lastDay']=$day;
    tradeVisit($db,$world);
}

// Úrok: obilí podle počtu dospělých, vejce a slepice. Co vesnice nemá nebo nesmí uvolnit (krmivo pro slepice), zůstane dlužné.
function tributeDaily(PDO $db,array &$world): void {
    $now=(int)$world['minute'];
    $calendar=calendarAt($now,$world['calendarOriginDay']);
    foreach (TRIBUTE_DUES as $key=>$due) {
        if ($calendar['monthIndex']!==$due['month'] || $calendar['day']!==$due['day'] || ($world['tribute']['paid'][$key]??-1)===$calendar['year']) continue;
        $world['tribute']['paid'][$key]=$calendar['year'];
        $adults=max(1,adultCount($world));
        $grainDue=(float)ceil($adults*$due['grain']);
        $grain=min($grainDue,max(0.0,$world['grain']-henGrainReserve($world)));
        $eggs=min((float)$due['eggs'],floor(max(0.0,$world['eggs'])));
        $hens=0;
        if ($due['hens']>0 && ($world['hens']??0)>=4) { $hens=$due['hens']; $world['hens']-=$hens; }
        $world['grain']-=$grain;
        $world['eggs']-=$eggs;
        $missing=1-($grain+.5*$eggs)/max(1.0,$grainDue+.5*$due['eggs']);
        $parts=[fmtAmount($grain).' obilí'];
        if ($eggs>0) $parts[]=fmtAmount($eggs).' vajec';
        if ($hens>0) $parts[]=$hens.' slepice';
        $world['tribute']['log'][]=['year'=>$calendar['year'],'kind'=>$key,'grain'=>$grain,'eggs'=>$eggs,'hens'=>$hens,'missing'=>round($missing,2)];
        $world['tribute']['log']=array_slice($world['tribute']['log'],-8);
        if ($missing>.2) {
            foreach ($world['people'] as &$p) if ($p['age']>=14) { $p['mood']=clamp($p['mood']-4); $p['fear']=clamp($p['fear']+3); }
            unset($p);
            journal($db,$world,'','Úrok '.$due['label'].' se nepodařilo odevzdat celý','Vrchnosti jsme dali '.implode(', ',$parts).', ale víc nebylo. Lidé mají strach z trestu.','Příběh');
        } else {
            journal($db,$world,'','Odevzdán úrok '.$due['label'],'Vrchnosti jsme odvedli '.implode(', ',$parts).'.','Příběh');
        }
    }
}

// Látka v zimě opotřebovává (teplé oblečení) a chrání před nemocemi; vyřizuje se v winterDaily přes clothed().
function clothed(array $world): bool {
    return ($world['cloth']??0)>=max(1,count($world['people']))*.25;
}
