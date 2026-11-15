<?php
declare(strict_types=1);

// Nové budovy (kurník, chlív, ohrada, stáje, hřbitov, kostel): pracnost, pevné pořadí, cíle a mírné účinky.
// Použití: php tests/budovy.php   (bez modelu)
require __DIR__.'/fixture.php';

// 1. Pracnost a pořadí.
$hours=['studna'=>60,'zahrada'=>30,'sklep'=>80,'udirna'=>30,'milir'=>24,'chmelnice'=>40,'sladovna'=>80,'pivovar'=>120,'kovarna'=>100,'dul'=>200,'vinice'=>150,'lis'=>60,'kurnik'=>20,'chliv'=>30,'ohrada'=>40,'staje'=>60,'hrbitov'=>100,'kostel'=>500];
foreach ($hours as $key=>$work) check((BUILDINGS[$key]['work']??0)===$work,"Pracnost $key = $work h");
check(array_slice(BUILD_ORDER,6)===['zahrada','hospoda','kurnik','chliv','ohrada','sklep','udirna','milir','chmelnice','sladovna','pivovar','kovarna','dul','staje','hrbitov','kostel','vinice','lis','vcelin','spychar','mlekarna','rybnik','kozeluzna','tkalcovna','fara','cihelna','lazen'],'Nové budovy navazují na statek');
foreach (BUILD_ORDER as $key) check(isset(BUILDINGS[$key]) && in_array($key,array_keys(BUILDINGS),true),"Definice $key");

// 2. Pevné pořadí: nezaplatitelná stavba počká, další se před ní nezačne.
$base=function(): array {
    [$db,$world]=freshWorld();
    $world['buildings']=array_fill_keys(['mlyn','pila','pec','studna','dilna','statek','zahrada','hospoda'],1);
    $world['project']=null;
    $world['arrivedFamilies'][MILLER_FAMILY]=1; // rodina Mlynářových se zkouší zvlášť v rodina.php
    foreach (['wood','stone','planks'] as $r) $world[$r]=0.0;
    return [$db,$world];
};
[$db,$world]=$base();
check(projectOptions($world)===[],'Bez materiálu se nezačne žádná další stavba');
$needs=materialNeeds($world);
check(($needs['wood']??0)===8.0 && ($needs['planks']??0)===2.0 && !isset($needs['stone']),'Plánovač chystá materiál na kurník (první v pořadí)');
$world['wood']=30.0; $world['stone']=10.0; $world['planks']=3.0;
check(projectOptions($world)[0]==='kurnik' && !in_array('ohrada',projectOptions($world),true),'První v pořadí je kurník a ohrada (prkna 4) se před doplněním materiálu nevybere');
chooseProject($db,$world);
check($world['project']['key']==='kurnik' && $world['project']['work']===20,'Projekt: kurník, 20 h');
$world['buildings']['kurnik']=1; $world['project']=null;
$world['planks']=2.0;
check(projectOptions($world)===[],'Chlív čeká na prkna, ohrada se před ním nezačne');
$world['planks']=3.0;
check(projectOptions($world)===['chliv'],'Po doplnění prken je na řadě chlív');

// 3. Závislosti: stáje potřebují statek a pilu, kostel hřbitov a dílnu, hřbitov hospodu.
[$db,$world]=$base();
foreach (['wood','stone','planks'] as $r) $world[$r]=200.0;
foreach (['kurnik','chliv','ohrada','sklep','udirna','milir','chmelnice','sladovna','pivovar','kovarna','dul','staje'] as $key) $world['buildings'][$key]=1;
check((projectOptions($world)[0]??'')==='hrbitov' && !in_array('kostel',projectOptions($world),true),'Hřbitov je na řadě po chovatelských budovách, kostel čeká na hřbitov');
unset($world['buildings']['hospoda']);
check(!in_array('hrbitov',projectOptions($world),true),'Hřbitov potřebuje hospodu');
$world['buildings']['hospoda']=1; $world['buildings']['hrbitov']=1;
check((projectOptions($world)[0]??'')==='kostel','Kostel až po hřbitově');
unset($world['buildings']['dilna']);
check(!in_array('kostel',projectOptions($world),true),'Kostel potřebuje dílnu');

// 4. Cíle.
[$db,$world]=$base();
$goals=array_column(goalState($world),null,'key');
check(isset($goals['livestock'],$goals['faith_house']) && $goals['livestock']['current']===0.0,'Nové cíle jsou v seznamu');
$world['buildings']+=['kurnik'=>1,'ohrada'=>1,'hrbitov'=>1];
check(goalValue($world,'livestock')===2.0 && goalValue($world,'faith_house')===1.0 && goalValue($world,'public_buildings')===11.0,'Hodnoty cílů');

// 5. Účinky chovu a sena.
[$db,$world]=$base();
$world['buildings']=['statek'=>1];
check(abs(livestockBuildingBoost($world)-1.0)<1e-9 && abs(winterHayNeed($world)-1.2)<1e-9,'Bez chovatelských budov se nic nemění');
$world['buildings']+=['kurnik'=>1,'chliv'=>1,'ohrada'=>1,'staje'=>1];
check(abs(livestockBuildingBoost($world)-1.20)<1e-9,'Chlív a ohrada dají +20 % jídla z péče o zvířata (kurník má vlastní řetězec vajec)');
check(abs(winterHayNeed($world)-(1.2+.15+.4+.3))<1e-9,'V zimě je potřeba víc sena');

// 6. Stáje zrychlí svoz kamene.
$qty=function(bool $stable): float {
    [$db,$world]=freshWorld();
    $world['buildings']=$stable?['staje'=>1]:[];
    $world['quarryStone']=50.0; $world['stone']=0.0;
    $world['people']['anna']['skills']=array_fill_keys(array_keys(CATEGORIES),77.0);
    assignWork($world,'anna','haul_stone',false);
    completeWork($db,$world,'anna');
    return $world['stone'];
};
$a=$qty(false); $b=$qty(true);
check($a>0 && abs($b/$a-1.3)<0.01,'Stáje: o třetinu víc kamene na cestu ('.round($a,2).' → '.round($b,2).')');

// 7. Kostel posílí obřad.
$prayer=function(bool $church): array {
    [$db,$world]=freshWorld();
    $world['buildings']=$church?['kostel'=>1]:[];
    $p=&$world['people']['anna'];
    $p['faith']=40.0; $p['fear']=30.0; $p['mood']=50.0;
    $p['skills']=array_fill_keys(array_keys(CATEGORIES),.15/.0033); // měkký vliv zkušenosti přesně 1,0
    unset($p);
    assignWork($world,'anna','prayer',false);
    completeWork($db,$world,'anna');
    $p=$world['people']['anna'];
    return [$p['faith'],$p['fear'],$p['mood']];
};
check($prayer(false)===[42.0,28.0,55.0] && $prayer(true)===[43.0,27.0,57.0],'Kostel: víc víry, klidu a nálady z obřadu');

// 8. Hřbitov zmírní smutek a strach, zápis v kronice se liší.
$death=function(bool $cemetery): array {
    [$db,$world]=freshWorld();
    $world['buildings']=$cemetery?['hrbitov'=>1]:[];
    $family=$world['people']['karel']['family'];
    $other=null;
    foreach ($world['people'] as $id=>$p) if ($id!=='karel' && $p['family']===$family) { $other=$id; break; }
    check($other!==null,'Karel má rodinu');
    $world['people'][$other]['mood']=80.0; $world['people'][$other]['fear']=20.0;
    removePerson($db,$world,'karel','stáří');
    $entry=(string)$db->query("SELECT detail FROM journal WHERE title LIKE 'Zemřel%' ORDER BY id DESC LIMIT 1")->fetchColumn();
    return [$world['people'][$other]['mood'],$world['people'][$other]['fear'],$entry];
};
[$moodA,$fearA,$textA]=$death(false);
[$moodB,$fearB,$textB]=$death($cemetery=true);
check($moodB>$moodA && $fearB<$fearA,'Hřbitov: menší pokles nálady i strachu');
check(str_contains($textB,'hřbitově') && !str_contains($textA,'hřbitově'),'Kronika zmíní hřbitov jen po jeho postavení');

// 9. Hřbitov a kostel nemohou vyhořet.
$src=file_get_contents(dirname(__DIR__).'/crisis.php');
check(str_contains($src,"['studna','hrbitov','kostel','sklep','zahrada','chmelnice','dul','vinice','rybnik','fara']"),'Kamenné budovy, sklep, zahrada, chmelnice, rybník a fara se při požáru nevybírají');

// 10. Činnost z balíčku může vyžadovat budovu (pole building).
$dir=sys_get_temp_dir().'/minilidi-budova-'.getmypid();
mkdir($dir);
$entry=['label'=>'Sebrat vejce','work'=>'Sbírá vejce','result'=>'Vejce jsou v koši.','place'=>'yard','effect'=>'food','minutes'=>30,
    'age'=>12,'from'=>6,'until'=>20,'score'=>8,'season'=>'any'];
file_put_contents($dir.'/ok.json',json_encode(['version'=>1,'activities'=>['pack_vejce'=>$entry+['building'=>'kurnik']]]));
file_put_contents($dir.'/bad.json',json_encode(['version'=>1,'activities'=>['pack_vejce'=>$entry+['building'=>'hrad']]]));
$catalog=readActivityPack($dir.'/ok.json');
check(($catalog['pack_vejce']['building']??null)==='kurnik','Pole building se načte');
$failed=false;
try { readActivityPack($dir.'/bad.json'); } catch (RuntimeException $e) { $failed=str_contains($e->getMessage(),'Neznámá budova') && str_contains($e->getMessage(),'kurnik'); }
check($failed,'Neznámá budova se odmítne s českou zprávou');
[$db,$world]=freshWorld();
$leisure=['effect'=>'leisure','label'=>'Odpočinout','work'=>'Odpočívá','result'=>'Odpočinek.','place'=>'home','minutes'=>30,'age'=>12,'from'=>6,'until'=>22,'score'=>1,'season'=>'any'];
$world['minute']=10*60.0; $world['crisis']=null;
$world['people']['anna']['fatigue']=40.0; $world['people']['anna']['illUntil']=0;
check(packActivityAvailable($world,'anna',$leisure),'Bez požadavku na budovu je činnost k dispozici');
check(!packActivityAvailable($world,'anna',$leisure+['building'=>'kurnik']),'Bez kurníku není sběr vajec k dispozici');
$world['buildings']['kurnik']=1;
check(packActivityAvailable($world,'anna',$leisure+['building'=>'kurnik']),'Po dokončení kurníku ano');
array_map('unlink',glob($dir.'/*')); rmdir($dir);

echo "Nové budovy: pracnost, pořadí, závislosti, cíle a účinky OK\n";
