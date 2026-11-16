<?php
declare(strict_types=1);

// Fáze G: včelín (med, vosk, svíčky, opylení), cihelna (cihly, tašky, přestavba střech, požár), lázeň (hygiena, nálada).
// Použití: php tests/faze-g.php   (bez modelu)
require __DIR__.'/fixture.php';

$setDate=function(array &$world,int $month,int $day=1,int $hour=8,int $year=3): void {
    $world['calendarOriginDay']=0;
    $world['minute']=(float)((($year-1)*360+$month*30+$day-1)*1440+$hour*60);
    $world['calmUntil']=$world['minute']+99999999;   // bez deště a větru, ať testy nezávisí na počasí
};
$ready=function(int $month=3,array $extra=[],int $year=3) use ($setDate): array {
    [$db,$world]=freshWorld();
    $setDate($world,$month,1,8,$year);
    foreach (array_merge(['statek','dilna','sklep','hospoda','studna','zahrada','kostel','pila','mlyn'],$extra) as $b) $world['buildings'][$b]=1;
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
$saturday=function(array &$world,int $hour=15): void {
    while (intdiv((int)$world['minute'],1440)%7!==BATH_SATURDAY) $world['minute']+=1440;
    $world['minute']=(float)(intdiv((int)$world['minute'],1440)*1440+$hour*60);
};

// 1. Definice, pořadí, ceny, akce.
$defs=['vcelin'=>[40,['zahrada']],'cihelna'=>[90,['dilna','studna']],'lazen'=>[70,['studna','dilna']]];
foreach ($defs as $key=>[$w,$needs]) {
    check((BUILDINGS[$key]['work']??0)===$w,"Pracnost $key = $w h");
    check(BUILDINGS[$key]['needs']===$needs,"$key navazuje na ".implode(', ',$needs));
    check(in_array($key,BUILD_ORDER,true) && in_array($key,BUILD_PREPAY,true),"$key je v pevném pořadí a staví se ze zásob");
    foreach (BUILDINGS[$key]['cost'] as $res=>$amount) check(isset(RESOURCES[$res]) || in_array($res,['wood','stone','planks'],true),"Cena $key: $res");
}
check(array_search('vcelin',BUILD_ORDER,true)===array_search('lis',BUILD_ORDER,true)+1,'Včelín je hned za lisem');
check(array_slice(BUILD_ORDER,-3)===['cihelna','lazen','ovcin'],'Cihelna a lázeň jsou na konci před ovčínem');
foreach (array_merge(BEE_TASKS,BRICK_TASKS,[BATH_TASK]) as $key) check(isset(TASK_STEMS[$key]) && isset(ACTIONS[$key]) && isset(ACTION_CATEGORY[$key]),"$key má kmeny, akci a obor");
foreach (['honey','wax','candles','clay','bricks'] as $res) check(isset(RESOURCES[$res]) && isset(RESOURCE_WORDS[$res]),"Surovina $res má popisky");

// 2. Včelín: bez budovy nic, po stavbě tři úly, péče, med a vosk, zazimování.
[$db,$world]=$ready(3);
check(!workAvailable($world,'anna','tend_bees') && pollination($world)===1.0 && lessonLight($world),'Bez včelína žádné včely, opylení ani svíčky');
$world['buildings']['vcelin']=1;
beesDaily($db,$world);
check($world['hives']===HIVES_START && $world['apiary']['founded'],'Včelín dostane tři úly');
check(workAvailable($world,'anna','tend_bees') && !workAvailable($world,'anna','harvest_honey'),'Na jaře se prohlížejí úly, med ještě není');
for ($i=0;$i<14 && workAvailable($world,'anna','tend_bees');$i++) $work($db,$world,'anna','tend_bees');
check($world['apiary']['care']>=.999 && !workAvailable($world,'anna','tend_bees'),'Plná péče o včely');
$setDate($world,5,2); beesDaily($db,$world);
check($world['apiary']['done'] && abs($world['apiary']['crop']-HIVES_START*HONEY_PER_HIVE*(.4+.6*$world['apiary']['care']))<.2 && $world['apiary']['crop']>7,'V srpnu se spočítá medná úroda ('.$world['apiary']['crop'].')');
check(workAvailable($world,'anna','harvest_honey'),'V srpnu se vytáčí med');
$text=$work($db,$world,'anna','harvest_honey');
check($world['honey']>2 && abs($world['wax']-$world['honey']*WAX_SHARE)<.01 && str_contains($text,'medu'),'Med dá i vosk');
$setDate($world,9); check(!workAvailable($world,'anna','harvest_honey'),'V prosinci se nevytáčí');
// Zazimování a ztráty přes zimu.
$setDate($world,7,3);
check(workAvailable($world,'anna','prepare_hives'),'Na podzim se zazimovávají úly');
for ($i=0;$i<10 && workAvailable($world,'anna','prepare_hives');$i++) $work($db,$world,'anna','prepare_hives');
check($world['apiary']['prepared']>=.999,'Zazimované úly');
$world['hives']=8;
$setDate($world,0,3,8,4); beesDaily($db,$world);   // nový rok včel
check($world['hives']>=6 && $world['hives']<=7 && $world['apiary']['prepared']===0.0,'Zazimované úly zimu většinou přežijí ('.$world['hives'].' z 8)');
[$db,$noprep]=$ready(3,['vcelin']); beesDaily($db,$noprep);
$noprep['hives']=8; $noprep['apiary']['prepared']=0.0; $setDate($noprep,0,3,8,4); beesDaily($db,$noprep);
check($noprep['hives']>=4 && $noprep['hives']<=5,'Nezazimované úly zimu často nepřežijí ('.$noprep['hives'].' z 8)');
$noprep['hives']=1; $noprep['apiary']['season']=3; $noprep['apiary']['prepared']=0.0; $setDate($noprep,0,3,8,4); beesDaily($db,$noprep);
check($noprep['hives']>=1,'Aspoň jedno včelstvo vždycky zůstane');
// Roj.
[$db,$world]=$ready(3,['vcelin']); beesDaily($db,$world);
$world['apiary']['care']=.8; $before=$world['hives']; $setDate($world,2,5); beesDaily($db,$world);
check($world['hives']===$before+1 && $world['apiary']['swarm'],'Silné včelstvo se vyrojí');
$world['apiary']['care']=.8; beesDaily($db,$world); check($world['hives']===$before+1,'Jeden roj za rok');
// Opylení.
[$db,$world]=$ready(5,['vcelin']); $world['hives']=4;
check(abs(pollination($world)-(1+4*POLLINATION_PER_HIVE))<1e-9,'Čtyři úly = +6 % úrody');
$world['hives']=8; check(abs(pollination($world)-(1+POLLINATION_MAX))<1e-9,'Opylení má strop +10 %');
$a=$world; $b=$world; unset($b['buildings']['vcelin']);
foreach ([&$a,&$b] as &$w) { $w['garden']=['season'=>3,'sown'=>1.0,'care'=>1.0,'crop'=>0.0,'done'=>false]; }
unset($w);
$a['minute']=$b['minute']=(float)(((2*360)+5*30+1)*1440+8*60);
winterDaily($db,$a); winterDaily($db,$b);
check($b['garden']['crop']>10 && abs($a['garden']['crop']/$b['garden']['crop']-1.10)<.01,'Včely zvednou úrodu zahrady o 10 % ('.$a['garden']['crop'].' proti '.$b['garden']['crop'].')');
// Med při ošetření.
[$db,$world]=$ready(9,['vcelin']);
$world['herbsDry']=10.0; $world['honey']=5.0;
$remaining=function(array $w,string $patient): int { return (int)($w['people'][$patient]['illUntil']-$w['minute']); };
$ids=array_keys($world['people']);
$nurse=$ids[0]; $sick=$ids[1];
$noHoney=$world; $noHoney['honey']=0.0;
foreach ([&$world,&$noHoney] as &$w) { $w['people'][$sick]['illUntil']=(int)$w['minute']+3000; $w['people'][$sick]['task']=null; }
unset($w);
assignWork($world,$nurse,'nurse',false); $world['people'][$nurse]['task']['other']=$sick; completeWork($db,$world,$nurse);
assignWork($noHoney,$nurse,'nurse',false); $noHoney['people'][$nurse]['task']['other']=$sick; completeWork($db,$noHoney,$nurse);
check($remaining($world,$sick)<$remaining($noHoney,$sick) && abs($world['honey']-(5.0-NURSE_HONEY))<1e-6,'S medem se nemoc zkrátí víc a med se spotřebuje');
// Svíčky a škola.
[$db,$world]=$ready(3,['vcelin','fara']);
$world['wax']=2.0; $world['candles']=0.0;
check(workAvailable($world,'anna','make_candles'),'Z vosku se lijí svíčky');
$work($db,$world,'anna','make_candles');
check($world['wax']===1.0 && $world['candles']>=2.9,'Vosk se změní na svíčky');
$world['wax']=0.0; check(!workAvailable($world,'anna','make_candles'),'Bez vosku nejsou svíčky');
$kid=array_values(schoolChildren($world))[0]??null;
if ($kid===null) { $world['people']['kid']=array_merge($world['people']['anna'],['id'=>'kid','name'=>'Dítě','age'=>9,'family'=>'Kovářovi','task'=>null]); }
$world['candles']=0.0;
$setDate($world,3); while (isSunday($world)) $world['minute']+=1440;
check(workAvailable($world,'anna','teach'),'Na jaře se učí i bez svíček');
$setDate($world,9); while (isSunday($world)) $world['minute']+=1440;
check(!lessonLight($world) && !workAvailable($world,'anna','teach'),'V zimě se bez svíček neučí');
$world['candles']=1.0;
check(workAvailable($world,'anna','teach'),'V zimě se se svíčkou učí');
$work($db,$world,'anna','teach');
check(abs($world['candles']-(1.0-CANDLE_LESSON))<1e-9,'Lekce spotřebuje šestinu svíčky');
[$db,$nofull]=$ready(9,['fara']); while (isSunday($nofull)) $nofull['minute']+=1440;
check(lessonLight($nofull),'Bez včelína se v zimě učí jako dřív');

// 3. Cihelna: hlína, pálení, přestavba střech, požár.
check(roofDefault(['id'=>'Hruškovi','kind'=>'static'])==='thatch' && roofDefault(['id'=>'Kovářovi','kind'=>'static'])==='tile'
    && roofDefault(['id'=>'dum1','kind'=>'dum'])==='tile' && roofDefault(['id'=>'dum2','kind'=>'domek'])==='thatch','Původní střechy domů');
[$db,$world]=$ready(3);
check(!workAvailable($world,'anna','dig_clay') && roofShare($world)===0.0 && fireRiskFactor($world)===1.0 && fireDamageFactor($world)===1.0,'Bez cihelny žádná hlína ani vliv na požár');
$world['buildings']['cihelna']=1;
check(count(thatchHouses($world))===3,'Tři doškové pevné domy');
check(workAvailable($world,'anna','dig_clay') && !workAvailable($world,'anna','fire_bricks'),'Hlína se kope, pálit není z čeho');
$text=$work($db,$world,'anna','dig_clay');
check($world['clay']>=3 && str_contains($text,'hlíny'),'Kopání dá hlínu');
$world['clay']=CLAY_CAP; check(!workAvailable($world,'anna','dig_clay'),'Hliniště má strop');
$world['wood']=100.0; $world['charcoal']=0.0;
check(workAvailable($world,'anna','fire_bricks') && kilnFuel($world)==='wood','Cihly se pálí dřevem, když není uhlí');
$wood=$world['wood']; $clay=$world['clay'];
$text=$work($db,$world,'anna','fire_bricks');
check($world['bricks']>=10 && abs($world['clay']-($clay-BRICK_CLAY))<1e-9 && abs($world['wood']-($wood-BRICK_WOOD))<1e-9 && str_contains($text,'dřevem'),'Pálení spotřebuje hlínu a dřevo');
$world['charcoal']=charcoalTarget($world)+BRICK_COAL+1; $world['bricks']=0.0; $world['clay']=60.0;
check(kilnFuel($world)==='charcoal','Přebytek uhlí se použije přednostně');
$coal=$world['charcoal']; $wood=$world['wood']; $work($db,$world,'anna','fire_bricks');
check(abs($world['charcoal']-($coal-BRICK_COAL))<1e-9 && $world['wood']===$wood,'Pálení uhlím šetří dřevo');
$world['bricks']=BRICKS_TARGET; check(!workAvailable($world,'anna','fire_bricks'),'Dost cihel se nepálí');
$setDate($world,9); check(!workAvailable($world,'anna','dig_clay') && !workAvailable($world,'anna','reroof'),'V zimě se nekope a nepřestavuje');
$setDate($world,3);
$world['bricks']=REROOF_BRICKS-1; check(!workAvailable($world,'anna','reroof'),'Bez dost tašek se střecha nepřestavuje');
$world['bricks']=REROOF_BRICKS+5;
check(workAvailable($world,'anna','reroof'),'S taškami se střecha přestavuje');
$first=thatchHouses($world)[0];
for ($i=0;$i<14 && ($world['roofs'][$first]??null)!=='tile';$i++) { $world['people']['anna']['task']=null; $work($db,$world,'anna','reroof'); }
check(($world['roofs'][$first]??null)==='tile' && abs($world['bricks']-5)<1e-9,'Střecha je z tašek a stála '.REROOF_BRICKS.' kusů');
check(roofMap($world)[$first]==='tile' && count(thatchHouses($world))===2 && abs(roofShare($world)-1/3)<1e-9,'Podíl přestavěných střech 1/3');
check(abs(fireRiskFactor($world)-(1-ROOF_FIRE_RISK/3))<1e-9 && abs(fireDamageFactor($world)-(1-ROOF_FIRE_DAMAGE/3))<1e-9,'Požár je méně pravděpodobný a méně škodí');
$world['roofs']=array_fill_keys(THATCH_STATIC,'tile');
check(roofShare($world)===1.0 && abs(fireRiskFactor($world)-.5)<1e-9,'Všechny střechy z tašek: riziko požáru poloviční');
// Postavený domek je dřevěný s došky a jde přestavět, dům má tašky.
$world['homesBuilt']=[100,200]; $world['houseKinds']=[0=>'dum',1=>'domek'];
check(thatchHouses($world)===['dum2'],'Domek má došky, dům tašky');
// Škody při požáru: s taškami se ztratí méně dřeva.
$mk=function(float $share) use ($ready,$setDate): array {
    [$db,$w]=$ready(3); $w['wood']=100.0; $w['food']=300.0; $w['hay']=50.0;
    $w['roofs']=$share>0 ? array_fill_keys(THATCH_STATIC,'tile') : [];
    $w['crisis']=['type'=>'fire','label'=>'Požár','start'=>$w['minute'],'end'=>$w['minute']+60,'need'=>6,'done'=>0.0,'severity'=>0];
    foreach ($w['people'] as &$p) { $p['task']=null; } unset($p);
    resolveCrisis($db,$w);
    return $w;
};
$plain=$mk(0.0); $tiled=$mk(1.0);
check($tiled['wood']>$plain['wood'] && $tiled['food']>$plain['food'],'Tašky zmírní škody při požáru ('.round($tiled['wood'],1).' proti '.round($plain['wood'],1).' dřeva)');

// 4. Lázeň: sobota, dřevo, nálada, hygiena.
[$db,$world]=$ready(3,['lazen']);
$saturday($world,15);
check(workAvailable($world,'anna','bathe'),'V sobotu odpoledne se koupe');
$setDate($world,3); $world['minute']=(float)(intdiv((int)$world['minute'],1440)*1440+15*60);
while (intdiv((int)$world['minute'],1440)%7===BATH_SATURDAY) $world['minute']+=1440;
check(!workAvailable($world,'anna','bathe'),'Mimo sobotu se nekoupe');
$saturday($world,10); check(!workAvailable($world,'anna','bathe'),'Ráno se nekoupe');
$saturday($world,15);
$world['wood']=BATH_RESERVE-1; check(!workAvailable($world,'anna','bathe'),'Při nedostatku dřeva lázeň zavře');
$world['wood']=100.0;
$mood=$world['people']['anna']['mood']; $wood=$world['wood'];
$text=$work($db,$world,'anna','bathe');
check($world['people']['anna']['mood']>$mood && abs($world['wood']-($wood-BATH_WOOD))<1e-9 && !workAvailable($world,'anna','bathe') && str_contains($text,'Koupel'),'Koupel zvedne náladu, spotřebuje dřevo a platí jednou za den');
bathDaily($db,$world);
$adults=count(array_filter($world['people'],fn($p)=>$p['age']>=12));
check($world['hygiene']>0 && abs($world['hygiene']-1/$adults)<.01,'Hygiena roste s koupanými ('.$world['hygiene'].')');
foreach ($world['people'] as $id=>&$p) $p['bathDay']=intdiv((int)$world['minute'],1440)-3;
unset($p);
bathDaily($db,$world); check($world['hygiene']===1.0,'Všichni čistí');
foreach ($world['people'] as $id=>&$p) $p['bathDay']=intdiv((int)$world['minute'],1440)-40;
unset($p);
bathDaily($db,$world); check($world['hygiene']===0.0,'Po měsíci bez koupele hygiena klesne');
// Zima: vyšší rezerva dřeva a nálada.
[$db,$winter]=$ready(10,['lazen']); $saturday($winter,14);
$winter['wood']=BATH_RESERVE_WINTER-1; check(!workAvailable($winter,'anna','bathe'),'V zimě je rezerva dřeva vyšší');
$winter['wood']=150.0; check(workAvailable($winter,'anna','bathe'),'V zimě se s dřevem koupe');
$mood=$winter['people']['anna']['mood']; $work($db,$winter,'anna','bathe');
check($winter['people']['anna']['mood']-$mood>BATH_MOOD,'V zimě lázeň zahřeje víc');
[$db,$none]=$ready(3); bathDaily($db,$none); check($none['hygiene']===0.0,'Bez lázně žádná hygiena');
// Hygiena snižuje zimní nemoci: porovnání dvou vesnic se stejnou stravou.
$sickCount=function(float $hygiene) use ($ready): int {
    [$db,$w]=$ready(10,['lazen']);
    $w['variety']=0.0; $w['hygiene']=$hygiene; $w['herbsDry']=0.0;
    $sick=0;
    for ($d=0;$d<300;$d++) {
        $w['minute']+=1440;
        foreach ($w['people'] as &$p) { $p['illUntil']=0; $p['mood']=80.0; } unset($p);
        $before=($w['counters']['winterSick']??0);
        winterDaily($db,$w);
        $sick+=($w['counters']['winterSick']??0)-$before;
        $w['variety']=0.0;
    }
    return $sick;
};
$s0=$sickCount(0.0); $s1=$sickCount(1.0);
check($s0>0 && $s1<$s0,'Hygiena snižuje zimní nemoci ('.$s1.' proti '.$s0.')');

// 5. Plánovač, cíle, obchod, starý stav.
[$db,$world]=$ready(3,['vcelin','cihelna','lazen']); beesDaily($db,$world);
$offered=[]; foreach (villageCandidates($world) as $list) foreach ($list as $key=>$score) $offered[$key]=1;
check(isset($offered['tend_bees']) && isset($offered['dig_clay']),'Plánovač nabízí včely a hlínu');
$saturday($world,15);
$offered=[]; foreach (villageCandidates($world) as $list) foreach ($list as $key=>$score) $offered[$key]=1;
check(isset($offered['bathe']),'V sobotu plánovač nabízí koupel');
$world['buildings']=['vcelin'=>1,'lazen'=>1]; $world['roofs']=['Hruškovi'=>'tile','Novákovi'=>'tile'];
check(goalValue($world,'bees_house')===1.0 && goalValue($world,'bath_house')===1.0 && goalValue($world,'roofs_tiled')===2.0,'Cíle nových budov');
check(isset(tradeSellRules($world)['honey']) && isset(tradeSellRules($world)['wax']) && isset(tradeSellRules($world)['bricks']),'Obchodník vykupuje med, vosk a cihly');
$old=$world; foreach (['hives','apiary','honey','wax','candles','clay','bricks','roofs','roofProject','hygiene'] as $k) unset($old[$k]);
$restored=decodeWorld(encode($old));
check($restored['hives']===0 && $restored['honey']===0.0 && $restored['roofs']===[] && $restored['roofProject']===null && $restored['apiary']['season']===-1 && $restored['hygiene']===0.0,'Starý uložený stav dostane výchozí hodnoty');
check(roofMap($restored)!==[] && count(roofMap($restored))>=6,'Mapa střech je v API pro každý dům');

// 6. Soak: roky běhu s novými budovami bez varování.
$warn=0;
set_error_handler(function($no,$msg) use (&$warn) { ++$warn; return true; });
[$db,$world]=freshWorld(0,false);
foreach (['mlyn','pila','pec','studna','dilna','statek','zahrada','sklep','hospoda','kostel','fara','vcelin','cihelna','lazen'] as $b) $world['buildings'][$b]=1;
$world['arrivedFamilies'][MILLER_FAMILY]=1; $world['project']=null;
runDays($db,$world,400,false);
restore_error_handler();
check($warn===0,'Běh 400 dnů s novými budovami bez varování PHP ('.$warn.')');
check(($world['hives']??0)>=1 && count($world['people'])>0,'Po roce stále žijí včely i lidé');

echo "OK faze-g\n";
