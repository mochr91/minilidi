<?php
declare(strict_types=1);

// Řemesla a hospodářské budovy po vinici (fáze F): koželužna s ševcovnou, tkalcovna, špýchar, mlékárna, rybník a fara se školou.
// Všechny se staví z vlastních zásob ve pevném pořadí (BUILD_ORDER) a navazují na existující suroviny:
//   kožky -> useň -> boty (zimní zdraví), len z pole -> látka (teplé oblečení, obchod), mléko -> sýr (jídlo i obchod),
//   kapři z rybníka -> jídlo na podzim, špýchar -> menší kažení obilí a mouky, fara -> učení dětí.

const CRAFT_TASKS = ['tan_hides','make_boots','spin_weave','make_cheese','harvest_pond','teach'];

// Koželužna a ševcovna.
const TAN_HIDES = 4.0;                // kožek na jedno vydělávání
const TAN_YIELD = 3.0;                // useň z jedné dávky
const TAN_WOOD = 2.0;
const LEATHER_TARGET = 12.0;
const BOOTS_LEATHER = 2.0;
const BOOTS_KEEP = .997;              // boty se opotřebují (zhruba rok a půl)
const LEATHER_KEEP = .9995;
const HIDES_FROM_PIG = .04;           // kožky z domácího zabíjení za den (s chlívem)
const HIDES_FROM_COW = .05;           // (s ohradou)

// Tkalcovna: část úrody z pole je len.
const FLAX_SHARE = .06;
const FLAX_CAP = 30.0;
const FLAX_KEEP = .999;
const WEAVE_FLAX = 6.0;
const WEAVE_CLOTH = 3.0;

// Mlékárna: mléko od krav z ohrady.
const MILK_DAY = 2.2;
const MILK_CAP = 14.0;
const MILK_KEEP = .8;                 // mléko se bez zpracování rychle kazí
const CHEESE_MILK = 8.0;
const CHEESE_YIELD = 2.0;
const CHEESE_FOOD = 3.0;              // jídlo z jednoho kusu sýra
const CHEESE_KEEP = .998;
const CHEESE_TARGET = 30.0;
const CHEESE_MEAL_SHARE = .12;

// Rybník.
const POND_START = 8.0;
const POND_MAX = 60.0;
const POND_GROWTH = .014;             // denní růst obsádky (zjara až do podzimu)
const POND_KEEP = 8.0;                // tolik kaprů se při výlovu vždy nechá
const POND_CATCH = 6.0;
const CARP_FOOD = 3.5;

// Fara a škola.
const SCHOOL_POINTS = 1.4;            // bodů zkušenosti na lekci a obor
const SCHOOL_FAITH = 1.5;

const BOOTS_COVER = .3;               // boty na osobu, od kterých se počítá zimní ochrana

// --- pomocné ---

function bootsTarget(array $world): float {
    return (float)ceil(count($world['people'])*.5);
}

function shod(array $world): bool {
    return ($world['boots']??0)>=max(1,count($world['people']))*BOOTS_COVER;
}

function clothTarget(array $world): float {
    return round(max(1,count($world['people']))*.8,1);
}

function cheeseStock(array $world): float {
    return (float)($world['cheese']??0);
}

function pondFish(array $world): float {
    return (float)($world['pond']['fish']??0);
}

function schoolChildren(array $world): array {
    $list=[];
    foreach ($world['people'] as $id=>$p) if ($p['age']>=6 && $p['age']<14 && ($p['awayUntil']??0)<=$world['minute']) $list[]=$id;
    return $list;
}

function isSunday(array $world): bool {
    return intdiv((int)$world['minute'],1440)%7===6;
}

// Přidá úrodu z pole do obilí, ječmene a lnu (len jen s tkalcovnou a při zdravé zásobě).
function addFlax(array &$world,float $amount): float {
    if (!hasBuilding($world,'tkalcovna') || ($world['flax']??0)>=FLAX_CAP || $world['grain']<BARLEY_GRAIN_MIN) return 0.0;
    $flax=$amount*FLAX_SHARE;
    $world['flax']=($world['flax']??0)+$flax;
    countProduced($world,'flax',$flax);
    return $flax;
}

// --- dostupnost, body, dokončení ---

function craftWorkAvailable(array $world,string $id,string $key): ?bool {
    if (!in_array($key,CRAFT_TASKS,true)) return null;
    $m=calendarMonth($world);
    $weather=villageWeather($world);
    $person=$world['people'][$id];
    return match ($key) {
        'tan_hides'=>hasBuilding($world,'kozeluzna') && ($world['hides']??0)>=TAN_HIDES && $world['wood']>=TAN_WOOD+4
            && ($world['leather']??0)<LEATHER_TARGET && taskCount($world,'tan_hides')<1,
        'make_boots'=>hasBuilding($world,'kozeluzna') && ($world['leather']??0)>=BOOTS_LEATHER && ($world['boots']??0)<bootsTarget($world)
            && taskCount($world,'make_boots')<1,
        'spin_weave'=>hasBuilding($world,'tkalcovna') && (($world['wool']??0)>=WOOL_WEAVE || ($world['flax']??0)>=WEAVE_FLAX) && ($world['cloth']??0)<clothTarget($world)
            && taskCount($world,'spin_weave')<1,
        'make_cheese'=>hasBuilding($world,'mlekarna') && ($world['milk']??0)>=CHEESE_MILK && cheeseStock($world)<CHEESE_TARGET
            && taskCount($world,'make_cheese')<1,
        'harvest_pond'=>hasBuilding($world,'rybnik') && ($m===7 || $m===8) && !$weather['frozen'] && pondFish($world)>=POND_KEEP+POND_CATCH
            && taskCount($world,'harvest_pond')<1,
        'teach'=>hasBuilding($world,'fara') && $person['age']>=18 && !isSunday($world) && count(schoolChildren($world))>0 && lessonLight($world)
            && (int)($world['school']['lastDay']??-1)!==intdiv((int)$world['minute'],1440) && taskCount($world,'teach')<1,
    };
}

function craftScore(array $world,string $key): int {
    return match ($key) {
        'tan_hides'=>16,
        'make_boots'=>($world['boots']??0)<max(1,count($world['people']))*BOOTS_COVER ? 24 : 12,
        'spin_weave'=>clothed($world) ? 14 : 26,
        'make_cheese'=>($world['milk']??0)>=CHEESE_MILK*1.5 ? 28 : 18,
        'harvest_pond'=>30,
        'teach'=>20,
        default=>0,
    };
}

function completeCraftWork(PDO $db,array &$world,string $id,string $key): ?string {
    if (!in_array($key,CRAFT_TASKS,true)) return null;
    $skill=taskSkillFactor($world,$id,$key);
    switch ($key) {
        case 'tan_hides':
            if (($world['hides']??0)<TAN_HIDES || $world['wood']<TAN_WOOD || !hasBuilding($world,'kozeluzna')) return 'Na vydělání chybí kožky nebo dřevo.';
            $world['hides']-=TAN_HIDES;
            consumeStock($world,'wood',TAN_WOOD);
            $n=round(TAN_YIELD*min(1.15,$skill),1);
            $world['leather']=($world['leather']??0)+$n;
            countProduced($world,'leather',$n);
            return 'Z kožek je '.fmtAmount($n).' useně.';
        case 'make_boots':
            if (($world['leather']??0)<BOOTS_LEATHER || !hasBuilding($world,'kozeluzna')) return 'Na boty chybí useň.';
            $world['leather']-=BOOTS_LEATHER;
            $world['boots']=($world['boots']??0)+1;
            countProduced($world,'boots',1);
            return 'Švec ušil pár bot.';
        case 'spin_weave':
            if (!hasBuilding($world,'tkalcovna') || (($world['wool']??0)<WOOL_WEAVE && ($world['flax']??0)<WEAVE_FLAX)) return 'Na tkaní chybí len nebo vlna.';
            // Vlna je výhodnější než len (z pěti vlny pět látky, z šesti lnu tři), proto se tká přednostně.
            if (($world['wool']??0)>=WOOL_WEAVE) {
                $world['wool']-=WOOL_WEAVE;
                $n=round(WOOL_WEAVE*WOOL_CLOTH*min(1.15,$skill),1);
                $src='vlny';
            } else {
                $world['flax']-=WEAVE_FLAX;
                $n=round(WEAVE_CLOTH*min(1.15,$skill),1);
                $src='lnu';
            }
            $world['cloth']=($world['cloth']??0)+$n;
            countProduced($world,'cloth',$n);
            return 'Z '.$src.' je '.fmtAmount($n).' látky.';
        case 'make_cheese':
            if (($world['milk']??0)<CHEESE_MILK || !hasBuilding($world,'mlekarna')) return 'Na sýr chybí mléko.';
            $world['milk']-=CHEESE_MILK;
            $n=round(CHEESE_YIELD*min(1.15,$skill),1);
            $world['cheese']=($world['cheese']??0)+$n;
            countProduced($world,'cheese',$n);
            return 'Z mléka je '.fmtAmount($n).' sýra.';
        case 'harvest_pond':
            $take=min(POND_CATCH*min(1.15,$skill),pondFish($world)-POND_KEEP);
            if ($take<=0 || !hasBuilding($world,'rybnik')) return 'V rybníce není co lovit.';
            $world['pond']['fish']-=$take;
            $food=$take*CARP_FOOD;
            $world['food']+=$food;
            countProduced($world,'food',$food);
            return 'Z rybníka je '.fmtAmount($take).' kaprů (jídla '.fmtAmount($food).').';
        case 'teach':
            $kids=schoolChildren($world);
            if (!$kids || !hasBuilding($world,'fara')) return 'Žáci nepřišli.';
            // V tmavých měsících se s včelínem učí při svíčce (jedna vydrží šest lekcí).
            if (!lessonLight($world)) return 'Ve škole je tma a svíčky došly.';
            if (hasBuilding($world,'vcelin') && calendarMonth($world)>=8) $world['candles']=max(0.0,($world['candles']??0)-CANDLE_LESSON);
            $teacher=$world['people'][$id];
            $points=SCHOOL_POINTS*(.8+.4*min(1.0,skillLevel($teacher,'home')/100+skillLevel($teacher,'faith')/200));
            foreach ($kids as $cid) {
                $c=&$world['people'][$cid];
                $skills=skillsOf($c);
                foreach ($skills as $cat=>$v) $skills[$cat]=round(min(100.0,$v+$points),1);
                $c['skills']=$skills;
                $c['faith']=clamp(($c['faith']??50)+SCHOOL_FAITH);
                unset($c);
            }
            $world['school']['lastDay']=intdiv((int)$world['minute'],1440);
            $world['school']['lessons']=($world['school']['lessons']??0)+1;
            return 'U fary se učí '.count($kids).' dětí číst, počítat a modlit se.';
    }
    return null;
}

// Denní chod: kažení zásob, kožky z domácího zabíjení, mléko, obsádka rybníka.
function craftsDaily(PDO $db,array &$world): void {
    foreach (['leather'=>LEATHER_KEEP,'boots'=>BOOTS_KEEP,'flax'=>FLAX_KEEP,'cheese'=>CHEESE_KEEP] as $key=>$keep) {
        $world[$key]=($world[$key]??0)*$keep;
        if ($world[$key]<.01) $world[$key]=0.0;
    }
    if (hasBuilding($world,'chliv')) $world['hides']=($world['hides']??0)+HIDES_FROM_PIG;
    if (hasBuilding($world,'ohrada')) $world['hides']=($world['hides']??0)+HIDES_FROM_COW;
    // Mléko krav z ohrady se bez mlékárny spotřebuje v péči o zvířata; s mlékárnou se z něj dělá sýr.
    $world['milk']=($world['milk']??0)*MILK_KEEP;
    if (hasBuilding($world,'mlekarna') && hasBuilding($world,'ohrada')) $world['milk']=min(MILK_CAP,$world['milk']+MILK_DAY);
    if ($world['milk']<.01) $world['milk']=0.0;
    if (hasBuilding($world,'rybnik')) {
        $pond=&$world['pond'];
        if (($pond['fish']??0)<=0 && empty($pond['stocked'])) {
            $pond['fish']=POND_START;
            $pond['stocked']=true;
            journal($db,$world,'','Rybník osazen kapry','Sousedé do rybníka pustili první kapří násadu.','Hra');
        }
        $m=calendarMonth($world);
        if ($m>=0 && $m<=8 && $pond['fish']>0) $pond['fish']=min(POND_MAX,$pond['fish']*(1+POND_GROWTH*(1-$pond['fish']/POND_MAX)));
        unset($pond);
    }
    // Špýchar a jeho vliv na kažení obilí a mouky řeší economyDaily.
}
