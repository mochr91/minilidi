<?php
declare(strict_types=1);

// Fáze F: špýchar, mlékárna, rybník, koželužna s ševcovnou, tkalcovna, fara se školou.
// Použití: php tests/remesla.php   (bez modelu)
require __DIR__.'/fixture.php';

$setDate=function(array &$world,int $month,int $day=1,int $hour=8,int $year=3): void {
    $world['calendarOriginDay']=0;
    $world['minute']=(float)((($year-1)*360+$month*30+$day-1)*1440+$hour*60);
};
$ready=function(int $month=3,array $extra=[]) use ($setDate): array {
    [$db,$world]=freshWorld();
    $setDate($world,$month);
    foreach (array_merge(['statek','dilna','sklep','hospoda','studna','chliv','ohrada','kostel','pila','mlyn'],$extra) as $b) $world['buildings'][$b]=1;
    $world['arrivedFamilies'][MILLER_FAMILY]=1;
    $world['food']=400.0; $world['grain']=200.0; $world['wood']=100.0; $world['planks']=30.0; $world['stone']=30.0;
    $world['project']=null; $world['houseProject']=null;
    foreach ($world['people'] as &$p) { $p['fatigue']=10.0; $p['hunger']=10.0; $p['mood']=60.0; $p['illUntil']=0; $p['task']=null; }
    unset($p);
    return [$db,$world];
};
$work=function(PDO $db,array &$world,string $id,string $key): string {
    assignWork($world,$id,$key,false);
    $text=completeWork($db,$world,$id);
    $world['people'][$id]['task']=null;
    return $text;
};

// 1. Definice, pořadí, ceny a pracnost.
$hours=['spychar'=>120,'mlekarna'=>60,'rybnik'=>80,'kozeluzna'=>70,'tkalcovna'=>90,'fara'=>200];
foreach ($hours as $key=>$w) {
    check((BUILDINGS[$key]['work']??0)===$w,"Pracnost $key = $w h");
    check(in_array($key,BUILD_ORDER,true) && in_array($key,BUILD_PREPAY,true),"$key je v pevném pořadí a staví se ze zásob");
    foreach (BUILDINGS[$key]['cost'] as $res=>$amount) check(isset(RESOURCES[$res]) || in_array($res,['wood','stone','planks'],true),"Cena $key: $res");
    foreach (BUILDINGS[$key]['needs'] as $need) check(isset(BUILDINGS[$need]),"$key navazuje na $need");
}
check(array_slice(BUILD_ORDER,-6)===['spychar','mlekarna','rybnik','kozeluzna','tkalcovna','fara'],'Nové budovy jsou na konci pořadí');
check(BUILDINGS['fara']['needs']===['kostel'] && BUILDINGS['kozeluzna']['needs']===['chliv','dilna'],'Fara po kostele, koželužna po chlívu a dílně');

// 2. Koželužna: kožky -> useň -> boty, boty chrání v zimě.
[$db,$world]=$ready(3,['kozeluzna']);
check(!workAvailable($world,'anna','tan_hides') && !workAvailable($world,'anna','make_boots'),'Bez kožek se nic nedělá');
$world['hides']=9.0;
check(workAvailable($world,'anna','tan_hides'),'Z kožek se dělá useň');
$text=$work($db,$world,'anna','tan_hides');
check($world['hides']===5.0 && $world['leather']>=1.4 && $world['leather']<=3.5 && str_contains($text,'useně'),'Vydělání spotřebuje kožky a dá useň');
check(workAvailable($world,'anna','make_boots'),'Z useně se šijí boty');
$leather=$world['leather']; $work($db,$world,'anna','make_boots');
check($world['boots']===1.0 && abs($world['leather']-($leather-BOOTS_LEATHER))<1e-9,'Boty stojí useň');
$world['boots']=bootsTarget($world);
check(!workAvailable($world,'anna','make_boots'),'Víc bot než je potřeba se neušije');
$world['boots']=0.0; check(!shod($world),'Bez bot není zimní ochrana');
$world['boots']=count($world['people'])*BOOTS_COVER+.1; check(shod($world),'S botami je zimní ochrana');
unset($world['buildings']['kozeluzna']); $world['hides']=9.0;
check(!workAvailable($world,'anna','tan_hides'),'Bez koželužny se nevydělává');
// Kožky z domácího zabíjení.
[$db,$world]=$ready(3,['kozeluzna']);
$world['hides']=0.0; craftsDaily($db,$world);
check(abs($world['hides']-(HIDES_FROM_PIG+HIDES_FROM_COW))<1e-9,'Chlív a ohrada dávají kožky');
$world['boots']=10.0; $world['leather']=10.0; craftsDaily($db,$world);
check(abs($world['boots']-10*BOOTS_KEEP)<1e-9 && abs($world['leather']-10*LEATHER_KEEP)<1e-9,'Boty se opotřebují, useň drží');

// 3. Tkalcovna: len z pole -> látka.
[$db,$world]=$ready(3,['tkalcovna']);
$world['grain']=100.0; $before=$world['grain'];
addFieldGrain($world,50.0);
check(abs($world['flax']-50.0*FLAX_SHARE)<1e-9 && abs($world['grain']-($before+50.0-50.0*FLAX_SHARE))<1e-9,'Z úrody je část len');
[$db2,$noloom]=$ready(3); addFieldGrain($noloom,50.0);
check(($noloom['flax']??0)===0.0,'Bez tkalcovny se len neseje');
$world['flax']=FLAX_CAP; $f=$world['flax']; addFieldGrain($world,50.0);
check($world['flax']===$f,'Zásoba lnu má strop');
$world['flax']=8.0; $world['cloth']=0.0;
check(workAvailable($world,'anna','spin_weave'),'Z lnu se tká');
$text=$work($db,$world,'anna','spin_weave');
check($world['flax']===2.0 && $world['cloth']>=1.4 && str_contains($text,'látky'),'Tkaní spotřebuje len a dá látku');
$world['flax']=8.0; $world['cloth']=clothTarget($world);
check(!workAvailable($world,'anna','spin_weave'),'Nad cílovou zásobu se netká');
$world['cloth']=0.0; $world['trade']['visits']=0;
check(!isset(tradeBuyRules($world)['cloth']),'S tkalcovnou se látka nekupuje');
check(isset(tradeBuyRules($ready(3)[1])['cloth']),'Bez tkalcovny se látka kupuje');

// 4. Mlékárna: mléko -> sýr, sýr jako jídlo.
[$db,$world]=$ready(3,['mlekarna']);
craftsDaily($db,$world);
check(abs($world['milk']-MILK_DAY)<1e-9,'Mlékárna sbírá mléko z ohrady');
for ($i=0;$i<30;$i++) craftsDaily($db,$world);
check($world['milk']<=MILK_CAP,'Mléko má strop');
check(workAvailable($world,'anna','make_cheese'),'Z mléka se dělá sýr');
$milk=$world['milk']; $text=$work($db,$world,'anna','make_cheese');
check(abs($world['milk']-($milk-CHEESE_MILK))<1e-9 && $world['cheese']>=1.0 && str_contains($text,'sýra'),'Sýření spotřebuje mléko');
$world['milk']=0.0; check(!workAvailable($world,'anna','make_cheese'),'Bez mléka se sýr nedělá');
$world['cheese']=10.0; $world['food']=50.0; $reserve=foodReserve($world);
check(abs($reserve-(50.0+$world['grain']+$world['flour']*HEARTH_FLOUR_FOOD+($world['eggs']??0)*EGG_FOOD+plantStock($world)+(($world['game']??0)+($world['meatSmoked']??0))*GAME_FOOD+10*CHEESE_FOOD))<1e-6,'Sýr se počítá do jídelní zásoby');
$cheese=$world['cheese']; drawFood($world,10.0);
check($world['cheese']<$cheese && abs(($cheese-$world['cheese'])*CHEESE_FOOD-10.0*CHEESE_MEAL_SHARE)<1e-6,'Sýr tvoří část jídla');
$world['milk']=10.0; unset($world['buildings']['ohrada']); craftsDaily($db,$world);
check($world['milk']<10.0*MILK_KEEP+.01,'Bez ohrady se mléko nepřidává');

// 5. Rybník: osazení, růst jaro až podzim, výlov na podzim.
[$db,$world]=$ready(3,['rybnik']);
craftsDaily($db,$world);
check($world['pond']['fish']>=POND_START && !empty($world['pond']['stocked']) && (int)$db->query("SELECT COUNT(*) FROM journal WHERE title LIKE 'Rybník osazen%'")->fetchColumn()===1,'Rybník se osadí kapry');
$f=$world['pond']['fish']; for ($i=0;$i<120;$i++) craftsDaily($db,$world);
check($world['pond']['fish']>$f && $world['pond']['fish']<=POND_MAX,'Obsádka přes léto roste ('.round($world['pond']['fish'],1).')');
$setDate($world,11); $f=$world['pond']['fish']; craftsDaily($db,$world);
check(abs($world['pond']['fish']-$f)<1e-9,'V zimě obsádka neroste');
$setDate($world,3); $world['pond']['fish']=30.0;
check(!workAvailable($world,'anna','harvest_pond'),'Na jaře se nelovi');
$setDate($world,7); $world['pond']['fish']=30.0;
check(workAvailable($world,'anna','harvest_pond'),'Na podzim se vylovuje');
$food=$world['food']; $text=$work($db,$world,'anna','harvest_pond');
check($world['pond']['fish']<30.0 && $world['pond']['fish']>=POND_KEEP && $world['food']>$food && str_contains($text,'kaprů'),'Výlov dá jídlo a nechá násadu');
$world['pond']['fish']=POND_KEEP+1; check(!workAvailable($world,'anna','harvest_pond'),'Malá obsádka se nevylovuje');

// 6. Špýchar: obilí a mouka se kazí pomaleji.
[$db,$a]=$ready(3); [$db,$b]=$ready(3,['spychar']);
$a['grain']=100.0; $b['grain']=100.0; $a['flour']=50.0; $b['flour']=50.0;
$a['minute']=(float)((2*360+3*30)*1440+8*60); $b['minute']=$a['minute'];
economyDaily($db,$a); economyDaily($db,$b);
check($b['grain']>$a['grain'] && $b['flour']>$a['flour'],'Špýchar zpomalí kažení obilí a mouky');

// 7. Fara: učení dětí.
[$db,$world]=$ready(3,['fara']);
$kids=schoolChildren($world);
if (!$kids) { $world['people']['kid']=array_merge($world['people']['anna'],['id'=>'kid','name'=>'Dítě','age'=>9,'family'=>'Kovářovi','task'=>null]); $kids=['kid']; }
check(!isSunday($world) || true,'Den v týdnu');
while (isSunday($world)) $world['minute']+=1440;
$teacher='anna';
check(workAvailable($world,$teacher,'teach'),'Dospělý může učit');
$kid=$kids[0]; $before=skillsOf($world['people'][$kid]); $faith=$world['people'][$kid]['faith']??50;
$text=$work($db,$world,$teacher,'teach');
$after=skillsOf($world['people'][$kid]);
check($after['craft']>$before['craft'] && ($world['people'][$kid]['faith']??0)>=$faith && str_contains($text,'dětí'),'Lekce zvýší zkušenosti dětí');
check(!workAvailable($world,$teacher,'teach'),'Jedna lekce za den');
$world['minute']+=1440; while (isSunday($world)) $world['minute']+=1440;
check(workAvailable($world,$teacher,'teach'),'Druhý den se učí znovu');
$world['people'][$teacher]['age']=15; check(!workAvailable($world,$teacher,'teach'),'Učit smí jen dospělý');
$world['people'][$teacher]['age']=30; unset($world['buildings']['fara']); check(!workAvailable($world,$teacher,'teach'),'Bez fary se neučí');

// 8. Plánovač, texty plánů, cíle, starý stav.
[$db,$world]=$ready(7,['kozeluzna','tkalcovna','mlekarna','rybnik','fara','spychar']);
$world['hides']=8.0; $world['flax']=10.0; $world['milk']=12.0; $world['pond']['fish']=40.0;
$offered=[]; foreach (villageCandidates($world) as $list) foreach ($list as $key=>$score) $offered[$key]=1;
foreach (['tan_hides','spin_weave','make_cheese','harvest_pond'] as $key) check(isset($offered[$key]),"Plánovač nabízí $key");
foreach (CRAFT_TASKS as $key) check(isset(TASK_STEMS[$key]) && isset(ACTIONS[$key]) && isset(ACTION_CATEGORY[$key]),"$key má kmeny, akci a obor");
$world['buildings']=['spychar'=>1,'mlekarna'=>1,'kozeluzna'=>1,'fara'=>1]; $world['boots']=5.0;
check(goalValue($world,'larder_house')===2.0 && goalValue($world,'crafts_house')===1.0 && goalValue($world,'school_house')===1.0 && goalValue($world,'boots_stock')===5.0,'Cíle nových budov');
$old=$world; foreach (['leather','boots','flax','milk','cheese','pond','school'] as $k) unset($old[$k]);
$restored=decodeWorld(encode($old));
check($restored['cheese']===0.0 && $restored['pond']['fish']===0.0 && $restored['school']['lastDay']===-1,'Starý uložený stav dostane výchozí hodnoty');

// 9. Obchodník: sýr, useň a boty se prodávají nad zásobu.
[$db,$world]=$ready(7,['hospoda','tkalcovna']);
$setDate($world,7,15);
$world['cheese']=30.0; $world['leather']=20.0; $world['boots']=20.0;
tradeDaily($db,$world);
check($world['cheese']<30.0 || $world['leather']<20.0 || $world['boots']<20.0,'Přebytek nových výrobků se smění');
echo "Špýchar, mlékárna, rybník, koželužna, tkalcovna a fara OK\n";
