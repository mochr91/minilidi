<?php
declare(strict_types=1);

// Chov, maso a vlna (fáze I rozšíření ekonomiky, návrh v ROZSIRENI-EKONOMIKY.md bod 14):
// prasata z chlívu se krmí přebytkem obilí a po první zimní zimě se zabíjejí (maso, sádlo, kožky),
// ovce z ovčína dávají vlnu, která se spřádá do látky. Před postavením chlívu a ovčína se nic nemění.

const HUSBANDRY_TASKS = ['slaughter_pig','shear_sheep','tend_sheep'];

// Prasata.
const PIG_START = 4;
const PIG_START_WEIGHT = .55;
const PIG_MAX = 10;
const PIG_KEEP = 2;                   // tolik prasat se při zabijačce vždy nechá na chov
const PIG_GRAIN = .15;                // obilí na prase a den
const PIG_GRAIN_FLOOR = 10.0;         // prasata žerou jen z obilí nad touto zásobou a nad rezervou slepic
const PIG_GRAIN_DAYS = 60;            // mlýn nechá prasatům obilí na tolik dní
const PIG_GROW = 1/200;               // denní přírůstek váhy při krmení
const PIG_LOSS = 1/60;                // denní úbytek váhy bez krmiva
const PIG_STARVE_DAYS = 10;           // po tolika dnech hladu prase zahyne
const PIG_RESTOCK_DAYS = 20;          // prázdný chlív se po této době znovu osadí
const PIG_MIN_WEIGHT = .7;            // lehčí prase se nezabíjí
const PIG_LITTER = 4;                  // selat na dvojici prasat ročně (nejvýš po strop)
const PORK_PER_PIG = 24.0;            // jednotek masa z prasete o váze 1
const LARD_PER_PIG = 3.0;
const HIDES_PER_PIG = 1.0;
const PORK_KEEP = .78;                // čerstvé maso se kazí 22 % denně, proto se hned uzuje
const LARD_KEEP = .9995;
const CANDLES_PER_LARD = 3.0;
const PIG_SLAUGHTER_FROM = 8;         // listopad
const PIG_SLAUGHTER_TO = 9;           // prosinec
const PIG_SLAUGHTER_TEMP = 2;         // zabíjí se při chladu (denní teplota nejvýš tolik)
const FEAST_NEIGHBORS = 4;
const FEAST_MOOD = 4.0;
const FEAST_BOND = 3.0;

// Ovce.
const SHEEP_START = 4;
const SHEEP_MAX = 12;
const SHEEP_HAY = .3;                 // sena na ovci a den v zimě
const WOOL_PER_SHEEP = 2.0;           // vlny z ovce za rok při plné péči
const WOOL_PER_TASK = 6.0;
const WOOL_KEEP = .9995;
const WOOL_CAP = 60.0;
const SHEEP_TEND_TASKS = 5;
const SHEEP_CARE_DECAY = .993;
const WOOL_CLOTH = 1.0;               // látky z jednotky vlny (z lnu jen .5)
const WOOL_WEAVE = 5.0;               // vlny na jedno tkaní
const WOOL_SELL_KEEP = 8.0;
const LARD_SELL_KEEP = 6.0;

function pigCap(array $world): int {
    return min(PIG_MAX,2+(int)ceil(count($world['people'])/8));
}

function sheepCap(array $world): int {
    return min(SHEEP_MAX,SHEEP_START+intdiv(count($world['people']),6));
}

// Obilí, které se nemele: krmivo pro prasata na PIG_GRAIN_DAYS dní (s henGrainReserve společná rezerva zvířat).
function pigGrainReserve(array $world): float {
    return pigsOf($world)*PIG_GRAIN*PIG_GRAIN_DAYS;
}

function pigsOf(array $world): float {
    return hasBuilding($world,'chliv') ? max(0.0,(float)($world['pigs']??0)) : 0.0;
}

// Seno ovcí na zimní den (i pro ještě nepřivedené stádo, aby se na ně léto před příchodem nachystalo); ostatní dobytek se krmí dřív
// a ovce dostanou, co zbyde (viz economyDaily).
function sheepHayNeed(array $world): float {
    if (!hasBuilding($world,'ovcin')) return 0.0;
    return ($world['sheep']??null)===null ? SHEEP_START*SHEEP_HAY : sheepOf($world)*SHEEP_HAY;
}

function sheepOf(array $world): float {
    return hasBuilding($world,'ovcin') ? max(0.0,(float)($world['sheep']??0)) : 0.0;
}

// Prasata, která se dají zabít (nad chovnou dvojicí) a jestli jsou dost vykrmená.
function slaughterablePigs(array $world): int {
    if ((float)($world['pigWeight']??0)<PIG_MIN_WEIGHT) return 0;
    return max(0,(int)floor(pigsOf($world))-PIG_KEEP);
}

function slaughterSeason(array $world): bool {
    $m=calendarMonth($world);
    return $m>=PIG_SLAUGHTER_FROM && $m<=PIG_SLAUGHTER_TO;
}

// Zimní ochrana oblečením: bez látky 1,0, s látkou 0,7, s vlněným oblečením z ovčína a dost látky o něco lépe.
function clothWarmth(array $world): float {
    if (!clothed($world)) return 1.0;
    $warm=hasBuilding($world,'ovcin') && ($world['cloth']??0)>=max(1,count($world['people']))*.5;
    return $warm ? .66 : .7;
}

function pigOpenDay(array $world): bool {
    return villageWeather($world)['temperature']<=PIG_SLAUGHTER_TEMP;
}

// null = činnost tento modul nezná, jinak jestli je teď dostupná.
function husbandryWorkAvailable(array $world,string $id,string $key): ?bool {
    if (!in_array($key,HUSBANDRY_TASKS,true)) return null;
    $m=calendarMonth($world);
    return match ($key) {
        'slaughter_pig'=>hasBuilding($world,'chliv') && slaughterSeason($world) && slaughterablePigs($world)>=1 && pigOpenDay($world)
            && taskCount($world,'slaughter_pig')<1,
        'shear_sheep'=>hasBuilding($world,'ovcin') && ($m===2 || $m===3) && ($world['woolCrop']??0)>=1 && taskCount($world,'shear_sheep')<2
            && villageWeather($world)['precip']==='none',
        'tend_sheep'=>hasBuilding($world,'ovcin') && $m>=1 && $m<=6 && ($world['sheep']??0)>=1 && ($world['sheepCare']??0)<.999
            && taskCount($world,'tend_sheep')<1,
    };
}

function husbandryScore(array $world,string $key): int {
    return match ($key) {
        'slaughter_pig'=>(float)($world['pork']??0)<8 ? 36 : 22,
        'shear_sheep'=>30,
        'tend_sheep'=>16,
        default=>0,
    };
}

// Zabijačka: kdo ji dělá, ten zve sousedy; maso se musí hned uzenit (smoke_meat).
function pigFeast(PDO $db,array &$world,string $id,float $weight,float $pork): void {
    $person=$world['people'][$id];
    $year=calendarAt((int)$world['minute'],$world['calendarOriginDay'])['year'];
    if (($world['pigFeastYear']??-1)===$year) return;
    $world['pigFeastYear']=$year;
    $others=[];
    foreach ($world['people'] as $oid=>$o) {
        if ($oid===$id || $o['age']<14 || ($o['awayUntil']??0)>$world['minute'] || !empty($o['illUntil']) && $o['illUntil']>$world['minute']) continue;
        $others[$oid]=(int)($person['bonds'][$oid]??48);
    }
    arsort($others);
    $guests=array_slice(array_keys($others),0,FEAST_NEIGHBORS);
    foreach (array_merge([$id],$guests) as $pid) {
        $world['people'][$pid]['mood']=clamp($world['people'][$pid]['mood']+FEAST_MOOD);
        if ($pid!==$id) {
            $world['people'][$pid]['bonds'][$id]=clamp(($world['people'][$pid]['bonds'][$id]??48)+FEAST_BOND);
            $world['people'][$id]['bonds'][$pid]=clamp(($world['people'][$id]['bonds'][$pid]??48)+FEAST_BOND);
        }
    }
    $names=array_map(fn($g)=>$world['people'][$g]['name'],$guests);
    journal($db,$world,$id,'Zabijačka','Začíná zimní zabijačka: '.$person['name'].' porazil'.(($person['sex']??'m')==='f'?'a':'').' prvního vykrmeného vepře ('.fmtAmount($pork).' masa)'
        .($names ? ' a pomáhají sousedé '.implode(', ',$names).'.' : '.').' Maso je třeba brzy vyudit.','Hra');
}

function completeHusbandryWork(PDO $db,array &$world,string $id,string $key): ?string {
    if (!in_array($key,HUSBANDRY_TASKS,true)) return null;
    $skill=taskSkillFactor($world,$id,$key);
    switch ($key) {
        case 'slaughter_pig':
            if (!hasBuilding($world,'chliv') || slaughterablePigs($world)<1) return 'Žádné prase není dost vykrmené.';
            $w=(float)$world['pigWeight'];
            $pork=round(PORK_PER_PIG*$w*min(1.15,$skill),1);
            $lard=round(LARD_PER_PIG*$w,1);
            $world['pigs']=(float)$world['pigs']-1;
            $world['pork']=($world['pork']??0)+$pork;
            $world['lard']=($world['lard']??0)+$lard;
            $world['hides']=($world['hides']??0)+HIDES_PER_PIG;
            countProduced($world,'pork',$pork);
            countProduced($world,'lard',$lard);
            $world['counters']['pigsSlaughtered']=($world['counters']['pigsSlaughtered']??0)+1;
            pigFeast($db,$world,$id,$w,$pork);
            return 'Z prasete je '.fmtAmount($pork).' masa a '.fmtAmount($lard).' sádla.';
        case 'shear_sheep':
            $n=min((float)($world['woolCrop']??0),WOOL_PER_TASK*$skill);
            if ($n<=0) return 'Ovce už jsou ostříhané.';
            $world['woolCrop']-=$n;
            $world['wool']=min(WOOL_CAP,($world['wool']??0)+$n);
            countProduced($world,'wool',$n);
            return 'Ostříháno '.fmtAmount($n).' vlny.';
        case 'tend_sheep':
            $world['sheepCare']=min(1.0,($world['sheepCare']??0)+$skill/SHEEP_TEND_TASKS);
            return 'Ovce jsou prohlédnuté, péče '.round($world['sheepCare']*100).' %.';
    }
    return null;
}

// Jednou denně: kažení masa, krmení prasat, jejich růst a zabijačka, ovce (seno v zimě, jehňata, vlna).
function husbandryDaily(PDO $db,array &$world): void {
    $world['pork']=($world['pork']??0)*PORK_KEEP;
    $world['lard']=($world['lard']??0)*LARD_KEEP;
    $world['wool']=($world['wool']??0)*WOOL_KEEP;
    foreach (['pork','lard','wool'] as $key) {
        $world[$key]=(float)$world[$key];
        if ($world[$key]<.02) $world[$key]=0.0;
    }
    pigsDaily($db,$world);
    sheepDaily($db,$world);
}

function pigsDaily(PDO $db,array &$world): void {
    if (!hasBuilding($world,'chliv')) return;
    $calendar=calendarAt((int)$world['minute'],$world['calendarOriginDay']);
    $m=$calendar['monthIndex'];
    if (($world['pigs']??null)===null) {
        $world['pigs']=(float)PIG_START;
        $world['pigWeight']=PIG_START_WEIGHT;
        $world['pigHungry']=0;
        $world['pigEmptyDays']=0;
        $world['pigYear']=$calendar['year'];   // v roce osazení se selata nerodí
        journal($db,$world,'','Do chlíva přibyla prasata',PIG_START.' prasata se zabydlela v chlívě. Žerou obilí z přebytku a na začátku zimy se z vykrmených dělá zabijačka.','Hra');
    }
    if ($world['pigs']<1) {
        $world['pigs']=0.0;
        if (++$world['pigEmptyDays']>=PIG_RESTOCK_DAYS) {
            $world['pigs']=2.0;
            $world['pigWeight']=.3;
            $world['pigEmptyDays']=0;
            journal($db,$world,'','Chlív znovu osazen','Sousedé přivedli dvě selata, aby chlív nezůstal prázdný.','Hra');
        }
        return;
    }
    // Krmení jen z přebytku obilí (nad rezervou slepic a nad pevnou zásobou pro lidi).
    $need=$world['pigs']*PIG_GRAIN;
    $spare=max(0.0,$world['grain']-(henGrainReserve($world)-pigGrainReserve($world))-PIG_GRAIN_FLOOR);
    $fed=$need>0 ? min(1.0,$spare/$need) : 1.0;
    $world['grain']-=$need*$fed;
    if ($fed>=.5) {
        $world['pigHungry']=0;
        $world['pigWeight']=min(1.0,(float)$world['pigWeight']+PIG_GROW*$fed);
    } else {
        $world['pigWeight']=max(.1,(float)$world['pigWeight']-PIG_LOSS);
        $world['counters']['pigHungry']=($world['counters']['pigHungry']??0)+1;
        if (++$world['pigHungry']>=PIG_STARVE_DAYS) {
            $world['pigs']-=1;
            $world['pigHungry']=0;
            journal($db,$world,'','Prase hladem zahynulo','V zásobách chybí obilí nad rezervou, prasata hubnou a jedno zahynulo.','Hra');
        }
    }
    // Selata v březnu: čtyři na každou dvojici, nejvýš po strop. Průměrná váha se naředí selaty.
    $year=$calendar['year'];
    if ($m===0 && ($world['pigYear']??-1)!==$year) {
        $world['pigYear']=$year;
        $pairs=(int)floor($world['pigs']/2);
        $born=(float)max(0,min(PIG_LITTER*$pairs,pigCap($world)-(int)floor($world['pigs'])));
        if ($born>0 && $world['pigHungry']===0) {
            $total=$world['pigs']+$born;
            $world['pigWeight']=round($world['pigWeight']*$world['pigs']/$total,3);
            $world['pigs']=$total;
            journal($db,$world,'','Selata v chlívě','Prasnice přivedly '.(int)$born.' selat (prasat je '.(int)$world['pigs'].').','Hra');
        }
    }
}

function sheepDaily(PDO $db,array &$world): void {
    if (!hasBuilding($world,'ovcin')) return;
    $calendar=calendarAt((int)$world['minute'],$world['calendarOriginDay']);
    $m=$calendar['monthIndex'];
    $year=$calendar['year'];
    if (($world['sheep']??null)===null) {
        if ($m<1 || $m>6) return;   // ovce se přivádějí na jaře a v létě; seno na jejich první zimu se stihne nasekat
        $world['sheep']=(float)SHEEP_START;
        $world['sheepCare']=0.0;
        $world['sheepShort']=0;
        $world['woolCrop']=0.0;
        $world['sheepYear']=$year;   // v roce osazení se jehňata nerodí
        journal($db,$world,'','Do ovčína přibyly ovce',SHEEP_START.' ovce se zabydlely v ovčíně. Na jaře se stříhají a z vlny se tká látka.','Hra');
    }
    // V zimě se ovce krmí senem, které zbylo po ostatním dobytku (cíl sečení počítá winterHayNeed i s nimi); bez sena je příští vlna slabší.
    if ($calendar['season']==='Zima' || villageWeather($world)['ground']==='snow') {
        $need=sheepOf($world)*SHEEP_HAY;
        $take=min($need,max(0.0,(float)$world['hay']));
        $world['hay']-=$take;
        if ($take<$need-.001) {
            $world['sheepShort']=($world['sheepShort']??0)+1;
            $world['counters']['sheepHungry']=($world['counters']['sheepHungry']??0)+1;
        }
    }
    if ($m>=1 && $m<=5) $world['sheepCare']=max(0.0,(float)$world['sheepCare']*SHEEP_CARE_DECAY);
    if ($m===1 && ($world['sheepYear']??-1)!==$year) {
        // Duben: jehňata (jedno na dvě ovce, nejvýš po strop) a nová péče.
        $world['sheepYear']=$year;
        $world['sheepCare']=0.0;   // péče se každé jaro začíná znovu
        $lambs=(float)max(0,min((int)floor($world['sheep']/2),sheepCap($world)-(int)floor($world['sheep'])));
        if ($lambs>0) {
            $world['sheep']+=$lambs;
            journal($db,$world,'','Jehňata v ovčíně','Narodilo se '.(int)$lambs.' jehňat (ovcí je '.(int)$world['sheep'].').','Hra');
        }
    }
    // Květen: zásoba vlny na stříhání podle počtu ovcí, péče a zimního krmení.
    if ($m===2 && ($world['woolYear']??-1)!==$year) {
        $world['woolYear']=$year;
        $penalty=1-.5*min(1.0,(float)($world['sheepShort']??0)/30);
        $world['woolCrop']=round($world['sheep']*WOOL_PER_SHEEP*(.5+.5*(float)$world['sheepCare'])*$penalty,1);
        $world['sheepShort']=0;
        if ($world['woolCrop']>=1) journal($db,$world,'','Ovce jsou na stříhání','V ovčíně je k ostříhání asi '.fmtAmount($world['woolCrop']).' vlny.','Hra');
    }
    if ($m>=4 && ($world['woolCrop']??0)>0) $world['woolCrop']=$world['woolCrop']<1?0.0:$world['woolCrop']*.85;
}
