<?php
declare(strict_types=1);

// Fáze I: chov, maso a vlna (prasata a zabijačka, ovčín a vlna, svíčky ze sádla, látka z vlny).
// Použití: php tests/chov.php   (bez modelu)
require __DIR__.'/fixture.php';

$warnings=[];
set_error_handler(function($no,$msg,$file,$line) use (&$warnings){ $warnings[]=basename($file).':'.$line.' '.$msg; return true; });
error_reporting(E_ALL);

$setDate=function(array &$world,int $month,int $day=1,int $hour=8,int $year=3): void {
    $world['calendarOriginDay']=0;
    $world['minute']=(float)((($year-1)*360+$month*30+$day-1)*1440+$hour*60);
    $world['calmUntil']=$world['minute']+99999999;   // bez deště a větru; teplota se ale nemění
};
$ready=function(int $month=8,array $extra=[],int $year=3) use ($setDate): array {
    [$db,$world]=freshWorld();
    $setDate($world,$month,3,8,$year);
    foreach (array_merge(['statek','dilna','sklep','hospoda','studna','zahrada','kostel','pila','mlyn','udirna'],$extra) as $b) $world['buildings'][$b]=1;
    $world['arrivedFamilies'][MILLER_FAMILY]=1;
    $world['food']=400.0; $world['grain']=300.0; $world['wood']=100.0; $world['planks']=30.0; $world['stone']=30.0; $world['hay']=200.0;
    $world['project']=null; $world['houseProject']=null;
    foreach ($world['people'] as &$p) { $p['fatigue']=10.0; $p['hunger']=10.0; $p['mood']=60.0; $p['illUntil']=0; $p['task']=null; }
    unset($p);
    return [$db,$world];
};
$work=function(PDO $db,array &$world,string $id,string $key): string {
    assignWork($world,$id,$key,false);
    $text=completeWork($db,$world,$id);
    $world['people'][$id]['task']=null;
    return $text;
};
// Najde den v měsíci, kdy je chladno (nejvýš PIG_SLAUGHTER_TEMP) nebo naopak teplo.
$findTemp=function(array &$world,int $month,bool $cold) use ($setDate): bool {
    for ($d=1;$d<=30;$d++) for ($h=8;$h<=16;$h+=4) {
        $setDate($world,$month,$d,$h);
        if ((villageWeather($world)['temperature']<=PIG_SLAUGHTER_TEMP)===$cold) return true;
    }
    return false;
};
$day=fn(array $w)=>intdiv((int)$w['minute'],1440);

// 1. Definice, pořadí, akce, popisky.
check((BUILDINGS['ovcin']['work']??0)===50 && BUILDINGS['ovcin']['needs']===['ohrada','tkalcovna'],'Ovčín: 50 h, navazuje na ohradu a tkalcovnu');
check(in_array('ovcin',BUILD_ORDER,true) && in_array('ovcin',BUILD_PREPAY,true) && array_slice(BUILD_ORDER,-1)===['ovcin'],'Ovčín je poslední ve stavebním pořadí a staví se ze zásob');
foreach (BUILDINGS['ovcin']['cost'] as $res=>$amount) check(in_array($res,['wood','stone','planks'],true),"Cena ovčína: $res");
foreach (HUSBANDRY_TASKS as $key) check(isset(TASK_STEMS[$key]) && isset(ACTIONS[$key]) && isset(ACTION_CATEGORY[$key]),"$key má kmeny, akci a obor");
foreach (['pork','lard','wool'] as $res) check(isset(RESOURCES[$res]) && isset(RESOURCE_WORDS[$res]),"Surovina $res má popisky");
check(isset(BUILDING_DOES['ovcin']) && isset(GOALS['pigs_herd']) && isset(GOALS['sheep_house']) && isset(GOALS['wool_cloth']),'Popis budovy a cíle chovu');

// 2. Prasata: bez chlívu nic, po stavbě čtyři kusy, krmení z přebytku, růst a hlad.
[$db,$world]=$ready(8);
check(pigsOf($world)===0.0 && !workAvailable($world,'anna','slaughter_pig') && clothWarmth($world)===($world['cloth']>=max(1,count($world['people']))*.25?.7:1.0),'Bez chlívu žádná prasata');
$grain0=$world['grain']; husbandryDaily($db,$world);
check($world['pigs']===null && $world['grain']===$grain0,'Bez chlívu se nic nespotřebuje');
$world['buildings']['chliv']=1; husbandryDaily($db,$world);
check($world['pigs']===(float)PIG_START && abs($world['pigWeight']-PIG_START_WEIGHT)<.02,'Chlív dostane čtyři prasata');
$w0=$world['pigWeight']; $g0=$world['grain'];
for ($i=0;$i<10;$i++) husbandryDaily($db,$world);
check($world['pigWeight']>$w0+.04 && $world['grain']<$g0-PIG_START*PIG_GRAIN*8,'Krmená prasata tloustnou a žerou obilí ('.round($w0,2).' → '.round($world['pigWeight'],2).')');
$floor=henGrainReserve($world)-pigGrainReserve($world)+PIG_GRAIN_FLOOR;
$world['grain']=$floor; $w1=$world['pigWeight']; husbandryDaily($db,$world);
check(abs($world['grain']-$floor)<1e-9 && $world['pigWeight']<$w1,'Prasata žerou jen z přebytku obilí, lidem ho neberou');
$world['pigHungry']=0; $before=$world['pigs'];
for ($i=0;$i<PIG_STARVE_DAYS+1;$i++) husbandryDaily($db,$world);
check($world['pigs']===$before-1,'Po deseti dnech hladu jedno prase zahyne');
check(($world['counters']['pigHungry']??0)>=PIG_STARVE_DAYS,'Hlad prasat se počítá');
// Prázdný chlív se po čase znovu osadí.
$world['pigs']=0.0; $world['pigEmptyDays']=0; $world['grain']=300.0;
for ($i=0;$i<PIG_RESTOCK_DAYS+1;$i++) husbandryDaily($db,$world);
check($world['pigs']>=2.0,'Prázdný chlív se znovu osadí selaty');

// 3. Selata v březnu a strop.
[$db,$world]=$ready(0,['chliv']); $world['pigs']=2.0; $world['pigWeight']=.8; $world['pigHungry']=0; $world['pigEmptyDays']=0;
$cap=pigCap($world);
check($cap===min(PIG_MAX,2+(int)ceil(count($world['people'])/8)) && $cap>=3,'Strop prasat podle počtu lidí ('.$cap.')');
husbandryDaily($db,$world);
$expected=2+min(PIG_LITTER,$cap-2);
check($world['pigs']===(float)$expected,'V březnu přibudou selata, nejvýš po strop ('.$world['pigs'].')');
check(abs($world['pigWeight']-.8*2/$expected)<.01,'Selata naředí průměrnou váhu');
$p0=$world['pigs']; husbandryDaily($db,$world); check($world['pigs']===$p0,'Jedna várka selat za rok');
$world['pigs']=(float)$cap; $world['pigYear']=-1; husbandryDaily($db,$world); check($world['pigs']===(float)$cap,'Na stropu se selata nerodí');
check(pigCap(array_merge($world,['people'=>array_fill(0,64,['x'=>1])]))===PIG_MAX,'Strop prasat je deset');

// 4. Zabijačka: sezóna, chlad, váha, chovná dvojice, zdroje, kronika a sousedé.
[$db,$world]=$ready(8,['chliv']); husbandryDaily($db,$world);
$world['pigs']=5.0; $world['pigWeight']=.9;
check($findTemp($world,8,true),'V listopadu je aspoň jeden chladný den');
check(workAvailable($world,'anna','slaughter_pig'),'V listopadu za chladu se dělá zabijačka');
$world['pigWeight']=.5; check(!workAvailable($world,'anna','slaughter_pig'),'Příliš lehká prasata se nezabíjejí');
$world['pigWeight']=.9; $world['pigs']=(float)PIG_KEEP; check(!workAvailable($world,'anna','slaughter_pig'),'Chovná dvojice se nezabíjí');
$world['pigs']=5.0;
check($findTemp($world,8,false),'V listopadu je i teplý den'); check(!workAvailable($world,'anna','slaughter_pig'),'Za tepla se nezabíjí');
$world['calmUntil']=$world['minute']+99999999;
$setDate($world,6,3); check(!workAvailable($world,'anna','slaughter_pig'),'V září se nezabíjí');
$setDate($world,10,3); check(!workAvailable($world,'anna','slaughter_pig'),'V lednu se nezabíjí');
$findTemp($world,9,true);
check(workAvailable($world,'anna','slaughter_pig'),'V prosinci za chladu se zabíjí');
$hides=$world['hides']; $mood=[]; foreach ($world['people'] as $pid=>$p) $mood[$pid]=$p['mood'];
$text=$work($db,$world,'anna','slaughter_pig');
$expPork=round(PORK_PER_PIG*.9*min(1.15,1.0),1);
check($world['pigs']===4.0 && $world['pork']>PORK_PER_PIG*.9*.6 && $world['pork']<=PORK_PER_PIG*.9*1.15+.1 && $world['lard']>=LARD_PER_PIG*.9-.1 && abs($world['hides']-$hides-HIDES_PER_PIG)<1e-9 && str_contains($text,'masa'),'Zabijačka dá maso, sádlo a kožku ('.$world['pork'].' masa)');
check(($world['counters']['pigsSlaughtered']??0)===1 && ($world['pigFeastYear']??-1)>0,'Zabijačka se počítá a svolá sousedy');
$up=0; foreach ($world['people'] as $pid=>$p) if ($p['mood']>$mood[$pid]+1) ++$up;
check($up>=2 && $up<=1+FEAST_NEIGHBORS,'Sousedé na zabijačce dostanou lepší náladu ('.$up.' lidí)');
$m1=$world['people']['anna']['mood']; $work($db,$world,'anna','slaughter_pig');
check($world['pigs']===3.0 && $world['people']['anna']['mood']<=$m1+.01,'Druhá zabijačka v roce už sousedy nesvolává');
// Zákaz v původní vesnici bez chlívu.
[$db,$nopig]=$ready(8); $findTemp($nopig,8,true); $nopig['pigs']=5.0; $nopig['pigWeight']=.9;
check(!workAvailable($nopig,'anna','slaughter_pig'),'Bez chlívu se zabijačka nekoná');
// Dítě zabijačku nedělá.
[$db,$kidw]=$ready(8,['chliv']); $findTemp($kidw,8,true); $kidw['pigs']=5.0; $kidw['pigWeight']=.9; husbandryDaily($db,$kidw);
$kidw['people']['kid']=array_merge($kidw['people']['anna'],['id'=>'kid','name'=>'Dítě','age'=>9,'task'=>null]);
check(!workAvailable($kidw,'kid','slaughter_pig') && workAvailable($kidw,'anna','slaughter_pig'),'Děti zabijačku nedělají');

// 5. Maso: kažení, uzení, jídlo.
[$db,$world]=$ready(8,['chliv']); $world['pork']=10.0; $world['game']=0.0; $world['meatSmoked']=0.0;
husbandryDaily($db,$world); check(abs($world['pork']-10*PORK_KEEP)<1e-9,'Čerstvé maso se kazí 22 % denně');
$world['pork']=12.0;
check(workAvailable($world,'anna','smoke_meat'),'Čerstvé maso se dá vyudit');
$wood=$world['wood']; $text=$work($db,$world,'anna','smoke_meat');
check($world['pork']<12 && abs($world['meatSmoked']-(12-$world['pork'])*SMOKE_YIELD)<1e-9 && $world['wood']<$wood && str_contains($text,'vepřového'),'Uzení spotřebuje maso a dřevo ('.$world['meatSmoked'].' uzeného)');
$world['pork']=4.0; $world['game']=5.0; $world['meatSmoked']=0.0; $world['wood']=100.0;
$work($db,$world,'anna','smoke_meat');
check($world['pork']===0.0 && $world['game']>=0 && $world['game']<5 && $world['meatSmoked']>4,'Nejdřív se udí vepřové, pak zvěřina');
$world['pork']=0.0; $world['game']=2.0; check(!workAvailable($world,'anna','smoke_meat'),'Z dvou jednotek se neudí');
$world['pork']=3.0; check(workAvailable($world,'anna','smoke_meat'),'Pork s malým množstvím zvěřiny stačí');
$a=$world; $a['pork']=20.0; $a['game']=0.0; $r0=foodReserve($a); $a['pork']=0.0; check(abs($r0-foodReserve($a)-20*GAME_FOOD)<1e-9,'Čerstvé maso se počítá do rezervy jídla');
$b=$world; $b['pork']=10.0; $b['game']=0.0; $b['meatSmoked']=0.0; $b['food']=400.0; $b['dietToday']=['plant'=>0.0,'animal'=>0.0,'total'=>0.0];
drawFood($b,20.0); check($b['pork']<10 && $b['dietToday']['animal']>0,'Vepřové se jí a zvyšuje podíl masa ve stravě');

// 6. Ovce: osazení, péče, jehňata, stříhání, seno, vlna.
[$db,$world]=$ready(1);
check(sheepOf($world)===0.0 && !workAvailable($world,'anna','tend_sheep') && !workAvailable($world,'anna','shear_sheep'),'Bez ovčína žádné ovce');
$world['buildings']['ovcin']=1; husbandryDaily($db,$world);
check($world['sheep']===(float)SHEEP_START && $world['woolCrop']===0.0,'Ovčín dostane čtyři ovce');
check(workAvailable($world,'anna','tend_sheep'),'Na jaře se prohlíží stádo');
for ($i=0;$i<8 && workAvailable($world,'anna','tend_sheep');$i++) $work($db,$world,'anna','tend_sheep');
check($world['sheepCare']>=.999,'Plná péče o ovce');
$setDate($world,1,1,8,4); $world['sheepCare']=1.0; $s0=$world['sheep']; husbandryDaily($db,$world);
$lambs=min((int)floor($s0/2),sheepCap($world)-(int)floor($s0));
check($world['sheep']===$s0+$lambs && $world['sheepCare']===0.0,'V dubnu přibudou jehňata a péče se začíná znovu ('.$world['sheep'].')');
$world['sheepCare']=1.0; $setDate($world,2,1,8,4); husbandryDaily($db,$world);
check(abs($world['woolCrop']-$world['sheep']*WOOL_PER_SHEEP)<.15,'V květnu je vlna k ostříhání ('.$world['woolCrop'].')');
check(workAvailable($world,'anna','shear_sheep'),'V květnu se stříhají ovce');
$w0=$world['wool']; $text=$work($db,$world,'anna','shear_sheep');
check($world['wool']>$w0+3 && str_contains($text,'vlny'),'Stříhání dá vlnu');
for ($i=0;$i<5 && workAvailable($world,'anna','shear_sheep');$i++) $work($db,$world,'anna','shear_sheep');
check($world['woolCrop']<1 && !workAvailable($world,'anna','shear_sheep'),'Ostříhané ovce se nestříhají znovu');
$setDate($world,4,1); check(!workAvailable($world,'anna','shear_sheep'),'V červenci se už nestříhá');
$world['wool']=WOOL_CAP*2; countProduced($world,'wool',0.0); $world['woolCrop']=5.0; $setDate($world,2,5); $work($db,$world,'anna','shear_sheep'); check($world['wool']<=WOOL_CAP,'Vlna má strop');
// Péče zvyšuje úrodu vlny, hlad v zimě ji snižuje.
$mk=function(float $care,int $short) use ($ready,$setDate): float {
    [$db,$w]=$ready(1,['ovcin']); husbandryDaily($db,$w);
    $w['sheepCare']=$care; $w['sheepShort']=$short; $w['sheep']=6.0; $w['woolYear']=-1;
    $setDate($w,2,1); husbandryDaily($db,$w);
    return $w['woolCrop'];
};
check($mk(1.0,0)>$mk(0.0,0)*1.5 && abs($mk(1.0,0)-12.0)<.2,'Péče zvyšuje vlnu ('.$mk(0.0,0).' → '.$mk(1.0,0).')');
check($mk(1.0,40)<$mk(1.0,0)*.6,'Hladové ovce dají míň vlny ('.$mk(1.0,40).' proti '.$mk(1.0,0).')');
// Zimní seno.
[$db,$world]=$ready(10,['ovcin']); husbandryDaily($db,$world);
check(($world['sheep']??null)===null && abs(sheepHayNeed($world)-SHEEP_START*SHEEP_HAY)<1e-9,'V zimě se ovce nepřivádějí, ale seno se na ně už plánuje');
$noSheep=$world; $world['sheep']=4.0; $world['sheepCare']=0.0; $world['sheepShort']=0; $world['woolCrop']=0.0; $noSheep['sheep']=0.0;
check(abs(winterHayNeed($world)-winterHayNeed($noSheep)-$world['sheep']*SHEEP_HAY)<1e-9 && hayTarget($world)>hayTarget($noSheep),'Ovce zvyšují zimní potřebu sena i cíl sečení ('.round(winterHayNeed($world),2).' proti '.round(winterHayNeed($noSheep),2).')');
$hay=$world['hay']; economyDaily($db,$world);
check($world['hay']<$hay-winterHayNeed($world)+.5 && !$world['hayShort'] && $world['sheepShort']===0,'V zimě ovce žerou seno spolu s ostatním dobytkem ($hay → '.round($world['hay'],1).')');
// Při nedostatku sena se ostatní dobytek krmí dřív než ovce.
$world['hay']=winterHayNeed($noSheep)+.1; $world['sheepShort']=0; $world['hayShort']=false; economyDaily($db,$world);
check(!$world['hayShort'] && $world['hay']<sheepHayNeed($world) && $world['sheepShort']===1 && ($world['counters']['sheepHungry']??0)>=1,'Když seno nestačí, ovce hladoví dřív než ostatní dobytek');
$world['hay']=0.0; $world['sheepShort']=0; economyDaily($db,$world);
check(!empty($world['hayShort']) && $world['sheepShort']>=1,'Bez sena hladoví všichni');
[$db,$summer]=$ready(4,['ovcin']); husbandryDaily($db,$summer); $h=$summer['hay']; husbandryDaily($db,$summer);
check($summer['hay']===$h,'V létě ovce pasou a seno neberou');
check(sheepCap(array_merge($world,['people'=>array_fill(0,64,['x'=>1])]))===SHEEP_MAX,'Strop ovcí je dvanáct');

// 7. Vlna a látka: tkaní.
[$db,$world]=$ready(7,['tkalcovna','ovcin']);
$world['wool']=WOOL_WEAVE+1; $world['flax']=0.0; $world['cloth']=0.0;
check(workAvailable($world,'anna','spin_weave'),'Z vlny se dá tkát');
$text=$work($db,$world,'anna','spin_weave');
check(abs($world['wool']-1)<1e-9 && $world['cloth']>=WOOL_WEAVE*.7 && $world['cloth']<=WOOL_WEAVE*1.2 && str_contains($text,'vlny'),'Pět vlny dá pět látky ('.$world['cloth'].')');
$world['wool']=WOOL_WEAVE; $world['flax']=WEAVE_FLAX; $world['cloth']=0.0; $work($db,$world,'anna','spin_weave');
check($world['wool']===0.0 && $world['flax']===WEAVE_FLAX,'Vlna má přednost před lnem');
$world['wool']=0.0; $world['cloth']=0.0; $work($db,$world,'anna','spin_weave');
check($world['flax']===0.0 && $world['cloth']>=WEAVE_CLOTH*.7 && $world['cloth']<=WEAVE_CLOTH*1.2,'Bez vlny se tká jako dřív z lnu');
$world['wool']=1.0; $world['flax']=1.0; check(!workAvailable($world,'anna','spin_weave'),'Na tkaní nestačí málo vlny ani lnu');
// Zimní oblečení.
[$db,$a]=$ready(10,['tkalcovna']); $people=count($a['people']);
$a['cloth']=0.0; check(clothWarmth($a)===1.0,'Bez látky bez ochrany');
$a['cloth']=$people*.3; check(clothWarmth($a)===.7,'S látkou 0,7');
$a['cloth']=$people*.6; check(clothWarmth($a)===.7,'Bez ovčína zůstává 0,7');
$a['buildings']['ovcin']=1; check(clothWarmth($a)===.66,'S ovčínem a dost látky je ochrana lepší');
$a['cloth']=$people*.3; check(clothWarmth($a)===.7,'S ovčínem, ale málo látky zůstává 0,7');
$sick=function(float $factor) use ($ready): int {
    [$db,$w]=$ready(10,['tkalcovna']); $w['cloth']=count($w['people'])*.6; if ($factor<1) $w['buildings']['ovcin']=1;
    $w['variety']=0.0; $w['herbsDry']=0.0; $n=0;
    for ($d=0;$d<400;$d++) {
        $w['minute']+=1440; foreach ($w['people'] as &$p) { $p['illUntil']=0; $p['mood']=80.0; } unset($p);
        $b=($w['counters']['winterSick']??0); winterDaily($db,$w); $n+=($w['counters']['winterSick']??0)-$b; $w['variety']=0.0; $w['cloth']=count($w['people'])*.6;
    }
    return $n;
};
$s70=$sick(1.0); $s66=$sick(.66);
check($s70>0 && $s66<=$s70,'S vlněným oblečením ubývá zimních nemocí ('.$s66.' proti '.$s70.')');

// 8. Sádlo a svíčky.
[$db,$world]=$ready(3,['vcelin']);
$world['wax']=0.0; $world['lard']=2.0; $world['candles']=0.0;
check(workAvailable($world,'anna','make_candles'),'Ze sádla se lijí svíčky');
$text=$work($db,$world,'anna','make_candles');
check($world['lard']===1.0 && $world['candles']>=CANDLES_PER_LARD*.7 && str_contains($text,'sádla'),'Sádlo se změní na lojové svíčky ('.$world['candles'].')');
$world['wax']=2.0; $world['lard']=2.0; $world['candles']=0.0; $work($db,$world,'anna','make_candles');
check($world['wax']===1.0 && $world['lard']===2.0,'Vosk má před sádlem přednost');
$world['wax']=0.0; $world['lard']=0.0; check(!workAvailable($world,'anna','make_candles'),'Bez vosku a sádla nejsou svíčky');

// 9. Obchod.
[$db,$world]=$ready(8);
$rules=tradeSellRules($world);
check(isset($rules['wool']) && isset($rules['lard']) && $rules['wool'][0]===WOOL_SELL_KEEP && $rules['lard'][0]===LARD_SELL_KEEP,'Obchodník bere vlnu a sádlo nad rezervu');

// 10. Cíle, popis budovy, stav a rozhraní.
[$db,$world]=$ready(8,['chliv','ovcin']); husbandryDaily($db,$world);
check(goalValue($world,'pigs_herd')===4.0 && goalValue($world,'sheep_house')===1.0,'Cíle chovu čtou stav');
$info=buildingInfo($world,'chliv'); check($info!==null && str_contains(implode(' ',$info['facts']),'Prasat'),'Okénko chlívu ukazuje prasata');
$info=buildingInfo($world,'ovcin'); check($info!==null && str_contains(implode(' ',$info['facts']),'Ovcí') && !empty($info['does']),'Okénko ovčína ukazuje ovce');
// Starý stav bez klíčů se přečte beze změny.
[$db,$old]=$ready(8,['chliv','ovcin']);
foreach (['pigs','pigWeight','pigHungry','pigEmptyDays','pork','lard','sheep','sheepCare','woolCrop','wool','pigYear','pigFeastYear','woolYear','sheepYear','sheepShort'] as $k) unset($old[$k]);
$warnings=[]; husbandryDaily($db,$old); economyDaily($db,$old); foodReserve($old); goalState($old);
check(!$warnings && $old['pigs']===(float)PIG_START,'Starý stav bez klíčů chovu funguje bez varování');
$round=decodeWorld(json_encode($old)); check(isset($round['pigs']) && array_key_exists('wool',$round),'decodeWorld doplní výchozí hodnoty chovu');

// 11. Dlouhý provoz: dva roky s chlívem a ovčínem bez varování, hladu a zablokování.
[$db,$world]=$ready(5,['chliv','ovcin','tkalcovna','vcelin','kozeluzna','ohrada','kurnik']);
$world['hay']=900.0; $world['food']=4000.0; $world['grain']=4000.0;   // soustředíme se na chov, ne na hlad malé zkušební vesnice
$warnings=[];
$slaughter=0; $maxPigs=0.0; $hungry=0;
$tot=['wool'=>0.0,'cloth'=>0.0,'pork'=>0.0,'lard'=>0.0]; $prev=$tot;
for ($d=0;$d<720;$d++) {
    runDays($db,$world,1.0,false);
    $maxPigs=max($maxPigs,$world['pigs']??0);
    foreach ($tot as $k=>$v) { $cur=(float)($world['counters']['produced'][$k]??0); $tot[$k]+=$cur>=$prev[$k] ? $cur-$prev[$k] : $cur; $prev[$k]=$cur; }   // týdenní souhrn čítače nuluje
}
$slaughter=(int)($world['counters']['pigsSlaughtered']??0);
check(!$warnings,'Dva roky chovu bez varování PHP'.($warnings?' ('.implode('; ',array_slice($warnings,0,3)).')':''));
check($maxPigs>=PIG_START && ($world['pigs']??0)>=2 && ($world['sheep']??0)>=SHEEP_START,'Po dvou letech chov žije (prasat '.round($world['pigs']??0,1).', ovcí '.round($world['sheep']??0,1).')');
check($tot['wool']>0 && $tot['cloth']>0 && $tot['pork']>0,'Vlna se stříhala, tkala a prasata se zabíjela (vlna '.round($tot['wool'],1).', látka '.round($tot['cloth'],1).', maso '.round($tot['pork'],1).')');
echo "\nzabijaček: $slaughter, uzené maso: ".round($world['meatSmoked'],1).", vlna celkem: ".round($tot['wool'],1).", prasat: ".round($world['pigs'],1).", ovcí: ".round($world['sheep'],1)."\n";
echo "Vse OK\n";
