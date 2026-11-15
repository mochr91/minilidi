<?php
declare(strict_types=1);

// Zima a zásoby (fáze A rozšíření ekonomiky, návrh v ROZSIRENI-EKONOMIKY.md):
// zahrada (sázet, okopat, sklidit), sklep a kyselé zelí, byliny a ošetřování, pestrá strava, příprava na zimu.
// Kažení je po druzích: čerstvá zelenina se kazí rychle, zelí, luštěniny a sušené byliny drží přes zimu.

const PLANT_FOODS = ['veg'=>.7,'kraut'=>.8,'legumes'=>1.4];   // sytost jedné jednotky zeleniny, zelí a luštěnin
const ANIMAL_MEAL_TARGET = .05;      // podíl vajec, zvěřiny a masa na jídle, při kterém je živočišná složka pestrosti plná
const PLANT_MEAL_SHARE = .12;        // nejvýš tato část jídla se bere ze zeleniny, zelí a luštěnin (zbytek chléb, kaše, ryby)
const VEG_KEEP = .97;                // zásoba po jednom dni (kazí se 3 %)
const VEG_KEEP_CELLAR = .992;        // ve sklepě 0,8 %
const KRAUT_KEEP = .997;
const KRAUT_KEEP_CELLAR = .999;
const LEGUME_KEEP = .9985;
const HERB_KEEP = .96;               // čerstvé byliny 4 %
const HERB_DRY_KEEP = .998;
const GARDEN_PER_PERSON = 26.0;      // jednotek úrody zahrady na obyvatele a sezónu při plném zasetí a ošetření (zhruba 12 % zimní spotřeby)
const GARDEN_SOW_TASKS = 6;          // kolik běžných úkolů je třeba k osetí celé zahrady
const GARDEN_TEND_TASKS = 8;         // kolik běžných úkolů je třeba k plné péči
const GARDEN_HARVEST_PER_TASK = 24.0;
const GARDEN_VEG_SHARE = .6;         // zbytek úrody jsou luštěniny
const KRAUT_YIELD = .9;              // z jednotky zeleniny je 0,9 zelí
const HERB_DAILY_USE = .008;         // sušené byliny, které domácnost v zimě spotřebuje na osobu a den
const WINTER_FOOD_DAYS = 60;         // zimní příprava: jídlo na tolik dní
const WINTER_FUEL_DAYS = 45;         // topivo na tolik dní
const WINTER_HAY_DAYS = 150;         // seno na zimu a přilehlé sněhové dny (od listopadu do března)
const WINTER_WOOD_PER_DAY = 3.5;
const NURSE_SHARE = .35;             // o kolik zkrátí ošetření bylinami zbývající nemoc

function calendarMonth(array $world): int {
    return calendarAt((int)$world['minute'],$world['calendarOriginDay'])['monthIndex'];
}

// Prosinec až březen: kdo nemá zásoby zeleniny a bylin, tomu se zhorší nálada a snáz onemocní.
function isWinterMonth(int $monthIndex): bool {
    return in_array($monthIndex,[9,10,11,0],true);
}

function plantStock(array $world): float {
    $sum=0.0;
    foreach (PLANT_FOODS as $key=>$value) $sum+=($world[$key]??0)*$value;
    return $sum;
}

function herbStock(array $world): float {
    return ($world['herbs']??0)+($world['herbsDry']??0);
}

function hayTarget(array $world): float {
    return max(70.0,winterHayNeed($world)*WINTER_HAY_DAYS*1.15+10);
}

// Příprava na zimu: nejmenší z poměrů zásoba jídla, topiva a sena k cíli.
function winterPrep(array $world): array {
    $food=min(1.0,foodReserve($world)/max(1.0,dailyFoodNeed($world)*WINTER_FOOD_DAYS));
    $fuel=min(1.0,(($world['wood']??0)+3*($world['charcoal']??0))/(WINTER_WOOD_PER_DAY*WINTER_FUEL_DAYS));
    $hay=min(1.0,($world['hay']??0)/max(1.0,winterHayNeed($world)*WINTER_HAY_DAYS));
    return ['food'=>round($food,3),'fuel'=>round($fuel,3),'hay'=>round($hay,3),'min'=>round(min($food,$fuel,$hay),3)];
}

function nurseTarget(array $world,string $id): ?string {
    $selected=null; $most=0;
    foreach ($world['people'] as $other=>$p) {
        if ($other===$id || ($p['awayUntil']??0)>$world['minute']) continue;
        $remaining=($p['illUntil']??0)-$world['minute'];
        if ($remaining>120 && $remaining>$most) { $selected=$other; $most=$remaining; }
    }
    return $selected;
}

// null = činnost tento modul nezná, jinak jestli je teď dostupná.
function winterWorkAvailable(array $world,string $id,string $key): ?bool {
    if (!in_array($key,['sow_garden','tend_garden','harvest_garden','pickle','gather_herbs','dry_herbs','nurse'],true)) return null;
    $m=calendarMonth($world);
    $weather=villageWeather($world);
    $open=!$weather['frozen'] && $weather['ground']!=='snow';
    $garden=$world['garden']??[];
    $people=max(1,count($world['people']));
    return match ($key) {
        'sow_garden'=>hasBuilding($world,'zahrada') && $m<=3 && $open && ($garden['sown']??0)<.999,
        'tend_garden'=>hasBuilding($world,'zahrada') && $m>=2 && $m<=6 && $open && ($garden['sown']??0)>=.15 && ($garden['care']??0)<.999,
        'harvest_garden'=>hasBuilding($world,'zahrada') && $m>=5 && $m<=9 && $open && ($garden['crop']??0)>=1,
        'pickle'=>hasBuilding($world,'sklep') && $m>=5 && $m<=9 && ($world['veg']??0)>=3,
        'gather_herbs'=>$m>=1 && $m<=5 && $open && herbStock($world)<max(12.0,$people*1.2),
        'dry_herbs'=>($world['herbs']??0)>=3,
        'nurse'=>herbStock($world)>=1 && nurseTarget($world,$id)!==null,
    };
}

// Doplňkové body pro plánovač: kdy se které zimní práci vyplatí dát přednost.
function winterScore(array $world,string $key): int {
    $m=calendarMonth($world);
    $garden=$world['garden']??[];
    return match ($key) {
        'sow_garden'=>18,
        'tend_garden'=>15,
        'harvest_garden'=>26,
        'pickle'=>20,
        'gather_herbs'=>herbStock($world)<count($world['people'])?14:3,
        'dry_herbs'=>18,
        'nurse'=>34,
        'mow'=>($m>=2 && $m<=6 && ($world['hay']??0)<hayTarget($world))?12:0,
        default=>0,
    };
}

function completeWinterWork(PDO $db,array &$world,string $id,string $key): ?string {
    if (!in_array($key,['sow_garden','tend_garden','harvest_garden','pickle','gather_herbs','dry_herbs','nurse'],true)) return null;
    $skill=taskSkillFactor($world,$id,$key);
    switch ($key) {
        case 'sow_garden':
            $before=$world['garden']['sown'];
            $world['garden']['sown']=min(1.0,$before+$skill/GARDEN_SOW_TASKS);
            return 'Záhony jsou osety ze '.round($world['garden']['sown']*100).' %.';
        case 'tend_garden':
            $world['garden']['care']=min(1.0,$world['garden']['care']+$skill/GARDEN_TEND_TASKS);
            return 'Zahrada je ošetřená z '.round($world['garden']['care']*100).' %.';
        case 'harvest_garden':
            $n=min($world['garden']['crop'],GARDEN_HARVEST_PER_TASK*$skill);
            if ($n<=0) return 'V zahradě nezbylo co sklízet.';
            $world['garden']['crop']-=$n;
            $veg=$n*GARDEN_VEG_SHARE; $legumes=$n-$veg;
            $world['veg']+=$veg; $world['legumes']+=$legumes;
            countProduced($world,'veg',$veg);
            countProduced($world,'legumes',$legumes);
            return 'Ze zahrady je '.fmtAmount($veg).' zeleniny a '.fmtAmount($legumes).' luštěnin.';
        case 'pickle':
            $n=min(8.0*$skill,$world['veg']);
            if ($n<1 || !hasBuilding($world,'sklep')) return 'Nebylo co nakládat.';
            // Se solí z obchodníka je výtěžnost vyšší (jedna sůl na dávku), bez soli zelí vyjde hůř.
            $salted=($world['salt']??0)>=1;
            if ($salted) $world['salt']-=1;
            $yield=$n*($salted?SALT_YIELD:SALT_YIELD_NONE);
            $world['veg']-=$n;
            $world['kraut']+=$yield;
            countProduced($world,'kraut',$yield);
            return 'Z '.fmtAmount($n).' zeleniny je '.fmtAmount($yield).' kyselého zelí ve sklepě'.($salted?'.':' (bez soli).');
        case 'gather_herbs':
            $gain=round(3.0*$skill*weatherYield($world,'gathering'),1);
            $world['herbs']+=$gain;
            countProduced($world,'herbs',$gain);
            return 'Nasbíráno '.fmtAmount($gain).' bylin.';
        case 'dry_herbs':
            $n=min(6.0*$skill,$world['herbs']);
            if ($n<1) return 'Nebylo co sušit.';
            $world['herbs']-=$n;
            $world['herbsDry']+=$n*.5;
            return 'Usušeno '.fmtAmount($n*.5).' bylin na zimu.';
        case 'nurse':
            $other=$world['people'][$id]['task']['other']??null;
            if ($other===null || !isset($world['people'][$other]) || herbStock($world)<1) return 'Nebylo koho ošetřit.';
            $patient=&$world['people'][$other];
            $remaining=($patient['illUntil']??0)-$world['minute'];
            if ($remaining<=60) return $patient['name'].' se už uzdravuje.';
            if (($world['herbsDry']??0)>=1) $world['herbsDry']-=1; else $world['herbs']-=1;
            // S medem se nemoc zkrátí o dalších 20 % (kašel, bolest v krku).
            $share=NURSE_SHARE;
            $honey=($world['honey']??0)>=NURSE_HONEY;
            if ($honey) { $world['honey']-=NURSE_HONEY; $share+=NURSE_HONEY_SHARE; }
            $cut=$remaining*$share*softSkill($world['people'][$id],'neighbors');
            $patient['illUntil']=(int)($world['minute']+max(60,$remaining-$cut));
            rememberHelp($world,$id,$other,6);
            journal($db,$world,$other,$patient['name'].' dostává odvar z bylin'.($honey?' s medem':''),$world['people'][$id]['name'].' nemocného ošetřila a nemoc se zkrátí.','Hra');
            return 'Nemoc se u '.$patient['name'].' zkrátí.';
    }
    return null;
}

// Jednou denně: kažení po druzích, zahrada podle ročního období, pestrost stravy a zimní důsledky.
function winterDaily(PDO $db,array &$world): void {
    $now=(int)$world['minute'];
    $day=intdiv($now,1440);
    $calendar=calendarAt($now,$world['calendarOriginDay']);
    $m=$calendar['monthIndex'];
    $cellar=hasBuilding($world,'sklep');
    $people=max(1,count($world['people']));
    // Kažení.
    $world['veg']*=$cellar?VEG_KEEP_CELLAR:VEG_KEEP;
    $world['kraut']*=$cellar?KRAUT_KEEP_CELLAR:KRAUT_KEEP;
    $world['legumes']*=LEGUME_KEEP;
    $world['herbs']*=HERB_KEEP;
    $world['herbsDry']*=HERB_DRY_KEEP;
    foreach (['veg','kraut','legumes','herbs','herbsDry'] as $key) if ($world[$key]<.05) $world[$key]=0.0;
    // Zahrada: nový rok od března, úroda se spočítá na začátku srpna a nesklizená zmrzne v prosinci.
    $garden=&$world['garden'];
    if (($garden['season']??-1)!==$calendar['year'] && $m<=5) {
        $garden=['season'=>$calendar['year'],'sown'=>0.0,'care'=>0.0,'crop'=>0.0,'done'=>false];
    }
    if (hasBuilding($world,'zahrada') && !$garden['done'] && $m>=5 && ($garden['season']??-1)===$calendar['year']) {
        $factor=($world['harvestUntil']??0)>$now ? $world['harvestFactor'] : 1.0;
        $garden['crop']=round(GARDEN_PER_PERSON*$people*$garden['sown']*(.4+.6*$garden['care'])*$factor*pollination($world),1);
        $garden['done']=true;
        if ($garden['crop']>=1) journal($db,$world,'','V zahradě dozrála úroda','Zelenina a luštěniny čekají na sklizeň. Co zůstane na záhonech, zmrzne.','Hra');
    }
    if ($m>=5 && $garden['done'] ) $garden['sown']=0.0;
    if ($m>=2 && $m<=6) $garden['care']=max(0.0,$garden['care']*.985);
    if ($m>=9 && $garden['crop']>0) $garden['crop']=$garden['crop']<1?0.0:$garden['crop']*.85;
    unset($garden);
    // Látka: v zimě se pomalu spotřebovává (teplé oblečení), jinak velmi pomalu stárne.
    $world['cloth']=($world['cloth']??0)*CLOTH_KEEP;
    if (isWinterMonth($m)) $world['cloth']=max(0.0,$world['cloth']-$people*CLOTH_WINTER_USE);
    // Pestrost stravy: klouzavý průměr podílu zeleniny, zelí a luštěnin na snědeném jídle za zhruba dva týdny.
    $today=$world['dietToday']??['plant'=>0.0,'animal'=>0.0,'total'=>0.0];
    $diet=$world['diet']??['plant'=>0.0,'animal'=>0.0,'total'=>0.0];
    $diet=['plant'=>$diet['plant']*.93+$today['plant'],'animal'=>($diet['animal']??0)*.93+($today['animal']??0),'total'=>$diet['total']*.93+$today['total']];
    $world['diet']=$diet;
    $world['dietToday']=['plant'=>0.0,'animal'=>0.0,'total'=>0.0];
    $share=$diet['total']>.5?$diet['plant']/$diet['total']:0.0;
    $animalShare=$diet['total']>.5?$diet['animal']/$diet['total']:0.0;
    $variety=min(1.0,$share/PLANT_MEAL_SHARE)*.6+min(1.0,$animalShare/ANIMAL_MEAL_TARGET)*.15+min(1.0,($world['herbsDry']??0)/max(1.0,$people*.5))*.25;
    $world['variety']=round($variety,3);
    // Zimní důsledky nedostatku pestré stravy.
    // V prvním roce se zimní důsledky počítají až od prosince (hra začíná v březnu bez zásob a bez zahrady).
    if ($m>=9 || ($m===0 && $calendar['year']>1)) {
        $world['herbsDry']=max(0.0,$world['herbsDry']-$people*HERB_DAILY_USE);
        $lack=1-$variety;
        if ($lack>.05) {
            $cold=villageWeather($world)['frozen'];
            foreach ($world['people'] as $id=>&$p) {
                if ($p['age']<2) continue;
                $p['mood']=clamp($p['mood']-3*$lack*(clothed($world)?.6:1.0)*(shod($world)?.9:1.0));
                $sick=!empty($p['illUntil']) && $p['illUntil']>$now;
                if (!$sick && weatherNoise($day,'zimnice:'.$id)<.012*$lack*($cold?1.5:1.0)*(clothed($world)?.7:1.0)*(shod($world)?.9:1.0)*(1-HYGIENE_SICK_CUT*($world['hygiene']??0))) {
                    $p['illUntil']=$now+3*1440;
                    $world['counters']['winterSick']=($world['counters']['winterSick']??0)+1;
                    journal($db,$world,$id,$p['name'].' onemocněl'.(($p['sex']??'m')==='f'?'a':'').' z jednotvárné stravy','Zima a chybějící zelenina oslabily organismus. Pomůže odvar z bylin a odpočinek.','Hra');
                }
            }
            unset($p);
        }
    }
    $prep=winterPrep($world);
    $world['winterPrep']=$prep['min'];
    $world['winterPrepDetail']=$prep;
}
