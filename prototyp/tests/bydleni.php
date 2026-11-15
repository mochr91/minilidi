<?php
declare(strict_types=1);

// Bydlení, zasnoubení, svatba v kapli a v kostele, stěhování při těsnu, dvojčata.
// Použití: php tests/bydleni.php   (bez modelu)
require __DIR__.'/fixture.php';

$setDate=function(array &$world,int $day,int $hour=8): void {
    $world['calendarOriginDay']=0;
    $world['minute']=(float)($day*1440+$hour*60);
};
// Prázdná vesnice se zadanými lidmi; domy jsou jen pevné, kdo je kde, určuje test.
[, $template]=freshWorld();
$TPL=$template['people']['karel'];
$person=function(array $world,string $id,string $name,int $age,string $sex,string $family,string $home,array $extra=[]) use ($TPL): array {
    $p=$TPL;
    $absDay=intdiv((int)$world['minute'],1440);
    $p=array_merge($p,['id'=>$id,'name'=>$name,'age'=>$age,'sex'=>$sex,'family'=>$family,'home'=>$home,'spouse'=>null,'pregnant'=>null,
        'lastBirth'=>-999999,'mother'=>null,'father'=>null,'bonds'=>[],'grudges'=>[],'task'=>null,'betrothed'=>null,
        'birthDay'=>$absDay-$age*360-crc32($id)%360,'illUntil'=>0,'awayUntil'=>0,'mood'=>70.0,'fun'=>50.0,'fatigue'=>10.0,'hunger'=>10.0,'place'=>'home']);
    unset($p['crush'],$p['wedding']);
    return array_merge($p,$extra);
};
$village=function(array $people) use ($person): array {
    [$db,$world]=freshWorld();
    $world['people']=[];
    $world['homesBuilt']=[]; $world['houseKinds']=[]; $world['houseWanted']=null; $world['houseProject']=null;
    $world['arrivedFamilies'][MILLER_FAMILY]=1;
    $world['food']=500.0; $world['wood']=100.0; $world['stone']=30.0; $world['planks']=30.0; $world['grain']=100.0;
    foreach ($people as $spec) { $p=$person($world,...$spec); $world['people'][$p['id']]=$p; }
    foreach ($world['people'] as $id=>&$p) foreach ($world['people'] as $oid=>$o) if ($oid!==$id) $p['bonds'][$oid]=60;
    unset($p);
    return [$db,$world];
};
$names=fn(array $ids)=>implode(',',$ids);

// 1. Domy: šest pevných (po šesti), postavené dům (6) nebo domek (4), kapacita bydlení.
[$db,$world]=$village([]);
$houses=houseList($world);
check(count($houses)===6 && array_sum(array_column($houses,'capacity'))===36 && housingCapacity($world)===36,'Šest pevných domů po šesti místech');
$world['homesBuilt']=[100,200]; $world['houseKinds']=[1=>'domek'];
$houses=houseList($world);
check($houses['dum1']['capacity']===6 && $houses['dum2']['capacity']===4 && $houses['dum2']['kind']==='domek' && housingCapacity($world)===46,'Postavený dům má 6 míst, domek 4');
check(houseLabel('dum2',$houses)==='nový domek č. 2' && houseLabel('Novákovi',$houses)==='dům rodiny Novákovi','Popisky domů');

// 2. Starý uložený stav bez `home`: lidé se rozdělí podle rodiny, mlynářova rodina do druhého postaveného domu.
[$db,$world]=freshWorld();
$world['homesBuilt']=[100,200];
$world['people']['vavrinec']=$person($world,'vavrinec','Vavřinec Mlynář',36,'m','Mlynářovi','x');
foreach ($world['people'] as &$p) unset($p['home']);
unset($p);
$restored=decodeWorld(encode($world));
check(homeOf($restored['people']['anna'])==='Kovářovi' && homeOf($restored['people']['karel'])==='Hruškovi' && homeOf($restored['people']['katerina'])==='Královi','Staré uložení: bydlí podle rodiny');
check(homeOf($restored['people']['vavrinec'])==='dum2','Staré uložení: mlynářova rodina v druhém postaveném domě');
check(count(residentsOf($restored,'Hruškovi'))===5,'Hruškovi bydlí v jednom domě');
$world2=$world; $world2['homesBuilt']=[];
$restored=decodeWorld(encode($world2));
check(isset(houseList($restored)[homeOf($restored['people']['vavrinec'])]),'Rodina bez postaveného domu dostane stojící dům');
$summary=housingSummary($restored);
check(count($summary)===6 && isset($summary[0]['label'],$summary[0]['residents']),'Souhrn bydlení pro rozhraní');

// 3. Zasnoubení místo okamžité svatby; bez manželství žádné těhotenství.
[$db,$world]=$village([['eliska','Eliška Malá',20,'f','Malí','Královi'],['petr','Petr Novák',23,'m','Novákovi','Novákovi']]);
$world['people']['eliska']['crush']='petr'; $world['people']['petr']['crush']='eliska';
foreach (['eliska','petr'] as $x) foreach (['eliska','petr'] as $y) if ($x!==$y) $world['people'][$x]['bonds'][$y]=80;
$done=false;
for ($d=1;$d<2000 && !$done;$d++) { checkRomance($db,$world,$d); $done=!empty($world['people']['eliska']['betrothed']); }
check($done && $world['people']['petr']['betrothed']==='eliska' && empty($world['people']['eliska']['spouse']) && empty($world['people']['petr']['spouse']),'Zamilovaný pár se zasnoubí, ještě nejsou manželé');
check(!isset($world['people']['eliska']['crush']) && (int)$db->query("SELECT COUNT(*) FROM journal WHERE title LIKE 'Zasnoubení%'")->fetchColumn()===1,'Kronika zapíše zasnoubení');
$conceived=false;
for ($d=1;$d<3000;$d++) { checkConception($db,$world,$d); if (!empty($world['people']['eliska']['pregnant'])) $conceived=true; }
check(!$conceived,'Bez manželství dítě nevznikne');
$before=count($db->query("SELECT 1 FROM journal WHERE title LIKE 'Zasnoubení%'")->fetchAll());
for ($d=2000;$d<2100;$d++) checkRomance($db,$world,$d);
check(count($db->query("SELECT 1 FROM journal WHERE title LIKE 'Zasnoubení%'")->fetchAll())===$before,'Zasnoubení se neopakuje');

// 4. Bydlení nového páru: u rodičů ženicha, u rodičů nevěsty, ve volném domě, jinde s místem, jinak nic.
$pair=function(array $extra=[]) use ($village): array {
    [$db,$world]=$village(array_merge([['eliska','Eliška Malá',20,'f','Malí','Královi'],['petr','Petr Novák',23,'m','Novákovi','Novákovi']],$extra));
    betroth($db,$world,'eliska','petr');
    return [$db,$world];
};
[$db,$world]=$pair();
$needs=[]; planWeddings($db,$world,$needs);
$plan=$world['people']['petr']['wedding']??null;
check($plan!==null && $plan['house']==='Novákovi' && $plan['movers']===['eliska'] && $world['people']['eliska']['wedding']===$plan && !$needs,'Pár bydlí u rodičů ženicha, pokud je místo');
check($plan['day']===intdiv((int)$world['minute'],1440)+WEDDING_LEAD_DAYS,'Svatba je za '.WEDDING_LEAD_DAYS.' dny');
$filler=function(string $house,int $n,int $from=1): array { $out=[]; for ($i=0;$i<$n;$i++) $out[]=['f'.$house.($from+$i),'Osoba '.$house.($from+$i),30+$i,$i%2?'m':'f','Rod'.$house,$house]; return $out; };
[$db,$world]=$pair($filler('Novákovi',5));
$needs=[]; planWeddings($db,$world,$needs);
$plan=$world['people']['petr']['wedding']??null;
check($plan!==null && $plan['house']==='Královi' && $plan['movers']===['petr'],'Plný dům ženicha: pár u rodičů nevěsty');
$extra=array_merge($filler('Novákovi',5),$filler('Královi',5));
[$db,$world]=$pair($extra);
$needs=[]; planWeddings($db,$world,$needs);
$plan=$world['people']['petr']['wedding']??null;
check($plan!==null && in_array($plan['house'],['Kovářovi','Doležalovi','Hruškovi','Veselí'],true) && count($plan['movers'])===2,'Oba domy plné: pár do volného domu ('.($plan['house']??'-').')');
$all=[]; foreach (array_keys(HOUSE_SITES) as $h) $all=array_merge($all,$filler($h,6));
[$db,$world]=$pair(array_slice($all,0,0));
foreach ($filler('Novákovi',6) as $spec) { $p=$person($world,...$spec); $world['people'][$p['id']]=$p; }
foreach ($filler('Královi',6) as $spec) { $p=$person($world,...$spec); $world['people'][$p['id']]=$p; }
foreach (array_keys(HOUSE_SITES) as $h) if (!in_array($h,['Novákovi','Královi'],true)) foreach ($filler($h,6) as $spec) { $p=$person($world,...$spec); $world['people'][$p['id']]=$p; }
foreach (['eliska','petr'] as $x) $world['people'][$x]['home']=$x==='eliska'?'Královi':'Novákovi';
$needs=[]; planWeddings($db,$world,$needs);
check(!isset($world['people']['petr']['wedding']) && $needs===[2],'Všechno plné: čeká se na bydlení, potřeba pro dva');
householdDaily($db,$world);
check($world['houseWanted']==='domek','Vesnice potřebuje domek pro pár');
check(houseKindWanted($world)==='domek' && houseWorkAvailable($world),'Domek se dá začít stavět');

// 5. Stavba domku: levnější a kratší; po dokončení je v seznamu se čtyřmi místy a poptávka zmizí.
check(homeCost(0,'domek')===['wood'=>8,'stone'=>4] && COTTAGE_WORK<HOUSE_WORK,'Domek je levnější a rychlejší než dům');
$world['people']['anna']=$person($world,'anna','Anna Kovářová',34,'f','Kovářovi','Kovářovi');
$world['wood']=100.0; $world['stone']=30.0;
$rounds=0;
while (count($world['homesBuilt'])===0 && $rounds<80) { ++$rounds; $who='anna'; assignWork($world,$who,'build_house',false); completeWork($db,$world,$who); $world['people'][$who]['task']=null; if ($rounds===1) check(($world['houseProject']['kind']??'')==='domek' && $world['houseProject']['work']===COTTAGE_WORK,'Projekt je domek'); }
check(count($world['homesBuilt'])===1 && ($world['houseKinds'][0]??'')==='domek' && $world['houseWanted']===null && houseList($world)['dum1']['capacity']===4,'Domek je hotový a má 4 místa');
check((int)$db->query("SELECT COUNT(*) FROM journal WHERE title='Dokončen nový domek'")->fetchColumn()===1,'Kronika zapíše domek');
$needs=[]; planWeddings($db,$world,$needs);
check(($world['people']['petr']['wedding']['house']??'')==='dum1' && !$needs,'Po dostavbě má pár kde bydlet');

// 6. Svatba: obřad v kapli, přesun do domu, hosté mají radost; počkat na termín; nemocní a zaneprázdnění odloží.
[$db,$world]=$pair();
$needs=[]; planWeddings($db,$world,$needs);
$plan=$world['people']['petr']['wedding'];
$setDate($world,$plan['day']-1,10);
weddingHour($db,$world);
check($world['people']['eliska']['task']===null,'Před termínem se nikdo nežení');
$setDate($world,$plan['day'],9);
weddingHour($db,$world);
check($world['people']['eliska']['task']===null,'Obřad je v 10 hodin');
$world['people']['petr']['illUntil']=(int)$world['minute']+3*1440;
$setDate($world,$plan['day'],10);
weddingHour($db,$world);
check($world['people']['eliska']['task']===null && $world['people']['petr']['wedding']['day']===$plan['day']+1 && $world['people']['eliska']['wedding']['day']===$plan['day']+1,'Nemocný ženich: svatba se odloží o den');
$world['people']['petr']['illUntil']=0;
$world['people']['eliska']['task']=['action'=>'rest','start'=>1,'end'=>999999];
$setDate($world,$plan['day']+1,10);
weddingHour($db,$world);
check($world['people']['petr']['task']===null,'Člověk s vlastním úkolem od hráče se neruší');
$world['people']['eliska']['task']=null;
$world['crisis']=['type'=>'fire'];
weddingHour($db,$world);
check($world['people']['petr']['task']===null,'Za krize se nežení');
$world['crisis']=null;
$setDate($world,$plan['day']+2,10);   // zaneprázdněný pár se odložil o další den
$guest=$person($world,'host','Host Dobrý',40,'m','Hostovi','Doležalovi'); $guest['bonds']=['petr'=>70,'eliska'=>70];
$world['people']['host']=$guest;
$stranger=$person($world,'cizi','Cizí Člověk',40,'m','Cizí','Doležalovi'); $stranger['bonds']=['petr'=>40,'eliska'=>40];
$world['people']['cizi']=$stranger;
$moodHost=$world['people']['host']['mood']; $moodStranger=$world['people']['cizi']['mood']; $faith0=$world['people']['petr']['faith'];
weddingHour($db,$world);
check(($world['people']['petr']['task']['action']??'')==='wedding' && ($world['people']['eliska']['task']['action']??'')==='wedding' && $world['people']['eliska']['task']['other']==='petr','Pár dostane úkol Svatba');
$task=$world['people']['petr']['task'];
check($task['end']-$task['start']===90 && ACTIONS['wedding']['place']==='chapel','Obřad trvá 90 minut v kapli');
check(!workAvailable($world,'host','wedding') && !isset(villageCandidates($world)['host']['wedding']),'Plánovač svatbu nenabízí');
$text=completeWork($db,$world,'petr');
$world['people']['petr']['task']=null;
check(!empty($world['people']['petr']['spouse']) && $world['people']['petr']['spouse']==='eliska' && $world['people']['eliska']['spouse']==='petr','Po obřadu jsou manželé');
check($world['people']['eliska']['family']==='Novákovi' && homeOf($world['people']['eliska'])==='Novákovi' && homeOf($world['people']['petr'])==='Novákovi','Nevěsta bydlí u manžela');
check(!isset($world['people']['petr']['betrothed']) && !isset($world['people']['petr']['wedding']) && !isset($world['people']['eliska']['wedding']),'Zasnoubení a plán zmizí');
check(str_contains($text,'v kapli') && (int)$db->query("SELECT COUNT(*) FROM journal WHERE title LIKE 'Svatba:%' AND detail LIKE '%v kapli%'")->fetchColumn()===1,'Kronika: obřad v kapli');
check($world['people']['petr']['faith']>$faith0 && $world['people']['host']['mood']>$moodHost && $world['people']['cizi']['mood']===$moodStranger,'Hosté s vazbou mají radost, cizí ne');
$text2=completeWork($db,$world,'eliska');
check(str_contains($text2,'už proběhla') && (int)$db->query("SELECT COUNT(*) FROM journal WHERE title LIKE 'Svatba:%'")->fetchColumn()===1,'Druhý úkol svatbu nezopakuje');
$world['people']['eliska']['task']=null;
// Kostel: obřad v kostele.
[$db,$world]=$pair();
$world['buildings']['kostel']=1;
$needs=[]; planWeddings($db,$world,$needs);
$setDate($world,$world['people']['petr']['wedding']['day'],10);
weddingHour($db,$world);
$text=completeWork($db,$world,'petr');
check(str_contains($text,'v kostele') && (int)$db->query("SELECT COUNT(*) FROM journal WHERE title LIKE 'Svatba:%' AND detail LIKE '%v kostele%'")->fetchColumn()===1,'S kostelem je obřad v kostele');
// Pár, který se stěhuje do volného domu.
$extra=array_merge($filler('Novákovi',6),$filler('Královi',6));
[$db,$world]=$pair($extra);
$needs=[]; planWeddings($db,$world,$needs);
$setDate($world,$world['people']['petr']['wedding']['day'],10);
weddingHour($db,$world);
completeWork($db,$world,'petr');
$h=homeOf($world['people']['petr']);
check($h===homeOf($world['people']['eliska']) && !in_array($h,['Novákovi','Královi'],true) && (int)$db->query("SELECT COUNT(*) FROM journal WHERE title='Novomanželé se stěhují'")->fetchColumn()===1,'Novomanželé se odstěhují do vlastního domu ('.$h.')');
// Smrt snoubence zruší zasnoubení.
[$db,$world]=$pair();
$needs=[]; planWeddings($db,$world,$needs);
removePerson($db,$world,'petr','nemoc');
check(empty($world['people']['eliska']['betrothed']) && !isset($world['people']['eliska']['wedding']),'Smrt snoubence zruší zasnoubení i svatbu');
// Snoubenec, který zmizel (stará data), se vyčistí.
[$db,$world]=$pair();
unset($world['people']['petr']);
$needs=[]; planWeddings($db,$world,$needs);
check(empty($world['people']['eliska']['betrothed']),'Zasnoubení s neexistujícím člověkem se vyčistí');

// 7. Těsno v domě: prarodiče se odstěhují do volného domu, do domu s místem, jinak se postaví domek.
$crowd=function() use ($village,$filler): array {
    // Dům Hruškovi: prarodiče 60 a 58, rodiče 38 a 36, tři děti a dítě navíc = 8 lidí v domě pro šest.
    return $village([
        ['ded','Dědeček Starý',60,'m','Hruškovi','Hruškovi'],['baba','Babička Stará',58,'f','Hruškovi','Hruškovi'],
        ['otec','Otec Hruška',38,'m','Hruškovi','Hruškovi'],['matka','Matka Hrušková',36,'f','Hruškovi','Hruškovi'],
        ['d1','Dítě Jedna',10,'m','Hruškovi','Hruškovi'],['d2','Dítě Dvě',7,'f','Hruškovi','Hruškovi'],['d3','Dítě Tři',3,'f','Hruškovi','Hruškovi'],
    ]);
};
[$db,$world]=$crowd();
$world['people']['ded']['spouse']='baba'; $world['people']['baba']['spouse']='ded'; $world['people']['otec']['spouse']='matka'; $world['people']['matka']['spouse']='otec';
foreach (['d1','d2','d3'] as $kid) { $world['people'][$kid]['mother']='matka'; $world['people'][$kid]['father']='otec'; }
check(count(residentsOf($world,'Hruškovi'))===7,'Sedm lidí v domě pro šest');
householdDaily($db,$world);
check($world['houseWanted']===null,'Volné domy jsou: nic se nestaví');
check(homeOf($world['people']['ded'])!=='Hruškovi' && homeOf($world['people']['ded'])===homeOf($world['people']['baba']) && count(residentsOf($world,'Hruškovi'))===5,'Těsno: prarodiče se odstěhují do volného domu');
check((int)$db->query("SELECT COUNT(*) FROM journal WHERE title LIKE 'Prarodiče se stěhují%'")->fetchColumn()===1,'Kronika zapíše stěhování prarodičů');
check(homeOf($world['people']['otec'])==='Hruškovi' && homeOf($world['people']['d3'])==='Hruškovi','Rodiče a děti zůstanou');
// Prarodiče raději k příbuzným s místem než do prázdného domu: dcera bydlí jinde.
[$db,$world]=$crowd();
$world['people']['ded']['spouse']='baba'; $world['people']['baba']['spouse']='ded';
$world['people']['dcera']=$person($world,'dcera','Dcera Dobrá',30,'f','Dobrých','Doležalovi',['mother'=>'baba','father'=>'ded']);
$world['people']['dcera']['bonds']=[];
householdDaily($db,$world);
check(homeOf($world['people']['ded'])==='Doležalovi','Prarodiče se přestěhují k dceři, ne do prázdného domu');
// Žádný volný dům a žádné místo: potřeba domku pro dva; po dostavbě se prarodiče odstěhují.
[$db,$world]=$crowd();
$world['people']['ded']['spouse']='baba'; $world['people']['baba']['spouse']='ded';
foreach (array_keys(HOUSE_SITES) as $h) if ($h!=='Hruškovi') foreach ($filler($h,6) as $spec) { $p=$person($world,...$spec); $world['people'][$p['id']]=$p; }
householdDaily($db,$world);
check($world['houseWanted']==='domek' && homeOf($world['people']['ded'])==='Hruškovi','Bez volného místa vesnice potřebuje domek pro prarodiče');
$world['homesBuilt']=[(int)$world['minute']]; $world['houseKinds']=[0=>'domek']; $world['houseWanted']=null;
householdDaily($db,$world);
check(homeOf($world['people']['ded'])==='dum1' && homeOf($world['people']['baba'])==='dum1' && $world['houseWanted']===null,'Po dostavbě domku se prarodiče odstěhují');
// Při čtyřech postavených domech se nic dalšího nestaví.
[$db,$world]=$crowd();
foreach (array_keys(HOUSE_SITES) as $h) if ($h!=='Hruškovi') foreach ($filler($h,6) as $spec) { $p=$person($world,...$spec); $world['people'][$p['id']]=$p; }
$world['people']['ded']['spouse']='baba'; $world['people']['baba']['spouse']='ded';
$world['homesBuilt']=[1,2,3,4];
for ($i=1;$i<=4;$i++) foreach ($filler('dum'.$i,6) as $spec) { $p=$person($world,...$spec); $world['people'][$p['id']]=$p; }
householdDaily($db,$world);
check($world['houseWanted']===null,'Strop čtyř nových domů: žádná další poptávka');
// Mladý pár s dítětem se odstěhuje, když nejsou prarodiče.
[$db,$world]=$village([
    ['otec','Otec Starý',45,'m','Starých','Hruškovi'],['matka','Matka Stará',43,'f','Starých','Hruškovi'],
    ['k1','Dítě Jedna',15,'m','Starých','Hruškovi',['father'=>'otec','mother'=>'matka']],['k2','Dítě Dvě',12,'f','Starých','Hruškovi',['father'=>'otec','mother'=>'matka']],
    ['syn','Syn Starý',24,'m','Starých','Hruškovi',['father'=>'otec','mother'=>'matka']],['snacha','Snacha Stará',22,'f','Starých','Hruškovi'],
    ['vnuk','Vnuk Starý',1,'m','Starých','Hruškovi',['father'=>'syn','mother'=>'snacha']],
]);
$world['people']['otec']['spouse']='matka'; $world['people']['matka']['spouse']='otec'; $world['people']['syn']['spouse']='snacha'; $world['people']['snacha']['spouse']='syn';
householdDaily($db,$world);
check(homeOf($world['people']['syn'])!=='Hruškovi' && homeOf($world['people']['syn'])===homeOf($world['people']['snacha']) && homeOf($world['people']['vnuk'])===homeOf($world['people']['syn']) && homeOf($world['people']['k1'])==='Hruškovi','Těsno: mladý pár s dítětem se odstěhuje, sourozenci zůstanou');
// Šestičlenná rodina bez dalších párů se nehýbe, ani když je sedm v domě (jen se nezačne další těhotenství).
[$db,$world]=$village([
    ['otec','Otec Starý',40,'m','Starých','Hruškovi'],['matka','Matka Stará',38,'f','Starých','Hruškovi'],
    ['a','Dítě A',14,'m','Starých','Hruškovi'],['b','Dítě B',12,'f','Starých','Hruškovi'],['c','Dítě C',9,'f','Starých','Hruškovi'],
    ['d','Dítě D',6,'m','Starých','Hruškovi'],['e','Dítě E',0,'m','Starých','Hruškovi'],
]);
$world['people']['otec']['spouse']='matka'; $world['people']['matka']['spouse']='otec';
householdDaily($db,$world);
check(count(residentsOf($world,'Hruškovi'))===7 && $world['houseWanted']===null,'Rodina se sedmi lidmi se nestěhuje (není kdo)');
// Domek s pěti lidmi: celá domácnost se přestěhuje do volného velkého domu.
[$db,$world]=$village([
    ['otec','Otec Starý',32,'m','Starých','dum1'],['matka','Matka Stará',30,'f','Starých','dum1'],
    ['a','Dítě A',8,'m','Starých','dum1',['father'=>'otec','mother'=>'matka']],['b','Dítě B',6,'f','Starých','dum1',['father'=>'otec','mother'=>'matka']],['c','Dítě C',0,'f','Starých','dum1',['father'=>'otec','mother'=>'matka']],
]);
$world['people']['otec']['spouse']='matka'; $world['people']['matka']['spouse']='otec';
$world['homesBuilt']=[1]; $world['houseKinds']=[0=>'domek'];
householdDaily($db,$world);
$h=homeOf($world['people']['otec']);
check($h!=='dum1' && $h===homeOf($world['people']['c']) && houseList($world)[$h]['capacity']===6,'Domek je pro pět lidí malý: rodina do velkého domu ('.$h.')');

// 7b. Nezletilé dítě nezůstane po odchodu matky samo: stěhuje se s ní; děti bez dospělého jdou k příbuzným.
[$db,$world]=$village([
    ['anna','Anna Kovářová',34,'f','Kovářovi','Kovářovi'],['matyas','Matyáš Kovář',15,'m','Kovářovi','Kovářovi',['mother'=>'anna']],
    ['petr','Petr Novák',36,'m','Novákovi','Novákovi'],
]);
betroth($db,$world,'anna','petr');
$needs=[]; planWeddings($db,$world,$needs);
$plan=$world['people']['petr']['wedding']??null;
check($plan!==null && $plan['house']==='Novákovi' && in_array('matyas',$plan['movers'],true) && in_array('anna',$plan['movers'],true),'Matka s dítětem: syn jde s ní, ne sám do prázdného domu');
$setDate($world,$plan['day'],10);
weddingHour($db,$world);
completeWork($db,$world,'petr');
check(homeOf($world['people']['matyas'])==='Novákovi' && homeOf($world['people']['anna'])==='Novákovi' && count(residentsOf($world,'Kovářovi'))===0,'Po svatbě se přestěhuje i syn, dům Kovářovi je prázdný');
[$db,$world]=$village([
    ['sirotek','Sirotek Malý',10,'m','Malých','Hruškovi',['mother'=>'teta']],['sestra','Sestra Malá',8,'f','Malých','Hruškovi',['mother'=>'teta']],
    ['teta','Teta Dobrá',35,'f','Malých','Doležalovi'],
]);
householdDaily($db,$world);
check(homeOf($world['people']['sirotek'])==='Doležalovi' && homeOf($world['people']['sestra'])==='Doležalovi' && (int)$db->query("SELECT COUNT(*) FROM journal WHERE title LIKE 'Děti bez dospělého%'")->fetchColumn()===1,'Děti bez dospělého se přestěhují k příbuzné');
// 8. Narození: dítě bydlí u matky; dvojčata, mrtvé dítě a smrt matky podle čísla narození.
$findN=function(callable $ok): int { for ($n=1;$n<2000000;$n++) if ($ok($n)) return $n; throw new RuntimeException('Nenalezeno číslo narození'); };
$mother=function(array &$world,int $n) {
    $world['nextChild']=$n-1;
    $world['people']['matka']['pregnant']=['since'=>0,'due'=>1,'father'=>'otec','revealed'=>true];
};
$base=function() use ($village): array {
    [$db,$world]=$village([['otec','Otec Novák',30,'m','Novákovi','Novákovi'],['matka','Matka Nováková',28,'f','Novákovi','Novákovi']]);
    $world['people']['otec']['spouse']='matka'; $world['people']['matka']['spouse']='otec';
    return [$db,$world];
};
$twinsAlive=$findN(fn($n)=>twinBirth($n) && weatherNoise($n,'newborn-death')>=.02 && weatherNoise($n+1,'newborn-death')>=.02 && weatherNoise($n,'mother-death')>=.03);
[$db,$world]=$base(); $mother($world,$twinsAlive);
giveBirth($db,$world,'matka');
check(isset($world['people']['d'.$twinsAlive],$world['people']['d'.($twinsAlive+1)]) && $world['nextChild']===$twinsAlive+1,'Dvojčata: dvě děti s po sobě jdoucími čísly');
check(homeOf($world['people']['d'.$twinsAlive])==='Novákovi' && homeOf($world['people']['d'.($twinsAlive+1)])==='Novákovi' && $world['people']['d'.$twinsAlive]['mother']==='matka','Děti bydlí u matky');
check(($world['counters']['births']??0)===2 && ($world['counters']['twins']??0)===1,'Dvojčata se počítají jako dvě narození a jedno dvojčatství');
check((int)$db->query("SELECT COUNT(*) FROM journal WHERE title LIKE 'Narodila se dvojčata%'")->fetchColumn()===1,'Kronika: dvojčata');
$single=$findN(fn($n)=>!twinBirth($n) && weatherNoise($n,'newborn-death')>=.02 && weatherNoise($n,'mother-death')>=.01);
[$db,$world]=$base(); $mother($world,$single);
giveBirth($db,$world,'matka');
check(isset($world['people']['d'.$single]) && !isset($world['people']['d'.($single+1)]) && $world['nextChild']===$single && ($world['counters']['births']??0)===1,'Jedno dítě jako dřív');
$twinOneDead=$findN(fn($n)=>twinBirth($n) && (weatherNoise($n,'newborn-death')>=.02) !== (weatherNoise($n+1,'newborn-death')>=.02) && weatherNoise($n,'mother-death')>=.03);
[$db,$world]=$base(); $mother($world,$twinOneDead);
giveBirth($db,$world,'matka');
check((isset($world['people']['d'.$twinOneDead]) xor isset($world['people']['d'.($twinOneDead+1)])) && count($world['graveyard'])===1 && $world['graveyard'][0]['cause']==='porod','Jedno z dvojčat se narodí mrtvé');
$still=$findN(fn($n)=>!twinBirth($n) && weatherNoise($n,'newborn-death')<.02 && weatherNoise($n,'mother-death')>=.01);
[$db,$world]=$base(); $mother($world,$still);
giveBirth($db,$world,'matka');
check(!isset($world['people']['d'.$still]) && count($world['graveyard'])===1 && isset($world['people']['matka']),'Mrtvě narozené dítě');
$riskTwin=$findN(fn($n)=>twinBirth($n) && weatherNoise($n,'mother-death')>=.01 && weatherNoise($n,'mother-death')<.03);
[$db,$world]=$base(); $mother($world,$riskTwin);
giveBirth($db,$world,'matka');
check(!isset($world['people']['matka']),'Při dvojčatech je riziko pro matku trojnásobné');
$riskSingle=$findN(fn($n)=>!twinBirth($n) && weatherNoise($n,'mother-death')>=.01 && weatherNoise($n,'mother-death')<.03);
[$db,$world]=$base(); $mother($world,$riskSingle);
giveBirth($db,$world,'matka');
check(isset($world['people']['matka']),'Při jednom dítěti je riziko 1 %');
$dies=$findN(fn($n)=>!twinBirth($n) && weatherNoise($n,'mother-death')<.01);
[$db,$world]=$base(); $mother($world,$dies);
giveBirth($db,$world,'matka');
check(!isset($world['people']['matka']),'Matka při porodu může zemřít');
// Příchod dalšího dítěte do plného domu: těsno se řeší další den.
// 9. Početí se v přeplněném domě nezačne.
$day=$findN(fn($d)=>weatherNoise($d,'conceive:matka')<.003);
[$db,$world]=$base();
$world['people']['matka']['lastBirth']=-999999;
checkConception($db,$world,$day);
check(!empty($world['people']['matka']['pregnant']),'V domě s místem se dítě počne');
[$db,$world]=$base();
foreach ($filler('Novákovi',6) as $spec) { $p=$person($world,...$spec); $world['people'][$p['id']]=$p; }
checkConception($db,$world,$day);
check(empty($world['people']['matka']['pregnant']),'V přeplněném domě se dítě nepočne');

// 10. Příchozí a mlynářova rodina bydlí ve volném domě.
[$db,$world]=$village([['anna','Anna Kovářová',34,'f','Kovářovi','Kovářovi']]);
$setDate($world,30);
welcomeNewcomer($db,$world);
check(!isset($world['people']['novy1']),'Před '.NEWCOMER_FIRST_DAY.'. dnem nikdo nepřichází');
$setDate($world,NEWCOMER_FIRST_DAY);
welcomeNewcomer($db,$world);
check(isset($world['people']['novy1']) && homeOf($world['people']['novy1'])==='Novákovi' && $world['newcomers']===1,'První příchozí bydlí v domě svého příjmení');
welcomeNewcomer($db,$world);
check(!isset($world['people']['novy2']),'Další příchozí až po '.NEWCOMER_GAP_DAYS.' dnech');
$setDate($world,NEWCOMER_FIRST_DAY+NEWCOMER_GAP_DAYS);
welcomeNewcomer($db,$world);
check(isset($world['people']['novy2']) && homeOf($world['people']['novy2'])==='Veselí','Druhý příchozí do domu Veselí');
$setDate($world,NEWCOMER_FIRST_DAY+2*NEWCOMER_GAP_DAYS);
welcomeNewcomer($db,$world);
check(isset($world['people']['novy3']) && homeOf($world['people']['novy3'])!=='Hrubí' && isset(houseList($world)[homeOf($world['people']['novy3'])]),'Třetí příchozí dostane volný stojící dům ('.homeOf($world['people']['novy3']).')');
foreach (array_keys(HOUSE_SITES) as $h) if (empty(residentsOf($world,$h))) { $p=$person($world,'z'.$h,'Zábor '.$h,40,'m','Z'.$h,$h); $world['people'][$p['id']]=$p; }
$setDate($world,NEWCOMER_FIRST_DAY+3*NEWCOMER_GAP_DAYS);
$count=count($world['people']);
welcomeNewcomer($db,$world);
check(count($world['people'])===$count && !isset($world['people']['novy4']),'Bez volného domu nikdo nepřichází');
$world['homesBuilt']=[(int)$world['minute']-1000];
welcomeNewcomer($db,$world);
check(isset($world['people']['novy4']) && homeOf($world['people']['novy4'])==='dum1','Po dostavbě domu přijde další a bydlí v něm');
// Mlynářova rodina: potřebuje volný dům pro pět lidí, nejnovější postavený.
[$db,$world]=$village([['anna','Anna Kovářová',34,'f','Kovářovi','Kovářovi']]);
unset($world['arrivedFamilies'][MILLER_FAMILY]);
$world['homesBuilt']=[1000,2000]; $world['minute']=(float)(5*1440);
welcomeMillerFamily($db,$world);
check(isset($world['people']['vavrinec']) && homeOf($world['people']['vavrinec'])==='dum2' && count(residentsOf($world,'dum2'))===5,'Mlynářovi bydlí v nejnovějším volném domě');
[$db,$world]=$village([['anna','Anna Kovářová',34,'f','Kovářovi','Kovářovi']]);
unset($world['arrivedFamilies'][MILLER_FAMILY]);
$world['homesBuilt']=[1000,2000]; $world['minute']=(float)(5*1440);
foreach (array_keys(HOUSE_SITES) as $h) { $p=$person($world,'z'.$h,'Zábor '.$h,40,'m','Z'.$h,$h); $world['people'][$p['id']]=$p; }
foreach (['dum1','dum2'] as $h) { $p=$person($world,'z'.$h,'Zábor '.$h,40,'m','Z'.$h,$h); $world['people'][$p['id']]=$p; }
welcomeMillerFamily($db,$world);
check(!isset($world['people']['vavrinec']),'Bez volného domu pro pět lidí mlynářova rodina nepřijde');

// 11. Plánovač nabízí stavbu domu jen při potřebě; nic o ní neřeknou ani úkoly.
[$db,$world]=$village([['anna','Anna Kovářová',34,'f','Kovářovi','Kovářovi']]);
$world['wood']=200.0; $world['stone']=100.0; $world['planks']=100.0;
check(!houseWorkAvailable($world) && houseKindWanted($world)===null,'Bez potřeby se nestaví');
unset($world['arrivedFamilies'][MILLER_FAMILY]);
check(houseKindWanted($world)==='dum' && houseWorkAvailable($world),'Čekající mlynářova rodina spustí stavbu domu');
$world['arrivedFamilies'][MILLER_FAMILY]=1;
$world['houseWanted']='domek'; $world['wood']=0.0;
check(isset(materialNeeds($world)['wood']) && !houseWorkAvailable($world),'Na domek se chystá materiál a bez něj se nezačne');

// 12. Běh: vesnice s lidmi 300 dní bez varování a bez nesmyslných stavů.
$seen=[]; set_error_handler(function($no,$msg,$file,$line) use (&$seen){ $seen[]=basename($file).':'.$line.' '.$msg; return true; });
[$db,$world]=freshWorld(0,false);
$world['minute']=382.0;
$world['food']=1500.0; $world['wood']=300.0; $world['stone']=60.0; $world['planks']=60.0; $world['grain']=200.0;
runDays($db,$world,300);
restore_error_handler();
check(!$seen,'300 dní bez varování PHP: '.implode('; ',array_slice($seen,0,3)));
$houses=houseList($world);
foreach ($world['people'] as $id=>$p) check(isset($houses[homeOf($p)]),'Každý bydlí ve stojícím domě ('.$id.' v '.homeOf($p).')');
foreach ($houses as $hid=>$h) check(count(residentsOf($world,$hid))<=$h['capacity']+3,'Dům '.$hid.' není absurdně přeplněný ('.count(residentsOf($world,$hid)).')');
echo "Bydlení: domy, zasnoubení, svatba, stěhování, domek, dvojčata a běh OK\n";
