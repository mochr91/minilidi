<?php
declare(strict_types=1);

// Domy v člověkohodinách: samostatná stavba vedle veřejných budov, materiál platí jednou, víc stavitelů, záznam v kronice.
// Použití: php tests/domy.php   (bez modelu)
require __DIR__.'/fixture.php';

$prepare=function(): array {
    [$db,$world]=freshWorld();
    $world['wood']=200.0; $world['stone']=100.0; $world['planks']=100.0;
    $world['project']=null; $world['houseProject']=null; $world['homesBuilt']=[]; $world['houseWanted']='dum';
    $world['arrivedFamilies'][MILLER_FAMILY]=1; // rodina Mlynářových se zkouší zvlášť v rodina.php
    foreach (['anna','karel','jan','matyas'] as $id) { $world['people'][$id]['fatigue']=10.0; $world['people'][$id]['hunger']=10.0; }
    return [$db,$world];
};

// 1. Začátek: je dost lidí a materiálu, dům je k dispozici, ale nejvýš 3 stavitelé najednou.
[$db,$world]=$prepare();
check(houseKindWanted($world)==='dum','Vesnice potřebuje dům');
check(houseWorkAvailable($world),'Dům je k dispozici');
foreach (['anna','karel','jan'] as $id) assignWork($world,$id,'build_house',false);
check(!workAvailable($world,'matyas','build_house'),'Čtvrtý stavitel domu nemůže přidat ruku');
foreach (['anna','karel','jan'] as $id) $world['people'][$id]['task']=null;

// 2. Stavba: materiál se zaplatí jednou, postup v hodinách, dům se dokončí, kronika vypíše stavitele.
$woodBefore=$world['wood']; $stoneBefore=$world['stone'];
$cost=homeCost(0);
$rounds=0;
while (count($world['homesBuilt'])===0 && $rounds<200) {
    ++$rounds;
    foreach (['anna','karel','jan'] as $id) {
        if (count($world['homesBuilt'])>0) break;
        assignWork($world,$id,'build_house',false);
        completeWork($db,$world,$id);
        $world['people'][$id]['task']=null;
        if ($rounds===1 && $id==='anna') {
            check(($world['houseProject']['paid']??false)===true && $world['houseProject']['work']===HOUSE_WORK,'Po první práci je materiál zaplacený');
            check($world['houseProject']['progress']>0 && $world['houseProject']['progress']<3.5,'Dvouhodinový úkol přidá nejvýš 2 h × 1,5 ('.round($world['houseProject']['progress'],2).')');
        }
    }
}
check(count($world['homesBuilt'])===1 && $world['houseProject']===null,'Dům je hotový');
check($rounds>=8 && $rounds<=40,'Tři lidé stavěli zhruba desítky hodin ('.$rounds.' kol)');
check($woodBefore-$world['wood']===(float)($cost['wood']) && $stoneBefore-$world['stone']===(float)($cost['stone']),'Materiál se zaplatil jednou');
$log=$world['buildLog']['dum1']??null;
check($log!==null && $log['work']===HOUSE_WORK && count($log['builders'])===3 && abs($log['worked']-array_sum(array_column($log['builders'],'hours')))<0.6,'buildLog dum1 má tři stavitele');
$entry=(string)$db->query("SELECT detail FROM journal WHERE title='Dokončen nový dům' ORDER BY id DESC LIMIT 1")->fetchColumn();
check(str_contains($entry,'Stavěli:'),'Kronika říká, kdo dům stavěl');

// 3. Rozestavěný dům jde dokončit, i když materiál mezitím došel (už je zaplacený).
[$db,$world]=$prepare();
assignWork($world,'anna','build_house',false);
completeWork($db,$world,'anna');
$world['people']['anna']['task']=null;
$world['wood']=0.0; $world['stone']=0.0; $world['planks']=0.0;
check(houseWorkAvailable($world),'Zaplacený dům jde dostavět bez dalšího materiálu');
// 4. Bez rozestavěného domu je potřeba materiál a nedostatek bydlení.
[$db,$world]=$prepare();
$world['wood']=0.0;
check(!houseWorkAvailable($world),'Bez materiálu se dům nezačne');
[$db,$world]=$prepare();
$world['houseWanted']=null;
check(!houseWorkAvailable($world),'Bez potřeby bydlení se dům nezačne');
[$db,$world]=$prepare();
$world['homesBuilt']=[1,2,3,4];
check(!houseWorkAvailable($world),'Nejvýš čtyři domy nebo domky');
// 5. Starý uložený stav bez klíče houseProject.
[$db,$world]=$prepare();
unset($world['houseProject']);
$old=decodeWorld(encode($world));
check(array_key_exists('houseProject',$old) && $old['houseProject']===null && houseWorkAvailable($old),'Starý stav převezme houseProject');
// 6. Příchozí se nasadí až po dokončení domu (původní pravidlo zůstává).
[$db,$world]=$prepare();
$before=count($world['people']);
$world['newcomers']=0;
welcomeNewcomer($db,$world);
check(count($world['people'])===$before,'Na začátku hry nikdo nepřichází');
echo "Domy v člověkohodinách OK\n";
