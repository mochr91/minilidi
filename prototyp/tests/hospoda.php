<?php
declare(strict_types=1);
require __DIR__.'/fixture.php';

[$db,$world]=freshWorld();
$world['buildings']=array_fill_keys(array_diff(BUILD_ORDER,['hospoda']),1);
$world['wood']=24.0;
$world['stone']=7.0;
$world['planks']=20.0;
$world['project']=null;
check(!in_array('hospoda',projectOptions($world),true),'Hospoda potřebuje dost materiálu');
check((materialNeeds($world)['stone']??0)===1.0,'Plánovač začne chystat chybějící materiál');
$world['stone']=16.0;
$oldWorld=decodeWorld(encode($world));
check(in_array('hospoda',projectOptions($oldWorld),true),'Starý stav může postavit hospodu');
$withoutSawmill=$world;
unset($withoutSawmill['buildings']['pila']);
check(!in_array('hospoda',projectOptions($withoutSawmill),true),'Nejdřív musí stát pila');
chooseProject($db,$world);
check($world['project']['key']==='hospoda','Pravidla vyberou hospodu');
for ($i=0;$i<150 && !hasBuilding($world,'hospoda');$i++) {
    assignWork($world,'anna','construct',false);
    completeWork($db,$world,'anna');
    $world['people']['anna']['task']=null;
}
check(hasBuilding($world,'hospoda') && $world['project']===null,'Dokončení hospody');
check($world['wood']===12.0 && $world['stone']===8.0 && $world['planks']===10.0,'Materiál se zaplatil jednou');
$world['minute']=18*60+15;
foreach (['anna','jan'] as $id) {
    $world['people'][$id]['mood']=80;
    $world['people'][$id]['hunger']=20;
    $world['people'][$id]['fatigue']=20;
    $world['people'][$id]['fun']=45;
}
tick($db,$world);
check($world['people']['anna']['place']==='tavern' && $world['people']['anna']['fun']>45,'Dospělí se baví v hospodě');
check($world['people']['tomas']['place']!=='tavern','Dítě zůstává mimo hospodu');
$socialWorld=$world;
$socialWorld['people']=array_intersect_key($socialWorld['people'],array_flip(['anna','jan']));
$scene=socialOptions($socialWorld);
check(($scene['place']??null)==='tavern','Skutečné setkání v hospodě');
$before=$socialWorld['people']['anna']['bonds']['jan'];
applyMeeting($db,$socialWorld,$scene,null);
check($socialWorld['people']['anna']['bonds']['jan']>$before,'Setkání posiluje vztah');
$world['crisis']=['type'=>'fire'];
check(!tavernOpen($world,18),'Za krize se hospoda zavře');
$world['crisis']=null;
check(!tavernOpen($world,22),'Po 22. hodině je zavřeno');
$world['tools']=5.0;
goalsDaily($db,$world);
foreach (['tavern:1','public_buildings:7','plank_stock:10','tool_stock:5'] as $slot) {
    check(isset($world['goals']['done'][$slot]),'Cíl '.$slot);
}
echo "Hospoda: cena, plánování, stavba, návštěvy, vztahy a nové cíle OK\n";
