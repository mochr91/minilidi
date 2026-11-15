<?php
declare(strict_types=1);

// Pivo (fáze C rozšíření ekonomiky, návrh v ROZSIRENI-EKONOMIKY.md):
// ječmen z pole, chmelnice, sladovna, pivovar a pivo v hospodě.
// Řetězec: pole (část úrody je ječmen) → sladovna (slad) → pivovar (mladé pivo) → zrání → hospoda.

const BARLEY_SHARE = .25;            // část polní úrody, která je ječmen (po postavení sladovny)
const BARLEY_CAP = 70.0;              // do této zásoby (ječmen plus slad přepočtený na ječmen) se ječmen dělí z úrody
const BARLEY_GRAIN_MIN = 40.0;       // ječmen se z úrody dělí, jen když je obilí na skladě aspoň tolik
const BARLEY_KEEP = .9985;
const MALT_KEEP = .997;
const HOPS_KEEP = .995;              // chmel se kazí 0,5 % denně
const BEER_YOUNG_KEEP = .98;         // mladé pivo se kazí 2 % denně
const BEER_RIPEN = .1;               // za den dozraje desetina mladého piva (zhruba čtrnáct dní zrání)
const BEER_KEEP = .995;              // zralé pivo ve sklepě 0,5 % denně

const HOP_YIELD = 20.0;              // chmele za podzim při plné péči a zavedené chmelnici
const HOP_ESTABLISH_DAYS = 300;      // dokud chmelnice nestojí tolik dní, dává jen část úrody
const HOP_FIRST_YEAR = .4; 
const HOP_TEND_TASKS = 6;            // úkolů s vazbou chmele na tyče k plné péči
const HOP_HARVEST_PER_TASK = 4.0;

const MALT_BATCH = 10.0;             // ječmene na jeden úkol sladování
const MALT_YIELD = .8;               // z deseti ječmene je osm sladu
const MALT_WOOD = 4.0;               // dřeva na sušení jedné dávky
const MALT_TARGET = 48.0;            // do této zásoby se sladuje

const BREW_WORK = 18.0;              // člověkohodin na jednu várku
const BREW_COST = ['malt'=>8,'hops'=>1,'wood'=>10];
const BREW_BEER = 100.0;             // dávek piva z jedné várky (půl džbánu na návštěvníka a večer)
const BEER_TARGET = 300.0;           // pivo (mladé i zralé) se vaří, dokud je ho méně
const BEER_RATION = .5;              // pivo, které vypije návštěvník hospody za večer
const BEER_YOUNG_EFFECT = .6;        // mladé pivo působí z 60 %
const TAVERN_DRY_FACTOR = .4;        // hospoda bez piva má 40 % obvyklého účinku (jen když stojí pivovar)

const BEER_TASKS = ['tend_hops','harvest_hops','make_malt','brew_beer'];

// Pivo se smí vařit od sv. Michala (29. 9.) do sv. Jiří (23. 4.).
function brewSeason(array $world): bool {
    $c=calendarAt((int)$world['minute'],$world['calendarOriginDay']);
    $m=$c['monthIndex'];
    return ($m===6 && $c['day']>=29) || ($m>=7 && $m<=11) || $m===0 || ($m===1 && $c['day']<=23);
}

// Sladuje se od října do dubna.
function maltSeason(int $m): bool {
    return $m>=7 || $m<=1;
}

function beerStock(array $world): float {
    return ($world['beer']??0)+($world['beerYoung']??0);
}

// Ječmen v zásobě včetně sladu (jeden slad = 1/MALT_YIELD ječmene).
function barleyReserve(array $world): float {
    return ($world['barley']??0)+($world['malt']??0)/MALT_YIELD;
}

// Přidá úrodu z pole do obilí a (po postavení sladovny) části do ječmene.
function addFieldGrain(array &$world,float $amount): void {
    $barley=0.0;
    // Ječmen se dělí jen při zdravé zásobě obilí (jinak by chyběla mouka a krmivo pro slepice).
    if (hasBuilding($world,'sladovna') && barleyReserve($world)<BARLEY_CAP && $world['grain']>=BARLEY_GRAIN_MIN) $barley=$amount*BARLEY_SHARE;
    $flax=addFlax($world,$amount);
    $world['grain']+=$amount-$barley-$flax;
    countProduced($world,'grain',$amount-$barley-$flax);
    if ($barley>0) {
        $world['barley']=($world['barley']??0)+$barley;
        countProduced($world,'barley',$barley);
    }
}

function hopYardMature(array $world): bool {
    $since=(int)$world['minute']-(int)($world['buildings']['chmelnice']??$world['minute']);
    return $since>=HOP_ESTABLISH_DAYS*1440;
}

// null = činnost tento modul nezná, jinak jestli je teď dostupná.
function beerWorkAvailable(array $world,string $id,string $key): ?bool {
    if (!in_array($key,BEER_TASKS,true)) return null;
    $m=calendarMonth($world);
    $weather=villageWeather($world);
    $open=!$weather['frozen'] && $weather['ground']!=='snow';
    $yard=$world['hopYard']??[];
    return match ($key) {
        'tend_hops'=>hasBuilding($world,'chmelnice') && $m>=1 && $m<=5 && $open && ($yard['care']??0)<.999,
        'harvest_hops'=>hasBuilding($world,'chmelnice') && ($m===6 || $m===7) && $open && ($yard['crop']??0)>=1,
        'make_malt'=>hasBuilding($world,'sladovna') && maltSeason($m) && ($world['barley']??0)>=MALT_BATCH/2
            && ($world['malt']??0)<MALT_TARGET && $world['wood']>=MALT_WOOD+8 && taskCount($world,'make_malt')<1,
        'brew_beer'=>hasBuilding($world,'pivovar') && brewSeason($world) && taskCount($world,'brew_beer')<2
            && (!empty($world['brewProject']['paid']) || (canPay($world,BREW_COST) && $world['wood']>=BREW_COST['wood']+10
                && beerStock($world)<BEER_TARGET)),
    };
}

// Doplňkové body pro plánovač.
function beerScore(array $world,string $key): int {
    return match ($key) {
        'tend_hops'=>14,
        'harvest_hops'=>30,
        'make_malt'=>($world['malt']??0)<MALT_BATCH*.8 ? 22 : 12,
        'brew_beer'=>beerStock($world)<BEER_TARGET/4 ? 30 : 20,
        default=>0,
    };
}

function completeBeerWork(PDO $db,array &$world,string $id,string $key): ?string {
    if (!in_array($key,BEER_TASKS,true)) return null;
    $person=$world['people'][$id];
    $task=$person['task']??[];
    $skill=taskSkillFactor($world,$id,$key);
    $now=(int)$world['minute'];
    switch ($key) {
        case 'tend_hops':
            $world['hopYard']['care']=min(1.0,($world['hopYard']['care']??0)+$skill/HOP_TEND_TASKS);
            return 'Chmel je uvázaný na tyčích z '.round($world['hopYard']['care']*100).' %.';
        case 'harvest_hops':
            $n=min($world['hopYard']['crop']??0,HOP_HARVEST_PER_TASK*$skill);
            if ($n<=0) return 'Na chmelnici nezbylo co česat.';
            $world['hopYard']['crop']-=$n;
            $world['hops']=($world['hops']??0)+$n;
            countProduced($world,'hops',$n);
            return 'Natrháno '.fmtAmount($n).' chmele.';
        case 'make_malt':
            $n=min(MALT_BATCH*$skill,$world['barley']??0);
            if ($n<2 || !hasBuilding($world,'sladovna')) return 'Nebylo co sladovat.';
            $world['barley']-=$n;
            $world['malt']=($world['malt']??0)+$n*MALT_YIELD;
            consumeStock($world,'wood',min($world['wood'],MALT_WOOD*$n/MALT_BATCH));
            countProduced($world,'malt',$n*MALT_YIELD);
            return 'Z '.fmtAmount($n).' ječmene je '.fmtAmount($n*MALT_YIELD).' sladu.';
        case 'brew_beer':
            $project=$world['brewProject']??['progress'=>0.0,'paid'=>false,'hours'=>[],'since'=>$now];
            if (!$project['paid']) {
                if (beerStock($world)>=BEER_TARGET) return 'Pivovar už má piva dost.';
                if (!canPay($world,BREW_COST)) return 'Na várku zatím chybí slad, chmel nebo dřevo.';
                pay($world,BREW_COST);
                $project['paid']=true;
            }
            $hours=max(0,(int)($task['end']??0)-(int)($task['start']??0))/60;
            $power=max(.5,productivity($person)/70)*$skill;
            $project['progress']+=$hours*min(1.5,$power/BUILD_NORM_POWER);
            $project['hours'][$id]=round(($project['hours'][$id]??0)+$hours,2);
            if ($project['progress']>=BREW_WORK) {
                $yield=BREW_BEER*(!empty($world['trade']['kettle'])?KETTLE_YIELD:1.0);
                $world['beerYoung']=($world['beerYoung']??0)+$yield;
                countProduced($world,'beerYoung',$yield);
                $world['brewProject']=null;
                $world['beerLog']['brews']=($world['beerLog']['brews']??0)+1;
                arsort($project['hours']);
                $names=[];
                foreach ($project['hours'] as $brewer=>$spent) $names[]=($world['people'][$brewer]['name']??$brewer).' '.fmtAmount($spent).' h';
                journal($db,$world,'','Uvařeno nové pivo','Z várky je '.fmtAmount($yield).' dávek piva, která zrají ve sklepě asi čtrnáct dní. Vařili: '.implode(', ',$names).'.','Hra');
                return 'Várka piva je hotová a ukládá se do sklepa.';
            }
            $world['brewProject']=$project;
            return 'Várka postoupila ('.fmtAmount($project['progress']).' z '.fmtAmount(BREW_WORK).' člověkohodin).';
    }
    return null;
}

// Večer v hospodě: každý dospělý vypije za večer jednu dávku piva (zralé, jinak mladé), které zvedne zábavu i náladu.
// Dokud pivovar nestojí, hospoda funguje jako dřív (násobek 1,0); po jeho postavení bez piva dostává jen 40 % účinku.
function tavernServe(array &$world,array &$p,int $now): float {
    $day=intdiv($now,1440);
    // Nedělní večer s vínem: kdo se napije, má velkou radost (víno zatím neopíjí).
    if ($day%7===6 && ($p['wineDay']??-1)!==$day && ($world['wine']??0)>=WINE_FEAST_RATION) {
        $p['wineDay']=$day;
        $world['wine']-=WINE_FEAST_RATION;
        $p['fun']=clamp($p['fun']+8);
        $p['mood']=clamp($p['mood']+3);
        $world['beerLog']['wine']=($world['beerLog']['wine']??0)+WINE_FEAST_RATION;
    }
    if (!hasBuilding($world,'pivovar')) return 1.0;
    if (($p['beerDay']??-1)!==$day) {
        $p['beerDay']=$day;
        $quality=0.0;
        if (($world['beer']??0)>=BEER_RATION) { $world['beer']-=BEER_RATION; $quality=1.0; }
        elseif (($world['beerYoung']??0)>=BEER_RATION) { $world['beerYoung']-=BEER_RATION; $quality=BEER_YOUNG_EFFECT; }
        $p['beerQuality']=$quality;
        if ($quality>0) {
            $p['fun']=clamp($p['fun']+6*$quality);
            $p['mood']=clamp($p['mood']+2*$quality);
            $world['beerLog']['drunk']=($world['beerLog']['drunk']??0)+BEER_RATION;
        } else {
            $world['beerLog']['dry']=($world['beerLog']['dry']??0)+1;
        }
    }
    return ($p['beerQuality']??0)>0 ? 1.0 : TAVERN_DRY_FACTOR;
}

// Jednou denně: kažení, zrání piva, chmelnice.
function beerDaily(PDO $db,array &$world): void {
    $now=(int)$world['minute'];
    $calendar=calendarAt($now,$world['calendarOriginDay']);
    $m=$calendar['monthIndex'];
    $world['barley']=($world['barley']??0)*BARLEY_KEEP;
    $world['malt']=($world['malt']??0)*MALT_KEEP;
    $world['hops']=($world['hops']??0)*HOPS_KEEP;
    $world['beer']=($world['beer']??0)*BEER_KEEP;
    $young=($world['beerYoung']??0)*BEER_YOUNG_KEEP;
    $ripe=$young*BEER_RIPEN;
    $world['beerYoung']=$young-$ripe;
    $world['beer']+=$ripe;
    foreach (['barley','malt','hops','beer','beerYoung'] as $key) if ($world[$key]<.05) $world[$key]=0.0;
    // Chmelnice: péče na jaře a v létě, úroda se spočítá na začátku září a nesklizená do listopadu shnije.
    $yard=&$world['hopYard'];
    if (hasBuilding($world,'chmelnice')) {
        if (($yard['season']??-1)!==$calendar['year'] && $m<=5) {
            $yard=['season'=>$calendar['year'],'care'=>0.0,'crop'=>0.0,'done'=>false];
        }
        if (!($yard['done']??true) && $m>=6 && ($yard['season']??-1)===$calendar['year']) {
            $share=hopYardMature($world) ? 1.0 : HOP_FIRST_YEAR;
            $yard['crop']=round(HOP_YIELD*$share*(.4+.6*($yard['care']??0)),1);
            $yard['done']=true;
            if ($yard['crop']>=1) journal($db,$world,'','Chmel na chmelnici dozrál','Šišky je třeba trhat do konce října, pak shnijí.','Hra');
        }
        if ($m>=2 && $m<=5) $yard['care']=max(0.0,($yard['care']??0)*.985);
        if ($m>=8 && ($yard['crop']??0)>0) $yard['crop']=$yard['crop']<1?0.0:$yard['crop']*.85;
    }
    unset($yard);
}
