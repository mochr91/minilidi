<?php
declare(strict_types=1);

// Zima a zásoby (fáze A): kažení po druzích, zahrada, sklep a zelí, byliny a ošetřování, pestrá strava, příprava na zimu.
// Použití: php tests/zima.php   (bez modelu)
require __DIR__.'/fixture.php';

// Pomocník: nastaví kalendář na měsíc (0 = březen až 11 = únor), den v měsíci a hodinu.
$setDate=function(array &$world,int $month,int $day=1,int $hour=8,int $year=1): void {
    $world['calendarOriginDay']=0;
    $world['minute']=(float)((($year-1)*360+$month*30+$day-1)*1440+$hour*60);
};
$ready=function(int $month=2,array $buildings=['statek','zahrada','sklep']) use ($setDate): array {
    [$db,$world]=freshWorld();
    $setDate($world,$month);
    foreach ($buildings as $b) $world['buildings'][$b]=1;
    $world['arrivedFamilies'][MILLER_FAMILY]=1;
    $world['food']=400.0; $world['grain']=100.0; $world['hay']=50.0; $world['wood']=200.0;
    $world['project']=null; $world['houseProject']=null;
    foreach ($world['people'] as &$p) { $p['fatigue']=10.0; $p['hunger']=10.0; $p['mood']=80.0; $p['illUntil']=0; }
    unset($p);
    return [$db,$world];
};
$work=function(PDO $db,array &$world,string $id,string $key,?string $other=null): string {
    assignWork($world,$id,$key,false);
    if ($other!==null) $world['people'][$id]['task']['other']=$other;
    $text=completeWork($db,$world,$id);
    $world['people'][$id]['task']=null;
    return $text;
};

// 1. Nové budovy a pořadí.
check(BUILDINGS['zahrada']['work']===30 && BUILDINGS['sklep']['work']===80 && BUILDINGS['sklep']['cost']['stone']===12,'Zahrada 30 h, sklep 80 h a 12 kamene');
check(array_search('zahrada',BUILD_ORDER,true)>array_search('statek',BUILD_ORDER,true) && array_search('sklep',BUILD_ORDER,true)>array_search('zahrada',BUILD_ORDER,true),'Zahrada po statku, sklep po zahradě');
check(in_array('zahrada',BUILD_PREPAY,true) && in_array('sklep',BUILD_PREPAY,true),'Obě se stavějí ze zásob, až je materiál na skladě');
foreach (['zahrada','sklep'] as $key) {
    foreach (BUILDINGS[$key]['cost'] as $res=>$amount) check(isset(RESOURCES[$res]),"Cena $key používá jen suroviny, které vesnice umí získat ($res)");
}

// 2. Kažení po druzích a sklep.
[$db,$world]=$ready(7);
foreach (['veg','kraut','legumes','herbs','herbsDry'] as $k) $world[$k]=100.0;
winterDaily($db,$world);
check(abs($world['veg']-100*VEG_KEEP_CELLAR)<1e-6 && abs($world['kraut']-100*KRAUT_KEEP_CELLAR)<1e-6,'Se sklepem se zelenina kazí 0,8 % a zelí 0,1 %');
check(abs($world['legumes']-100*LEGUME_KEEP)<1e-6 && abs($world['herbs']-100*HERB_KEEP)<1e-6 && abs($world['herbsDry']-100*HERB_DRY_KEEP)<1e-6,'Luštěniny drží, čerstvé byliny se kazí 4 %, sušené 0,2 %');
[$db,$world]=$ready(7,['statek','zahrada']);
$world['veg']=100.0; $world['kraut']=100.0;
winterDaily($db,$world);
check(abs($world['veg']-100*VEG_KEEP)<1e-6 && abs($world['kraut']-100*KRAUT_KEEP)<1e-6,'Bez sklepa se zelenina kazí 3 % a zelí 0,3 %');
check($world['veg']<100*VEG_KEEP_CELLAR,'Sklep je znatelně lepší');

// 3. Zahrada: sázení, péče, úroda v srpnu, sklizeň a mráz.
[$db,$world]=$ready(2);
$people=count($world['people']);
check(workAvailable($world,'anna','sow_garden'),'V květnu se sází');
check(!workAvailable($world,'anna','harvest_garden') && !workAvailable($world,'anna','tend_garden'),'Bez zasetí není co okopávat ani sklízet');
winterDaily($db,$world);
for ($i=0;$i<GARDEN_SOW_TASKS;$i++) $work($db,$world,'anna','sow_garden');
check($world['garden']['sown']>.9 && $world['garden']['sown']<=1.0,'Šest úkolů zaseje zahradu ('.round($world['garden']['sown'],2).')');
$sown=$world['garden']['sown'];
$setDate($world,3);
check(workAvailable($world,'anna','tend_garden'),'V červnu se okopává');
for ($i=0;$i<GARDEN_TEND_TASKS;$i++) $work($db,$world,'anna','tend_garden');
$care=$world['garden']['care'];
check($care>.9,'Péče zahradu ošetří ('.round($care,2).')');
$setDate($world,5);
winterDaily($db,$world);
$crop=$world['garden']['crop'];
check($crop>GARDEN_PER_PERSON*$people*.8 && $crop<=GARDEN_PER_PERSON*$people,'Úroda v srpnu odpovídá zasetí a péči ('.$crop.' z '.GARDEN_PER_PERSON*$people.')');
check((int)$db->query("SELECT COUNT(*) FROM journal WHERE title='V zahradě dozrála úroda'")->fetchColumn()===1,'Kronika zapíše dozrání');
$setDate($world,6);
$veg0=$world['veg']; $leg0=$world['legumes'];
for ($i=0;$i<3;$i++) $work($db,$world,'anna','harvest_garden');
$harvested=$crop-$world['garden']['crop'];
check(abs(($world['veg']-$veg0)-$harvested*GARDEN_VEG_SHARE)<.01 && abs(($world['legumes']-$leg0)-$harvested*(1-GARDEN_VEG_SHARE))<.01,'Sklizeň dá 60 % zeleniny a 40 % luštěnin');
// Co zůstane na záhonech, v prosinci zmrzne.
$setDate($world,9);
$beforeFrost=$world['garden']['crop'];
for ($i=0;$i<40;$i++) { $world['minute']+=1440; winterDaily($db,$world); }
check($beforeFrost>20 && $world['garden']['crop']==0.0,'Nesklizená úroda ('.round($beforeFrost).') zmrzne během prosince a ledna');
// Bez zahrady nic neroste; v dalším březnu začíná nový rok.
[$db,$world]=$ready(2,['statek']);
check(!workAvailable($world,'anna','sow_garden'),'Bez zahrady se nesází');
[$db,$world]=$ready(0);
$world['garden']['season']=0; $world['garden']['sown']=.7; $world['garden']['done']=true; $world['garden']['crop']=0.0;
winterDaily($db,$world);
check($world['garden']['season']===1 && $world['garden']['sown']==0.0 && $world['garden']['done']===false,'V březnu začíná nová zahradní sezóna');
// Sezónní okna.
[$db,$world]=$ready(7);
$world['garden']['sown']=.8; $world['garden']['crop']=20.0;
check(!workAvailable($world,'anna','sow_garden') && !workAvailable($world,'anna','tend_garden'),'V říjnu se už nesází ani neokopává');

// 4. Zelí: potřebuje sklep a sezónu, ztrácí 10 %.
[$db,$world]=$ready(7);
$world['veg']=20.0; $world['salt']=5.0;
check(workAvailable($world,'anna','pickle'),'V říjnu se nakládá zelí');
$text=$work($db,$world,'anna','pickle');
check($world['kraut']>4 && $world['veg']<20 && abs($world['kraut']-(20-$world['veg'])*KRAUT_YIELD)<1e-6 && str_contains($text,'zelí'),'Z zeleniny je zelí, 10 % se ztratí ('.round($world['kraut'],1).' z '.round(20-$world['veg'],1).')');
[$db,$world]=$ready(7,['statek','zahrada']);
$world['veg']=20.0;
check(!workAvailable($world,'anna','pickle'),'Bez sklepa se nenakládá');
[$db,$world]=$ready(2);
$world['veg']=20.0;
check(!workAvailable($world,'anna','pickle'),'V květnu se zelí nenakládá');

// 5. Byliny: sběr v sezóně, sušení, ošetřování.
[$db,$world]=$ready(2);
check(workAvailable($world,'anna','gather_herbs'),'Na jaře se sbírají byliny');
$work($db,$world,'anna','gather_herbs'); $work($db,$world,'anna','gather_herbs'); $work($db,$world,'anna','gather_herbs');
check($world['herbs']>3,'Sběr dá byliny ('.round($world['herbs'],1).')');
$fresh=$world['herbs'];
$work($db,$world,'anna','dry_herbs');
check($world['herbsDry']>0 && $world['herbs']<$fresh && abs($world['herbsDry']-($fresh-$world['herbs'])*.5)<.01,'Sušení promění dvě čerstvé na jednu sušenou');
[$db,$world]=$ready(7);
check(!workAvailable($world,'anna','gather_herbs'),'V říjnu se byliny nesbírají');
// Ošetření: zkrátí nemoc, spotřebuje bylinu, potřebuje nemocného.
[$db,$world]=$ready(9);
$world['herbsDry']=5.0;
check(!workAvailable($world,'anna','nurse'),'Bez nemocného se neošetřuje');
$world['people']['jan']['illUntil']=(int)$world['minute']+3000;
check(workAvailable($world,'anna','nurse'),'S nemocným a bylinami se ošetřuje');
$before=$world['people']['jan']['illUntil']-$world['minute'];
assignWork($world,'anna','nurse',false);
check(($world['people']['anna']['task']['other']??null)==='jan','Ošetřující dostane za cíl nemocného');
completeWork($db,$world,'anna');
$after=$world['people']['jan']['illUntil']-$world['minute'];
check($after<$before*.8 && $after>=60 && abs($world['herbsDry']-4.0)<1e-9,'Ošetření zkrátí nemoc o třetinu a spotřebuje bylinu ('.round($before).' → '.round($after).' minut)');
$world['herbsDry']=0.0; $world['herbs']=0.0;
check(!workAvailable($world,'anna','nurse'),'Bez bylin se neošetřuje');

// 6. Pestrá strava: zelenina, zelí a luštěniny tvoří část jídla.
[$db,$world]=$ready(9);
$world['veg']=10.0; $world['kraut']=10.0; $world['legumes']=10.0; $world['food']=100.0;
$total=PLANT_MEAL_SHARE;
check(drawFood($world,10.0),'Jídlo se dá vzít');
$plantEaten=(10-$world['veg'])*.7+(10-$world['kraut'])*.8+(10-$world['legumes'])*1.4;
check(abs($plantEaten-10*PLANT_MEAL_SHARE)<1e-6 && $world['veg']<10 && $world['kraut']==10.0,'Z jednoho jídla je 12 % rostlinných zásob, čerstvé dřív');
check(abs($world['dietToday']['plant']-1.2)<1e-6 && abs($world['dietToday']['total']-10)<1e-6,'Strava se eviduje');
// Zbytek zásob zachrání hlad, i když jiné jídlo došlo.
[$db,$world]=$ready(9);
$world['food']=0.0; $world['grain']=0.0; $world['flour']=0.0; $world['eggs']=0.0;
$world['veg']=0.0; $world['kraut']=5.0; $world['legumes']=0.0;
check(drawFood($world,3.0) && abs($world['kraut']-(5-3/.8))<1e-6,'Když nic jiného není, uživí zelí');
check(!drawFood($world,10.0),'Víc než zbývá se nevezme');
// Rezerva jídla počítá rostlinné zásoby.
[$db,$world]=$ready(9);
$r0=foodReserve($world); $world['kraut']=10.0;
check(abs(foodReserve($world)-$r0-8.0)<1e-9,'Rezerva jídla počítá zelí');

// 7. Pestrost a důsledky zimy.
$people=function(array $world): int { return count($world['people']); };
[$db,$world]=$ready(9);
$world['diet']=['plant'=>0.0,'total'=>40.0]; $world['dietToday']=['plant'=>0.0,'total'=>0.0];
winterDaily($db,$world);
check($world['variety']<.05,'Bez rostlinné stravy a bylin je pestrost nulová');
$moods=array_sum(array_column($world['people'],'mood'))/count($world['people']);
check($moods<80-2.5,'V zimě bez zeleniny klesá nálada ('.round($moods,1).')');
[$db,$world]=$ready(9);
$world['diet']=['plant'=>6.0,'animal'=>3.0,'total'=>40.0]; $world['herbsDry']=count($world['people'])*.6;
winterDaily($db,$world);
check($world['variety']>.95,'S rostlinnou i živočišnou stravou a sušenými bylinami je pestrost plná ('.$world['variety'].')');
$moods=array_sum(array_column($world['people'],'mood'))/count($world['people']);
check(abs($moods-80)<.01,'Při plné pestrosti se nálada nesnižuje');
// Zimnice: bez pestré stravy onemocní za zimu víc lidí než s ní.
$sick=function(bool $varied) use ($ready,$setDate): int {
    [$db,$world]=$ready(9);
    $count=0;
    for ($d=0;$d<60;$d++) {
        $setDate($world,9+intdiv($d,30)%3,($d%30)+1);
        $world['diet']=$varied?['plant'=>8.0,'animal'=>4.0,'total'=>60.0]:['plant'=>0.0,'animal'=>0.0,'total'=>60.0];
        $world['dietToday']=['plant'=>0.0,'total'=>0.0];
        $world['herbsDry']=$varied?20.0:0.0;
        foreach ($world['people'] as &$p) { $p['illUntil']=0; $p['mood']=80.0; }
        unset($p);
        winterDaily($db,$world);
        $count+=count(array_filter($world['people'],fn($p)=>($p['illUntil']??0)>$world['minute']));
    }
    return $count;
};
$sickLack=$sick(false); $sickVaried=$sick(true);
check($sickLack>=3 && $sickVaried===0,'Zimnice jen při nedostatku pestré stravy ('.$sickLack.' proti '.$sickVaried.')');
// V létě se pestrost neprojeví.
[$db,$world]=$ready(4);
$world['diet']=['plant'=>0.0,'total'=>40.0];
winterDaily($db,$world);
check(abs(array_sum(array_column($world['people'],'mood'))/count($world['people'])-80)<.01,'V létě chybějící zelenina nálada nesnižuje');

// 8. Příprava na zimu a seno.
[$db,$world]=$ready(6);
$world['food']=0.0; $world['grain']=0.0; $world['flour']=0.0; $world['wood']=0.0; $world['hay']=0.0;
$p0=winterPrep($world);
check($p0['min']==0.0 && $p0['food']==0.0 && $p0['fuel']==0.0 && $p0['hay']==0.0,'Prázdné sklady jsou nepřipravené');
$world['food']=dailyFoodNeed($world)*WINTER_FOOD_DAYS; $world['wood']=WINTER_WOOD_PER_DAY*WINTER_FUEL_DAYS; $world['hay']=winterHayNeed($world)*WINTER_HAY_DAYS;
check(winterPrep($world)['min']===1.0,'Zásoby na zimu dávají poměr 1');
$world['hay']=winterHayNeed($world)*WINTER_HAY_DAYS/2;
check(abs(winterPrep($world)['min']-.5)<.01,'Nejmenší poměr rozhoduje');
$world['hay']=0.0;
check(hayTarget($world)>=winterHayNeed($world)*WINTER_HAY_DAYS,'Seno se kosí do plné zimní potřeby, ne jen do 70');
check(workAvailable($world,'anna','mow'),'Kosit se dá, dokud seno nedosáhne cíle');
$world['hay']=hayTarget($world)+1;
check(!workAvailable($world,'anna','mow'),'Po dosažení cíle se nekosí');
$world['hay']=10.0;
check(winterScore($world,'mow')>0,'Plánovač dá senu body, když chybí');
winterDaily($db,$world);
check(isset($world['winterPrep']) && $world['winterPrep']>=0,'Ukazatel se ukládá do stavu');

// 9. Plánovač nabídne zimní práce a stará hra je nemá problém převzít.
[$db,$world]=$ready(2);
$candidates=villageCandidates($world);
$offered=[];
foreach ($candidates as $perPerson) foreach (array_keys($perPerson) as $key) $offered[$key]=true;
check(isset($offered['sow_garden']) && isset($offered['gather_herbs']),'Plánovač nabízí sázení a sběr bylin');
$old=$world; foreach (['veg','kraut','legumes','herbs','herbsDry','garden','diet','dietToday','variety','winterPrep'] as $k) unset($old[$k]);
$old=decodeWorld(encode($old));
check($old['veg']===0.0 && $old['garden']['season']===-1 && $old['diet']['total']===0.0 && $old['variety']===1.0,'Starý stav převezme nové klíče');
winterDaily($db,$old);
check(isset($old['garden']['season']) && $old['variety']>=0,'Starý stav projde denním během');

// 10. Běh: celý rok s hotovou zahradou a sklepem, zima bez hladovění a varování.
$seen=[]; set_error_handler(function($no,$msg,$file,$line) use (&$seen){ $seen[]=basename($file).':'.$line.' '.$msg; return true; });
[$db,$world]=$ready(0);
$world['people']=array_slice($world['people'],0,12,true);
$world['wood']=300.0; $world['food']=900.0; $world['grain']=200.0; $world['hay']=60.0;
$setDate($world,0);
$minVariety=1.0; $stockedAutumn=0.0;
for ($d=1;$d<=360;$d++) {
    runDays($db,$world,1.0,false);
    $m=calendarMonth($world);
    if ($m===8 && $d%30===0) $stockedAutumn=max($stockedAutumn,plantStock($world));
    if (isWinterMonth($m)) $minVariety=min($minVariety,$world['variety']);
}
restore_error_handler();
check(!$seen,'Bez varování PHP: '.implode('; ',array_slice($seen,0,3)));
check((float)($world['counters']['produced']['veg']??0)+(float)($world['counters']['produced']['legumes']??0)>0 || plantStock($world)>0 || $stockedAutumn>0,'Za rok zahrada něco dala');
check(count($world['people'])>=10,'Vesnice zimu přežila');
echo "Zima a zásoby OK (nejnižší pestrost v zimě ".round($minVariety,2).", zásoba rostlin na podzim ".round($stockedAutumn,1).")\n";
