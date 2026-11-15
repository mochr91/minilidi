<?php
declare(strict_types=1);

// Rodina Mlynářových: příchod až po druhém domě, složení, vazby, zkušenosti, spuštění stavby domu předem.
// Použití: php tests/rodina.php   (bez modelu)
require __DIR__.'/fixture.php';

$ready=function(): array {
    [$db,$world]=freshWorld();
    $world['wood']=300.0; $world['stone']=100.0; $world['planks']=100.0; $world['food']=800.0;
    $world['project']=null; $world['houseProject']=null;
    return [$db,$world];
};

// 1. Před druhým domem rodina nepřichází a vesnice na ni chystá dům.
[$db,$world]=$ready();
check(millerWaiting($world),'Rodina čeká na dům');
$before=count($world['people']);
welcomeMillerFamily($db,$world);
check(count($world['people'])===$before && empty($world['arrivedFamilies']),'Bez domů nikdo nepřichází');
check(houseWorkAvailable($world),'Čekající rodina spustí stavbu domu i při volném bydlení');
$world['wood']=0.0; $world['stone']=0.0; $world['planks']=0.0;
check(houseWorkAvailable($world)===false && count(materialNeeds($world))>0,'Bez materiálu se dům nezačne a plánovač ho chystá');
[$db,$world]=$ready();
$world['homesBuilt']=[(int)$world['minute']-5000];
welcomeMillerFamily($db,$world);
check(count($world['people'])===$before,'S jedním domem rodina nepřichází');

// 2. Po druhém domě přijde celá rodina a vše je správně napojené.
[$db,$world]=$ready();
$world['homesBuilt']=[(int)$world['minute']-9000,(int)$world['minute']-300];
$world['newcomers']=2;
welcomeMillerFamily($db,$world);
foreach (['vavrinec','dorota','matej','anezka','marta'] as $id) check(isset($world['people'][$id]) && $world['people'][$id]['family']===MILLER_FAMILY,"Člen $id je ve vsi");
check(count($world['people'])===$before+5,'Přibylo pět lidí');
check($world['people']['vavrinec']['spouse']==='dorota' && $world['people']['dorota']['spouse']==='vavrinec','Manželé jsou propojeni');
check($world['people']['matej']['mother']==='dorota' && $world['people']['anezka']['father']==='vavrinec','Děti mají rodiče');
check($world['people']['vavrinec']['sex']==='m' && $world['people']['dorota']['sex']==='f' && $world['people']['marta']['sex']==='f','Pohlaví podle tabulky');
check($world['people']['vavrinec']['skills']['craft']===78.0 && $world['people']['marta']['skills']['faith']===72.0,'Zkušenosti: mlynář v řemeslech, babička ve víře');
check($world['people']['anna']['bonds']['vavrinec']==48 && $world['people']['vavrinec']['bonds']['anna']==48,'Vazby na ostatní vesničany');
check($world['people']['matej']['bonds']['marta']===76 && $world['people']['marta']['bonds']['anezka']===76,'Vazby v rodině');
check($world['arrivedFamilies'][MILLER_FAMILY]===(int)$world['minute'] && !millerWaiting($world),'Příchod je zapsaný');
$entry=(string)$db->query("SELECT detail FROM journal WHERE title='Do vsi přichází rodina Mlynářových'")->fetchColumn();
check(str_contains($entry,'Mlýn má konečně svého mlynáře'),'Kronika zapíše příchod');
welcomeMillerFamily($db,$world);
check(count($world['people'])===$before+5,'Podruhé nikdo nepřibude');
// 3. Rodina sama nepřijde, když chybí místo, jídlo nebo dřevo, nebo ještě neuplynuly dvě hodiny od dokončení.
[$db,$world]=$ready();
$world['homesBuilt']=[(int)$world['minute']-9000,(int)$world['minute']-30];
welcomeMillerFamily($db,$world);
check(count($world['people'])===$before,'Čerstvě dokončený dům: počkat');
$world['homesBuilt']=[(int)$world['minute']-9000,(int)$world['minute']-300];
$world['food']=1.0; $world['grain']=0.0; $world['flour']=0.0;
welcomeMillerFamily($db,$world);
check(count($world['people'])===$before,'Bez zásob rodina nepřijde');
// 4. Starý uložený stav bez klíče arrivedFamilies.
[$db,$world]=$ready();
unset($world['arrivedFamilies']);
$old=decodeWorld(encode($world));
check($old['arrivedFamilies']===[],'Starý stav převezme arrivedFamilies');
// 5. Běh: se dvěma domy přijde rodina sama a vesnice to ustojí bez hladovění a varování.
$seen=[]; set_error_handler(function($no,$msg,$file,$line) use (&$seen){ $seen[]=basename($file).':'.$line.' '.$msg; return true; });
[$db,$world]=$ready();
$world['homesBuilt']=[(int)$world['minute']-9000,(int)$world['minute']-600];
runDays($db,$world,2.0);
check(isset($world['people']['vavrinec']),'V běhu rodina přijde sama');
runDays($db,$world,90.0);
restore_error_handler();
check(!$seen,'Bez varování PHP: '.implode('; ',array_slice($seen,0,3)));
check(isset($world['people']['marta']) || isset($world['graveyard'][0]),'Babička je ve vsi (nebo vzácně zemřela stářím)');
check(foodReserve($world)>dailyFoodNeed($world)*5,'Zásoby jídla po 90 dnech s větší vsí ('.round(foodReserve($world)/dailyFoodNeed($world),1).' dní)');
echo "Rodina Mlynářových OK\n";
