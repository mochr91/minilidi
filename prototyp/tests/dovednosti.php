<?php
declare(strict_types=1);

// Kategorie činností a dovednosti. Použití: php tests/dovednosti.php
require __DIR__.'/fixture.php';

// 1. Každá činnost patří právě do jedné z osmi kategorií a katalog (nabídka v GUI) nese její název.
[$db,$world]=freshWorld();
check(count(CATEGORIES)===8,'Osm kategorií');
foreach (array_keys(ACTIONS) as $key) check(isset(ACTION_CATEGORY[$key],CATEGORIES[ACTION_CATEGORY[$key]]),'Činnost '.$key.' nemá kategorii');
check(array_diff(array_keys(ACTION_CATEGORY),array_keys(ACTIONS))===[],'Mapa kategorií neobsahuje neznámé činnosti');
$catalog=activityCatalog($world);
foreach ($catalog as $key=>$def) check($def['group']===CATEGORIES[$def['category']],'Skupina odpovídá kategorii u '.$key);
check($catalog['mill']['group']==='Řemesla' && $catalog['market']['group']==='Domov a společnost' && $catalog['help']['group']==='Sousedé','Příklady názvů skupin');
$sorted=catalogByCategory($catalog);
check(array_keys($sorted)===array_keys(array_intersect_key($sorted,$catalog)) && count($sorted)===count($catalog),'Seřazený katalog je úplný');
$firstCategories=array_values(array_unique(array_map(fn($d)=>$d['category'],$sorted)));
check($firstCategories===array_keys(CATEGORIES),'Katalog pro GUI je seřazen podle kategorií');
echo "Kategorie činností OK\n";

// 2. Balíček: kategorie z účinku nebo ze souboru, neplatná se odmítne.
$path=tempnam(sys_get_temp_dir(),'minilidi-cat-');
try {
    $pack=json_decode(file_get_contents(__DIR__.'/../balicky/cinnosti.json'),true,12,JSON_THROW_ON_ERROR);
    file_put_contents($path,encode($pack));
    $actions=readActivityPack($path);
    check($actions['pack_orchard']['category']==='forest' && $actions['pack_harvest']['category']==='field'
        && $actions['pack_sick_visit']['category']==='neighbors' && $actions['pack_tavern_games']['category']==='home','Výchozí kategorie podle účinku');
    $pack['activities']['pack_orchard']['category']='craft';
    file_put_contents($path,encode($pack));
    $actions=readActivityPack($path);
    check($actions['pack_orchard']['category']==='craft' && $actions['pack_orchard']['group']==='Řemesla','Kategorie ze souboru');
    $pack['activities']['pack_orchard']['category']='neexistuje';
    file_put_contents($path,encode($pack));
    $rejected=false;
    try { readActivityPack($path); } catch (RuntimeException $error) { $rejected=str_contains($error->getMessage(),'pack_orchard') && str_contains($error->getMessage(),'kategorie'); }
    check($rejected,'Neznámá kategorie se odmítne a chyba ji jmenuje');
} finally { unlink($path); }
echo "Kategorie v balíčku OK\n";

// 3. Výchozí zkušenosti staré hry bez `skills`.
[$db,$world]=freshWorld();
$anna=$world['people']['anna']; $tomas=$world['people']['tomas']; $jan=$world['people']['jan'];
check(!isset($anna['skills']),'Stará hra nemá skills');
check(skillLevel($anna,'field')>=70 && skillLevel($anna,'craft')<=55,'Rolnice umí pole, ostatní hůř: '.json_encode(skillsOf($anna)));
check(skillLevel($jan,'forest')>=70,'Dřevorubec umí les');
check(max(skillsOf($tomas))<=5.01,'Dítě do 12 let nic neumí');
check(count(skillsOf($anna))===8,'Úplná mapa dovedností');
check(abs(skillFactor(['skills'=>['field'=>0]],'field')-.5)<1e-9 && abs(skillFactor(['skills'=>['field'=>100]],'field')-1.15)<1e-9,'Výnos 50 % až 115 %');
echo "Výchozí zkušenosti OK\n";

// 4. Výnos roste se zkušeností (zdroje, zpracování, stavba).
function yieldFor(string $key,float $level,callable $setup): float {
    [$db,$world]=freshWorld();
    $setup($world);
    $world['people']['anna']['skills']=array_fill_keys(array_keys(CATEGORIES),$level);
    $world['people']['anna']['fatigue']=20.0; $world['people']['anna']['hunger']=20.0; $world['people']['anna']['mood']=80.0;
    $world['people']['anna']['toolFactor']=1.0;
    assignWork($world,'anna',$key,false);
    $world['people']['anna']['task']['end']=$world['minute'];
    $before=array_sum($world['counters']['produced']??[]);
    completeWork($db,$world,'anna');
    $after=array_sum($world['counters']['produced']??[]);
    return $after-$before;
}
$none=function(array &$w){};
$plenty=function(array &$w){ $w['buildings']=['mlyn'=>1,'pec'=>1,'pila'=>1,'dilna'=>1]; $w['grain']=30.0; $w['flour']=30.0; $w['wood']=60.0; $w['stone']=10.0; $w['planks']=20.0; $w['quarryStone']=30.0; $w['project']=null; };
foreach (['felling','fieldwork','mill','saw','mow','quarrying'] as $key) {
    $low=yieldFor($key,10.0,$plenty); $high=yieldFor($key,95.0,$plenty);
    check($low>0 && $high>$low*1.4 && $high<$low*2.4,'Výnos '.$key.' roste se zkušeností: '.round($low,2).' → '.round($high,2));
}
$lowBake=yieldFor('bake',10.0,$plenty); $highBake=yieldFor('bake',95.0,$plenty);
check($highBake>$lowBake*1.4,'Pečení roste se zkušeností');
echo "Výnos podle zkušenosti OK\n";

// 5. Učení: roste prací, pomalu u zkušených, mladí rychleji, učitel urychlí, po 100 se nezvyšuje.
function trained(array $person,string $cat,array $extra=[],float $hours=200): array {
    [$db,$world]=freshWorld();
    foreach ($extra as $id=>$p) $world['people'][$id]=$p;
    $world['people'][$person['id']]=$person;
    for ($i=0;$i<$hours;$i++) trainSkill($db,$world,$person['id'],$cat,60.0);
    return [skillLevel($world['people'][$person['id']],$cat),$db,$world];
}
[$db,$world]=freshWorld();
$template=$world['people']['anna'];
$adult=['id'=>'u1','age'=>38,'family'=>'Cizí','skills'=>array_fill_keys(array_keys(CATEGORIES),30.0),'mother'=>null,'father'=>null]+$template;
$young=['id'=>'u2','age'=>15,'family'=>'Cizí2']+$adult;
$old=['id'=>'u3','age'=>65,'family'=>'Cizí3']+$adult;
[$levelAdult]=trained($adult,'craft'); [$levelYoung]=trained($young,'craft'); [$levelOld]=trained($old,'craft');
check($levelAdult>30.5 && $levelYoung>$levelAdult*1.0 && ($levelYoung-30)>1.6*($levelAdult-30),'Mladí se učí rychleji: '.round($levelYoung-30,2).' proti '.round($levelAdult-30,2));
check(($levelOld-30)<($levelAdult-30)*.5,'Starý se učí pomalu');
$expert=['id'=>'u4','skills'=>array_fill_keys(array_keys(CATEGORIES),85.0)]+$adult;
$slow=['id'=>'u5','skills'=>array_fill_keys(array_keys(CATEGORIES),80.0)]+$adult;
[$levelHigh]=trained($slow,'craft');
check($levelHigh-80<$levelAdult-30,'U zkušeného růst zpomaluje: '.round($levelHigh-80,2));
$child=['id'=>'u6','age'=>14,'family'=>'Rod T','mother'=>'u7','father'=>null,'skills'=>array_fill_keys(array_keys(CATEGORIES),20.0)]+$template;
$parent=['id'=>'u7','age'=>40,'family'=>'Rod T','mother'=>null,'father'=>null,'skills'=>array_fill_keys(array_keys(CATEGORIES),70.0)]+$template;
$parentWeak=['id'=>'u7','skills'=>array_fill_keys(array_keys(CATEGORIES),22.0)]+$parent;
[$withTeacher]=trained($child,'craft',['u7'=>$parent]); [$withoutTeacher]=trained($child,'craft',['u7'=>$parentWeak]);
check(($withTeacher-20)>($withoutTeacher-20)*1.25,'Rodič s vyšší zkušeností urychlí učení: '.round($withTeacher-20,2).' proti '.round($withoutTeacher-20,2));
$tiny=['id'=>'u8','age'=>8,'skills'=>array_fill_keys(array_keys(CATEGORIES),5.0)]+$adult;
[$levelTiny]=trained($tiny,'craft');
check(abs($levelTiny-5.0)<1e-9,'Dítě do 12 let se neučí prací');
$full=['id'=>'u9','skills'=>array_fill_keys(array_keys(CATEGORIES),100.0)]+$adult;
[$levelFull]=trained($full,'craft');
check($levelFull<=100.0,'Zkušenost nepřekročí 100');
echo "Učení OK\n";

// 6. Milník: vyučil se (50) a mistr (80) jednou do kroniky.
[$db,$world]=freshWorld();
$apprentice=$world['people']['marek'];
$world['people']['marek']['skills']=array_fill_keys(array_keys(CATEGORIES),49.99);
trainSkill($db,$world,'marek','field',90.0);
$count=(int)$db->query("SELECT COUNT(*) FROM journal WHERE person='marek' AND title LIKE '%vyučil%'")->fetchColumn();
check($count===1,'Milník vyučení se zapíše jednou: '.$count);
trainSkill($db,$world,'marek','field',90.0);
check((int)$db->query("SELECT COUNT(*) FROM journal WHERE person='marek' AND title LIKE '%vyučil%'")->fetchColumn()===1,'Neopakuje se');
$world['people']['marek']['skills']['forest']=79.99;
trainSkill($db,$world,'marek','forest',120.0);
check((int)$db->query("SELECT COUNT(*) FROM journal WHERE person='marek' AND title LIKE '%mistrem%'")->fetchColumn()===1,'Milník mistra');
echo "Milníky OK\n";

// 7. Dědění: dítě dostane třetinu průměru rodičů a ve 12 letech aspoň základ.
[$db,$world]=freshWorld();
$mother=$world['people']['bara']; $father=$world['people']['jan'];
$world['people']['bara']['skills']=array_fill_keys(array_keys(CATEGORIES),80.0);
$world['people']['jan']['skills']=array_fill_keys(array_keys(CATEGORIES),60.0);
$child=newChild($world,'m',$world['people']['bara'],$world['people']['jan'],99);
check(abs($child['skills']['craft']-21.0)<.11,'Dítě zdědí 30 % průměru rodičů: '.$child['skills']['craft']);
$child['age']=11; $child['birthDay']=intdiv((int)$world['minute'],1440)-12*360-5; $child['skills']['craft']=4.0;
$world['people'][$child['id']]=$child;
lifeDaily($db,$world);
$grown=$world['people'][$child['id']];
check($grown['age']===12 && $grown['skills']['craft']>=12.0,'Ve 12 letech aspoň základní zkušenost: '.json_encode([$grown['age'],$grown['skills']['craft']??null]));
echo "Dědění OK\n";

// 8. Smrt jediného zkušeného řemeslníka: bez nástupce klesne nejlepší zkušenost, se zkušeným synem ne.
[$db,$world]=freshWorld();
foreach ($world['people'] as $id=>&$p) $p['skills']=array_merge(skillsOf($p),['craft'=>25.0]);
unset($p);
$world['people']['karel']['skills']['craft']=90.0;
$best=bestInCategory($world,'craft');
check($best[0]==='karel' && $best[1]===90.0,'Nejlepší řemeslník');
$withHeir=$world;
$withHeir['people']['marek']['skills']['craft']=70.0;      // zkušený syn
removePerson($db,$world,'karel','nemoc');
check(bestInCategory($world,'craft')[1]<=30,'Bez nástupce klesne nejlepší řemeslo: '.round(bestInCategory($world,'craft')[1]));
removePerson($db,$withHeir,'karel','nemoc');
check(bestInCategory($withHeir,'craft')[1]===70.0,'Zkušený syn udrží řemeslo');
$lowF=skillFactor($world['people']['anna'],'craft'); $highF=skillFactor($withHeir['people']['marek'],'craft');
check($highF>$lowF*1.3,'Rozdíl ve výnosu řemesla: '.round($lowF,2).' proti '.round($highF,2));
echo "Ohrožení řemesla OK\n";

// 9. Plánovač zohlední zkušenost (mírně) a obě větve se vyvíjejí stejně.
[$db,$world]=freshWorld();
$world['minute']=10*60+720*0;   // dopoledne
$world['minute']=(float)(intdiv((int)$world['minute'],1440)*1440+9*60);
$world['buildings']=['mlyn'=>1,'pec'=>1,'pila'=>1,'dilna'=>1];
$world['grain']=40.0; $world['flour']=12.0;
$a=$world; $b=$world;
$a['people']['anna']['skills']=array_merge(skillsOf($a['people']['anna']),['craft'=>95.0]);
$b['people']['anna']['skills']=array_merge(skillsOf($b['people']['anna']),['craft'=>5.0]);
$ca=villageCandidates($a)['anna']['mill']??null; $cb=villageCandidates($b)['anna']['mill']??null;
check($ca!==null && $cb!==null && $ca>$cb && ($ca-$cb)<25,'Zkušený mlynář má vyšší pořadí mletí, rozdíl je mírný: '.round((float)$ca-(float)$cb,1));
[$db1,$w1]=freshWorld(); [$db2,$w2]=freshWorld();
runDays($db1,$w1,20); runDays($db2,$w2,20);
check(array_map(fn($p)=>$p['skills']??null,$w1['people'])===array_map(fn($p)=>$p['skills']??null,$w2['people']),'Dovednosti se vyvíjejí deterministicky');
check(count(array_filter($w1['people'],fn($p)=>isset($p['skills'])))>=8,'Po práci mají osoby uloženou zkušenost');
echo "Plánování a determinismus OK\n";
echo "Vše prošlo\n";
