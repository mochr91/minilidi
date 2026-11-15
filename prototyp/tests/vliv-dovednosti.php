<?php
declare(strict_types=1);

// Měkký vliv zkušenosti ve Víře, Domově a Sousedech (násobek 0,85 až 1,18) a cvičení z rozhovorů a obřadů.
// Použití: php tests/vliv-dovednosti.php   (bez modelu)
require __DIR__.'/fixture.php';

$level=fn(float $v)=>array_fill_keys(array_keys(CATEGORIES),$v);

check(abs(softSkill(['skills'=>$level(0.0)],'faith')-.85)<1e-9 && abs(softSkill(['skills'=>$level(100.0)],'home')-1.18)<1e-9,'Rozsah měkkého vlivu 0,85 až 1,18');
check(abs(softSkill(['skills'=>$level(45.0)],'neighbors')-1.0)<.01,'Běžný dospělý je zhruba 1,0');

// 1. Obřad: zkušený věřící posílí víru, klid i náladu víc.
$prayer=function(float $skill): array {
    [$db,$world]=freshWorld();
    $p=&$world['people']['anna'];
    $p['faith']=40.0; $p['fear']=30.0; $p['mood']=50.0;
    $p['skills']=array_fill_keys(array_keys(CATEGORIES),$skill);
    unset($p);
    assignWork($world,'anna','prayer',false);
    completeWork($db,$world,'anna');
    $p=$world['people']['anna'];
    return [$p['faith']-40,30-$p['fear'],$p['mood']-50];
};
[$f0,$c0,$m0]=$prayer(0.0); [$f1,$c1,$m1]=$prayer(100.0);
check($f1>$f0*1.3 && $c1>$c0*1.3 && $m1>$m0*1.3,'Obřad: víc víry, klidu a nálady při vysoké zkušenosti');

// 2. Sousedé: donesené jídlo víc zasytí a posílí vztah.
$meal=function(float $skill): array {
    [$db,$world]=freshWorld();
    $world['food']=100.0;
    $world['people']['anna']['skills']=array_fill_keys(array_keys(CATEGORIES),$skill);
    $world['people']['jan']['hunger']=80.0;
    $bond=$world['people']['jan']['bonds']['anna']??48;
    assignWork($world,'anna','deliver_meal',false);
    $world['people']['anna']['task']['other']='jan';
    completeWork($db,$world,'anna');
    return [80-$world['people']['jan']['hunger'],($world['people']['jan']['bonds']['anna']??48)-$bond];
};
[$h0,$b0]=$meal(0.0); [$h1,$b1]=$meal(100.0);
check($h1>$h0*1.3 && $b1>$b0*1.3,'Donesené jídlo: víc zasytí a víc zlepší vztah při vysoké zkušenosti');

// 3. Rozhovor na návsi: zkušený soused zvedne náladu a vztah víc a oba se v oboru cvičí.
$talk=function(float $skill): array {
    [$db,$world]=freshWorld();
    foreach (['anna','jan'] as $id) {
        $world['people'][$id]['skills']=array_fill_keys(array_keys(CATEGORIES),$skill);
        $world['people'][$id]['place']='market'; $world['people'][$id]['task']=null;
        $world['people'][$id]['mood']=60.0; $world['people'][$id]['fun']=50.0;
    }
    $bond=$world['people']['anna']['bonds']['jan']??48;
    $scene=['id'=>'anna','other'=>'jan','key'=>'anna:jan','place'=>'market','types'=>['talk','comfort'],'group'=>['anna','jan']];
    applyMeeting($db,$world,$scene,null);
    return [$world['people']['anna']['mood']-60,($world['people']['anna']['bonds']['jan']??48)-$bond,$world['people']['anna']['skills']['neighbors']-$skill];
};
[$m0,$b0,$t0]=$talk(0.0); [$m1,$b1,$t1]=$talk(100.0);
check($m1>$m0*1.3 && $b1>$b0*1.3,'Rozhovor: větší účinek při vysoké zkušenosti ('.round($m0,2).' → '.round($m1,2).')');
check($t0>0 && $t1===0.0,'Rozhovor cvičí zkušenost mezi sousedy (plná zkušenost už neroste)');

// 4. Obřad ve skupině cvičí víru a zesiluje ho nejzkušenější z účastníků.
[$db,$world]=freshWorld();
foreach (['anna','jan','karel'] as $id) { $world['people'][$id]['place']='chapel'; $world['people'][$id]['task']=null; $world['people'][$id]['skills']=$level(10.0); $world['people'][$id]['faith']=60.0; }
$world['people']['karel']['skills']['faith']=100.0;
$scene=['id'=>'anna','other'=>'jan','key'=>'anna:jan','place'=>'chapel','types'=>['pray'],'group'=>['anna','jan','karel']];
$faithBefore=$world['people']['anna']['faith'];
$world['people']['anna']['faith']=60.0;
applyMeeting($db,$world,$scene,null);
check($world['people']['anna']['faith']-60>2.3 && $world['people']['anna']['skills']['faith']>10.0,'Skupinový obřad: účinek podle nejzkušenějšího a cvičení víry');

// 5. Plánovač dál mírně preferuje ty, kdo to umějí (stávající pravidlo se nezměnilo).
check(skillFactor(['skills'=>$level(100.0)],'faith')>skillFactor(['skills'=>$level(0.0)],'faith'),'Faktor zkušenosti roste');
echo "Vliv zkušenosti ve Víře, Domově a Sousedech OK\n";
