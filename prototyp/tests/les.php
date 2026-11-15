<?php
declare(strict_types=1);

// Les a doprava (fáze B): hromada dřeva, vůz, milíř a uhlí, lov drobné zvěře, udírna.
// Použití: php tests/les.php   (bez modelu)
require __DIR__.'/fixture.php';

$setDate=function(array &$world,int $month,int $day=1,int $hour=8,int $year=1): void {
    $world['calendarOriginDay']=0;
    $world['minute']=(float)((($year-1)*360+$month*30+$day-1)*1440+$hour*60);
};
$ready=function(int $month=3,array $buildings=['statek','dilna','sklep','milir','udirna']) use ($setDate): array {
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
$work=function(PDO $db,array &$world,string $id,string $key,array $extra=[]): string {
    assignWork($world,$id,$key,false);
    foreach ($extra as $k=>$v) $world['people'][$id]['task'][$k]=$v;
    $text=completeWork($db,$world,$id);
    $world['people'][$id]['task']=null;
    return $text;
};

// 1. Budovy: udírna a milíř.
check(BUILDINGS['udirna']['work']===30 && BUILDINGS['milir']['work']===24 && BUILDINGS['udirna']['needs']===['sklep'] && BUILDINGS['milir']['needs']===['dilna'],'Udírna 30 h po sklepě, milíř 24 h po dílně');
check(in_array('udirna',BUILD_PREPAY,true) && in_array('milir',BUILD_PREPAY,true),'Obě se stavějí ze zásob');
foreach (['udirna','milir'] as $key) foreach (BUILDINGS[$key]['cost'] as $res=>$amount) check(isset(RESOURCES[$res]),"Cena $key používá jen suroviny, které vesnice získá ($res)");

// 2. Pokácené dřevo leží u lesa a musí se svézt.
[$db,$world]=$ready();
$wood0=$world['wood'];
$text=$work($db,$world,'anna','felling');
check($world['forestWood']>2 && $world['wood']===$wood0 && str_contains($text,'lesa'),'Kácení naplní hromadu u lesa, ne sklad ('.round($world['forestWood'],1).')');
$pile=$world['forestWood'];
$world['forestWood']=FOREST_PILE_MAX+1;
check(!workAvailable($world,'anna','felling'),'Při plné hromadě se dál nekácí');
$world['forestWood']=20.0;
check(workAvailable($world,'anna','haul_wood'),'Hromada je k odvozu');
$text=$work($db,$world,'anna','haul_wood');
$moved=$world['wood']-$wood0;
check($moved>3 && $moved<=WOOD_HAND_TRIP*1.5+.01 && abs($world['forestWood']-(20-$moved))<1e-9 && str_contains($text,'svezeno'),'Bez vozu se veze nejvýš nůše ('.round($moved,1).')');
$world['forestWood']=0.0;
check(!workAvailable($world,'anna','haul_wood'),'Prázdná hromada se nevozí');
$world['forestWood']=10.0;
$world['minute']+=1440; forestDaily($db,$world);
check(abs($world['forestWood']-10*FOREST_PILE_KEEP)<1e-9,'Pokácené dřevo venku se trochu kazí');

// 3. Vůz: spouštěče, výroba, nosnost, souběžné cesty.
[$db,$world]=$ready(3,['statek']);
$world['forestWood']=40.0;
check(!workAvailable($world,'anna','build_cart'),'Bez dílny se vůz nevyrábí');
[$db,$world]=$ready();
check(!workAvailable($world,'anna','build_cart'),'Bez hromad se vůz nevyrábí');
$world['forestWood']=CART_WOOD_THRESHOLD;
check(workAvailable($world,'anna','build_cart'),'Hodně dřeva u lesa spustí výrobu vozu');
$world['forestWood']=0.0; $world['quarryStone']=CART_STONE_THRESHOLD;
check(workAvailable($world,'anna','build_cart'),'Hodně kamene u lomu spustí výrobu vozu');
$world['planks']=2.0;
check(!workAvailable($world,'anna','build_cart'),'Bez prken se vůz nezačne');
$world['planks']=30.0; $world['tools']=0.0;
check(!workAvailable($world,'anna','build_cart'),'Bez nářadí se vůz nezačne');
$world['tools']=5.0;
$planks0=$world['planks']; $wood1=$world['wood']; $tools0=$world['tools'];
$rounds=0;
while ($world['carts']===0 && $rounds<40) { ++$rounds; $work($db,$world,['anna','karel','jan'][$rounds%3],'build_cart'); }
check($world['carts']===1 && $world['cartProject']===null,'Po práci je vůz hotový');
check($rounds>=7 && $rounds<=25,'Vůz stojí zhruba desítky hodin práce ('.$rounds.' úkolů po dvou hodinách)');
check(abs($planks0-$world['planks']-CART_COST['planks'])<1e-9 && abs($wood1-$world['wood']-CART_COST['wood'])<1e-9 && abs($tools0-$world['tools']-CART_COST['tools'])<1e-9,'Materiál se zaplatil jednou ze zásob');
check((int)$db->query("SELECT COUNT(*) FROM journal WHERE title='Hotov nový vůz'")->fetchColumn()===1,'Kronika zapíše nový vůz a kdo ho vyráběl');
check(cartCapacity($world)===CART_BASE,'Vůz bez zvířat veze '.CART_BASE);
$world['buildings']['ohrada']=1; check(cartCapacity($world)===CART_OX,'S voly z ohrady '.CART_OX);
$world['buildings']['staje']=1; check(cartCapacity($world)===CART_HORSE,'S koňmi ze stájí '.CART_HORSE);
$world['carts']=CART_MAX; $world['cartProject']=null;
check(!workAvailable($world,'anna','build_cart'),'Víc než '.CART_MAX.' vozy se nevyrábí');
// Cesty vozem a bez něj.
[$db,$world]=$ready();
$world['carts']=1; $world['forestWood']=50.0; $world['quarryStone']=30.0;
assignWork($world,'anna','haul_wood',false);
check(!empty($world['people']['anna']['task']['cart']) && !cartFree($world),'První cesta dostane vůz, jiný není volný');
assignWork($world,'karel','haul_stone',false);
check(empty($world['people']['karel']['task']['cart']),'Druhá cesta jede bez vozu (trakařem)');
$woodBefore=$world['wood'];
completeWork($db,$world,'anna');
$gainCart=$world['wood']-$woodBefore;
check($gainCart>CART_BASE*.6 && $gainCart<=CART_BASE*1.5+.01,'S vozem se veze podstatně víc dřeva ('.round($gainCart,1).')');
$stoneBefore=$world['stone'];
completeWork($db,$world,'karel');
$gainBarrow=$world['stone']-$stoneBefore;
check($gainBarrow<=4.0*1.5+.01 && $gainBarrow>=2,'Trakař veze do čtyř ('.round($gainBarrow,1).')');
[$db,$world]=$ready(); $world['carts']=1; $world['quarryStone']=40.0;
assignWork($world,'anna','haul_stone',false);
check(workAvailable($world,'karel','haul_stone'),'S vozem mohou jet dvě cesty se kamenem');
assignWork($world,'karel','haul_stone',false);
check(!workAvailable($world,'jan','haul_stone'),'Třetí cesta už není k dispozici');
[$db,$world]=$ready(); $world['carts']=0; $world['quarryStone']=40.0;
assignWork($world,'anna','haul_stone',false);
check(!workAvailable($world,'karel','haul_stone'),'Bez vozu jede se kamenem jen jedna cesta (jako dosud)');

// 4. Milíř a dřevěné uhlí.
[$db,$world]=$ready();
check(workAvailable($world,'anna','load_kiln'),'Milíř se dá naplnit');
$text=$work($db,$world,'anna','load_kiln');
check($world['kiln']['state']==='burning' && abs($world['wood']-(100-KILN_WOOD))<1e-9 && str_contains($text,'zapálený'),'Milíř hoří a spotřeboval '.KILN_WOOD.' dřeva');
check((int)$db->query("SELECT COUNT(*) FROM journal WHERE title='Milíř zapálen'")->fetchColumn()===1,'Kronika zapíše zapálení');
check(!workAvailable($world,'karel','load_kiln') && workAvailable($world,'karel','watch_kiln'),'Hořící milíř se nenaplní znovu, ale hlídá se');
// S dohledem je celá zásoba.
$coal0=$world['charcoal'];
$world['minute']=$world['kiln']['end']; $world['kiln']['lastWatch']=$world['minute']-60;
forestDaily($db,$world);
check(abs($world['charcoal']-$coal0-KILN_YIELD)<1e-9 && $world['kiln']['state']==='idle','Hlídaný milíř dá '.KILN_YIELD.' uhlí');
// Bez dohledu se uhlí ztrácí.
[$db,$world]=$ready();
$work($db,$world,'anna','load_kiln');
$start=(int)$world['minute']; $loss=0.0;
for ($d=1;$d<=KILN_DAYS+1;$d++) {
    $world['minute']=(float)(intdiv($start,1440)*1440+$d*1440);
    forestDaily($db,$world);
    if ($world['kiln']['state']==='idle') break;
}
check(($world['counters']['kilnFire']??0)>0 || ($world['charcoal']>0 && $world['charcoal']<KILN_YIELD*.5),'Bez dohledu uhlí ubylo nebo hranice vzplála ('.round($world['charcoal'],1).')');
// Požár: najdeme den, kdy hod vyjde.
$fireDay=null; for ($d=300;$d<3000;$d++) if (weatherNoise($d,'milir')<KILN_FIRE_CHANCE) { $fireDay=$d; break; }
[$db,$world]=$ready(); $world['minute']=(float)($fireDay*1440); $world['kiln']=['state'=>'burning','end'=>$fireDay*1440+9999,'lastWatch'=>$fireDay*1440-2000,'loss'=>0.0];
forestDaily($db,$world);
check($world['kiln']['state']==='idle' && ($world['counters']['kilnFire']??0)===1 && $world['charcoal']==0.0 && (int)$db->query("SELECT COUNT(*) FROM journal WHERE title='Milíř vzplál'")->fetchColumn()===1,'Nehlídaný milíř může vzplanout a uhlí není');
// Sezóna a strop zásoby.
[$db,$world]=$ready(9);
check(!workAvailable($world,'anna','load_kiln'),'V zimě se nepálí');
[$db,$world]=$ready(); $world['charcoal']=CHARCOAL_TARGET;
check(!workAvailable($world,'anna','load_kiln'),'Při dostatku uhlí se nepálí');
[$db,$world]=$ready(3,['statek','dilna']);
check(!workAvailable($world,'anna','load_kiln'),'Bez milíře se nepálí');
$world['buildings']['milir']=1; $world['wood']=10.0;
check(!workAvailable($world,'anna','load_kiln'),'Bez dřeva se nepálí');
// Uhlí: kalené nářadí, nouzové topení, zimní příprava.
[$db,$world]=$ready();
$world['charcoal']=5.0; $world['tools']=0.0; $world['planks']=20.0; $world['stone']=10.0;
$work($db,$world,'anna','craft');
$withCoal=$world['tools'];
check($world['charcoal']===4.0,'Kalení spotřebuje kus uhlí');
$world['charcoal']=0.0; $world['tools']=0.0;
$work($db,$world,'anna','craft');
check($withCoal>$world['tools']*1.4,'S uhlím je z jedné práce víc nářadí ('.round($withCoal,2).' proti '.round($world['tools'],2).')');
[$db,$world]=$ready(9);
$world['wood']=0.0; $world['charcoal']=0.0; $p0=winterPrep($world)['fuel'];
$world['charcoal']=10.0; $p1=winterPrep($world)['fuel'];
check($p1>$p0 && abs($p1-30/(WINTER_WOOD_PER_DAY*WINTER_FUEL_DAYS))<.01,'Uhlí se počítá do zimního topiva (třikrát dřevo)');
[$db,$world]=$ready(9);
$world['wood']=0.0; $world['charcoal']=5.0; $world['minute']=(float)(intdiv((int)$world['minute'],1440)*1440+1440-15);
$before=$world['charcoal'];
advance($db,$world,15);
check($world['charcoal']<$before && $world['charcoal']>=0,'Když dřevo došlo, zatopí se uhlím ('.round($before-$world['charcoal'],2).')');

// 5. Lov drobné zvěře, jen v sezóně a bez velkých ztrát.
[$db,$world]=$ready(8);
check(workAvailable($world,'anna','hunt_small'),'V listopadu se loví drobná zvěř');
[$db,$world]=$ready(3);
check(!workAvailable($world,'anna','hunt_small'),'V létě se neloví');
[$db,$world]=$ready(8);
$stock0=$world['gameStock'];
$text=$work($db,$world,'anna','hunt_small');
check($world['game']>1 && $world['game']<=GAME_TRIP*1.5 && $world['gameStock']<$stock0 && str_contains($text,'zvěřiny'),'Z léček je zvěřina a zásoba v lese ubývá ('.round($world['game'],1).')');
$world['gameStock']=GAME_STOCK_MAX; $world['game']=0.0; $work($db,$world,'anna','hunt_small'); $full=$world['game'];
$world['gameStock']=20.0; $world['game']=0.0; $work($db,$world,'anna','hunt_small'); $thin=$world['game'];
check($thin<$full*.4,'Při přelovu je úlovek menší ('.round($thin,2).' proti '.round($full,2).')');
$world['gameStock']=5.0;
check(!workAvailable($world,'anna','hunt_small'),'Vylovený les se neloví');
$world['minute']+=1440; $before=$world['gameStock']; forestDaily($db,$world);
check(abs($world['gameStock']-($before+GAME_REGEN))<1e-9,'Zvěř se za den obnoví o '.GAME_REGEN);
$world['gameStock']=GAME_STOCK_MAX-.1; forestDaily($db,$world);
check($world['gameStock']===GAME_STOCK_MAX,'Zásoba zvěře má strop');
// Kažení čerstvé a uzené zvěřiny.
[$db,$world]=$ready(8);
$world['game']=10.0; $world['meatSmoked']=10.0;
forestDaily($db,$world);
check(abs($world['game']-10*GAME_KEEP)<1e-9 && abs($world['meatSmoked']-10*MEAT_KEEP)<1e-9,'Čerstvá zvěřina se kazí 20 %, uzená 0,4 %');
// Udírna.
$world['game']=12.0; $world['meatSmoked']=0.0; $wood=$world['wood'];
check(workAvailable($world,'anna','smoke_meat'),'S udírnou a zvěřinou se udí');
$text=$work($db,$world,'anna','smoke_meat');
check($world['meatSmoked']>3 && $world['game']<12 && abs($world['meatSmoked']-(12-$world['game'])*SMOKE_YIELD)<1e-9 && $world['wood']<$wood && str_contains($text,'uzeného'),'Udírna promění zvěřinu na uzené maso s 10 % ztrátou a spotřebuje dřevo');
[$db,$world]=$ready(8,['statek','dilna']); $world['game']=12.0;
check(!workAvailable($world,'anna','smoke_meat'),'Bez udírny se neudí');

// 6. Jídlo: zvěřina se jí dřív, uzené maso v zimě část jídla.
[$db,$world]=$ready(9);
unset($world['buildings']['udirna']);
$world['game']=4.0; $world['meatSmoked']=20.0; $world['food']=100.0; $world['eggs']=0.0;
$r0=foodReserve($world);
check(abs($r0-(100+$world['grain']+$world['flour']*HEARTH_FLOUR_FOOD+24*GAME_FOOD))<1e-9,'Rezerva jídla počítá zvěřinu i uzené maso');
check(drawFood($world,3.0) && abs($world['game']-(4-3/GAME_FOOD))<1e-9 && $world['meatSmoked']===20.0,'Bez udírny se čerstvá zvěřina jí jako první, uzené zůstává');
$world['buildings']['udirna']=1; $world['game']=4.0;
check(drawFood($world,10.0) && abs((4-$world['game'])*GAME_FOOD-10*.15)<1e-9,'S udírnou se čerstvé jí jen 15 % jídla a zbytek se uzuje');
unset($world['buildings']['udirna']);
$world['game']=0.0; $world['meatSmoked']=20.0;
drawFood($world,10.0);
check(abs((20-$world['meatSmoked'])*GAME_FOOD-10*MEAT_MEAL_SHARE)<1e-9,'Uzené maso tvoří v zimě 10 % jídla');
[$db,$world]=$ready(4);
$world['game']=0.0; $world['meatSmoked']=20.0; $world['food']=100.0;
drawFood($world,10.0);
check($world['meatSmoked']===20.0,'V létě se uzené maso šetří');
$world['food']=0.0; $world['grain']=0.0; $world['flour']=0.0; $world['eggs']=0.0; $world['veg']=0.0; $world['kraut']=0.0; $world['legumes']=0.0;
check(drawFood($world,3.0) && $world['meatSmoked']<20,'V nouzi se uzené maso sní i v létě');

// 7. Plánovač nabízí práce a dřevo pro stavbu se vozí z hromady.
[$db,$world]=$ready(3);
$world['forestWood']=25.0;
$offered=[];
foreach (villageCandidates($world) as $per) foreach (array_keys($per) as $k) $offered[$k]=true;
check(isset($offered['haul_wood']) && isset($offered['load_kiln']),'Plánovač nabízí svoz dřeva a pálení milíře');
[$db,$world]=$ready(3,['mlyn','pila','pec','studna','dilna','statek']);
$world['wood']=0.0; $world['forestWood']=25.0; $world['stone']=30.0; $world['planks']=30.0;
chooseProject($db,$world);
applyVillagePlan($db,$world,prepareVillagePlan($db,$world,false),false);
$firsts=array_count_values(array_column($world['plan'],'first'));
check(($firsts['haul_wood']??0)>=1,'Když stavba čeká na dřevo a u lesa leží, vozí se ('.json_encode($firsts).')');

// 8. Starý uložený stav.
[$db,$world]=$ready();
foreach (['forestWood','charcoal','game','meatSmoked','gameStock','carts','cartProject','kiln'] as $k) unset($world[$k]);
$old=decodeWorld(encode($world));
check($old['forestWood']===0.0 && $old['carts']===0 && $old['kiln']['state']==='idle' && $old['gameStock']===70.0 && $old['cartProject']===null,'Starý stav převezme nové klíče');
forestDaily($db,$old);
check($old['gameStock']>70.0,'Starý stav projde denním během');

// 9. Běh celého roku: žádná varování, zásoby dřeva se svážejí, uhlí se pálí.
$seen=[]; set_error_handler(function($no,$msg,$file,$line) use (&$seen){ $seen[]=basename($file).':'.$line.' '.$msg; return true; });
[$db,$world]=$ready(0,['mlyn','pila','pec','studna','dilna','statek','zahrada','sklep','udirna','milir']);
$world['wood']=120.0; $world['planks']=40.0;
$setDate($world,0);
$coalMax=0.0; $cartDay=null; $hauled=false; $gameSeen=0.0;
for ($d=1;$d<=360;$d++) {
    runDays($db,$world,1.0,false);
    $coalMax=max($coalMax,$world['charcoal']);
    if ($cartDay===null && $world['carts']>0) $cartDay=$d;
    $gameSeen=max($gameSeen,$world['game']+$world['meatSmoked']);
}
restore_error_handler();
check(!$seen,'Bez varování PHP: '.implode('; ',array_slice($seen,0,3)));
check($world['wood']>0 && count($world['people'])>=10,'Dřevo ve skladu zůstává a vesnice přežije rok ('.round($world['wood']).')');
check($coalMax>=KILN_YIELD*.5,'Za rok se vypálilo uhlí (nejvíc zásoba '.round($coalMax,1).')');
check($gameSeen>1,'Za rok se ulovila a uzená zvěřina (nejvíc '.round($gameSeen,1).')');
echo 'Les a doprava OK (vůz '.($cartDay!==null?'od dne '.$cartDay:'nevznikl').", uhlí do ".round($coalMax,1).", zvěřina do ".round($gameSeen,1).")\n";
