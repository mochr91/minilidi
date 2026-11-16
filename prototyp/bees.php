<?php
declare(strict_types=1);

// Včelín (fáze G rozšíření ekonomiky, návrh v ROZSIRENI-EKONOMIKY.md bod 12):
// úly z jara do podzimu (péče), med a vosk v srpnu, zazimování v říjnu a listopadu, svíčky z vosku.
// Užitek: opylení zahrady, chmelnice a vinice, med zkracuje nemoc, svíčky potřebuje škola v zimě, přebytek se prodává.
// Před postavením včelína se nic nemění (stará vesnice se nezhorší).

const BEE_TASKS = ['tend_bees','harvest_honey','prepare_hives','make_candles'];
const HIVES_START = 3;
const HIVES_MAX = 8;
const BEE_TEND_TASKS = 6;             // kolik běžných úkolů je třeba k plné péči
const BEE_CARE_DECAY = .993;          // denní úbytek péče v dubnu až srpnu
const HONEY_PER_HIVE = 4.0;           // medu z úlu za sezónu při plné péči
const HONEY_PER_TASK = 6.0;
const WAX_SHARE = .3;                 // vosku z jednotky medu
const HONEY_KEEP = .9998;
const CANDLE_KEEP = .9995;
const CANDLES_PER_WAX = 6.0;
const CANDLE_TARGET = 18.0;
const CANDLE_LESSON = 1/6;            // jedna svíčka vydrží šest lekcí
const WINTER_SURVIVE = .65;           // podíl úlů, které přežijí zimu bez zazimování
const WINTER_SURVIVE_PREPARED = .92;
const HARSH_WINTER_CHANCE = .2;
const HARSH_WINTER_LOSS = .10;
const POLLINATION_PER_HIVE = .015;
const POLLINATION_MAX = .10;
const NURSE_HONEY = .5;               // med na jedno ošetření
const NURSE_HONEY_SHARE = .2;         // o kolik víc zkrátí nemoc
const HONEY_SELL_KEEP = 3.0;
const WAX_SELL_KEEP = 4.0;

function hivesOf(array $world): int {
    return hasBuilding($world,'vcelin') ? (int)($world['hives']??0) : 0;
}

function hivesCap(array $world): int {
    return min(HIVES_MAX,max(3,(int)ceil(count($world['people'])/3)));
}

// Násobek výnosu zahrady, chmelnice a vinice díky opylení.
function pollination(array $world): float {
    return 1.0+min(POLLINATION_MAX,POLLINATION_PER_HIVE*hivesOf($world));
}

// Listopad až únor: děti se bez svíčky neučí (jen když stojí včelín).
function lessonLight(array $world): bool {
    if (!hasBuilding($world,'vcelin')) return true;
    $m=calendarMonth($world);
    if ($m<8) return true;
    return ($world['candles']??0)>=CANDLE_LESSON-.0001;
}

function beeOpenDay(array $world): bool {
    $weather=villageWeather($world);
    return !$weather['frozen'] && $weather['ground']!=='snow' && $weather['precip']==='none';
}

// null = činnost tento modul nezná, jinak jestli je teď dostupná.
function beesWorkAvailable(array $world,string $id,string $key): ?bool {
    if (!in_array($key,BEE_TASKS,true)) return null;
    if (!hasBuilding($world,'vcelin')) return false;
    $m=calendarMonth($world);
    $yard=$world['apiary']??[];
    return match ($key) {
        'tend_bees'=>$m>=1 && $m<=6 && ($world['hives']??0)>0 && ($yard['care']??0)<.999 && beeOpenDay($world) && taskCount($world,'tend_bees')<1,
        'harvest_honey'=>($m===5 || $m===6) && ($yard['crop']??0)>=1 && beeOpenDay($world) && taskCount($world,'harvest_honey')<2,
        'prepare_hives'=>($m===7 || $m===8) && ($world['hives']??0)>0 && ($yard['prepared']??0)<.999 && taskCount($world,'prepare_hives')<1,
        'make_candles'=>(($world['wax']??0)>=1 || ($world['lard']??0)>=1) && ($world['candles']??0)<CANDLE_TARGET && taskCount($world,'make_candles')<1,
    };
}

function beesScore(array $world,string $key): int {
    $m=calendarMonth($world);
    return match ($key) {
        'tend_bees'=>17,
        'harvest_honey'=>32,
        'prepare_hives'=>$m===8 ? 36 : 28,
        'make_candles'=>($m>=6 && $m<=8 ? 24 : 16),
        default=>0,
    };
}

function completeBeesWork(PDO $db,array &$world,string $id,string $key): ?string {
    if (!in_array($key,BEE_TASKS,true)) return null;
    $skill=taskSkillFactor($world,$id,$key);
    switch ($key) {
        case 'tend_bees':
            $world['apiary']['care']=min(1.0,($world['apiary']['care']??0)+$skill/BEE_TEND_TASKS);
            return 'Úly jsou prohlédnuté, péče '.round($world['apiary']['care']*100).' %.';
        case 'harvest_honey':
            $n=min($world['apiary']['crop']??0,HONEY_PER_TASK*$skill);
            if ($n<=0) return 'V úlech nezbyl med.';
            $world['apiary']['crop']-=$n;
            $wax=$n*WAX_SHARE;
            $world['honey']=($world['honey']??0)+$n;
            $world['wax']=($world['wax']??0)+$wax;
            countProduced($world,'honey',$n);
            countProduced($world,'wax',$wax);
            return 'Vytočeno '.fmtAmount($n).' medu a '.fmtAmount($wax).' vosku.';
        case 'prepare_hives':
            $hives=max(1,(int)($world['hives']??1));
            $world['apiary']['prepared']=min(1.0,($world['apiary']['prepared']??0)+2*$skill/$hives);
            return 'Úly jsou zazimované z '.round($world['apiary']['prepared']*100).' %.';
        case 'make_candles':
            if (($world['wax']??0)>=1) {
                $world['wax']-=1;
                $n=round(CANDLES_PER_WAX*min(1.15,$skill),1);
                $src='vosku';
            } elseif (($world['lard']??0)>=1) {
                // Lojové svíčky ze sádla ze zabijačky (méně vydatné než voskové).
                $world['lard']-=1;
                $n=round(CANDLES_PER_LARD*min(1.15,$skill),1);
                $src='sádla';
            } else return 'Na svíčky chybí vosk nebo sádlo.';
            $world['candles']=($world['candles']??0)+$n;
            countProduced($world,'candles',$n);
            return 'Z '.$src.' je '.fmtAmount($n).' svíček.';
    }
    return null;
}

// Jednou denně: kažení zásob, nový rok včel (zimní ztráty), péče, medná úroda, roj.
function beesDaily(PDO $db,array &$world): void {
    $world['honey']=($world['honey']??0)*HONEY_KEEP;
    $world['candles']=($world['candles']??0)*CANDLE_KEEP;
    foreach (['honey','wax','candles'] as $key) {
        $world[$key]=(float)($world[$key]??0);
        if ($world[$key]<.02) $world[$key]=0.0;
    }
    if (!hasBuilding($world,'vcelin')) return;
    $now=(int)$world['minute'];
    $calendar=calendarAt($now,$world['calendarOriginDay']);
    $m=$calendar['monthIndex'];
    $year=$calendar['year'];
    $yard=&$world['apiary'];
    if (empty($yard['founded'])) {
        $yard['founded']=true;
        $world['hives']=HIVES_START;
        journal($db,$world,'','Ve včelíně bzučí první včely','Sousedé nastěhovali do včelína '.HIVES_START.' úly. Včely opylí zahradu a dají med a vosk.','Hra');
    }
    if (($yard['season']??-1)!==$year) {
        // Nový rok včel (od března): kolik úlů přežilo zimu.
        if (($yard['season']??-1)>=0 && ($world['hives']??0)>0) {
            $survive=WINTER_SURVIVE+(WINTER_SURVIVE_PREPARED-WINTER_SURVIVE)*min(1.0,(float)($yard['prepared']??0));
            $harsh=weatherNoise((int)$yard['season']*31+7,'bees-winter')<HARSH_WINTER_CHANCE;
            if ($harsh) $survive-=HARSH_WINTER_LOSS;
            $before=(int)$world['hives'];
            $world['hives']=max(1,(int)round($before*$survive));
            if ($world['hives']<$before) {
                journal($db,$world,'','Část včel zimu nepřežila',($before-$world['hives']).' z '.$before.' úlů zahynulo'.($harsh?' v tuhé zimě':'').'. '.(($yard['prepared']??0)<.5?'Úly nebyly zazimované.':'Zbylé včely se rozrojí.'),'Hra');
            }
        }
        $yard=['season'=>$year,'care'=>0.0,'crop'=>0.0,'done'=>false,'prepared'=>0.0,'swarm'=>false,'founded'=>true];
    }
    if (!$yard['done'] && $m>=5) {
        $yard['crop']=round((int)$world['hives']*HONEY_PER_HIVE*(.4+.6*(float)$yard['care']),1);
        $yard['done']=true;
        if ($yard['crop']>=1) journal($db,$world,'','V úlech dozrál med','Med se vytáčí v srpnu a v září, pak ho včely spotřebují na zimu.','Hra');
    }
    if ($m>=1 && $m<=5) $yard['care']=max(0.0,(float)$yard['care']*BEE_CARE_DECAY);
    if ($m>=7 && ($yard['crop']??0)>0) $yard['crop']=$yard['crop']<1?0.0:$yard['crop']*.85;
    if (!$yard['swarm'] && $m>=2 && $m<=3 && $yard['care']>=.5 && $world['hives']<hivesCap($world)) {
        $yard['swarm']=true;
        ++$world['hives'];
        journal($db,$world,'','Včely se vyrojily','Silné včelstvo se rozdělilo a v prázdném úlu je nový roj ('.$world['hives'].' úlů).','Hra');
    }
    unset($yard);
}
