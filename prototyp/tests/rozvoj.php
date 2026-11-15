<?php
declare(strict_types=1);

require __DIR__.'/fixture.php';

$stub=(int)($argv[1]??0);
$stubDown=(int)($argv[2]??0);

// 1. Jídlo se bere nejdřív z jídla, pak z mouky a obilí; děti jedí méně.
[$db,$world]=freshWorld();
$world['food']=0.0; $world['flour']=2.0; $world['grain']=5.0;
check(drawFood($world,1.0) && abs($world['flour']-(2-1/HEARTH_FLOUR_FOOD))<1e-6,'Mouka se spotřebuje jako první náhrada');
$world['flour']=0.0;
check(drawFood($world,2.0) && abs($world['grain']-3.0)<1e-6,'Obilí jako poslední náhrada');
$world['grain']=0.0;
check(!drawFood($world,1.0),'Bez zásob se nenají');
check(mealCost(['age'=>1])===0.0 && mealCost(['age'=>4])===.5 && mealCost(['age'=>30])===1.0,'Cena jídla podle věku');
echo "Jídlo OK\n";

// 2. Řetězec obilí → mouka → chléb, dřevo → prkna → nářadí.
[$db,$world]=freshWorld();
$world['buildings']=['mlyn'=>1,'pec'=>1,'pila'=>1,'dilna'=>1];
$world['grain']=10.0; $world['wood']=40.0; $world['stone']=5.0;
$world['people']['anna']['skills']=array_fill_keys(array_keys(CATEGORIES),100*SKILL_FLOOR/SKILL_SLOPE);   // výnos přesně 100 %
$food=$world['food'];
foreach (['mill','bake','saw','craft'] as $key) {
    check(workAvailable($world,'anna',$key) || $key==='bake','Dostupnost '.$key);
    assignWork($world,'anna',$key,false);
    $world['people']['anna']['task']['end']=$world['minute'];
    $message=completeWork($db,$world,'anna');
    check($message!=='' ,'Výsledek '.$key);
    $world['people']['anna']['task']=null;
}
check($world['flour']<6 && $world['food']>$food,'Mouka a chléb vznikly: '.round($world['flour'],1).' '.round($world['food'],1));
check(abs($world['planks']-2.0)<.01 && $world['tools']>2,'Prkna a nářadí vznikly: '.round($world['planks'],2));
$world['buildings']=[];
check(!workAvailable($world,'anna','mill') && !workAvailable($world,'anna','saw'),'Bez budovy se nevyrábí');
echo "Ekonomický řetězec OK\n";

// 3. Stavba: materiál se zaplatí jednou, projekt postupuje a dokončí se.
[$db,$world]=freshWorld();
$world['wood']=40.0; $world['stone']=20.0; $world['project']=null;
chooseProject($db,$world);
check($world['project']['key']==='mlyn','První projekt je mlýn');
check(projectReady($world) && workAvailable($world,'anna','construct'),'Stavba je připravená');
$woodBefore=$world['wood'];
for ($i=0;$i<120 && !hasBuilding($world,'mlyn');$i++) {
    assignWork($world,'anna','construct',false);
    completeWork($db,$world,'anna');
    $world['people']['anna']['task']=null;
}
check(hasBuilding($world,'mlyn') && $woodBefore-$world['wood']===14.0,'Mlýn dokončen a zaplacen jednou');
check($world['project']['key']==='pila','Následuje pila');
echo "Stavba OK\n";

// 4. Těhotenství, porod a omezení těžké práce.
[$db,$world]=freshWorld();
check(!empty($world['people']['bara']['pregnant']),'Barbora je těhotná');
check(!isset($world['people']['dite1']) && count($world['people'])===10,'Zatím žádné dítě');
$world['people']['bara']['pregnant']['due']=$world['minute']+3*1440;
check(!workAvailable($world,'bara','felling'),'Těhotná před porodem netěží dřevo');
runDays($db,$world,5);
$children=array_filter($world['people'],fn($p)=>$p['age']===0);
check(count($children)===1 || isset($world['graveyard'][0]),'Narodilo se jedno dítě (nebo vzácně nepřežilo)');
check(empty($world['people']['bara']['pregnant']) || !isset($world['people']['bara']),'Těhotenství skončilo');
if ($children) {
    $child=array_values($children)[0];
    check($child['mother']==='bara' && $child['father']==='jan' && $child['family']==='Doležalovi','Rodiče dítěte');
    check(mealCost($child)===0.0 && $child['activity']==='Spí v kolébce','Novorozenec nejí a spí');
}
echo "Porod OK\n";

// 5. Stárnutí a realistický počet narození za 6 let (ne pět dětí po sobě).
[$db,$world]=freshWorld();
$births=[]; $deaths=0;
$before=count($world['people']);
$ageBefore=$world['people']['tomas']['age'];
for ($year=1;$year<=6;$year++) {
    $world['food']=max($world['food'],300.0); $world['wood']=max($world['wood'],200.0);
    runDays($db,$world,360);
    $births[$year]=$world['counters']['births'];
    $summary=(int)$db->query('SELECT COUNT(*) FROM weekly')->fetchColumn();
}
$totalBirths=0;
foreach ($db->query("SELECT data FROM weekly") as $row) { $totalBirths+=json_decode($row['data'],true)['births']; }
$totalBirths+=$world['counters']['births'];
$graves=count($world['graveyard']);
check($world['people']['tomas']['age']===$ageBefore+6,'Stárnutí o šest let: '.$world['people']['tomas']['age']);
check($totalBirths>=1 && $totalBirths<=14,'Počet narození za 6 let: '.$totalBirths);
check(count($world['people'])<=housingCapacity($world),'Kapacita bydlení se neprolomí');
check($graves<=8,'Úmrtí za 6 let: '.$graves);
check($summary>=300,'Týdenní souhrny se ukládají: '.$summary);
echo "Šest let: narozeno $totalBirths, zemřelo $graves, populace ".count($world['people'])." OK\n";

// 6. Smrt ve stáří odstraní postavu a rozjede truchlení, hnutí i fronty se uklidí.
[$db,$world]=freshWorld();
$world['people']['katerina']['age']=96;
$world['people']['katerina']['birthDay']=intdiv((int)$world['minute'],1440)-96*360;
$world['religions']=[['name'=>'Test','doctrine'=>'tree','leader'=>'katerina','members'=>['katerina','karel','jan'],'relationToPlayer'=>'vlastní víra','strength'=>30]];
$db->prepare("INSERT INTO queue (kind,person,value) VALUES ('inspiration','katerina','rest')")->execute();
$moodAnna=$world['people']['anna']['mood'];
for ($i=0;$i<200 && isset($world['people']['katerina']);$i++) { $world['minute']=((intdiv((int)$world['minute'],1440)+1)*1440); lifeDaily($db,$world); }
check(!isset($world['people']['katerina']),'Devadesátiletá zemřela');
check(end($world['graveyard'])['id']==='katerina' && in_array(end($world['graveyard'])['cause'],['stáří','nemoc'],true),'Hřbitov');
check($world['religions'][0]['leader']!=='katerina' && !in_array('katerina',$world['religions'][0]['members'],true),'Vůdce hnutí nahrazen');
check((int)$db->query("SELECT COUNT(*) FROM queue WHERE person='katerina'")->fetchColumn()===0,'Fronta vyčištěna');
check(!isset($world['people']['karel']['bonds']['katerina']),'Vazby odstraněny');
echo "Smrt OK\n";

// 7. Mladý a zdravý člověk umírá extrémně vzácně (stovky dní bez úmrtí).
[$db,$world]=freshWorld(0,false);
$healthy=0;
for ($d=300;$d<300+3*360;$d++) foreach ($world['people'] as $p) if ($p['age']<50 && deathCause($world,$p,$d)!==null) $healthy++;
check($healthy<=2,'Zdraví dospělí a děti: úmrtí za 3 roky '.$healthy);
echo "Vzácnost úmrtí OK\n";

// 8. Krize: požár vyvolá všechny schopné k hašení; výsledek zaznamená ztráty.
[$db,$world]=freshWorld();
$world['wood']=100.0; $world['food']=80.0;
startCrisis($db,$world,'fire',1);
$busy=count(array_filter($world['people'],fn($p)=>($p['task']['action']??null)==='firefight'));
check($busy>=5 && !empty($world['crisis']),'Požár: lidé hasí ('.$busy.')');
$guard=guardTask($world['people']['anna'],['firefight'=>1,'rest'=>1]);
check($guard==='firefight','Pojistka plánu pro krizi');
runDays($db,$world,1);
check(empty($world['crisis']) && ($world['counters']['crises'][0]['ratio']??null)!==null,'Požár skončil a byl vyhodnocen');
$lostSomething=$world['wood']<100;
check($lostSomething || ($world['counters']['crises'][0]['ratio']>=.8),'Požár: ztráty nebo úspěšná obrana');
[$db,$world]=freshWorld();
startCrisis($db,$world,'flood',0);
check(!empty($world['crisis']) && crisisAction($world)==='flood_defense','Povodeň');
runDays($db,$world,2);
check(empty($world['crisis']) && $world['fieldBlockedUntil']>0 && !weatherAllows(array_replace($world,['minute'=>$world['fieldBlockedUntil']-60]),'fieldwork'),'Povodeň blokuje pole');
[$db,$world]=freshWorld();
$world['minute']=(float)((5*30)*1440+6*60); // srpen, 1. den (měsíc 5), 6:00
$year=calendarAt($world['minute'],0)['year'];
check(calendarAt($world['minute'],0)['month']==='srpen','Kalendář: srpen');
echo "Krize OK\n";

// 9. Špatná úroda snižuje výnos pole.
[$db,$world]=freshWorld();
$normal=weatherYield($world,'fieldwork');
$world['harvestUntil']=$world['minute']+1000; $world['harvestFactor']=.55;
check(weatherYield($world,'fieldwork')<=$normal*.56+1e-9,'Špatná úroda snižuje výnos');
echo "Úroda OK\n";

// 10. Cíle a skóre se mění se stavbami; týdenní souhrn má text.
[$db,$world]=freshWorld();
$score=villageScore($world);
$world['buildings']=['mlyn'=>1,'pec'=>1];
goalsDaily($db,$world);
check(isset($world['goals']['done']['bread:2']) && villageScore($world)>$score,'Cíl Chléb a vyšší skóre');
$main=goalMain($world);
check($main!==null && $main['key']!=='bread','Hlavní cíl postoupil');
$record=weeklyRecord($world,3);
check(str_contains(summaryText($record),'Týden 3'),'Text souhrnu');
echo "Cíle a souhrny OK\n";

// 11. Rozhodování je shodné v obou větvích (stejný svět = stejné události).
[$dbA,$a]=freshWorld(); [$dbB,$b]=freshWorld();
runDays($dbA,$a,30); runDays($dbB,$b,30);
check($a['counters']['births']===$b['counters']['births'] && count($a['graveyard'])===count($b['graveyard']) &&
    abs($a['food']-$b['food'])<1e-6,'Shodný průběh dvou větví');
echo "Determinismus OK\n";

// 12. Model: velká vesnice se dělí na skupiny a plán obsáhne všechny.
if ($stub>0) {
    putenv('MINILIDI_NO_AI=0'); putenv('MINILIDI_AI=ollama'); putenv('MINILIDI_MODEL=valid');
    putenv('MINILIDI_AI_PORT='.$stub); putenv('MINILIDI_BATCH_TIMEOUT_MS=1500');
    [$db,$world]=freshWorld(34);
    $world['minute']=720.0;
    $batch=prepareVillagePlan($db,$world,true);
    $adults=count($batch['options']);
    check($batch['metrics']['calls']===(int)ceil($adults/AI_PLAN_CHUNK),'Počet volání modelu: '.$batch['metrics']['calls']);
    check(count($batch['response']['plan']??[])===$adults,'Plán obsahuje všechny dospělé: '.count($batch['response']['plan']??[]).' z '.$adults);
    echo "Dělení na skupiny OK ($adults lidí, {$batch['metrics']['calls']} volání)\n";
}
echo "Vše prošlo\n";
