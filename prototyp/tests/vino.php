<?php
declare(strict_types=1);

// Fáze E: vinice, pozdní mráz, sudy, lis, kvašení, víno v hospodě a v kostele.
// Použití: php tests/vino.php   (bez modelu)
require __DIR__.'/fixture.php';

$setDate=function(array &$world,int $month,int $day=1,int $hour=8,int $year=1): void {
    $world['calendarOriginDay']=0;
    $world['minute']=(float)((($year-1)*360+$month*30+$day-1)*1440+$hour*60);
};
$ready=function(int $month=3,int $year=4) use ($setDate): array {
    [$db,$world]=freshWorld();
    $setDate($world,$month,1,8,$year);
    foreach (['statek','dilna','sklep','hospoda','kostel','vinice','lis'] as $b) $world['buildings'][$b]=1;   // vinice stojí od začátku hry
    $world['arrivedFamilies'][MILLER_FAMILY]=1;
    $world['food']=400.0; $world['grain']=200.0; $world['wood']=100.0; $world['planks']=30.0;
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
// Rok bez pozdního mrazu a rok s mrazem (podle šumu).
$mild=null; $frosty=null;
for ($y=4;$y<200 && ($mild===null || $frosty===null);$y++) { $f=weatherNoise($y*37+11,'vine-frost')<VINE_FROST_CHANCE; if ($f && $frosty===null) $frosty=$y; if (!$f && $mild===null) $mild=$y; }

// 1. Budovy: vinice po kostele, lis po vinici, ze zásob.
check(BUILDINGS['vinice']['work']===150 && BUILDINGS['lis']['work']===60 && BUILDINGS['vinice']['needs']===['kostel'] && BUILDINGS['lis']['needs']===['vinice'],'Vinice 150 h po kostele, lis 60 h po vinici');
check(array_slice(BUILD_ORDER,-9,3)===['kostel','vinice','lis'] && in_array('vinice',BUILD_PREPAY,true) && in_array('lis',BUILD_PREPAY,true),'Pořadí a stavba ze zásob');

// 2. Vinice: péče, první úroda až po dvou letech, česání, hnití.
[$db,$world]=$ready(2,$mild);
check(workAvailable($world,'anna','tend_vines') && !workAvailable($world,'anna','harvest_grapes'),'Na jaře se réva ošetřuje');
wineDaily($db,$world);
for ($i=0;$i<12 && workAvailable($world,'anna','tend_vines');$i++) $work($db,$world,'anna','tend_vines');
check($world['vineyard']['care']>=.999,'Plná péče');
$setDate($world,6,2,8,$mild); wineDaily($db,$world);
check(abs($world['vineyard']['crop']-VINE_YIELD)<.3,'Zavedená vinice dá plnou úrodu ('.$world['vineyard']['crop'].')');
check(workAvailable($world,'anna','harvest_grapes'),'V září se česá');
$text=$work($db,$world,'anna','harvest_grapes');
check($world['grapes']>3 && str_contains($text,'hroznů'),'Česání dá hrozny');
$world['vineyard']['crop']=10.0; $setDate($world,8,5,8,$mild);
check(!workAvailable($world,'anna','harvest_grapes'),'V listopadu se už nečesá');
for ($i=0;$i<30;$i++) { $world['minute']+=1440; wineDaily($db,$world); }
check($world['vineyard']['crop']===0.0,'Nesklizené hrozny shnijí');
// Mladá vinice (do dvou let) úrodu nedá.
[$db,$world]=$ready(2,$mild);
$world['buildings']['vinice']=(int)$world['minute']-100*1440;
wineDaily($db,$world); $world['vineyard']['care']=1.0;
$setDate($world,6,2,8,$mild); wineDaily($db,$world);
check($world['vineyard']['crop']===0.0 && $world['vineyard']['done'],'Mladá vinice první dva roky nerodí');
// Pozdní mráz v dubnu.
[$db,$world]=$ready(1,$frosty);
wineDaily($db,$world); $world['vineyard']['care']=1.0;
$setDate($world,1,26,8,$frosty); wineDaily($db,$world);
check(!empty($world['vineyard']['frost']) && (int)$db->query("SELECT COUNT(*) FROM journal WHERE title LIKE 'Pozdní mráz%'")->fetchColumn()===1,'Pozdní mráz v dubnu zničí květy');
wineDaily($db,$world);
check((int)$db->query("SELECT COUNT(*) FROM journal WHERE title LIKE 'Pozdní mráz%'")->fetchColumn()===1,'Mráz se hlásí jednou');
$setDate($world,6,2,8,$frosty); wineDaily($db,$world);
check($world['vineyard']['crop']===0.0,'Po mrazu není úroda');
[$db,$world]=$ready(1,$mild);
wineDaily($db,$world); $setDate($world,1,26,8,$mild); wineDaily($db,$world);
check(empty($world['vineyard']['frost']),'V mírném roce mráz není');

// 3. Sud: 20 člověkohodin a prkna, jen když chybí.
[$db,$world]=$ready(4,$mild);
check(workAvailable($world,'anna','make_barrel'),'Před sklizní se dělá sud');
$planks=$world['planks']; $rounds=0;
while ($world['barrels']<1 && $rounds<20) { ++$rounds; $work($db,$world,['anna','karel','jan'][$rounds%3],'make_barrel'); }
check($world['barrels']===1.0 && $world['planks']===$planks-BARREL_PLANKS && $rounds>=3 && $rounds<=8,'Sud je hotový za '.$rounds.' úkoly a stál '.BARREL_PLANKS.' prkna');
check(!workAvailable($world,'anna','make_barrel'),'Další sud se nedělá, dokud jeden je');
$world['barrels']=0.0; $world['planks']=2.0;
check(!workAvailable($world,'anna','make_barrel'),'Bez prken se sud nedělá');

// 4. Lis a kvašení: až po sklizni (nebo s plným lisem), potřebuje sud; víno je za 60 dní.
[$db,$world]=$ready(7,$mild);
$world['grapes']=12.0; $world['barrels']=1.0; $world['vineyard']['crop']=15.0;
check(!workAvailable($world,'anna','press_wine'),'Dokud se česá, nelisuje se po troškách');
$world['grapes']=PRESS_GRAPES;
check(workAvailable($world,'anna','press_wine'),'Plný lis se lisuje');
$world['grapes']=12.0; $world['vineyard']['crop']=0.0;
check(workAvailable($world,'anna','press_wine'),'Po sklizni se lisuje i menší várka');
$world['barrels']=0.0;
check(!workAvailable($world,'anna','press_wine'),'Bez sudu se nelisuje');
$world['barrels']=1.0; $world['grapes']=PRESS_GRAPES+5;
$text=$work($db,$world,'anna','press_wine');
check($world['wineBatch']['state']==='fermenting' && $world['wineBatch']['yield']===WINE_YIELD && $world['grapes']===5.0 && $world['barrels']===0.0 && $world['wine']===0.0,'Lis naplní sud a víno kvasí');
check(!workAvailable($world,'anna','press_wine') && wineStock($world)===WINE_YIELD,'Druhá várka počká, kvasící víno se počítá do zásoby');
$world['minute']+=(FERMENT_DAYS-1)*1440; wineDaily($db,$world);
check($world['wine']===0.0,'Před šedesáti dny víno není');
$world['minute']+=1440; wineDaily($db,$world);
check(abs($world['wine']-WINE_YIELD)<.1 && $world['wineBatch']['state']==='idle' && (int)$db->query("SELECT COUNT(*) FROM journal WHERE title='Víno dozrálo'")->fetchColumn()===1,'Po šedesáti dnech je víno ve sklepě');
$world['wine']=100.0; $world['grapes']=100.0; wineDaily($db,$world);
check(abs($world['wine']-100*WINE_KEEP)<1e-9 && abs($world['grapes']-100*GRAPE_KEEP)<1e-9,'Víno drží, hrozny se kazí');

// 5. Víno v hospodě (nedělní večer) a při obřadu v kostele.
[$db,$world]=$ready(7,$mild);
$world['wine']=5.0;
$p=&$world['people']['anna']; $p['fun']=40.0; $p['mood']=50.0;
$sunday=(intdiv((int)$world['minute'],1440)|7)-(7-6)+7; while ($sunday%7!==6) ++$sunday;
tavernServe($world,$p,$sunday*1440+19*60);
check(abs($world['wine']-(5.0-WINE_FEAST_RATION))<1e-9 && $p['fun']===48.0 && $p['mood']===53.0,'Nedělní večer s vínem');
tavernServe($world,$p,$sunday*1440+20*60);
check(abs($world['wine']-(5.0-WINE_FEAST_RATION))<1e-9,'Jen jednou za večer');
tavernServe($world,$p,($sunday+1)*1440+19*60);
check(abs($world['wine']-(5.0-WINE_FEAST_RATION))<1e-9,'Ve všední den se víno nepije');
unset($p);
[$db,$world]=$ready(7,$mild);
while (intdiv((int)$world['minute'],1440)%7!==6) $world['minute']+=1440;   // neděle
$world['wine']=1.0; $world['people']['anna']['faith']=40.0;
$work($db,$world,'anna','prayer'); $withWine=$world['people']['anna']['faith'];
check(abs($world['wine']-(1.0-WINE_MASS))<1e-9,'Obřad v kostele spotřebuje víno');
$world['wine']=0.0; $world['people']['anna']['faith']=40.0;
$work($db,$world,'anna','prayer');
check($withWine>$world['people']['anna']['faith'],'S vínem obřad posílí víru víc');
unset($world['buildings']['kostel']); $world['wine']=1.0;
$work($db,$world,'anna','prayer');
check($world['wine']===1.0,'V kapli se víno nepodává');
$world['buildings']['kostel']=1; $world['minute']+=1440;
$work($db,$world,'anna','prayer');
check($world['wine']===1.0,'Ve všední den se víno při obřadu nepodává');

// 6. Plánovač, cíle, starý stav a prodej obchodníkovi.
[$db,$world]=$ready(7,$mild);
$world['grapes']=PRESS_GRAPES; $world['barrels']=1.0; $world['vineyard']['crop']=10.0;
$offered=[]; foreach (villageCandidates($world) as $list) foreach ($list as $key=>$score) $offered[$key]=1;
check(isset($offered['press_wine'],$offered['harvest_grapes']),'Plánovač nabízí česání a lisování');
check(isset(TASK_STEMS['tend_vines'],TASK_STEMS['harvest_grapes'],TASK_STEMS['press_wine'],TASK_STEMS['make_barrel']),'Texty plánů znají nové úkoly');
$world['wine']=12.0;
check(goalValue($world,'vineyard_house')===2.0 && goalValue($world,'wine_stock')===12.0,'Cíle vinice');
$world['wine']=40.0; $setDate($world,7,15,8,$mild);
tradeDaily($db,$world);
check($world['wine']<40.0 && $world['wine']>=15.0 && $world['salt']>0,'Přebytek vína se smění u obchodníka ('.$world['wine'].')');
$old=$world;
foreach (['grapes','wine','barrels','barrelWork','wineBatch','vineyard'] as $k) unset($old[$k]);
$restored=decodeWorld(encode($old));
check($restored['wine']===0.0 && $restored['wineBatch']['state']==='idle' && $restored['vineyard']['done']===false,'Starý uložený stav dostane výchozí hodnoty');
echo "Vinice, sudy, lis, víno v hospodě a v kostele OK\n";
