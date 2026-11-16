<?php
declare(strict_types=1);

// Les a doprava (fáze B rozšíření ekonomiky, návrh v ROZSIRENI-EKONOMIKY.md):
// pokácené dřevo leží u lesa a musí se svézt, vůz zrychlí svoz dřeva a kamene, milíř dává dřevěné uhlí,
// lov drobné zvěře (zajíc, králík, bažant) a udírna, která zvěřinu uchová přes zimu.

const CART_MAX = 3;
const CART_WORK = 24.0;              // člověkohodin na jeden vůz
const CART_COST = ['planks'=>8,'wood'=>6,'tools'=>1];
const CART_BASE = 10.0;              // jednotek na jednu cestu s lidským tahem
const CART_OX = 12.0;                // s volem z ohrady
const CART_HORSE = 14.0;             // s koněm ze stájí
const CART_STONE_THRESHOLD = 12.0;   // kámen u lomu, od kterého se vyplatí vyrobit vůz
const CART_WOOD_THRESHOLD = 30.0;    // dřevo u lesa, od kterého se vyplatí vyrobit vůz
const WOOD_HAND_TRIP = 6.0;          // cesta s nůší a saněmi bez vozu
const FOREST_PILE_MAX = 60.0;        // víc pokáceného dřeva u lesa se nehromadí
const FOREST_PILE_KEEP = .995;       // pokácené dřevo venku za den trochu zvlhne a práchniví

const KILN_WOOD = 40.0;
const KILN_YIELD = 10.0;
const KILN_DAYS = 6;
const KILN_WATCH_GAP = 720;          // minut bez dohledu, po kterých se začne ztrácet uhlí
const KILN_LOSS_STEP = .25;          // ztráta uhlí za každý den bez dohledu
const KILN_FIRE_CHANCE = .05;        // šance, že hranice při nedohlédnutí vzplane
const CHARCOAL_TARGET = 12.0;        // do této zásoby se pálí
const TEMPERED_TOOLS = 1.5;          // nářadí z jednoho kusu při kalení uhlím

const GAME_FOOD = 1.5;               // sytost jedné jednotky zvěřiny, čerstvé i uzené
const GAME_KEEP = .8;                // čerstvá zvěřina se kazí 20 % denně
const MEAT_KEEP = .996;              // uzená 0,4 % denně
const GAME_TRIP = 3.0;               // jednotek zvěřiny z jedné výpravy při plné zvěři
const GAME_REGEN = .8;               // obnova zásoby zvěře za den
const GAME_STOCK_MAX = 100.0;
const GAME_LOAD = 1.5;               // o kolik úlovek zásobu zvěře uleví
const SMOKE_YIELD = .9;
const SMOKE_BATCH = 6.0;
const MEAT_MEAL_SHARE = .10;         // uzené maso tvoří v zimě až tuto část jídla

function taskCount(array $world,string $key): int {
    $n=0;
    foreach ($world['people'] as $p) if (($p['task']['action']??null)===$key) ++$n;
    return $n;
}

function cartCapacity(array $world): float {
    return hasBuilding($world,'staje') ? CART_HORSE : (hasBuilding($world,'ohrada') ? CART_OX : CART_BASE);
}

// Je volný vůz? Vůz je zapřažený, dokud jeho cesta (svoz kamene nebo dřeva) neskončí.
function cartFree(array $world): bool {
    $used=0;
    foreach ($world['people'] as $p) if (!empty($p['task']['cart'])) ++$used;
    return ($world['carts']??0)>$used;
}

function huntMonth(int $m): bool {
    return $m>=7 && $m<=11;              // říjen až únor
}

// null = činnost tento modul nezná, jinak jestli je teď dostupná.
function forestWorkAvailable(array $world,string $id,string $key): ?bool {
    if (!in_array($key,['haul_wood','build_cart','load_kiln','watch_kiln','hunt_small','smoke_meat'],true)) return null;
    $m=calendarMonth($world);
    $kiln=$world['kiln']??['state'=>'idle'];
    return match ($key) {
        'haul_wood'=>($world['forestWood']??0)>=1 && taskCount($world,'haul_wood')<2+($world['carts']??0),
        'build_cart'=>hasBuilding($world,'dilna') && ($world['carts']??0)<CART_MAX && taskCount($world,'build_cart')<2
            && (!empty($world['cartProject']['paid']) || canPay($world,CART_COST))
            && ($world['cartProject']!==null || $world['quarryStone']>=CART_STONE_THRESHOLD || ($world['forestWood']??0)>=CART_WOOD_THRESHOLD),
        'load_kiln'=>hasBuilding($world,'milir') && $kiln['state']==='idle' && $world['wood']>=KILN_WOOD && $m<=7
            && ($world['charcoal']??0)<charcoalTarget($world),
        'watch_kiln'=>$kiln['state']==='burning' && taskCount($world,'watch_kiln')<1,
        'hunt_small'=>huntMonth($m) && ($world['gameStock']??0)>=10 && villageWeather($world)['wind']<35,
        'smoke_meat'=>hasBuilding($world,'udirna') && (($world['game']??0)+($world['pork']??0))>=3 && $world['wood']>=2 && taskCount($world,'smoke_meat')<1,
    };
}

// Doplňkové body pro plánovač.
function forestScore(array $world,string $key): int {
    $short=$world['wood']<3*villageWeather($world)['woodNeed'];
    $kiln=$world['kiln']??['lastWatch'=>0];
    $pile=$world['forestWood']??0;
    return match ($key) {
        'haul_wood'=>$pile>=6 ? 16+($short?16:0)+(int)min(10,$pile/5) : 0,
        'build_cart'=>30,
        'load_kiln'=>20,
        'watch_kiln'=>($world['minute']-($kiln['lastWatch']??0))>=360 ? 40 : 12,
        'hunt_small'=>foodReserve($world)<8*dailyFoodNeed($world) ? 24 : ((($world['variety']??1)<.8 && huntMonth(calendarMonth($world))) ? 14 : 5),
        'smoke_meat'=>($world['pork']??0)>=3 ? 40 : 26,
        default=>0,
    };
}

function completeForestWork(PDO $db,array &$world,string $id,string $key): ?string {
    if (!in_array($key,['haul_wood','build_cart','load_kiln','watch_kiln','hunt_small','smoke_meat'],true)) return null;
    $person=$world['people'][$id];
    $task=$person['task']??[];
    $skill=taskSkillFactor($world,$id,$key);
    $now=(int)$world['minute'];
    switch ($key) {
        case 'haul_wood':
            $cart=!empty($task['cart']);
            $n=min(($cart?cartCapacity($world):WOOD_HAND_TRIP)*$skill,$world['forestWood']);
            if ($n<=0) return 'U lesa už žádné dřevo neleží.';
            $world['forestWood']-=$n;
            $world['wood']+=$n;
            return 'Z lesa je '.($cart?'vozem ':'').'svezeno '.fmtAmount($n).' dřeva.';
        case 'build_cart':
            if ($world['carts']>=CART_MAX) return 'Vozů je dost.';
            $project=$world['cartProject']??['progress'=>0.0,'paid'=>false,'hours'=>[],'since'=>$now];
            if (!$project['paid']) {
                if (!canPay($world,CART_COST)) return 'Na vůz zatím chybí materiál.';
                pay($world,CART_COST);
                $project['paid']=true;
            }
            $hours=max(0,(int)($task['end']??0)-(int)($task['start']??0))/60;
            $power=max(.5,productivity($person)/70)*$skill;
            $project['progress']+=$hours*min(1.5,$power/BUILD_NORM_POWER);
            $project['hours'][$id]=round(($project['hours'][$id]??0)+$hours,2);
            if ($project['progress']>=CART_WORK) {
                ++$world['carts'];
                $names=[];
                arsort($project['hours']);
                foreach ($project['hours'] as $builder=>$spent) $names[]=($world['people'][$builder]['name']??$builder).' '.fmtAmount($spent).' h';
                $world['cartProject']=null;
                journal($db,$world,'','Hotov nový vůz','Vesnice má '.$world['carts'].' vozů, každý veze '.fmtAmount(cartCapacity($world)).' jednotek na cestu. Vyráběli: '.implode(', ',$names).'.','Hra');
                return 'Vůz je hotový.';
            }
            $world['cartProject']=$project;
            return 'Vůz postoupil ('.fmtAmount($project['progress']).' z '.CART_WORK.' člověkohodin).';
        case 'load_kiln':
            if ($world['kiln']['state']!=='idle' || $world['wood']<KILN_WOOD) return 'Milíř se nepodařilo naplnit.';
            consumeStock($world,'wood',KILN_WOOD);
            $world['kiln']=['state'=>'burning','end'=>$now+KILN_DAYS*1440,'lastWatch'=>$now,'loss'=>0.0];
            journal($db,$world,$id,'Milíř zapálen',$person['name'].' naskládal'.(($person['sex']??'m')==='f'?'a':'').' do milíře '.fmtAmount(KILN_WOOD).' dřeva. Hranice bude šest dní doutnat a potřebuje dohled.','Hra');
            return 'Milíř je naskládaný a zapálený.';
        case 'watch_kiln':
            if ($world['kiln']['state']!=='burning') return 'Milíř nehoří.';
            $world['kiln']['lastWatch']=$now;
            return 'Milíř je pod dohledem.';
        case 'hunt_small':
            $stock=$world['gameStock'];
            if ($stock<10) return 'V lese je zvěře málo.';
            $gain=round(GAME_TRIP*$skill*$stock/GAME_STOCK_MAX,1);
            $world['game']+=$gain;
            $world['gameStock']=max(0.0,$stock-GAME_LOAD*$gain/GAME_TRIP);
            countProduced($world,'game',$gain);
            $world['hides']=($world['hides']??0)+round($gain*.5,1);   // kožky zajíců a králíků čekají na obchodníka
            return 'Z léček je '.fmtAmount($gain).' zvěřiny (zajíci, králíci, bažanti).';
        case 'smoke_meat':
            $pork=(float)($world['pork']??0);
            $n=min(SMOKE_BATCH*$skill,$world['game']+$pork,$world['wood']*3);
            if ($n<1 || !hasBuilding($world,'udirna')) return 'Nebylo co udit.';
            // Čerstvé vepřové se uzuje přednostně, pak zvěřina.
            $fromPork=min($n,$pork);
            $world['pork']=$pork-$fromPork;
            $world['game']-=$n-$fromPork;
            $world['meatSmoked']+=$n*SMOKE_YIELD;
            consumeStock($world,'wood',$n/3);
            countProduced($world,'meatSmoked',$n*SMOKE_YIELD);
            return 'Z '.fmtAmount($n).($fromPork<=.001 ? ' zvěřiny' : ($fromPork>=$n-.001 ? ' vepřového masa' : ' masa a zvěřiny')).' je '.fmtAmount($n*SMOKE_YIELD).' uzeného masa.';
    }
    return null;
}

// Jednou denně: kažení zvěřiny, obnova zvěře, dřevo u lesa, milíř.
function forestDaily(PDO $db,array &$world): void {
    $now=(int)$world['minute'];
    $day=intdiv($now,1440);
    $world['forestWood']*=FOREST_PILE_KEEP;
    if ($world['forestWood']<.05) $world['forestWood']=0.0;
    $world['game']*=GAME_KEEP;
    $world['meatSmoked']*=MEAT_KEEP;
    foreach (['game','meatSmoked'] as $key) if ($world[$key]<.05) $world[$key]=0.0;
    $world['gameStock']=min(GAME_STOCK_MAX,$world['gameStock']+GAME_REGEN);
    $kiln=&$world['kiln'];
    if ($kiln['state']==='burning') {
        if ($now-$kiln['lastWatch']>KILN_WATCH_GAP) {
            $kiln['loss']=min(.75,$kiln['loss']+KILN_LOSS_STEP);
            if (weatherNoise($day,'milir')<KILN_FIRE_CHANCE) {
                $kiln=['state'=>'idle','end'=>0,'lastWatch'=>0,'loss'=>0.0];
                $world['counters']['kilnFire']=($world['counters']['kilnFire']??0)+1;
                journal($db,$world,'','Milíř vzplál','Nikdo ho nehlídal a hranice shořela s celou zásobou dřeva. Uhlí nebude.','Hra');
                return;
            }
        }
        if ($now>=$kiln['end']) {
            $coal=round(KILN_YIELD*(1-$kiln['loss']),1);
            $world['charcoal']+=$coal;
            countProduced($world,'charcoal',$coal);
            journal($db,$world,'','Z milíře je dřevěné uhlí','Vypálilo se '.fmtAmount($coal).' dřevěného uhlí'.($kiln['loss']>0?' (část se ztratila, protože milíř nebyl stále pod dohledem)':'').'.','Hra');
            $kiln=['state'=>'idle','end'=>0,'lastWatch'=>0,'loss'=>0.0];
        }
    }
    unset($kiln);
}
