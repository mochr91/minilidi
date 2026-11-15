<?php
declare(strict_types=1);

// Stavby v člověkohodinách: pevné pořadí, postup podle odpracovaných hodin, záznam stavitelů.
// Použití: php tests/stavba.php   (bez modelu)
require __DIR__.'/fixture.php';

// 1. Pracnost a pevné pořadí.
check(BUILDINGS['mlyn']['work']===100 && BUILDINGS['statek']['work']===200 && BUILDINGS['hospoda']['work']>=100,'Pracnost v člověkohodinách');
[$db,$world]=freshWorld();
$world['project']=null; $world['buildings']=[];
check(count(projectOptions($world))>=2,'Na začátku jsou k dispozici aspoň dvě stavby');
chooseProject($db,$world);
check($world['project']['key']==='mlyn' && $world['project']['work']===100 && $world['project']['hours']===[],'Pořadí je pevné, začíná mlýnem');

// 2. Postup odpovídá odpracovaným hodinám a výkonu; víc stavitelů je rychlejších.
$run=function(array $ids) use (&$db): array {
    [$db,$world]=freshWorld(14);
    $world['wood']=80.0; $world['stone']=40.0; $world['project']=null; $world['buildings']=[];
    chooseProject($db,$world);
    $rounds=0;
    while (!hasBuilding($world,'mlyn') && $rounds<400) {
        ++$rounds;
        foreach ($ids as $id) {
            $world['people'][$id]['fatigue']=10.0; $world['people'][$id]['hunger']=10.0;
            assignWork($world,$id,'construct',false);
        }
        foreach ($ids as $id) {
            $before=$world['project']['progress']??0.0;
            if ($world['project']===null) break;
            completeWork($db,$world,$id);
            $world['people'][$id]['task']=null;
            if ($world['project']!==null && $world['project']['key']==='mlyn') {
                $gain=$world['project']['progress']-$before;
                check($gain>0 && $gain<=3.0+1e-9,'Dvouhodinový úkol přidá nejvýš 2 h × 1,5');
            }
        }
    }
    return [$world,$rounds];
};
[$w1,$r1]=$run(['anna']);
[$w5,$r5]=$run(['anna','karel','jan','matyas','tomas']);
check(hasBuilding($w1,'mlyn') && hasBuilding($w5,'mlyn'),'Mlýn se dostaví jedním i pěti lidmi');
check($r1>=30 && $r1<=100,'Jeden člověk stavěl desítky dvouhodinových úkolů ('.$r1.')');
check($r5*4<=$r1*1.1 && $r5*5>=$r1*0.6,'Pět lidí je zhruba pětkrát rychlejších ('.$r5.' proti '.$r1.')');

// 3. Záznam stavitelů a zápis do deníku.
$log=$w5['buildLog']['mlyn']??null;
check($log!==null && $log['work']===100 && count($log['builders'])===5,'Záznam obsahuje všech pět stavitelů');
check(abs(array_sum(array_column($log['builders'],'hours'))-$log['worked'])<0.6,'Součet hodin stavitelů souhlasí');
check($log['builders'][0]['hours']>=$log['builders'][4]['hours'],'Stavitelé jsou seřazeni podle hodin');
$entry=$db->query("SELECT detail FROM journal WHERE title LIKE 'Dokončena stavba%' ORDER BY id DESC LIMIT 1")->fetchColumn();
check(is_string($entry) && str_contains($entry,'Stavěli:') && str_contains($entry,'Anna'),'Deník říká, kdo stavěl');
check($w5['project']!==null && $w5['project']['key']==='pila','Hned se plánuje další stavba');

// 4. Zkušený stavitel je rychlejší než nezkušený, mrtvý stavitel zůstane v záznamu jménem.
[$db,$world]=freshWorld();
$world['wood']=80.0; $world['stone']=40.0; $world['project']=null; $world['buildings']=[];
chooseProject($db,$world);
foreach (['anna','karel'] as $id) { $world['people'][$id]['fatigue']=10.0; $world['people'][$id]['hunger']=10.0; }
$world['people']['anna']['skills']=array_fill_keys(array_keys(CATEGORIES),0.0);
$world['people']['karel']['skills']=array_fill_keys(array_keys(CATEGORIES),95.0);
$gains=[];
foreach (['anna','karel'] as $id) {
    assignWork($world,$id,'construct',false);
    $before=$world['project']['progress'];
    completeWork($db,$world,$id);
    $world['people'][$id]['task']=null;
    $gains[$id]=$world['project']['progress']-$before;
}
check($gains['karel']>$gains['anna']*1.3,'Zkušený stavitel postoupí rychleji ('.round($gains['karel'],2).' proti '.round($gains['anna'],2).')');

// 5. Strop současných stavitelů.
[$db,$world]=freshWorld(14);
$world['wood']=80.0; $world['stone']=40.0; $world['project']=null; $world['buildings']=[];
chooseProject($db,$world);
$adults=array_keys(array_filter($world['people'],fn($p)=>$p['age']>=18 && empty($p['pregnant'])));
check(count($adults)>BUILDERS_MAX+1,'Pro zkoušku je dost dospělých');
foreach (array_slice($adults,0,BUILDERS_MAX) as $id) assignWork($world,$id,'construct',false);
check(!workAvailable($world,$adults[BUILDERS_MAX],'construct'),'Víc než '.BUILDERS_MAX.' stavitelů najednou nelze');
echo "Stavba v člověkohodinách OK\n";
