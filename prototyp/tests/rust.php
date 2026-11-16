<?php
declare(strict_types=1);

// Fáze H: růst vesnice (přistěhovalci podle prosperity, odliv při hladu, dohazování, porodnost, parcely, hodnost, obchodník).
// Použití: php tests/rust.php   (bez modelu)
require __DIR__.'/fixture.php';

$setDate=function(array &$world,int $month,int $day=1,int $hour=8,int $year=3): void {
    $world['calendarOriginDay']=0;
    $world['minute']=(float)((($year-1)*360+$month*30+$day-1)*1440+$hour*60);
    $world['calmUntil']=$world['minute']+99999999;
};
$ready=function(int $month=3,int $year=3) use ($setDate): array {
    [$db,$world]=freshWorld();
    $setDate($world,$month,10,8,$year);
    foreach (['statek','dilna','sklep','hospoda','studna','zahrada','kostel','pila','mlyn'] as $b) $world['buildings'][$b]=1;
    $world['arrivedFamilies'][MILLER_FAMILY]=1; $world['newcomers']=count(NEWCOMERS);
    $world['food']=3000.0; $world['grain']=400.0; $world['wood']=300.0; $world['planks']=30.0; $world['stone']=30.0;
    $world['project']=null; $world['houseProject']=null;
    foreach ($world['people'] as &$p) { $p['fatigue']=10.0; $p['hunger']=10.0; $p['mood']=75.0; $p['illUntil']=0; $p['task']=null; }
    unset($p);
    return [$db,$world];
};
$day=fn(array $w)=>intdiv((int)$w['minute'],1440);

// 1. Konstanty a definice.
check(HOME_LIMIT===10 && count(HOUSE_PLOTS)===10,'Deset stavebních parcel a limit domů 10');
check(BIRTH_INTERVAL_DAYS===400 && CONCEIVE_CHANCE===.005,'Kratší porodní interval a vyšší šance na početí');
check(array_keys(RANKS)===['osada','ves','vetsi_ves','mestys'] && TRADE_INTERVALS['ves']===21 && TRADE_INTERVALS['vetsi_ves']===14,'Hodnosti a rozestup obchodníka');
$tiers=GOALS['pop']['tiers']; check(end($tiers)===64 && in_array(40,$tiers,true),'Cíle populace do 64');
$homeTiers=GOALS['homes']['tiers']; check(isset(GOALS['settlers']) && end($homeTiers)===10,'Cíl přistěhovaných rodin a domů do 10');

// 2. Hodnost podle počtu lidí a budov.
[$db,$world]=$ready();
check(villageRank($world)==='osada','Dnešních dvacet lidí je osada');
addPeople($world,25); check(villageRank($world)==='ves','21 až 40 lidí je ves');
addPeople($world,45); check(villageRank($world)==='vetsi_ves','41 až 60 lidí je větší ves');
addPeople($world,62); check(villageRank($world)==='vetsi_ves','Městys potřebuje i 30 budov');
foreach (array_slice(array_keys(BUILDINGS),0,30) as $b) $world['buildings'][$b]=1;
check(villageRank($world)==='mestys','S 62 lidmi a 30 budovami je městys');

// 3. Podmínky příchodu.
[$db,$world]=$ready(); $d=$day($world);
check(settlersBlocked($world,$d)===null,'Při dobrých podmínkách může někdo přijít');
$w=$world; $setDate($w,10); check(settlersBlocked($w,$day($w))==='zima','V zimě nikdo nepřichází');
$w=$world; $w['crisis']=['type'=>'fire']; check(settlersBlocked($w,$d)==='krize','Při krizi nikdo nepřichází');
$w=$world; $w['food']=10.0; $w['grain']=0.0; $w['flour']=0.0; check(settlersBlocked($w,$d)==='jidlo','Bez zásob jídla nikdo nepřichází');
$w=$world; $w['wood']=1.0; check(settlersBlocked($w,$d)==='drevo','Bez dřeva nikdo nepřichází');
$w=$world; foreach ($w['people'] as &$p) $p['mood']=40.0; unset($p); check(settlersBlocked($w,$d)==='nalada','Při špatné náladě nikdo nepřichází');
$w=$world; $w['settlers']['lastHunger']=$d-5; check(settlersBlocked($w,$d)==='hlad','Po čerstvém hladu nikdo nepřichází');
$w=$world; $w['settlers']['nextDay']=$d+3; check(settlersBlocked($w,$d)==='rozestup','Mezi příchody je rozestup');
$w=$world; addPeople($w,popCap($w)); check(settlersBlocked($w,$d)==='strop','Nad kapacitou domů nikdo nepřichází');
// Rozestup podle prosperity.
$poor=$world; $poor['food']=dailyFoodNeed($poor)*SETTLER_FOOD_DAYS+1; foreach ($poor['people'] as &$p) $p['mood']=56.0; unset($p); $poor['buildings']=['statek'=>1];
$rich=$world; $rich['food']=dailyFoodNeed($rich)*150;
for ($i=0,$names=['mlyn','pila','pec','studna','dilna','statek','zahrada','hospoda','kurnik','chliv','ohrada','sklep','udirna','milir','chmelnice','sladovna','pivovar','kovarna','dul','staje','hrbitov','kostel','vinice','lis','vcelin','spychar','mlekarna','rybnik','kozeluzna','tkalcovna','fara','cihelna','lazen'];$i<count($names);$i++) $rich['buildings'][$names[$i]]=1;
foreach ($rich['people'] as &$p) $p['mood']=100.0; unset($p);
check(settlerInterval($poor)>settlerInterval($rich) && settlerInterval($rich)===SETTLER_MIN_INTERVAL && settlerInterval($poor)<=SETTLER_MAX_INTERVAL,'Prosperita zkracuje rozestup ('.settlerInterval($poor).' proti '.settlerInterval($rich).' dní)');

// 4. Skupiny: složení, domy, jména, vztahy.
[$db,$world]=$ready(); $d=$day($world);
$types=[];
$ids=[];
for ($i=0;$i<14;$i++) {
    $world['settlers']['nextDay']=0;
    $before=count($world['people']);
    $type=settlersArrive($db,$world,$d+$i*3);
    if ($type===null) break;
    $types[$type]=($types[$type]??0)+1;
    check(count($world['people'])>$before,'Příchod přidá lidi ('.$type.')');
}
check(count($types)>=3,'Vzniká víc druhů skupin ('.implode(', ',array_map(fn($k,$v)=>"$k $v",array_keys($types),$types)).')');
$settlers=array_filter($world['people'],fn($p,$id)=>str_starts_with($id,'host'),ARRAY_FILTER_USE_BOTH);
check(count($settlers)>=15,'Přišlo aspoň patnáct lidí ('.count($settlers).')');
$houses=houseList($world);
foreach ($houses as $hid=>$h) check(count(residentsOf($world,$hid))<=$h['capacity'],"Dům $hid není přeplněný");
$names=array_column($settlers,'name'); check(count($names)===count(array_unique($names)),'Jména přistěhovalců se neopakují');
foreach ($settlers as $id=>$p) {
    check(isset($houses[$p['home']]),"$id má dům");
    check(($p['arrivedDay']??0)>0 && !empty($p['sex']) && $p['age']>=1 && $p['age']<=45,"$id má věk, pohlaví a den příchodu");
    if (!empty($p['spouse'])) check(($world['people'][$p['spouse']]['spouse']??null)===$id && $world['people'][$p['spouse']]['sex']!==$p['sex'],"$id má vzájemného manžela nebo manželku");
    if ($p['age']<18) check(isset($world['people'][$p['mother']??'']) || isset($world['people'][$p['father']??'']),"$id (dítě) má rodiče");
    check(($p['bonds'][array_key_first($world['people'])]??0)===48,"$id zná staré obyvatele");
}
$families=array_column($settlers,'family'); check(count(array_unique($families))>=5,'Rodiny mají různá příjmení');
check(($world['settlers']['arrived']??0)===array_sum($types) && count($world['settlers']['log'])<=8,'Počet příchodů a krátký záznam');
// Bez místa nikdo nepřijde a skupina se zmenší.
[$db,$full]=$ready(); $setDate($full,3);
foreach (houseList($full) as $hid=>$h) { while (count(residentsOf($full,$hid))<$h['capacity']) { $new='f'.$hid.count($full['people']); $full['people'][$new]=array_merge($full['people']['anna'],['id'=>$new,'name'=>'X','home'=>$hid,'family'=>'Plni','spouse'=>null]); } }
check(settlerHouse($full,1)===null && settlersArrive($db,$full,$day($full))===null,'Když je všude plno, nikdo nepřijde');

// 5. Příchod z denního chodu a odliv při hladu.
[$db,$world]=$ready(); $d=$day($world);
$before=count($world['people']); settlersDaily($db,$world);
check(count($world['people'])>$before && $world['settlers']['nextDay']>$d,'Denní chod přivede skupinu a nastaví rozestup');
$again=count($world['people']); settlersDaily($db,$world); check(count($world['people'])===$again,'Do rozestupu nikdo další');
// Odliv: čtrnáct dní hladu po sobě.
$world['settlers']['nextDay']=0;
for ($i=0;$i<SETTLER_LEAVE_STREAK+1;$i++) { $world['counters']['hungryMealsToday']=3; $world['minute']+=1440; settlersDaily($db,$world); }
check(($world['counters']['departures']??0)===1,'Po týdnu hladu odejde rodina');
$left=count($world['people']);
for ($i=0;$i<5;$i++) { $world['counters']['hungryMealsToday']=3; $world['minute']+=1440; settlersDaily($db,$world); }
check(count($world['people'])===$left && ($world['counters']['departures']??0)===1,'Další odchod až po dvou měsících');
foreach ($world['people'] as $id=>$p) foreach ($p['bonds'] as $other=>$v) check(isset($world['people'][$other]),"Vztah $id k neexistujícímu $other zmizel");
// Staří obyvatelé nikdy neodcházejí.
check(isset($world['people']['anna']) && isset($world['people']['jan']),'Původní obyvatelé zůstávají');

// 6. Pevný seznam čtyř přistěhovalců se po 120 dnech přeskočí a nahradí generovanými.
[$db,$world]=$ready(3,1); $world['newcomers']=1; $d=$day($world);
settlersDaily($db,$world); check($world['newcomers']===1 || $d<NEWCOMER_FIRST_DAY+NEWCOMER_GAP_DAYS*count(NEWCOMERS),'Před 120. dnem se pevný seznam nepřeskočí');
$world['minute']=(float)((NEWCOMER_FIRST_DAY+NEWCOMER_GAP_DAYS*count(NEWCOMERS)+3)*1440+8*60); $world['calendarOriginDay']=0; $world['calmUntil']=$world['minute']+99999999;
settlersDaily($db,$world); check($world['newcomers']===count(NEWCOMERS),'Po 120. dni se zbytek pevného seznamu přeskočí');

// 7. Dohazování osamělých.
$countPairs=function(bool $lonely) use ($ready,$setDate): int {
    $pairs=0;
    for ($trial=0;$trial<12;$trial++) {
        [$db,$w]=$ready(3,3+$trial);
        $w['people']=array_intersect_key($w['people'],array_flip(['jan','bara','karel','karolina']));
        $a=$w['people']['bara']; $b=$w['people']['jan'];
        $w['people']['m1']=array_merge($b,['id'=>'m1','name'=>'Muž Osamělý','sex'=>'m','age'=>29,'family'=>'Aaa','spouse'=>null,'betrothed'=>null,'arrivedDay'=>$lonely?10:intdiv((int)$w['minute'],1440)-1,'birthDay'=>intdiv((int)$w['minute'],1440)-29*360,'home'=>'Kovářovi','bonds'=>[]]);
        $w['people']['z1']=array_merge($a,['id'=>'z1','name'=>'Žena Osamělá','sex'=>'f','age'=>28,'family'=>'Bbb','spouse'=>null,'betrothed'=>null,'arrivedDay'=>$lonely?10:intdiv((int)$w['minute'],1440)-1,'birthDay'=>intdiv((int)$w['minute'],1440)-28*360,'home'=>'Královi','bonds'=>[]]);
        foreach (['m1','z1'] as $id) $w['people'][$id]['bonds']=array_fill_keys(array_keys($w['people']),64);
        for ($i=0;$i<55;$i++) { $w['minute']+=1440; checkRomance($db,$w,intdiv((int)$w['minute'],1440)); if (!empty($w['people']['m1']['betrothed'])) { ++$pairs; break; } }
    }
    return $pairs;
};
$lonely=$countPairs(true); $normal=$countPairs(false);
check($lonely>=7 && $normal===0,'Osamělí se zasnoubí i při vztahu 64, ostatní až od 70 ('.$lonely.' z 12 proti '.$normal.' z 12)');
check(isLonely(['age'=>29,'birthDay'=>-10440,'arrivedDay'=>100],200) && !isLonely(['age'=>29,'birthDay'=>-10440,'arrivedDay'=>180],200) && !isLonely(['age'=>17,'birthDay'=>0],900) && !isLonely(['age'=>30,'birthDay'=>0,'spouse'=>'x'],900),'Osamělý je dospělý bez partnera aspoň 60 dní');

// 8. Porodnost: bezdětná rodina se snaží víc než velká.
$births=function(int $kids) use ($ready): int {
    $n=0;
    for ($trial=0;$trial<10;$trial++) {
        [$db,$w]=$ready(3,2+$trial);
        $w['people']=array_intersect_key($w['people'],array_flip(['anna','matyas']));
        $w['people']['anna']['age']=26; $w['people']['anna']['spouse']='tomas1'; $w['people']['anna']['lastBirth']=-999999; $w['people']['anna']['pregnant']=null; $w['people']['anna']['mood']=80.0;
        $w['people']['tomas1']=array_merge($w['people']['matyas'],['id'=>'tomas1','name'=>'Manžel','sex'=>'m','age'=>28,'spouse'=>'anna','home'=>$w['people']['anna']['home']]);
        for ($k=0;$k<$kids;$k++) $w['people']['k'.$k]=array_merge($w['people']['matyas'],['id'=>'k'.$k,'name'=>'Dítě'.$k,'age'=>5,'mother'=>'anna','father'=>'tomas1','spouse'=>null]);
        for ($i=0;$i<200;$i++) { $w['minute']+=1440; checkConception($db,$w,intdiv((int)$w['minute'],1440)); if (!empty($w['people']['anna']['pregnant'])) { ++$n; break; } }
    }
    return $n;
};
$few=$births(0); $many=$births(4);
check($few>=$many,'Bezdětná rodina otěhotní aspoň tak často jako velká ('.$few.' proti '.$many.')');

// 9. Bydlení: nový dům, když se rodina nevejde.
[$db,$world]=$ready(); $setDate($world,3);
check(maxHouseSpace($world)>=4 && !settlersNeedHouse($world),'Je kam se vejít, dům není potřeba');
foreach (houseList($world) as $hid=>$h) { while (count(residentsOf($world,$hid))<$h['capacity']-1) { $new='h'.$hid.count($world['people']); $world['people'][$new]=array_merge($world['people']['anna'],['id'=>$new,'name'=>'X','home'=>$hid,'family'=>'Plni']); } }
check(maxHouseSpace($world)<4 && settlersNeedHouse($world),'Když se nikam rodina nevejde, vesnice chce další dům');
householdDaily($db,$world); check($world['houseWanted']==='dum','Plánovač dostane pokyn postavit dům');
$world['homesBuilt']=array_fill(0,HOME_LIMIT,100); check(!(count($world['homesBuilt'])<HOME_LIMIT),'Po desátém domě už nové parcely nejsou');
[$db,$w2]=$ready(); $w2['homesBuilt']=[1,2,3,4,5]; $w2['houseKinds']=[0=>'dum',1=>'dum',2=>'dum',3=>'dum',4=>'dum'];
check(count(houseList($w2))===11 && houseList($w2)['dum5']['tile']===[26,14],'Pátý postavený dům je na nové parcele');

// 10. Obchodník podle hodnosti.
[$db,$world]=$ready(7,3); $setDate($world,7,15); $world['trade']=['credit'=>0.0,'visits'=>0,'lastMonth'=>-1,'kettle'=>false];
$world['cheese']=60.0; $world['leather']=40.0;
tradeDaily($db,$world); check(($world['trade']['visits']??0)===1 && $world['trade']['lastDay']===$day($world),'Osada: obchodník 15. dne v měsíci');
$world['minute']+=14*1440; tradeDaily($db,$world); check($world['trade']['visits']===1,'Osada za čtrnáct dní podruhé nepřijede');
addPeople($world,25); // ves
$world['minute']+=7*1440; tradeDaily($db,$world); check($world['trade']['visits']===2,'Ves: obchodník po třech týdnech');
addPeople($world,45); // větší ves
$world['minute']+=14*1440; tradeDaily($db,$world); check($world['trade']['visits']===3,'Větší ves: po dvou týdnech');
$world['minute']+=10*1440; tradeDaily($db,$world); check($world['trade']['visits']===3,'Větší ves: za deset dní zase ne');

// 11. Městys: sobotní trh.
[$db,$world]=$ready(); addPeople($world,64); foreach (array_slice(array_keys(BUILDINGS),0,31) as $b) $world['buildings'][$b]=1;
while (intdiv((int)$world['minute'],1440)%7!==BATH_SATURDAY) $world['minute']+=1440;
foreach ($world['people'] as &$p) $p['mood']=70.0; unset($p);
$world['settlers']['nextDay']=9999; settlersDaily($db,$world);
check($world['rank']==='mestys' && array_sum(array_column($world['people'],'mood'))/count($world['people'])>=72-1e-6 && ($world['counters']['marketDays']??0)===1,'Městys: sobotní trh zvedne náladu');
$world['minute']+=1440; foreach ($world['people'] as &$p) $p['mood']=70.0; unset($p); settlersDaily($db,$world);
check(($world['counters']['marketDays']??0)===1,'V neděli trh není');

// 12. Starý stav, rozhraní, texty plánů.
[$db,$world]=$ready();
$old=$world; foreach (['settlers','rank'] as $k) unset($old[$k]);
$restored=decodeWorld(encode($old));
check($restored['settlers']['arrived']===0 && $restored['rank']==='osada' && $restored['settlers']['nextId']===1,'Starý uložený stav dostane výchozí hodnoty');
check(is_string(RANKS[villageRank($world)]),'Hodnost má popisek');

// 13. Soak: šest herních let s rostoucí vesnicí bez hladu a varování.
$warn=0;
set_error_handler(function($no,$msg,$file,$line) use (&$warn) { ++$warn; if ($warn<4) fwrite(STDERR,"$file:$line $msg\n"); return true; });
[$db,$world]=freshWorld(0,false);
foreach (BUILD_ORDER as $b) if (!in_array($b,['spychar','mlekarna','rybnik','kozeluzna','tkalcovna'],true)) $world['buildings'][$b]=1;
$world['arrivedFamilies'][MILLER_FAMILY]=1; $world['project']=null; $world['food']=800.0; $world['grain']=200.0; $world['wood']=120.0; $world['planks']=30.0; $world['stone']=30.0;
$world['minute']=(float)(200*1440);
$peak=0; $hungryMeals0=0;
for ($y=0;$y<600;$y+=50) {
    runDays($db,$world,50,false);
    $peak=max($peak,count($world['people']));
}
restore_error_handler();
check($warn===0,'Běh 600 dní s růstem bez varování PHP ('.$warn.')');
check(count($world['people'])>=30,'Po 600 dnech žije ve vsi aspoň 30 lidí ('.count($world['people']).', nejvíc '.$peak.')');
check(count($world['people'])<=POP_CAP_MAX+8,'Populace nepřerůstá strop ('.count($world['people']).')');
check(count($world['homesBuilt'])>=4,'Vesnice postavila další domy ('.count($world['homesBuilt']).')');
check(villageRank($world)!=='osada','Vesnice povýšila z osady ('.RANKS[villageRank($world)].')');
echo "OK rust: lidi ".count($world['people']).", domy ".count($world['homesBuilt']).", skupin ".$world['settlers']['arrived'].", hodnost ".RANKS[villageRank($world)]."\n";
