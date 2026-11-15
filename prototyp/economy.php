<?php
declare(strict_types=1);

const BREAD_FOOD = 2.0;
const HEARTH_FLOUR_FOOD = 1.3;
const SAW_RATIO = 4/6;

const RESOURCES = [
    'food'=>'Jídlo','wood'=>'Dřevo','stone'=>'Kámen','grain'=>'Obilí','flour'=>'Mouka',
    'planks'=>'Prkna','hay'=>'Seno','tools'=>'Nářadí','eggs'=>'Vejce',
    'veg'=>'Zelenina','kraut'=>'Kyselé zelí','legumes'=>'Luštěniny','herbs'=>'Byliny','herbsDry'=>'Sušené byliny',
    'forestWood'=>'Dřevo u lesa','charcoal'=>'Dřevěné uhlí','game'=>'Zvěřina','meatSmoked'=>'Uzené maso',
    'barley'=>'Ječmen','malt'=>'Slad','hops'=>'Chmel','beerYoung'=>'Mladé pivo','beer'=>'Pivo',
    'ore'=>'Železná ruda','iron'=>'Železo','ironTools'=>'Železné nářadí','salt'=>'Sůl','cloth'=>'Látka','hides'=>'Kožky',
    'grapes'=>'Hrozny','wine'=>'Víno','barrels'=>'Sudy',
    'leather'=>'Useň','boots'=>'Boty','flax'=>'Len','milk'=>'Mléko','cheese'=>'Sýr',
];

const RESOURCE_WORDS = [
    'food'=>'jídla','wood'=>'dřeva','stone'=>'kamene','grain'=>'obilí','flour'=>'mouky',
    'planks'=>'prken','hay'=>'sena','tools'=>'nářadí','eggs'=>'vajec',
    'veg'=>'zeleniny','kraut'=>'zelí','legumes'=>'luštěnin','herbs'=>'bylin','herbsDry'=>'sušených bylin',
    'forestWood'=>'dřeva u lesa','charcoal'=>'uhlí','game'=>'zvěřiny','meatSmoked'=>'uzeného masa',
    'barley'=>'ječmene','malt'=>'sladu','hops'=>'chmele','beerYoung'=>'mladého piva','beer'=>'piva',
    'ore'=>'železné rudy','iron'=>'železa','ironTools'=>'železného nářadí','salt'=>'soli','cloth'=>'látky','hides'=>'kožek',
    'grapes'=>'hroznů','wine'=>'vína','barrels'=>'sudů',
    'leather'=>'useně','boots'=>'bot','flax'=>'lnu','milk'=>'mléka','cheese'=>'sýra',
];

// Slepice (první článek chovu): kurník dostane hejno, které žere obilí, snáší vejce a vejce se musí sbírat.
// Vejce zasytí, ale kazí se; sbírá je péče o zvířata. Liška chodí, když není opravený plot.
const HEN_START = 6;
const HEN_MAX = 12;
const HEN_FEED = .06;          // obilí na slepici a den
const EGG_FOOD = .8;           // jídlo z jednoho vejce
const EGG_KEEP = .93;          // zásoba vajec po jednom dni (kazí se 7 % denně)
const EGG_KEEP_CELLAR = .96;   // ve sklepě se vejce kazí 4 % denně
const HEN_LAY = ['Jaro'=>.7,'Léto'=>.8,'Podzim'=>.5,'Zima'=>.25];   // vejce na slepici a den při plném krmení
const HEN_FOX_CHANCE = .08;    // denní riziko lišky bez opraveného plotu
const HEN_RESTOCK_DAYS = 10;   // prázdný kurník vesnice po této době znovu osadí
const HEN_GRAIN_DAYS = 90;     // mlýn nechá slepicím obilí na tolik dní (jinak se vše semele na chléb a v zimě hejno vyhladoví)

// Pracnost staveb je v člověkohodinách běžného pracovníka: 100 h zvládne jeden člověk za 100 hodin práce,
// pět lidí za 20 hodin. Zkušený stavitel je rychlejší, nezkušený pomalejší (viz completeEconomyWork).
// Výkon, při kterém běžný dospělý vesničan (průměrná síla, nálada i zkušenost) odvede právě 1 člověkohodinu práce za hodinu.
const BUILD_NORM_POWER = 0.65;
const BUILDERS_MAX = 6;
const BUILDERS_PLANNED = 3;
// Dům je samostatná stavba vedle veřejných budov: pracnost v člověkohodinách, až 3 stavitelé, materiál se platí při první práci.
const HOUSE_WORK = 60;
const HOUSE_BUILDERS_MAX = 3;

const BUILDINGS = [
    'mlyn'=>['label'=>'Mlýn','cost'=>['wood'=>14,'stone'=>8],'work'=>100,'needs'=>[],
        'text'=>'Mele obilí na mouku.'],
    'pila'=>['label'=>'Pila','cost'=>['wood'=>16,'stone'=>6],'work'=>100,'needs'=>[],
        'text'=>'Řeže dřevo na prkna.'],
    'pec'=>['label'=>'Chlebová pec','cost'=>['wood'=>6,'stone'=>10],'work'=>80,'needs'=>['mlyn'],
        'text'=>'Z mouky peče chléb, který zasytí dvakrát víc než kaše.'],
    'studna'=>['label'=>'Obecní studna','cost'=>['wood'=>4,'stone'=>10],'work'=>60,'needs'=>[],
        'text'=>'Čistá voda: méně nemocí a menší škody při požáru.'],
    'dilna'=>['label'=>'Dílna','cost'=>['planks'=>8,'stone'=>6],'work'=>100,'needs'=>['pila'],
        'text'=>'Vyrábí nářadí, se kterým se pracuje rychleji.'],
    'statek'=>['label'=>'Statek','cost'=>['planks'=>10,'stone'=>8,'wood'=>6],'work'=>200,'needs'=>['pila'],
        'text'=>'Větší stádo, víc jídla a zásoba sena na zimu.'],
    'zahrada'=>['label'=>'Zahrada','cost'=>['wood'=>6],'work'=>30,'needs'=>['statek'],
        'text'=>'Zelenina a luštěniny: na jaře se sází, v létě okopává a na podzim sklízí. Sklizeň se kazí, proto je potřeba sklep.'],
    'hospoda'=>['label'=>'Hospoda','cost'=>['wood'=>12,'stone'=>8,'planks'=>10],'work'=>120,'needs'=>['pila'],
        'text'=>'Večerní setkávání u návsi zvedá náladu a dává sousedům příležitost utužit vztahy.'],
    'kurnik'=>['label'=>'Kurník','cost'=>['wood'=>8,'planks'=>2],'work'=>20,'needs'=>['statek'],
        'text'=>'Šest slepic snáší vejce. Žerou obilí, vejce je třeba sbírat při péči o zvířata a rychle se kazí.'],
    'chliv'=>['label'=>'Chlív pro prase','cost'=>['wood'=>10,'stone'=>4,'planks'=>3],'work'=>30,'needs'=>['statek'],
        'text'=>'Vykrmené prase: péče o zvířata dá o 8 % víc jídla, v zimě je potřeba trochu víc sena.'],
    'ohrada'=>['label'=>'Ohrada pro krávy','cost'=>['wood'=>14,'planks'=>4],'work'=>40,'needs'=>['statek'],
        'text'=>'Mléko a sýr: péče o zvířata dá o 12 % víc jídla, v zimě je potřeba víc sena.'],
    'sklep'=>['label'=>'Sklep','cost'=>['wood'=>8,'stone'=>12],'work'=>80,'needs'=>['zahrada'],
        'text'=>'Chladný sklep zpomalí kažení zeleniny a vajec a umožní nakládat zelí na zimu.'],
    'udirna'=>['label'=>'Udírna','cost'=>['wood'=>8,'stone'=>4],'work'=>30,'needs'=>['sklep'],
        'text'=>'Uzení zvěřiny z léček: z jednoho dne trvanlivosti je celá zima.'],
    'milir'=>['label'=>'Milíř','cost'=>['wood'=>6],'work'=>24,'needs'=>['dilna'],
        'text'=>'Z dřeva se tu za šest dní vypálí dřevěné uhlí. Potřebuje dohled, jinak se část uhlí ztratí nebo hranice vzplane.'],
    'chmelnice'=>['label'=>'Chmelnice','cost'=>['wood'=>10],'work'=>40,'needs'=>['zahrada'],
        'text'=>'Chmel na tyčích: na jaře se váže, na podzim česá. První rok dává jen málo, plná úroda přijde po roce.'],
    'sladovna'=>['label'=>'Sladovna','cost'=>['planks'=>6,'stone'=>8],'work'=>80,'needs'=>['sklep'],
        'text'=>'Z ječmene z pole se tu klíčením a sušením stává slad (od října do dubna).'],
    'pivovar'=>['label'=>'Pivovar','cost'=>['planks'=>12,'stone'=>8],'work'=>120,'needs'=>['sladovna','hospoda'],
        'text'=>'Ze sladu, chmele a dřeva se vaří pivo od sv. Michala do sv. Jiří. Zraje ve sklepě a pije se v hospodě.'],
    'kovarna'=>['label'=>'Kovárna','cost'=>['stone'=>10,'charcoal'=>4],'work'=>100,'needs'=>['dilna','milir'],
        'text'=>'Z železné rudy a dřevěného uhlí se tu taví železo a kuje železné nářadí, se kterým se pracuje rychleji.'],
    'dul'=>['label'=>'Důl','cost'=>['wood'=>20,'planks'=>10],'work'=>200,'needs'=>['kovarna'],
        'text'=>'Štola u lomu dává železnou rudu. Těžká práce s rizikem úrazu.'],
    'staje'=>['label'=>'Stáje','cost'=>['wood'=>16,'planks'=>8,'stone'=>6],'work'=>60,'needs'=>['statek','pila'],
        'text'=>'Koně s vozem svezou o třetinu víc kamene za jednu cestu; zvířata potřebují v zimě víc sena.'],
    'hrbitov'=>['label'=>'Hřbitov','cost'=>['wood'=>8,'stone'=>14],'work'=>100,'needs'=>['hospoda'],
        'text'=>'Důstojné místo posledního odpočinku: smrt blízkých zasáhne vesničany méně a vzbuzuje menší strach.'],
    'kostel'=>['label'=>'Kostel','cost'=>['wood'=>40,'planks'=>25,'stone'=>40],'work'=>500,'needs'=>['hrbitov','dilna'],
        'text'=>'Velká kamenná stavba: obřady posilují víru, náladu i klid víc než v kapli.'],
    'vinice'=>['label'=>'Vinice','cost'=>['wood'=>16],'work'=>150,'needs'=>['kostel'],
        'text'=>'Réva na jihovýchodním svahu: první úroda až po dvou letech, v dubnu hrozí pozdní mráz. Hrozny se česají na podzim.'],
    'lis'=>['label'=>'Lis','cost'=>['planks'=>8],'work'=>60,'needs'=>['vinice'],
        'text'=>'Z hroznů se tu lisuje víno, které kvasí šedesát dní v sudu ve sklepě. Sudy dělá dílna.'],
    'spychar'=>['label'=>'Špýchar','cost'=>['wood'=>14,'planks'=>8,'stone'=>6],'work'=>120,'needs'=>['sklep'],
        'text'=>'Suchá kamenná komora na obilí: obilí a mouka se kazí pomaleji a myši se k nim nedostanou.'],
    'mlekarna'=>['label'=>'Mlékárna','cost'=>['wood'=>6,'planks'=>4,'stone'=>6],'work'=>60,'needs'=>['ohrada','sklep'],
        'text'=>'Mléko od krav se tu sýří na sýr, který vydrží celou zimu a dá se prodat.'],
    'rybnik'=>['label'=>'Rybník','cost'=>['wood'=>6,'planks'=>2,'stone'=>8],'work'=>80,'needs'=>['studna','statek'],
        'text'=>'Kapří rybník: násada roste od jara do podzimu, na podzim se vylovuje.'],
    'kozeluzna'=>['label'=>'Koželužna a ševcovna','cost'=>['wood'=>10,'planks'=>4,'stone'=>6],'work'=>70,'needs'=>['chliv','dilna'],
        'text'=>'Z kožek se tu dělá useň a z ní boty, které v zimě chrání zdraví a náladu.'],
    'tkalcovna'=>['label'=>'Tkalcovna','cost'=>['planks'=>8,'wood'=>6],'work'=>90,'needs'=>['dilna'],
        'text'=>'Z lnu z pole se tu příde a tká látka na teplé oblečení; nemusí se kupovat od obchodníka.'],
    'fara'=>['label'=>'Fara a škola','cost'=>['wood'=>20,'planks'=>12,'stone'=>20],'work'=>200,'needs'=>['kostel'],
        'text'=>'U kostela bydlí kněz a učí děti číst, počítat a modlit se: děti rostou do řemesel rychleji.'],
];
const BUILD_ORDER = ['mlyn','pila','pec','studna','dilna','statek','zahrada','hospoda','kurnik','chliv','ohrada','sklep','udirna','milir','chmelnice','sladovna','pivovar','kovarna','dul','staje','hrbitov','kostel','vinice','lis','spychar','mlekarna','rybnik','kozeluzna','tkalcovna','fara'];
// Od zahrady dál se stavba plánuje, až je celý materiál na skladě; do té doby ho plánovač chystá (materialNeeds).
// Každou budovu staví vesničané z vlastních zásob, žádná nevznikne zdarma.
const BUILD_PREPAY = ['zahrada','hospoda','kurnik','chliv','ohrada','sklep','udirna','milir','chmelnice','sladovna','pivovar','kovarna','dul','staje','hrbitov','kostel','vinice','lis','spychar','mlekarna','rybnik','kozeluzna','tkalcovna','fara'];

function tavernOpen(array $world,int $hour): bool {
    return $hour>=18 && $hour<22 && hasBuilding($world,'hospoda') && empty($world['crisis']);
}

function economyDefaults(): array {
    return ['grain'=>4.0,'flour'=>0.0,'planks'=>0.0,'hay'=>8.0,'tools'=>2.0,'eggs'=>0.0,'hens'=>null,'coopEggs'=>0.0,'henEmptyDays'=>0,
        'veg'=>0.0,'kraut'=>0.0,'legumes'=>0.0,'herbs'=>0.0,'herbsDry'=>0.0,
        'forestWood'=>0.0,'charcoal'=>0.0,'game'=>0.0,'meatSmoked'=>0.0,'gameStock'=>70.0,'carts'=>0,'cartProject'=>null,
        'kiln'=>['state'=>'idle','end'=>0,'lastWatch'=>0,'loss'=>0.0],
        'barley'=>0.0,'malt'=>0.0,'hops'=>0.0,'beerYoung'=>0.0,'beer'=>0.0,'brewProject'=>null,'ore'=>0.0,'iron'=>0.0,'ironTools'=>0.0,'salt'=>0.0,'cloth'=>0.0,'hides'=>0.0,'grapes'=>0.0,'wine'=>0.0,'barrels'=>0.0,'barrelWork'=>0.0,'leather'=>0.0,'boots'=>0.0,'flax'=>0.0,'milk'=>0.0,'cheese'=>0.0,'pond'=>['fish'=>0.0,'stocked'=>false],'school'=>['lastDay'=>-1,'lessons'=>0],
        'wineBatch'=>['state'=>'idle','end'=>0,'yield'=>0.0],'vineyard'=>['season'=>-1,'care'=>0.0,'crop'=>0.0,'done'=>false,'frost'=>false],
        'trade'=>['credit'=>0.0,'visits'=>0,'lastMonth'=>-1,'kettle'=>false],'tribute'=>['paid'=>[],'log'=>[]],'beerLog'=>['brews'=>0,'drunk'=>0.0,'dry'=>0],'hopYard'=>['season'=>-1,'care'=>0.0,'crop'=>0.0,'done'=>false],
        'garden'=>['season'=>-1,'sown'=>0.0,'care'=>0.0,'crop'=>0.0,'done'=>false],
        'diet'=>['plant'=>0.0,'animal'=>0.0,'total'=>0.0],'dietToday'=>['plant'=>0.0,'animal'=>0.0,'total'=>0.0],'variety'=>1.0,'winterPrep'=>0.0,'winterPrepDetail'=>['food'=>0.0,'fuel'=>0.0,'hay'=>0.0,'min'=>0.0],
        'buildings'=>[],'buildLog'=>[],'project'=>null,'houseProject'=>null,'houseWanted'=>null,'houseKinds'=>[],
        'harvestFactor'=>1.0,'harvestUntil'=>0,'fieldBlockedUntil'=>0,'hayShort'=>false,'henHungry'=>false];
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
    return $world['food']+$world['grain']+$world['flour']*HEARTH_FLOUR_FOOD+($world['eggs']??0)*EGG_FOOD+plantStock($world)+(($world['game']??0)+($world['meatSmoked']??0))*GAME_FOOD+cheeseStock($world)*CHEESE_FOOD;
}

function drawFood(array &$world,float $cost): bool {
    if (foodReserve($world)<$cost) return false;
    $missing=$cost;
    $animal=0.0;
    // Vejce se kazí, proto se jedí jako první.
    if (($world['eggs']??0)>0) {
        $eggs=min($world['eggs'],$missing/EGG_FOOD);
        $world['eggs']-=$eggs;
        $missing-=$eggs*EGG_FOOD;
        $animal+=$eggs*EGG_FOOD;
    }
    // Sýr z mlékárny tvoří malou část každého jídla.
    if (($world['cheese']??0)>0 && $missing>.0001) {
        $cheese=min($world['cheese'],$cost*CHEESE_MEAL_SHARE/CHEESE_FOOD,$missing/CHEESE_FOOD);
        $world['cheese']-=$cheese;
        $missing-=$cheese*CHEESE_FOOD;
        $animal+=$cheese*CHEESE_FOOD;
    }
    // Čerstvá zvěřina se kazí rychle, proto se jí dřív než zásoby; s udírnou se jí jen část a zbytek se uzuje.
    if (($world['game']??0)>0 && $missing>.0001) {
        $fresh=hasBuilding($world,'udirna') ? $cost*.15 : $missing;
        $game=min($world['game'],$fresh/GAME_FOOD,$missing/GAME_FOOD);
        $world['game']-=$game;
        $missing-=$game*GAME_FOOD;
        $animal+=$game*GAME_FOOD;
    }
    // Zelenina, zelí a luštěniny tvoří část každého jídla (strava je pestrá), čerstvé dřív než uskladněné.
    $plant=0.0;
    $budget=$cost*PLANT_MEAL_SHARE;
    $winterNow=isWinterMonth(calendarMonth($world));
    foreach (PLANT_FOODS as $key=>$value) {
        if ($budget<=.0001 || $missing<=.0001) break;
        if (!$winterNow && $key!=='veg') continue;   // zelí a luštěniny se šetří na zimu
        $have=($world[$key]??0)*$value;
        if ($have<=0) continue;
        $take=min($have,$budget,$missing);
        $world[$key]-=$take/$value;
        $budget-=$take; $missing-=$take; $plant+=$take;
    }
    // Uzené maso se šetří na zimu a tvoří v ní část jídla.
    if ($winterNow && ($world['meatSmoked']??0)>0 && $missing>.0001) {
        $meat=min($world['meatSmoked']*GAME_FOOD,$cost*MEAT_MEAL_SHARE,$missing);
        $world['meatSmoked']-=$meat/GAME_FOOD;
        $missing-=$meat;
        $animal+=$meat;
    }
    $fromFood=min($world['food'],$missing);
    $world['food']-=$fromFood;
    $missing-=$fromFood;
    if ($missing>0 && $world['flour']>0) {
        $flour=min($world['flour'],$missing/HEARTH_FLOUR_FOOD);
        $world['flour']-=$flour;
        $missing-=$flour*HEARTH_FLOUR_FOOD;
    }
    if ($missing>0.0001) {
        $grain=min($world['grain'],$missing);
        $world['grain']-=$grain;
        $missing-=$grain;
    }
    // Co chybí na plné jídlo, doplní zbytek zeleniny a luštěnin.
    foreach (PLANT_FOODS as $key=>$value) {
        if ($missing<=.0001) break;
        $have=($world[$key]??0)*$value;
        if ($have<=0) continue;
        $take=min($have,$missing);
        $world[$key]-=$take/$value;
        $missing-=$take; $plant+=$take;
    }
    if ($missing>.0001 && ($world['meatSmoked']??0)>0) {
        $meat=min($world['meatSmoked']*GAME_FOOD,$missing);
        $world['meatSmoked']-=$meat/GAME_FOOD;
        $missing-=$meat;
        $animal+=$meat;
    }
    $world['dietToday']['animal']=($world['dietToday']['animal']??0)+$animal;
    $world['dietToday']['plant']=($world['dietToday']['plant']??0)+$plant;
    $world['dietToday']['total']=($world['dietToday']['total']??0)+$cost;
    return true;
}

function adultCount(array $world): int {
    return count(array_filter($world['people'],fn($p)=>$p['age']>=14));
}

// Chovatelské budovy zvyšují výnos péče o zvířata; zvířata v zimě žerou seno.
function livestockBuildingBoost(array $world): float {
    $boost=1.0;
    // Kurník má vlastní řetězec (slepice, vejce); procenta zůstávají u zatím nepřepracovaných chovů.
    foreach (['chliv'=>.08,'ohrada'=>.12] as $key=>$bonus) if (hasBuilding($world,$key)) $boost+=$bonus;
    return $boost;
}

function winterHayNeed(array $world): float {
    $need=hasBuilding($world,'statek') ? 1.2 : .6;
    foreach (['chliv'=>.15,'ohrada'=>.4,'staje'=>.3] as $key=>$extra) if (hasBuilding($world,$key)) $need+=$extra;
    return $need;
}

function henInit(PDO $db,array &$world): void {
    $world['hens']=(float)HEN_START;
    $world['henEmptyDays']=0;
    journal($db,$world,'','Do kurníku přibylo hejno','Šest slepic se zabydlelo. Žerou obilí a snášejí vejce, která je třeba sbírat při péči o zvířata.','Hra');
}

// Sebrání vajec z kurníku při péči o zvířata; vrací počet vajec. Nesebraná vejce se ve slepičinci kazí.
function collectEggs(array &$world): float {
    $n=(float)($world['coopEggs']??0);
    if ($n<=0 || !hasBuilding($world,'kurnik')) return 0.0;
    $world['eggs']=($world['eggs']??0)+$n;
    $world['coopEggs']=0.0;
    countProduced($world,'eggs',$n);
    return $n;
}

// Denní chod kurníku: kažení vajec, krmení, růst a úbytek hejna, snášení.
function henDaily(PDO $db,array &$world): void {
    $world['eggs']=($world['eggs']??0)*(hasBuilding($world,'sklep')?EGG_KEEP_CELLAR:EGG_KEEP);
    if ($world['eggs']<.05) $world['eggs']=0.0;
    if (!hasBuilding($world,'kurnik')) return;
    $now=(int)$world['minute'];
    $day=intdiv($now,1440);
    if ($world['hens']===null) henInit($db,$world);
    if ($world['hens']<1) {
        $world['hens']=0.0;
        if (++$world['henEmptyDays']>=HEN_RESTOCK_DAYS) {
            $world['hens']=3.0;
            $world['henEmptyDays']=0;
            journal($db,$world,'','Kurník znovu osazen','Sousedé přinesli tři slepice, aby kurník nezůstal prázdný.','Hra');
        }
        return;
    }
    $season=calendarAt($now,$world['calendarOriginDay'])['season'];
    $need=$world['hens']*HEN_FEED;
    $fed=$need>0 ? min(1.0,max(0.0,$world['grain'])/$need) : 1.0;
    $world['grain']-=$need*$fed;
    if ($fed<1) {
        $world['hens']-=$world['hens']*(1-$fed)*.08;
        $world['counters']['henHungry']=($world['counters']['henHungry']??0)+1;
        if (empty($world['henHungry'])) {
            $world['henHungry']=true;
            journal($db,$world,'','Slepice hladoví','V zásobách chybí obilí a slepice méně snášejí. Hejno řídne.','Hra');
        }
    } else {
        $world['henHungry']=false;
        if ($world['hens']<HEN_MAX && in_array($season,['Jaro','Léto'],true)) $world['hens']=min((float)HEN_MAX,$world['hens']+.12);
    }
    if ($world['fenceUntil']<=$now && $world['hens']>=2 && weatherNoise($day,'liska')<HEN_FOX_CHANCE) {
        $world['hens']-=1;
        $world['counters']['henFox']=($world['counters']['henFox']??0)+1;
        journal($db,$world,'','Liška v kurníku','Bez opraveného plotu se k slepicím dostala liška a jednu odnesla.','Hra');
    }
    $lay=(HEN_LAY[$season]??.5)*(.4+.6*$fed)*max(0.0,$world['hens']);
    $world['coopEggs']=min(($world['coopEggs']??0)+$lay,max(2.0,$world['hens']*2));
}

// Obilí, které se nemele: krmivo pro slepice na HEN_GRAIN_DAYS dní.
function henGrainReserve(array $world): float {
    return max(0.0,(float)($world['hens']??0))*HEN_FEED*HEN_GRAIN_DAYS;
}

function toolFactor(array $world): float {
    $need=max(1,(int)ceil(adultCount($world)/3));
    // Železné nářadí z kovárny pokryje potřebu a navíc zrychlí práci o IRON_TOOL_BONUS.
    $iron=($world['ironTools']??0)/$need;
    return .86+.2*min(1,$world['tools']/$need+$iron)+IRON_TOOL_BONUS*min(1,$iron);
}

// Domek (čtyři místa) je levnější a rychlejší než dům (šest míst); kapacita bydlení je v household.php.
function homeCost(int $built,string $kind='dum'): array {
    if ($kind==='domek') return ['wood'=>8,'stone'=>4];
    return $built===0 ? ['wood'=>HOUSE_WOOD,'stone'=>HOUSE_STONE] : ['wood'=>6,'planks'=>6,'stone'=>HOUSE_STONE];
}

// Jaké bydlení vesnice potřebuje postavit: null (žádné), 'domek' nebo 'dum'. Rozestavěná stavba a čekající mlynářova rodina mají přednost.
function houseKindWanted(array $world): ?string {
    if (!empty($world['houseProject']['kind'])) return $world['houseProject']['kind'];
    if (($world['houseWanted']??null)!==null) return $world['houseWanted'];
    return millerWaiting($world) ? 'dum' : null;
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
        if (!$ok) continue;
        // Pevné pořadí: nezaplatitelná stavba z BUILD_PREPAY počká, další se před ní nezačne.
        if (in_array($key,BUILD_PREPAY,true) && !canPay($world,BUILDINGS[$key]['cost'])) break;
        $options[]=$key;
    }
    return $options;
}

// Pořadí staveb je pevné (BUILD_ORDER); každá vesnice ho zvládá vlastním tempem podle zásob a lidí.
function chooseProject(PDO $db,array &$world): void {
    if ($world['project']!==null) return;
    $options=projectOptions($world);
    if (!$options) return;
    $key=$options[0];
    $world['project']=['key'=>$key,'label'=>BUILDINGS[$key]['label'],'progress'=>0.0,'work'=>BUILDINGS[$key]['work'],
        'paid'=>false,'since'=>(int)$world['minute'],'hours'=>[]];
    journal($db,$world,'','Obec plánuje stavbu: '.BUILDINGS[$key]['label'],BUILDINGS[$key]['text'],'Hra');
}

function materialNeeds(array $world): array {
    $needs=[];
    $project=$world['project'];
    $plannedKey=$project!==null && !$project['paid'] ? $project['key'] : null;
    if ($project===null) {
        // Až je na řadě stavba z BUILD_PREPAY, plánovač chystá zásoby ještě před jejím zařazením.
        foreach (BUILD_ORDER as $key) {
            if (hasBuilding($world,$key)) continue;
            $ready=true;
            foreach (BUILDINGS[$key]['needs'] as $need) if (!hasBuilding($world,$need)) $ready=false;
            if (!$ready) continue;
            if (in_array($key,BUILD_PREPAY,true)) $plannedKey=$key;
            break;
        }
    }
    if ($plannedKey!==null) {
        foreach (BUILDINGS[$plannedKey]['cost'] as $key=>$amount) {
            if ($world[$key]<$amount) $needs[$key]=($needs[$key]??0)+$amount-$world[$key];
        }
    }
    if (count($world['homesBuilt'])<HOME_LIMIT && empty($world['houseProject']['paid']) && ($kind=houseKindWanted($world))!==null) {
        foreach (homeCost(count($world['homesBuilt']),$kind) as $key=>$amount) {
            if ($world[$key]<$amount) $needs[$key]=max($needs[$key]??0,$amount-$world[$key]);
        }
    }
    return $needs;
}

// Zápis stavitelů po dokončení stavby (veřejné budovy i domy): buildLog a věta pro kroniku.
function recordBuilders(array &$world,string $key,array $project): string {
    $builders=[];
    foreach (($project['hours']??[]) as $builder=>$spent) {
        $builders[]=['id'=>$builder,'name'=>$world['people'][$builder]['name']??$builder,'hours'=>round((float)$spent,1)];
    }
    usort($builders,fn($a,$b)=>$b['hours']<=>$a['hours']);
    $worked=array_sum(array_column($builders,'hours'));
    $world['buildLog'][$key]=['start'=>(int)($project['since']??0),'end'=>(int)$world['minute'],
        'work'=>$project['work'],'worked'=>round($worked,1),'builders'=>$builders];
    $who=implode(', ',array_map(fn($b)=>$b['name'].' '.fmtAmount($b['hours']).' h',$builders));
    return 'Stavěli: '.$who.'. Odpracováno '.fmtAmount($worked).' h z plánovaných '.$project['work'].'.';
}

// Dá se teď na domě pracovat? Rozestavěný dům jde dokončit, nový se začne při nedostatku bydlení a dost materiálu.
function houseWorkAvailable(array $world): bool {
    if (count($world['homesBuilt'])>=HOME_LIMIT) return false;
    $house=$world['houseProject']??null;
    if ($house!==null) return $house['paid'] || canPay($world,homeCost((int)$house['index'],$house['kind']??'dum'));
    $kind=houseKindWanted($world);
    return $kind!==null && canPay($world,homeCost(count($world['homesBuilt']),$kind));
}

function completeHouseWork(PDO $db,array &$world,string $id): string {
    $p=$world['people'][$id];
    if (count($world['homesBuilt'])>=HOME_LIMIT) return 'Na dokončení domu chybí volné místo.';
    $house=$world['houseProject']??null;
    if ($house===null) {
        $kind=houseKindWanted($world)??'dum';
        $house=['index'=>count($world['homesBuilt']),'kind'=>$kind,'work'=>$kind==='domek'?COTTAGE_WORK:HOUSE_WORK,'progress'=>0.0,'paid'=>false,'since'=>(int)$world['minute'],'hours'=>[]];
    }
    $kind=$house['kind']??'dum';
    $cost=homeCost((int)$house['index'],$kind);
    if (!$house['paid']) {
        if (!canPay($world,$cost)) return 'Na stavbu domu zatím chybí materiál.';
        pay($world,$cost);
        $house['paid']=true;
    }
    $power=max(.5,productivity($p)/70)*taskSkillFactor($world,$id,'build_house');
    $hours=max(0,(int)($p['task']['end']??0)-(int)($p['task']['start']??0))/60;
    $house['progress']+=$hours*min(1.5,$power/BUILD_NORM_POWER);
    $house['hours'][$id]=round(($house['hours'][$id]??0)+$hours,2);
    if ($house['progress']>=$house['work']) {
        $world['houseKinds'][(int)$house['index']]=$kind;
        $world['homesBuilt'][]=(int)$world['minute'];
        $world['houseProject']=null;
        $world['houseWanted']=null;
        $who=recordBuilders($world,'dum'.($house['index']+1),$house);
        journal($db,$world,'',$kind==='domek'?'Dokončen nový domek':'Dokončen nový dům',($kind==='domek'?'Domek pro čtyři lidi ':'Dům pro šest lidí ').'čeká na obyvatele. '.$who,'Hra');
        maybeInjure($db,$world,$id,'build_house');
        return $kind==='domek'?'Nový domek je hotový.':'Nový dům je hotový.';
    }
    $world['houseProject']=$house;
    maybeInjure($db,$world,$id,'build_house');
    return 'Dům postoupil ('.fmtAmount($house['progress']).' z '.$house['work'].' člověkohodin).';
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
        $need=winterHayNeed($world);
        if ($world['hay']<$need) {
            $world['hayShort']=true;
            $world['counters']['hayShort']=($world['counters']['hayShort']??0)+1;
        }
        $world['hay']-=min($world['hay'],$need);
    }
    $granary=hasBuilding($world,'spychar');
    $world['grain']*=$granary?.9993:(hasBuilding($world,'statek')?.9985:.998);
    $world['flour']*=$granary?.997:.994;
    $world['hay']*=.997;
    $world['tools']*=.996;
    henDaily($db,$world);
    winterDaily($db,$world);
    forestDaily($db,$world);
    beerDaily($db,$world);
    forgeDaily($db,$world);
    craftsDaily($db,$world);
    wineDaily($db,$world);
    tributeDaily($db,$world);
    tradeDaily($db,$world);
    $reserve=dailyFoodNeed($world)*10;
    if ($world['food']>$reserve) $world['food']-=($world['food']-$reserve)*.02;
    if ($world['project']===null) chooseProject($db,$world);
}

function completeEconomyWork(PDO $db,array &$world,string $id,string $key): ?string {
    $p=$world['people'][$id];
    $skill=taskSkillFactor($world,$id,$key);
    $power=max(.5,productivity($p)/70)*$skill;
    switch ($key) {
        case 'mill':
            $n=min(6.0*$skill,$world['grain']-henGrainReserve($world));
            if ($n<1 || !hasBuilding($world,'mlyn')) return 'Nebylo co mlít.';
            $world['grain']-=$n;
            $world['flour']+=$n;
            countProduced($world,'flour',$n);
            return 'Z '.fmtAmount($n).' obilí je mouka.';
        case 'bake':
            $n=min(6.0*$skill,$world['flour']);
            if ($n<1 || !hasBuilding($world,'pec')) return 'Nebylo co péct.';
            $world['flour']-=$n;
            $world['food']+=$n*BREAD_FOOD;
            countProduced($world,'bread',$n*BREAD_FOOD);
            return 'Upečeno chleba za '.fmtAmount($n*BREAD_FOOD).' jídla.';
        case 'saw':
            $n=min(6.0*$skill,max(0.0,$world['wood']-8));
            if ($n<3 || !hasBuilding($world,'pila')) return 'Na prkna chybí dřevo.';
            consumeStock($world,'wood',$n);
            $world['planks']+=$n*SAW_RATIO;
            countProduced($world,'planks',$n*SAW_RATIO);
            return 'Z '.fmtAmount($n).' dřeva je '.fmtAmount($n*SAW_RATIO).' prken.';
        case 'craft':
            if ($world['planks']<2 || $world['stone']<1 || !hasBuilding($world,'dilna')) return 'Na nářadí chybí materiál.';
            consumeStock($world,'planks',2*$skill);
            consumeStock($world,'stone',$skill);
            // S dřevěným uhlím se nářadí v ohni zakalí a z jednoho kusu je půldruhého nářadí.
            $tempered=($world['charcoal']??0)>=1;
            if ($tempered) $world['charcoal']-=1;
            $made=$skill*($tempered?TEMPERED_TOOLS:1.0);
            $world['tools']+=$made;
            countProduced($world,'tools',$made);
            return $tempered ? 'Dílna vyrobila nářadí a zakalila ho v uhlí.' : 'Dílna vyrobila kus nářadí.';
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
            // Postup = odpracované hodiny × výkon vůči normě (síla, nálada, zkušenost); běžný vesničan za hodinu postoupí o 1 h.
            $hours=max(0,(int)($p['task']['end']??0)-(int)($p['task']['start']??0))/60;
            $world['project']['progress']+=$hours*min(1.5,$power/BUILD_NORM_POWER);
            $world['project']['hours'][$id]=round(($world['project']['hours'][$id]??0)+$hours,2);
            if ($world['project']['progress']>=$world['project']['work']) {
                $project=$world['project'];
                $who=recordBuilders($world,$project['key'],$project);
                $world['buildings'][$project['key']]=(int)$world['minute'];
                $world['project']=null;
                $world['counters']['built'][]=$project['key'];
                journal($db,$world,'','Dokončena stavba: '.BUILDINGS[$project['key']]['label'],BUILDINGS[$project['key']]['text'].' '.$who,'Hra');
                if ($project['key']==='kurnik' && $world['hens']===null) henInit($db,$world);
                chooseProject($db,$world);
                return 'Stavba je hotová.';
            }
            return 'Stavba postoupila ('.fmtAmount($world['project']['progress']).' z '.$world['project']['work'].' člověkohodin).';
        case 'firefight':
        case 'flood_defense':
            if (empty($world['crisis'])) return 'Nebezpečí už pominulo.';
            $world['crisis']['done']+=$power;
            return 'Obrana pokročila ('.fmtAmount($world['crisis']['done']).' z '.$world['crisis']['need'].').';
    }
    return null;
}
