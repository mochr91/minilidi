<?php
declare(strict_types=1);

// Fáze D: kovárna, důl, železné nářadí, obchodník a úrok vrchnosti.
// Použití: php tests/kovarna.php   (bez modelu)
require __DIR__.'/fixture.php';

$setDate=function(array &$world,int $month,int $day=1,int $hour=8,int $year=1): void {
    $world['calendarOriginDay']=0;
    $world['minute']=(float)((($year-1)*360+$month*30+$day-1)*1440+$hour*60);
};
$ready=function(int $month=3,array $buildings=['statek','dilna','sklep','hospoda','milir','kovarna','dul','pivovar','kurnik']) use ($setDate): array {
    [$db,$world]=freshWorld();
    $setDate($world,$month);
    foreach ($buildings as $b) $world['buildings'][$b]=1;
    $world['arrivedFamilies'][MILLER_FAMILY]=1;
    $world['food']=400.0; $world['grain']=200.0; $world['hay']=50.0; $world['wood']=100.0; $world['stone']=20.0; $world['planks']=30.0; $world['tools']=5.0;
    $world['project']=null; $world['houseProject']=null;
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

// 1. Budovy: kovárna a důl ze zásob vesnice (uhlí vypálí milíř), pořadí za pivovarem.
check(BUILDINGS['kovarna']['work']===100 && BUILDINGS['dul']['work']===200 && BUILDINGS['kovarna']['needs']===['dilna','milir'] && BUILDINGS['dul']['needs']===['kovarna'],'Kovárna 100 h po dílně a milíři, důl 200 h po kovárně');
foreach (['kovarna','dul'] as $key) { check(in_array($key,BUILD_PREPAY,true),"$key se staví ze zásob"); foreach (BUILDINGS[$key]['cost'] as $res=>$amount) check(isset(RESOURCES[$res]),"Cena $key: $res umí vesnice získat"); }
$i=array_search('pivovar',BUILD_ORDER,true);
check(array_slice(BUILD_ORDER,$i,3)===['pivovar','kovarna','dul'],'Pořadí: pivovar, kovárna, důl');
[$db,$world]=$ready(3,['statek','dilna','milir']);
$world['buildings']=array_fill_keys(array_slice(BUILD_ORDER,0,array_search('kovarna',BUILD_ORDER,true)),1);
$world['stone']=20.0; $world['charcoal']=0.0;
check(!in_array('kovarna',projectOptions($world),true) && (materialNeeds($world)['charcoal']??0)===4.0,'Bez uhlí se kovárna nezačne a plánovač ho chce');
$world['charcoal']=4.0;
check(projectOptions($world)[0]==='kovarna','S uhlím je kovárna na řadě');

// 2. Těžba rudy: jen s dolem, do zásoby, dokud je železného nářadí málo; těžká práce.
[$db,$world]=$ready();
check(workAvailable($world,'anna','mine_ore') && !workAvailable($world,'tomas','mine_ore'),'Rudu těží dospělí');
check(in_array('mine_ore',HEAVY_WORK,true),'Těžba je těžká práce');
$text=$work($db,$world,'anna','mine_ore');
check($world['ore']>2 && str_contains($text,'rudy'),'Z dolu je ruda ('.$world['ore'].')');
$world['ore']=ORE_TARGET;
check(!workAvailable($world,'anna','mine_ore'),'Při plné zásobě rudy se netěží');
$world['ore']=0.0; $world['ironTools']=ironToolTarget($world);
check(!workAvailable($world,'anna','mine_ore'),'Když je železného nářadí dost, netěží se');
$world['ironTools']=0.0; unset($world['buildings']['dul']);
check(!workAvailable($world,'anna','mine_ore'),'Bez dolu se netěží');

// 3. Tavba a kování: ruda a uhlí na železo, železo a uhlí na železné nářadí.
[$db,$world]=$ready();
$world['ore']=10.0; $world['charcoal']=10.0;
check(workAvailable($world,'anna','smelt_iron') && !workAvailable($world,'anna','forge_tools'),'Nejdřív tavba, kovat není z čeho');
$text=$work($db,$world,'anna','smelt_iron');
check($world['ore']===10.0-SMELT_ORE && $world['charcoal']===10.0-SMELT_CHARCOAL && $world['iron']===SMELT_IRON && str_contains($text,'železa'),'Tavba spotřebuje rudu a uhlí a dá železo');
check(workAvailable($world,'anna','forge_tools'),'Ze železa se dá kovat');
$text=$work($db,$world,'anna','forge_tools');
check($world['iron']===0.0 && $world['charcoal']===10.0-SMELT_CHARCOAL-FORGE_CHARCOAL && $world['ironTools']===1.0 && str_contains($text,'železného nářadí'),'Kování dá kus železného nářadí');
$world['charcoal']=1.0; $world['ore']=10.0; $world['iron']=1.0;
check(!workAvailable($world,'anna','smelt_iron') && !workAvailable($world,'anna','forge_tools'),'Bez uhlí se netaví ani nekuje');
$world['charcoal']=10.0; $world['iron']=IRON_STOCK_MAX;
check(!workAvailable($world,'anna','smelt_iron'),'Železa se drží jen malá zásoba');
unset($world['buildings']['kovarna']);
check(!workAvailable($world,'anna','forge_tools'),'Bez kovárny se nekuje');

// 4. Železné nářadí zrychlí práci a pomalu se opotřebuje; uhlí se s kovárnou pálí víc.
[$db,$world]=$ready();
$need=max(1,(int)ceil(adultCount($world)/3));
$world['tools']=(float)$need; $world['ironTools']=0.0;
$plain=toolFactor($world);
$world['ironTools']=(float)$need;
check(abs($plain-1.06)<1e-9 && abs(toolFactor($world)-(1.06+IRON_TOOL_BONUS))<1e-9,'Železné nářadí přidá '.(IRON_TOOL_BONUS*100).' % výkonu');
$world['tools']=0.0;
check(abs(toolFactor($world)-(1.06+IRON_TOOL_BONUS))<1e-9,'Železné nářadí nahradí i běžné');
$world['ironTools']=10.0; forgeDaily($db,$world);
check(abs($world['ironTools']-10*IRON_TOOLS_KEEP)<1e-9,'Opotřebení železného nářadí');
check(charcoalTarget($world)===24.0,'S kovárnou se pálí uhlí do 24');
unset($world['buildings']['kovarna']);
check(charcoalTarget($world)===CHARCOAL_TARGET,'Bez kovárny do 12');

// 5. Úrok: sv. Michal a sv. Martin, jednou za rok, slepicím zůstane krmivo, nedoplatek děsí.
[$db,$world]=$ready();
$world['hens']=6.0; $world['eggs']=10.0; $world['grain']=200.0;
$setDate($world,6,28); tributeDaily($db,$world);
check($world['grain']===200.0,'Den před sv. Michalem se neplatí');
$setDate($world,6,29); tributeDaily($db,$world);
$due=(float)ceil(adultCount($world)*2.5);
check(abs($world['grain']-(200.0-$due))<1e-9 && $world['eggs']===6.0 && $world['hens']===6.0,'Sv. Michal: obilí podle dospělých ('.$due.') a čtyři vejce');
tributeDaily($db,$world);
check(abs($world['grain']-(200.0-$due))<1e-9,'Úrok se v témže roce neplatí dvakrát');
check((int)$db->query("SELECT COUNT(*) FROM journal WHERE title LIKE 'Odevzdán úrok%'")->fetchColumn()===1 && count($world['tribute']['log'])===1,'Kronika a záznam úroku');
$setDate($world,8,11); tributeDaily($db,$world);
check($world['hens']===5.0 && $world['eggs']===0.0,'Sv. Martin: slepice a šest vajec');
$setDate($world,6,29,8,2); $grain=$world['grain']; tributeDaily($db,$world);
check($world['grain']<$grain,'Další rok se platí znovu');
[$db,$world]=$ready();
$world['hens']=10.0; $world['grain']=henGrainReserve($world)+5.0; $world['eggs']=0.0;
$mood=$world['people']['anna']['mood']; $fear=$world['people']['anna']['fear'];
$setDate($world,6,29); tributeDaily($db,$world);
check(abs($world['grain']-henGrainReserve($world))<1e-9,'Krmivo pro slepice se vrchnosti nedává');
check($world['people']['anna']['mood']<$mood && $world['people']['anna']['fear']>$fear && (int)$db->query("SELECT COUNT(*) FROM journal WHERE title LIKE '%nepodařilo odevzdat%'")->fetchColumn()===1,'Nedoplatek: strach a horší nálada');

// 6. Obchodník: jednou za měsíc, prodá jen tolik přebytku, kolik je třeba, a koupí kotel, sůl, železo a látku.
[$db,$world]=$ready();
$world['charcoal']=90.0; $world['hides']=10.0; $world['beer']=200.0;
$setDate($world,3,14); tradeDaily($db,$world);
check(($world['trade']['visits']??0)===0,'Obchodník přijíždí patnáctého');
$setDate($world,3,15); tradeDaily($db,$world);
check($world['trade']['visits']===1 && !empty($world['trade']['kettle']) && $world['salt']===12.0 && $world['iron']===IRON_STOCK_MAX && $world['cloth']>=floor(count($world['people'])*.5),'Koupil se kotel, sůl, železo a látka');
check($world['charcoal']>=charcoalTarget($world) && $world['charcoal']<90.0,'Prodalo se uhlí nad zásobu ('.$world['charcoal'].')');
check($world['trade']['credit']<3.0,'Nezbyly zbytečné groše ('.$world['trade']['credit'].')');
check($world['beer']===200.0 && $world['hides']===10.0,'Neprodalo se víc, než bylo třeba');
tradeDaily($db,$world);
check($world['trade']['visits']===1,'V témže měsíci jen jednou');
$setDate($world,4,15); $coal=$world['charcoal']; tradeDaily($db,$world);
check($world['trade']['visits']===2 && $world['charcoal']===$coal,'Další měsíc: nic nechybí, nic se neprodá');
[$db,$world]=$ready(3,['statek']);
$world['charcoal']=60.0; $setDate($world,3,15); tradeDaily($db,$world);
check(($world['trade']['visits']??0)===0,'Bez hospody obchodník nepřijede');
[$db,$world]=$ready();
$world['calendarOriginDay']=0; $world['minute']=(float)(14*1440+480); tradeDaily($db,$world);
check(($world['trade']['visits']??0)===0,'Před '.TRADE_FIRST_DAY.'. dnem hry nepřijede');
[$db,$world]=$ready();
$setDate($world,3,15); tradeDaily($db,$world);
check($world['trade']['visits']===1 && $world['salt']===0.0 && empty($world['trade']['kettle']),'Bez přebytku se nic nekoupí');

// 7. Účinky: kotel zvedne várku, sůl zelí, látka chrání v zimě, lov dává kožky.
[$db,$world]=$ready(7);
$world['trade']['kettle']=true; $world['malt']=40.0; $world['hops']=5.0;
$rounds=0; $work($db,$world,'anna','brew_beer');
while ($world['brewProject']!==null && $rounds<30) { ++$rounds; $work($db,$world,['anna','karel','jan'][$rounds%3],'brew_beer'); }
check(abs($world['beerYoung']-BREW_BEER*KETTLE_YIELD)<1e-9,'S měděným kotlem dá várka o čtvrtinu víc ('.$world['beerYoung'].')');
[$db,$world]=$ready(7);
$world['veg']=20.0; $world['salt']=0.0;
$work($db,$world,'anna','pickle'); $plain=$world['kraut']/(20-$world['veg']);
$world['veg']=20.0; $world['kraut']=0.0; $world['salt']=3.0;
$work($db,$world,'anna','pickle'); $salted=$world['kraut']/(20-$world['veg']);
check(abs($plain-SALT_YIELD_NONE)<1e-6 && abs($salted-SALT_YIELD)<1e-6 && $world['salt']===2.0,'Se solí vyjde zelí líp (0,9 proti 0,72) a sůl ubude');
[$db,$world]=$ready(9);
$world['cloth']=0.0; check(!clothed($world),'Bez látky nejsou teplé šaty');
$world['cloth']=count($world['people'])*.3; check(clothed($world),'S látkou ano');
$cloth=$world['cloth']; $world['minute']+=1440; winterDaily($db,$world);
check($world['cloth']<$cloth,'Látka se v zimě opotřebovává');
[$db,$world]=$ready(8);
$world['gameStock']=80.0; $work($db,$world,'anna','hunt_small');
check($world['hides']>0,'Lov dává i kožky ('.$world['hides'].')');

// 8. Plánovač, cíle a starý stav.
[$db,$world]=$ready();
$world['ore']=10.0; $world['charcoal']=10.0; $world['iron']=1.0;
$offered=[]; foreach (villageCandidates($world) as $list) foreach ($list as $key=>$score) $offered[$key]=1;
check(isset($offered['mine_ore'],$offered['smelt_iron'],$offered['forge_tools']),'Plánovač nabízí těžbu, tavbu a kování');
check(isset(TASK_STEMS['mine_ore'],TASK_STEMS['smelt_iron'],TASK_STEMS['forge_tools']),'Texty plánů znají nové úkoly');
$brief=developmentBrief($world,materialNeeds($world));
check(isset($brief['sklad']['ruda'],$brief['sklad']['zelezo'],$brief['sklad']['zelezne_naradi'],$brief['sklad']['sul'],$brief['sklad']['latka']),'Přehled pro plánování zná železo a obchod');
$world['ironTools']=3.0;
check(goalValue($world,'smithy')===2.0 && goalValue($world,'iron_tools')===3.0,'Cíle kovárny');
$old=$world;
foreach (['ore','iron','ironTools','salt','cloth','hides','trade','tribute'] as $k) unset($old[$k]);
$restored=decodeWorld(encode($old));
check($restored['ore']===0.0 && $restored['ironTools']===0.0 && $restored['trade']['visits']===0 && $restored['tribute']['log']===[],'Starý uložený stav dostane výchozí hodnoty');
echo "Kovárna, důl, železné nářadí, úrok a obchodník OK\n";
