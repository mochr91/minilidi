<?php
declare(strict_types=1);

// Pivo (fáze C): ječmen z pole, chmelnice, sladovna, pivovar, zrání a pivo v hospodě.
// Použití: php tests/pivo.php   (bez modelu)
require __DIR__.'/fixture.php';

$setDate=function(array &$world,int $month,int $day=1,int $hour=8,int $year=1): void {
    $world['calendarOriginDay']=0;
    $world['minute']=(float)((($year-1)*360+$month*30+$day-1)*1440+$hour*60);
};
$ready=function(int $month=7,array $buildings=['statek','dilna','sklep','hospoda','zahrada','chmelnice','sladovna','pivovar']) use ($setDate): array {
    [$db,$world]=freshWorld();
    $setDate($world,$month);
    foreach ($buildings as $b) $world['buildings'][$b]=1;
    $world['arrivedFamilies'][MILLER_FAMILY]=1;
    $world['food']=400.0; $world['grain']=100.0; $world['hay']=50.0; $world['wood']=100.0; $world['stone']=20.0; $world['planks']=30.0; $world['tools']=5.0;
    $world['project']=null; $world['houseProject']=null; $world['forestWood']=0.0; $world['quarryStone']=0.0;
    foreach ($world['people'] as &$p) { $p['fatigue']=10.0; $p['hunger']=10.0; $p['mood']=80.0; $p['illUntil']=0; $p['task']=null; }
    unset($p);
    return [$db,$world];
};
$work=function(PDO $db,array &$world,string $id,string $key): string {
    assignWork($world,$id,$key,false);
    $text=completeWork($db,$world,$id);
    $world['people'][$id]['task']=null;
    return $text;
};

// 1. Budovy: chmelnice, sladovna a pivovar se stavějí ze zásob v pevném pořadí.
check(BUILDINGS['chmelnice']['work']===40 && BUILDINGS['sladovna']['work']===80 && BUILDINGS['pivovar']['work']===120,'Pracnost 40, 80 a 120 h');
check(BUILDINGS['chmelnice']['needs']===['zahrada'] && BUILDINGS['sladovna']['needs']===['sklep'] && BUILDINGS['pivovar']['needs']===['sladovna','hospoda'],'Závislosti');
foreach (['chmelnice','sladovna','pivovar'] as $key) {
    check(in_array($key,BUILD_PREPAY,true),"$key se staví ze zásob");
    foreach (BUILDINGS[$key]['cost'] as $res=>$amount) check(isset(RESOURCES[$res]) && $res!=='copper',"Cena $key používá jen suroviny, které vesnice získá ($res)");
}
check(array_slice(BUILD_ORDER,11,6)===['sklep','udirna','milir','chmelnice','sladovna','pivovar'],'Pořadí: sklep, udírna, milíř, chmelnice, sladovna, pivovar');

// 2. Sezóny: vaří se od sv. Michala do sv. Jiří, sladuje se od října do dubna.
[$db,$world]=$ready();
$dates=[[6,28,false],[6,29,true],[7,1,true],[11,15,true],[0,10,true],[1,23,true],[1,24,false],[3,10,false]];
foreach ($dates as [$m,$d,$expect]) { $setDate($world,$m,$d); check(brewSeason($world)===$expect,"Vaření $d. den měsíce $m: ".($expect?'ano':'ne')); }
check(maltSeason(7) && maltSeason(11) && maltSeason(0) && maltSeason(1) && !maltSeason(2) && !maltSeason(6),'Sladuje se říjen až duben');

// 3. Ječmen z pole: čtvrtina úrody po postavení sladovny, nejvýš do zásoby.
[$db,$world]=$ready(3,['statek']);
$world['grain']=0.0; $world['barley']=0.0;
addFieldGrain($world,10.0);
check($world['grain']===10.0 && $world['barley']===0.0,'Bez sladovny je celá úroda obilí');
$world['buildings']['sladovna']=1;
addFieldGrain($world,10.0);
check($world['grain']===20.0 && $world['barley']===0.0,'Při nízké zásobě obilí se ječmen nedělí');
$world['grain']=100.0;
addFieldGrain($world,10.0);
check(abs($world['grain']-107.5)<1e-9 && abs($world['barley']-2.5)<1e-9,'Se sladovnou je čtvrtina úrody ječmen');
$world['barley']=BARLEY_CAP+1; $grain=$world['grain'];
addFieldGrain($world,10.0);
check(abs($world['grain']-$grain-10.0)<1e-9 && $world['barley']===BARLEY_CAP+1,'Při plné zásobě ječmene je celá úroda obilí');
$world['barley']=10.0; $world['malt']=BARLEY_CAP*MALT_YIELD; // slad se do zásoby započítá jako ječmen
$grain=$world['grain']; addFieldGrain($world,10.0);
check(abs($world['grain']-$grain-10.0)<1e-9,'Slad se do zásoby ječmene počítá');
$world['barley']=0.0; $world['malt']=0.0; $world['grain']=100.0; $setDate($world,3,10); $world['minute']+=0;
$world['people']['anna']['work']='field';
$text=$work($db,$world,'anna','fieldwork');
$gained=$world['grain']-100.0;
check($world['barley']>0 && $gained>0 && abs($world['barley']/($world['barley']+$gained)-BARLEY_SHARE)<.01 && str_contains($text,'obilí'),'Práce na poli dělí úrodu na obilí a ječmen');

// 4. Chmelnice: péče, úroda po roce, česání, hnití.
[$db,$world]=$ready(2);
$world['buildings']['chmelnice']=(int)$world['minute']-(HOP_ESTABLISH_DAYS+10)*1440;   // zavedená
check(!workAvailable($world,'anna','harvest_hops'),'Na jaře se chmel nečesá');
check(workAvailable($world,'anna','tend_hops'),'Na jaře se chmel váže');
beerDaily($db,$world);
check($world['hopYard']['season']===1 && $world['hopYard']['care']===0.0,'Nová sezóna chmelnice se založí na jaře');
for ($i=0;$i<HOP_TEND_TASKS;$i++) $work($db,$world,'anna','tend_hops');
check($world['hopYard']['care']>=.99,'Pět šest vazeb dá plnou péči ('.round($world['hopYard']['care'],2).')');
for ($i=0;$i<6 && workAvailable($world,'anna','tend_hops');$i++) $work($db,$world,'anna','tend_hops');
check(!workAvailable($world,'anna','tend_hops') && $world['hopYard']['care']>=.999,'Plně ošetřená chmelnice se dál nevazuje');
$setDate($world,6,2); beerDaily($db,$world);
check(abs($world['hopYard']['crop']-HOP_YIELD)<.2,'Zavedená chmelnice dá plnou úrodu ('.$world['hopYard']['crop'].')');
check(workAvailable($world,'anna','harvest_hops') && !workAvailable($world,'anna','tend_hops'),'V září se chmel česá, ne váže');
$crop=$world['hopYard']['crop']; $hops0=$world['hops'];
$text=$work($db,$world,'anna','harvest_hops');
check($world['hops']>$hops0+2 && abs($world['hopYard']['crop']-($crop-($world['hops']-$hops0)))<1e-9 && str_contains($text,'chmele'),'Česání převede úrodu do zásoby chmele');
for ($i=0;$i<10 && $world['hopYard']['crop']>=1;$i++) $work($db,$world,'anna','harvest_hops');
check($world['hopYard']['crop']<1 && !workAvailable($world,'anna','harvest_hops'),'Vyčesaná chmelnice nemá co sklízet');
// První rok jen čtvrtina.
[$db,$world]=$ready(2);
$world['buildings']['chmelnice']=(int)$world['minute'];
beerDaily($db,$world); $setDate($world,6,2); beerDaily($db,$world);
check(abs($world['hopYard']['crop']-HOP_YIELD*HOP_FIRST_YEAR*.4)<.2,'Čerstvá chmelnice bez péče dá jen málo ('.$world['hopYard']['crop'].')');
// Nečesaný chmel shnije.
$world['hopYard']['crop']=10.0; $setDate($world,8,5);
for ($i=0;$i<30;$i++) { $world['minute']+=1440; beerDaily($db,$world); }
check($world['hopYard']['crop']===0.0,'Nesklizený chmel do zimy shnije');
$world['hopYard']['crop']=5.0; $setDate($world,8,5);
check(!workAvailable($world,'anna','harvest_hops'),'V listopadu se chmel už nečesá');
[$db,$world]=$ready(6,['statek','zahrada']);
check(!workAvailable($world,'anna','tend_hops') && !workAvailable($world,'anna','harvest_hops'),'Bez chmelnice se chmel nepěstuje');
$world['weatherOverride']=null;

// 5. Sladování: sezóna, ječmen, dřevo, cílová zásoba.
[$db,$world]=$ready(7);
$world['barley']=30.0;
check(workAvailable($world,'anna','make_malt'),'V říjnu se sladuje');
$setDate($world,3,10);
check(!workAvailable($world,'anna','make_malt'),'V létě se nesladuje');
$setDate($world,7); $world['barley']=2.0;
check(!workAvailable($world,'anna','make_malt'),'Bez ječmene se nesladuje');
$world['barley']=30.0; $world['wood']=5.0;
check(!workAvailable($world,'anna','make_malt'),'Bez dřeva se nesladuje');
$world['wood']=100.0; $world['malt']=MALT_TARGET;
check(!workAvailable($world,'anna','make_malt'),'Při plné zásobě sladu se nesladuje');
$world['malt']=0.0; unset($world['buildings']['sladovna']);
check(!workAvailable($world,'anna','make_malt'),'Bez sladovny se nesladuje');
$world['buildings']['sladovna']=1;
$wood0=$world['wood'];
$text=$work($db,$world,'anna','make_malt');
check($world['barley']<30.0 && abs($world['malt']-(30.0-$world['barley'])*MALT_YIELD)<1e-9 && $world['wood']<$wood0 && str_contains($text,'sladu'),'Z ječmene je slad a spotřebuje se dřevo ('.round($world['malt'],1).')');
check($world['barley']>=30.0-MALT_BATCH*1.5-.01,'Jeden úkol zpracuje nejvýš dávku');

// 6. Vaření: sezóna, suroviny, jednorázová platba, postup, dokončení.
[$db,$world]=$ready(7);
$world['malt']=40.0; $world['hops']=5.0;
check(workAvailable($world,'anna','brew_beer'),'Pivovar vaří, když je slad, chmel a dřevo');
check(!workAvailable($world,'tomas','brew_beer'),'Dítě pivo nevaří');
$setDate($world,3,10);
check(!workAvailable($world,'anna','brew_beer'),'V létě se nevaří');
$setDate($world,7);
$world['hops']=0.0;
check(!workAvailable($world,'anna','brew_beer'),'Bez chmele se nevaří');
$world['hops']=5.0; $world['malt']=3.0;
check(!workAvailable($world,'anna','brew_beer'),'Bez dost sladu se nevaří');
$world['malt']=40.0; $world['wood']=12.0;
check(!workAvailable($world,'anna','brew_beer'),'Bez rezervy dřeva se nevaří');
$world['wood']=100.0; $world['beer']=BEER_TARGET;
check(!workAvailable($world,'anna','brew_beer'),'Při plné zásobě piva se nevaří');
$world['beer']=0.0; unset($world['buildings']['pivovar']);
check(!workAvailable($world,'anna','brew_beer'),'Bez pivovaru se nevaří');
$world['buildings']['pivovar']=1;
$text=$work($db,$world,'anna','brew_beer');
check($world['malt']===40.0-BREW_COST['malt'] && $world['hops']===5.0-BREW_COST['hops'] && $world['wood']===100.0-BREW_COST['wood'] && $world['brewProject']['paid'] && str_contains($text,'postoupila'),'První práce zaplatí várku');
$text=$work($db,$world,'karel','brew_beer');
check($world['malt']===40.0-BREW_COST['malt'] && $world['wood']===100.0-BREW_COST['wood'],'Druhá práce neplatí znovu');
$rounds=0;
while ($world['brewProject']!==null && $rounds<30) { ++$rounds; $work($db,$world,['anna','karel','jan'][$rounds%3],'brew_beer'); }
check($world['brewProject']===null && abs($world['beerYoung']-BREW_BEER)<1e-9 && $world['beer']===0.0,'Hotová várka je mladé pivo ('.$world['beerYoung'].')');
check($rounds>=3 && $rounds<=12,'Várka trvá zhruba '.BREW_WORK.' člověkohodin ('.$rounds.' úkolů)');
check(($world['beerLog']['brews']??0)===1,'Záznam počítá várky');
$world['beerYoung']=BEER_TARGET;
$text=$work($db,$world,'anna','brew_beer');
check($world['brewProject']===null && $world['malt']===40.0-BREW_COST['malt'] && str_contains($text,'dost'),'Opožděný úkol bez projektu při plné zásobě neplatí');
$world['beerYoung']=0.0;
$world['malt']=40.0; $world['hops']=5.0; $world['wood']=100.0;
$work($db,$world,'anna','brew_beer');
$world['malt']=0.0; $world['hops']=0.0; $world['wood']=0.0;
check($world['brewProject']['paid'] && workAvailable($world,'anna','brew_beer'),'Zaplacená várka se dokončí i bez dalších zásob');

// 7. Zrání: mladé pivo za čtrnáct dní dozraje a trochu se kazí.
[$db,$world]=$ready(7);
$world['beerYoung']=100.0; $world['beer']=0.0;
beerDaily($db,$world);
check(abs($world['beer']-100*BEER_YOUNG_KEEP*BEER_RIPEN)<1e-9 && abs($world['beerYoung']-100*BEER_YOUNG_KEEP*(1-BEER_RIPEN))<1e-9,'Za den dozraje desetina');
for ($i=0;$i<14;$i++) beerDaily($db,$world);
check($world['beer']>60 && $world['beer']<85 && $world['beerYoung']<25,'Po čtrnácti dnech je většina zralá ('.round($world['beer']).' zralého, '.round($world['beerYoung']).' mladého)');
$world['beer']=100.0; $world['beerYoung']=0.0; $world['barley']=100.0; $world['malt']=100.0; $world['hops']=100.0;
beerDaily($db,$world);
check(abs($world['beer']-100*BEER_KEEP)<1e-9 && $world['barley']<100 && $world['malt']<100 && $world['hops']<100,'Zásoby se kazí pomalu');
check(abs($world['hops']-100*HOPS_KEEP)<1e-9 && abs($world['malt']-100*MALT_KEEP)<1e-9,'Chmel 0,5 % a slad 0,3 % denně');

// 8. Hospoda: pivo se pije jednou za večer, bez pivovaru se nic nemění, bez piva klesá účinek.
[$db,$world]=$ready(7,['statek','hospoda']);
$p=&$world['people']['anna']; $p['fun']=40.0; $p['mood']=50.0;
$now=(int)$world['minute'];
check(tavernServe($world,$p,$now)===1.0 && $p['fun']===40.0,'Bez pivovaru hospoda funguje jako dřív');
$world['buildings']['pivovar']=1; $world['beer']=10.0;
check(tavernServe($world,$p,$now)===1.0 && abs($world['beer']-(10.0-BEER_RATION))<1e-9 && $p['fun']>45 && $p['mood']>51,'Se zralým pivem má hospoda plný účinek a pivo ubývá');
$beer=$world['beer']; $fun=$p['fun'];
check(tavernServe($world,$p,$now+15)===1.0 && $world['beer']===$beer && $p['fun']===$fun,'Za večer se pije jen jednou');
check(tavernServe($world,$p,$now+1440)===1.0 && $world['beer']<$beer,'Další den se pije znovu');
$world['beer']=0.0; $world['beerYoung']=5.0; $p['beerDay']=-1; $p['fun']=40.0;
check(tavernServe($world,$p,$now)===1.0 && $p['fun']>40 && $p['fun']<46 && abs($world['beerYoung']-(5.0-BEER_RATION))<1e-9,'Mladé pivo působí slaběji ('.round($p['fun']-40,1).')');
$world['beerYoung']=0.0; $p['beerDay']=-1; $p['fun']=40.0;
check(tavernServe($world,$p,$now)===TAVERN_DRY_FACTOR && $p['fun']===40.0 && ($world['beerLog']['dry']??0)>=1,'Bez piva má hospoda jen 40 % účinku');
unset($p);
// Skutečný tik: s pivem a bez něj.
$tickFun=function(float $beer) use ($ready): float {
    [$db,$world]=$ready(7,['statek','hospoda','pivovar']);
    $world['beer']=$beer;
    $world['minute']=(float)(7*30*1440+19*60);
    foreach (['anna','jan'] as $id) { $q=&$world['people'][$id]; $q['mood']=80; $q['hunger']=20; $q['fatigue']=20; $q['fun']=45.0; $q['beerDay']=-1; unset($q); }
    tick($db,$world);
    return $world['people']['anna']['fun']-45.0;
};
$with=$tickFun(50.0); $dry=$tickFun(0.0);
check($with>$dry+2,'V tiku se s pivem baví víc než bez něj ('.round($with,1).' proti '.round($dry,1).')');

// 9. Plánovač, cíle, starý stav.
[$db,$world]=$ready(7);
$world['barley']=30.0; $world['malt']=40.0; $world['hops']=5.0;
$candidates=villageCandidates($world);
$offered=[]; foreach ($candidates as $list) foreach ($list as $key=>$score) $offered[$key]=max($offered[$key]??0,$score);
check(isset($offered['brew_beer']) && isset($offered['make_malt']),'Plánovač nabízí vaření i sladování');
check(isset(TASK_STEMS['brew_beer'],TASK_STEMS['make_malt'],TASK_STEMS['tend_hops'],TASK_STEMS['harvest_hops']),'Texty plánů znají nové úkoly');
check(beerScore($world,'harvest_hops')>beerScore($world,'tend_hops'),'Česání má přednost před vazbou');
$brief=developmentBrief($world,materialNeeds($world));
check(isset($brief['sklad']['pivo'],$brief['sklad']['slad'],$brief['sklad']['chmel'],$brief['sklad']['jecmen']),'Přehled pro plánování zná pivo');
$world['beer']=30.0; $world['buildings']['hospoda']=1;
$goals=array_column(goalState($world),null,'key');
check(isset($goals['brewing_house'],$goals['beer_stock']) && $goals['brewing_house']['current']===3.0 && $goals['beer_stock']['current']===30.0,'Cíle pro pivovar a zásobu piva');
foreach (['chmelnice','sladovna','pivovar'] as $key) check(isset(ACTIONS['brew_beer']) && isset(ACTIONS['tend_hops']) && isset(ACTIONS['make_malt']) && isset(ACTIONS['harvest_hops']) && actionCategory($world,'brew_beer')==='craft' && actionCategory($world,'tend_hops')==='field',"Činnosti a kategorie ($key)");
// Starý stav bez piva dostane výchozí hodnoty.
$old=$world;
foreach (['barley','malt','hops','beerYoung','beer','brewProject','hopYard','beerLog'] as $k) unset($old[$k]);
$restored=decodeWorld(encode($old));
check($restored['barley']===0.0 && $restored['beer']===0.0 && $restored['brewProject']===null && $restored['hopYard']['done']===false && $restored['beerLog']['brews']===0,'Starý uložený stav dostane výchozí hodnoty piva');
// Požár nepostihne chmelnici.
$world['buildings']['chmelnice']=1;
check(!in_array('chmelnice',array_diff(array_keys($world['buildings']),['studna','hrbitov','kostel','sklep','zahrada','chmelnice']),true),'Chmelnice nehoří');

// 10. Delší běh od nuly bez varování: s hotovými budovami se uvaří pivo a hospoda ho vypije.
$seen=[]; set_error_handler(function($no,$msg,$file,$line) use (&$seen){ $seen[basename($file).':'.$line.' '.$msg]=1; return true; });
[$db,$world]=$ready(5);
$world['buildings']['chmelnice']=(int)$world['minute']-400*1440;
$world['barley']=60.0; $world['food']=1500.0; $world['grain']=300.0; $world['wood']=600.0; $world['planks']=40.0;
runDays($db,$world,300);
restore_error_handler();
check(!$seen,'300 dní bez varování PHP: '.implode('; ',array_slice(array_keys($seen),0,3)));
check(($world['beerLog']['brews']??0)>=3 && ($world['beerLog']['drunk']??0)>50,'V běhu se uvaří pivo a vypije ('.($world['beerLog']['brews']??0).' várek, '.round($world['beerLog']['drunk']??0).' dávek)');
foreach (['barley','malt','hops','beer','beerYoung','grain','wood'] as $key) check($world[$key]>=-1e-9,"Zásoba $key není záporná");
echo "Pivo: budovy, sezóny, ječmen, chmelnice, slad, várka, zrání, hospoda, plánovač a běh OK\n";
