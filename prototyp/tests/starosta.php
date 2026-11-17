<?php
declare(strict_types=1);

// Fáze K: starosta a volby (povaha, schůze, hlasování, zásada, účinky, souhlas, odvolání, bezvládí).
// Použití: php tests/starosta.php   (bez modelu)
require __DIR__.'/fixture.php';

$warnings=[];
set_error_handler(function($no,$msg,$file,$line) use (&$warnings){ $warnings[]=basename($file).':'.$line.' '.$msg; return true; });
error_reporting(E_ALL);

$at=function(array &$world,int $day,int $hour=8): void { $world['minute']=(float)($day*1440+$hour*60); $world['calmUntil']=$world['minute']+99999999; };
// Vesnice s $total lidmi, všichni zdraví a bez úkolů; přidaní jsou dospělí 25 až 44 let.
$ready=function(int $total=24): array {
    [$db,$world]=freshWorld();
    addPeople($world,$total);
    $absDay=intdiv((int)$world['minute'],1440);
    foreach ($world['people'] as $id=>&$p) {
        if (str_starts_with($id,'x')) { $p['age']=25+(int)substr($id,1)%20; $p['birthDay']=$absDay-$p['age']*360-crc32($id)%360; }
        $p['fatigue']=10.0; $p['hunger']=10.0; $p['mood']=60.0; $p['fear']=10; $p['illUntil']=0; $p['task']=null; $p['awayUntil']=0;
        $p['skills']=array_fill_keys(array_keys(CATEGORIES),50.0);
    }
    unset($p);
    foreach ($world['people'] as $id=>&$p) foreach ($world['people'] as $o=>$q) if ($o!==$id) $p['bonds'][$o]=48;
    unset($p);
    $world['food']=600.0; $world['grain']=300.0; $world['wood']=100.0; $world['hay']=200.0; $world['project']=null; $world['houseProject']=null;
    $world['crisis']=null;
    return [$db,$world];
};
$temper=function(array &$world,string $id,float $r,float $s): void { $world['people'][$id]['reason']=$r; $world['people'][$id]['stubborn']=$s; };
$mayorOf=function(array &$world,string $id,string $policy='zasoby',int $day=10): void {
    $world['mayor']=['id'=>$id,'since'=>$day,'until'=>$day+MAYOR_TERM_DAYS,'policy'=>$policy,'policyDay'=>$day,'approval'=>55,'lowDays'=>0,'graceUntil'=>$day+MAYOR_GRACE_DAYS,'lastRound'=>-1,'terms'=>1,'foodShort'=>false];
    $world['election']=['next'=>$day+MAYOR_TERM_DAYS,'kind'=>'election','heldDay'=>-1,'last'=>null,'tries'=>0];
};
$present=fn(array $w)=>array_values(array_filter(array_keys($w['people']),fn($id)=>$w['people'][$id]['age']>=18));

// 1. Definice a rozhraní.
foreach (MAYOR_TASKS as $key) check(isset(ACTIONS[$key]) && isset(ACTION_CATEGORY[$key]),"$key má akci a obor");
check(isset(TASK_STEMS['mayor_round']) && isset(GOALS['mayor_elected']),'Kmen pro plánovač a cíl starosty');
foreach (MAYOR_POLICIES as $key=>$def) foreach ($def['keys'] as $k) check(isset(ACTIONS[$k]),"Zásada $key: činnost $k existuje");
check(array_keys(MAYOR_TYPES)===['rozumny','vyvazeny','palicaty'] && MAYOR_TYPES['palicaty']['quarrel']>1 && MAYOR_TYPES['rozumny']['quarrel']<1,'Typy starosty');
check(ACTIONS['assembly']['place']==='market' && ACTIONS['assembly']['minutes']===90,'Schůze je na návsi, 90 minut');

// 2. Povaha z textu a ze zrnka, děti po rodičích.
[$db,$world]=$ready(24);
[$ar,$as]=personTemper($world,$world['people']['anna']); [$jr,$js]=personTemper($world,$world['people']['jan']);
check($ar>$as && temperType($ar,$as)==='rozumny','Anna „rozvážná“ je rozumná');
check($js>$jr && temperType($jr,$js)==='palicaty','Jan „paličatý“ je paličatý');
foreach ($world['people'] as $id=>$p) { [$r,$s]=personTemper($world,$p); check($r>=5 && $r<=95 && $s>=5 && $s<=95,"Hodnoty povahy $id v mezích"); }
check(personTemper($world,$world['people']['x3'])===personTemper($world,$world['people']['x3']),'Povaha je pro stejnou osobu stejná');
check(temperType(50,64)==='vyvazeny' && temperType(50,65)==='palicaty' && temperType(65,50)==='rozumny' && temperType(50,50)==='vyvazeny','Hranice typů');
check(!isset($world['people']['x3']['reason']),'Před denní obsluhou povaha uložena není');
$world['people']['kid']=array_merge($world['people']['x1'],['id'=>'kid','age'=>3,'mother'=>'anna','father'=>'jan','trait'=>'Dítě.']);
unset($world['people']['kid']['reason'],$world['people']['kid']['stubborn']);
$temper($world,'anna',80,20); $temper($world,'jan',20,80);
[$kr,$ks]=personTemper($world,$world['people']['kid']);
check(abs($kr-50)<=10.5 && abs($ks-50)<=10.5,'Dítě dědí průměr rodičů ±10');
mayorSetTempers($world);
foreach ($world['people'] as $id=>$p) check(isset($p['reason'],$p['stubborn']),"Povaha uložena u $id");
check($world['people']['anna']['reason']===80.0,'Uložená hodnota se nepřepisuje');

// 3. Volby: bez dospělých nic, první neděle po nasazení, schůze svolána ve 11.
[$db,$few]=freshWorld();
$at($few,5); mayorDaily($db,$few);
check($few['election']['next']%7===6 && $few['election']['next']>5,'První volby jsou v neděli po nasazení');
$few['election']['next']=0; $at($few,8,11); mayorHour($db,$few);
check(!array_filter(array_column($few['people'],'task')),'Pod 8 dospělých se schůze nesvolá');
[$db,$world]=$ready(24);
$at($world,5); mayorDaily($db,$world);
$next=$world['election']['next']; check($next%7===6 && $next>5 && $world['anarchySince']===5,'Bezvládí se zapíše, volby jsou v neděli');
$at($world,$next-1,11); mayorHour($db,$world); check(!array_filter(array_column($world['people'],'task')),'Před termínem se schůze nesvolá');
$at($world,$next,10); mayorHour($db,$world); check(!array_filter(array_column($world['people'],'task')),'Mimo 11. hodinu se schůze nesvolá');
$world['crisis']=['type'=>'fire','until'=>999999999]; $at($world,$next,11); mayorHour($db,$world); check(!array_filter(array_column($world['people'],'task')),'V krizi se schůze nesvolá');
$world['crisis']=null;
$at($world,$next,11); mayorHour($db,$world);
$tasks=array_filter(array_column($world['people'],'task'));
$attend=array_keys(array_filter($world['people'],fn($p)=>($p['task']['action']??null)==='assembly'));
check(count($attend)>=count($present($world))-1 && !array_filter($attend,fn($id)=>$world['people'][$id]['age']<18),'Na schůzi jdou všichni dospělí');
check($world['people'][$attend[0]]['task']['end']-$world['people'][$attend[0]]['task']['start']===90,'Schůze trvá 90 minut');

// 4. Průběh volby: dokončí první účastník, ostatní už ne.
$kids=array_keys(array_filter($world['people'],fn($p)=>$p['age']<18));
$world['minute']+=90;
$first=$attend[0];
$result=completeWork($db,$world,$first);
check($world['mayor']!==null && isset($world['people'][$world['mayor']['id']]) && str_starts_with($result,'Zvolen'),'Schůze zvolila starostu');
$mid=$world['mayor']['id'];
check($world['mayor']['until']-$world['mayor']['since']===MAYOR_TERM_DAYS && $world['election']['next']===$world['mayor']['until'],'Funkční období 720 dnů');
check($world['anarchySince']===null && $world['election']['heldDay']===$next,'Konec bezvládí a značka schůze');
$last=$world['election']['last'];
check($last['kind']==='election' && $last['winner']===$mid && $last['voted']<=$last['turnout'] && array_sum(array_column($last['tally'],'votes'))===$last['voted'],'Výsledek a součet hlasů');
check(count($last['tally'])>=1 && count($last['tally'])<=MAYOR_MAX_CANDIDATES,'Nejvýš tři kandidáti');
check($last['turnout']===count($attend)+(in_array($first,$attend,true)?0:1),'Účast = ti, kdo mají úkol schůze');
check(completeWork($db,$world,$attend[1])==='Schůze už skončila.' && $world['mayor']['id']===$mid,'Druhý účastník schůzi už nehodnotí');
$story=$db->query("SELECT title,detail FROM journal WHERE title='Volby starosty'")->fetch(PDO::FETCH_ASSOC);
check($story && str_contains($story['detail'],$world['people'][$mid]['name']) && str_contains($story['detail'],'Hlasy'),'Kronika zapíše volby');
check(goalValue($world,'mayor_elected')===1.0,'Cíl „Zvolený starosta“ čte stav');
foreach ($attend as $id) check($world['people'][$id]['mood']>=60,'Účast na schůzi náladu nesníží');
$kid=$kids[0]??null;

// 5. Hlas podle povahy: klid dává rozumnému, krize paličatému.
$tally=function(array $w,array $cands,int $day) use ($present) {
    $t=array_fill_keys($cands,0);
    foreach ($present($w) as $v) {
        if (in_array($v,$cands,true)) continue;
        $best=null; $bs=-INF;
        foreach ($cands as $c) { $s=voteScore($w,$v,$c,$day); if ($s>$bs) { $bs=$s; $best=$c; } }
        ++$t[$best];
    }
    return $t;
};
[$db,$world]=$ready(40);
$temper($world,'anna',85,15); $temper($world,'jan',15,85);
$calm=$tally($world,['anna','jan'],20);
check($calm['anna']>$calm['jan'],'V klidu vyhrává rozumný ('.$calm['anna'].':'.$calm['jan'].')');
$world['crisis']=['type'=>'fire','until'=>999999999]; $world['food']=1.0;
foreach ($world['people'] as &$p) { $p['fear']=70; $p['mood']=30.0; $p['hunger']=70; }
unset($p);
$stress=$tally($world,['anna','jan'],20);
check($stress['jan']>$stress['anna'],'V nouzi vyhrává paličatý ('.$stress['jan'].':'.$stress['anna'].')');
$world['crisis']=null; $world['food']=600.0;
foreach ($world['people'] as &$p) { $p['fear']=10; $p['mood']=60.0; $p['hunger']=10; }
unset($p);
// Důvěra a rodina přebijí povahu.
foreach ($present($world) as $v) { if ($v!=='anna' && $v!=='jan') $world['people'][$v]['bonds']['jan']=75; }
$trust=$tally($world,['anna','jan'],20);
check($trust['jan']>$trust['anna'],'Důvěra voličů v kandidáta převáží povahu');
check(voteScore($world,'jan','jan',20)>voteScore($world,'jan','anna',20)+10,'Kandidát volí sám sebe');
$world['people']['bara']['bonds']['anna']=48; $world['people']['x1']['family']=$world['people']['anna']['family'];
check(voteScore($world,'x1','anna',20)>voteScore($world,'x2','anna',20)+8,'Rodina dává hlas navíc');
$world['people']['x2']['grudges']['anna']=$world['minute']+1000;
check(voteScore($world,'x2','anna',20)<voteScore($world,'x4','anna',20)-8,'Zášť hlas ubírá');
mayorDaily($db,$world);   // povahy se uloží
// Kandidáti: věk, nejvýš 3, nikoho mladšího 25 let.
$cand=mayorCandidates($world,$present($world),20);
check(count($cand)>=1 && count($cand)<=3 && !array_filter($cand,fn($id)=>$world['people'][$id]['age']<MAYOR_CAND_MIN_AGE),'Kandidáti od 25 let, nejvýš tři');
check(mayorCandidates($world,[],20)===[],'Bez přítomných nikdo nekandiduje');

// 6. Zásada: rozumný podle potřeb, paličatý podle oboru.
[$db,$world]=$ready(24);
$temper($world,'anna',85,15); $temper($world,'jan',15,85);
$world['food']=5.0; $world['grain']=10.0;
check(mayorChoosePolicy($world,$world['people']['anna'],30)==='zasoby','Rozumný při nedostatku jídla volí zásoby');
check(mayorChoosePolicy($world,$world['people']['jan'],30)==='stavba','Paličatý dřevorubec volí stavbu a dřevo');
$world['food']=900.0; $world['grain']=500.0; foreach ($world['people'] as &$p) $p['mood']=25.0; unset($p);
check(mayorChoosePolicy($world,$world['people']['anna'],30)==='klid','Rozumný při špatné náladě volí klid');
check(mayorChoosePolicy($world,$world['people']['jan'],30)==='stavba','Paličatý drží svůj obor i při jiných potřebách');

// 7. Účinky starosty.
[$db,$world]=$ready(24);
$temper($world,'anna',85,15); $temper($world,'jan',15,85); $temper($world,'karel',50,52);
$none=array_fill_keys(['fieldwork','construct','prayer','firefight'],0);
foreach (array_keys($none) as $k) check(mayorScore($world,$k)===0 && mayorYield($world,$k)===1.0,"Bezvládí: žádný bonus ($k)");
check(mayorQuarrelFactor($world)===MAYOR_ANARCHY_ADULTS*0+1.15,'Bezvládí od 16 dospělých zvyšuje hádky o 15 %');
[$db,$small]=freshWorld(); check(mayorQuarrelFactor($small)===1.0,'Malá vesnice bez starosty bez trestu');
$mayorOf($world,'jan','stavba');
check(mayorScore($world,'construct')===10 && mayorScore($world,'prayer')===0 && mayorScore($world,'firefight')===10,'Paličatý: bonus zásady 10, v krizi 10');
check(abs(mayorYield($world,'construct')-1.04)<1e-9 && mayorYield($world,'prayer')===1.0,'Paličatý: výnos v zásadě +4 %');
check(abs(taskSkillFactor($world,'jan','construct')-skillFactor($world['people']['jan'],'build')*1.04)<1e-9,'Výnos práce se násobí');
check(mayorQuarrelFactor($world)===1.25,'Paličatý: hádky +25 %');
$world['mayor']['foodShort']=true; check(mayorScore($world,'fishing')===10,'Paličatý: při nedostatku jídla spěchá s jídlem'); $world['mayor']['foodShort']=false;
$mayorOf($world,'anna','zasoby');
check(mayorScore($world,'fieldwork')===5 && mayorScore($world,'firefight')===0 && mayorYield($world,'fieldwork')===1.0 && mayorQuarrelFactor($world)===.75,'Rozumný: menší bonus, žádný výnos, o čtvrtinu méně hádek');
$mayorOf($world,'karel','klid');
check(mayorScore($world,'prayer')===7 && abs(mayorYield($world,'prayer')-1.02)<1e-9 && mayorQuarrelFactor($world)===1.0,'Vyvážený: střední účinky');
// Hádky v rozhovorech: práh vztahu podle starosty.
$world['people']['x1']['bonds']['x2']=$world['people']['x2']['bonds']['x1']=40.0;
check(40<42*mayorQuarrelFactor($world),'Vyvážený: pod 42 se hádat může');
$mayorOf($world,'anna','zasoby'); check(!(40<42*mayorQuarrelFactor($world)),'Rozumný: při vztahu 40 se nehádají');
$mayorOf($world,'jan','stavba'); check(50<42*mayorQuarrelFactor($world),'Paličatý: hádají se i při vztahu 50');

// 8. Obchůzka starosty: jednou denně, jen starosta, usmíří nejhorší dvojici.
[$db,$world]=$ready(24);
$temper($world,'anna',85,15); $mayorOf($world,'anna','klid',1);
$at($world,10,9);
check(workAvailable($world,'anna','mayor_round') && !workAvailable($world,'jan','mayor_round'),'Obchůzku může dělat jen starosta');
check(!workAvailable($world,'anna','assembly'),'Schůzi si nikdo nevybere sám');
$at($world,10,20); check(!workAvailable($world,'anna','mayor_round'),'V noci se neobchází');
$at($world,10,9);
$world['people']['x1']['bonds']['x2']=30.0; $world['people']['x2']['bonds']['x1']=30.0; $world['people']['x2']['grudges']['x1']=$world['minute']+5000;
$text=''; assignWork($world,'anna','mayor_round',true); $text=completeWork($db,$world,'anna'); $world['people']['anna']['task']=null;
check(str_contains($text,'Usmířeni') && $world['people']['x1']['bonds']['x2']>30+7 && !isset($world['people']['x2']['grudges']['x1']),'Starosta usmíří nejhorší dvojici ('.$text.')');
check(!workAvailable($world,'anna','mayor_round') && $world['mayor']['lastRound']===10,'Obchůzka jen jednou denně');
$gain=$world['people']['x1']['bonds']['x2']-30;
$temper($world,'anna',15,85); $world['mayor']['lastRound']=-1; $world['people']['x1']['bonds']['x2']=30.0; $world['people']['x2']['bonds']['x1']=30.0;
assignWork($world,'anna','mayor_round',true); completeWork($db,$world,'anna'); $world['people']['anna']['task']=null;
check($world['people']['x1']['bonds']['x2']-30<$gain,'Paličatý usmiřuje hůř než rozumný');
$cand=villageCandidates($world); $world['mayor']['lastRound']=-1; $world['people']['anna']['task']=null;
$cand=villageCandidates($world);
check(isset($cand['anna']['mayor_round']),'Plánovač starostovi obchůzku nabízí');

// 9. Souhlas obce a nálada.
[$db,$world]=$ready(24);
$temper($world,'anna',85,15); $mayorOf($world,'anna','pole',1);
$at($world,400);   // mimo „líbánky“
foreach ($world['people'] as $id=>&$p) $p['work']=in_array($id,['x1','x2','x3'],true) ? 'forest' : 'field'; unset($p);
$approval=mayorOpinions($world);
$op=function(string $id) use (&$world) { return $world['people'][$id]['mayorOpinion']; };
check($approval>=0 && $approval<=100,'Souhlas obce 0 až 100 %');
check($op('x4')>$op('x1'),'Zásada vyhovuje oboru člověka (pole × les)');
$world['people']['x5']['family']=$world['people']['anna']['family']; mayorOpinions($world);
check($op('x5')>$op('x4')+10,'Rodina starosty souhlasí víc');
$world['people']['x6']['grudges']['anna']=$world['minute']+5000; mayorOpinions($world);
check($op('x6')<$op('x4')-15,'Zášť souhlas snižuje');
$world['people']['x7']['bonds']['anna']=80; mayorOpinions($world);
check($op('x7')>$op('x4')+15,'Přátelství souhlas zvyšuje');
foreach ($world['people'] as $id=>$p) if ($p['age']>=18 && $id!=='anna') check($op($id)>=-100 && $op($id)<=100,"Názor $id v mezích");
check(!isset($world['people']['anna']['mayorOpinion']),'Starosta názor na sebe nemá');
$world['food']=1.0; $a2=mayorOpinions($world); $world['food']=600.0;
check($a2<$approval,'Nedostatek jídla souhlas snižuje');
// Nálada podle souhlasu.
foreach ($world['people'] as &$p) $p['mood']=60.0; unset($p);
mayorDaily($db,$world);
check($world['people']['x4']['mood']>60 && $world['people']['x1']['mood']<60.001,'Spokojení si polepší, nespokojení ne');
$world['people']['x5']['mood']=60.0; $world['people']['x6']['mood']=60.0; $world['people']['x6']['mayorOpinion']=-50;
$temper($world,'anna',15,85); $m0=$world['people']['x6']['mood']; mayorDaily($db,$world);
check($world['people']['x6']['mood']<$m0,'Nespokojený má horší náladu');

// 10. Odvolání.
[$db,$world]=$ready(24);
$temper($world,'anna',85,15); $mayorOf($world,'anna','pole',1);
$at($world,400);
foreach ($world['people'] as $id=>&$p) { if ($id!=='anna' && $p['age']>=18) { $p['grudges']['anna']=$world['minute']+999999; $p['bonds']['anna']=10; } } unset($p);
for ($d=0;$d<MAYOR_RECALL_DAYS;$d++) { $at($world,400+$d); mayorDaily($db,$world); }
check($world['election']['kind']==='recall' && $world['election']['next']===400+MAYOR_RECALL_DAYS-1,'Po 30 dnech pod 30 % se svolá schůze o odvolání');
check($db->query("SELECT COUNT(*) FROM journal WHERE title LIKE 'Svolána schůze o odvolání%'")->fetchColumn()>0,'Kronika o odvolání');
$at($world,400+MAYOR_RECALL_DAYS-1,11); mayorHour($db,$world);
$att=array_keys(array_filter($world['people'],fn($p)=>($p['task']['action']??null)==='assembly'));
check(count($att)>=MAYOR_MIN_ATTEND,'Na odvolání se sejdou dospělí');
$world['minute']+=90; $res=completeWork($db,$world,$att[0]);
check($world['mayor']===null && $world['anarchySince']!==null && $res==='Starosta byl odvolán.','Většina proti starostu odvolá');
check($world['election']['kind']==='election' && $world['election']['next']===400+MAYOR_RECALL_DAYS-1+7,'Nové volby za týden');
// Obstál.
[$db,$world]=$ready(24);
$mayorOf($world,'anna','pole',1); $at($world,400);
$world['election']['kind']='recall'; $world['election']['next']=400; $world['mayor']['lowDays']=40;
foreach ($world['people'] as &$p) { $p['bonds']['anna']=70; } unset($p);
$at($world,400,11); mayorHour($db,$world); $att=array_keys(array_filter($world['people'],fn($p)=>($p['task']['action']??null)==='assembly'));
$world['minute']+=90; completeWork($db,$world,$att[0]);
check($world['mayor']['id']==='anna' && $world['mayor']['lowDays']===0 && $world['mayor']['graceUntil']===400+MAYOR_GRACE_DAYS && $world['election']['kind']==='election','Při příznivém hlasování starosta obstojí a má ochrannou lhůtu');
// Ochranná lhůta: odvolání se nesvolá.
[$db,$world]=$ready(24);
$mayorOf($world,'anna','pole',390); $at($world,400);
foreach ($world['people'] as $id=>&$p) { if ($id!=='anna' && $p['age']>=18) { $p['grudges']['anna']=$world['minute']+999999; $p['bonds']['anna']=10; } } unset($p);
for ($d=0;$d<MAYOR_RECALL_DAYS+2;$d++) { $at($world,400+$d); mayorDaily($db,$world); }
check($world['election']['kind']==='election','V ochranné lhůtě se odvolání nesvolá');

// 11. Smrt starosty, konec období, bez kandidáta, malý počet lidí.
[$db,$world]=$ready(24);
$mayorOf($world,'x5','pole',100); $at($world,300);
unset($world['people']['x5']);
mayorDaily($db,$world);
check($world['mayor']===null && $world['anarchySince']===300 && $world['election']['next']===300+MAYOR_VACANCY_DAYS,'Po smrti starosty je 14 dní bez vedení');
check($db->query("SELECT COUNT(*) FROM journal WHERE title='Vesnice je bez starosty'")->fetchColumn()==1,'Kronika zapíše odchod starosty');
[$db,$world]=$ready(24);
$mayorOf($world,'x5','pole',100); $world['mayor']['approval']=70.0; $at($world,100+MAYOR_TERM_DAYS,11); mayorHour($db,$world);
check(count(array_filter($world['people'],fn($p)=>($p['task']['action']??null)==='assembly'))>=10,'Po dvou letech se sejde schůze s volbou');
$att=array_keys(array_filter($world['people'],fn($p)=>($p['task']['action']??null)==='assembly'));
$world['minute']+=90; completeWork($db,$world,$att[0]);
check($world['mayor']!==null && $world['mayor']['since']===100+MAYOR_TERM_DAYS && $world['election']['next']===100+2*MAYOR_TERM_DAYS,'Nový mandát na další dva roky');
[$db,$world]=$ready(24);
foreach ($world['people'] as &$p) { $p['charisma']=5; $p['skills']=array_fill_keys(array_keys(CATEGORIES),0.0); $p['stubborn']=5.0; $p['reason']=5.0; } unset($p);
$at($world,20,11); $world['election']=['next'=>20,'kind'=>'election','heldDay'=>-1,'last'=>null,'tries'=>0];
mayorHour($db,$world); $att=array_keys(array_filter($world['people'],fn($p)=>($p['task']['action']??null)==='assembly'));
$world['minute']+=90; $res=completeWork($db,$world,$att[0]);
check($world['mayor']===null && $world['election']['next']===27 && $res==='Nikdo nekandidoval.','Bez kandidáta se volby odloží o týden');
// Málo lidí na návsi: schůze se odloží, po třech pokusech se koná i s malou účastí.
[$db,$world]=$ready(24);
foreach ($world['people'] as $id=>&$p) if ($p['age']>=18 && $id>'x1') $p['task']=['action'=>'fieldwork','start'=>0,'end'=>99999999]; unset($p);
$world['election']=['next'=>20,'kind'=>'election','heldDay'=>-1,'last'=>null,'tries'=>0];
$at($world,20,11); mayorHour($db,$world);
check($world['election']['next']===21 && $world['election']['tries']===1 && !array_filter($world['people'],fn($p)=>($p['task']['action']??null)==='assembly'),'Při malé účasti se schůze o den odloží');

// 12. Automatické nastavení po načtení starého uložení bez klíčů.
[$db,$world]=$ready(24);
foreach (['mayor','election','anarchySince'] as $k) unset($world[$k]);
foreach ($world['people'] as &$p) unset($p['reason'],$p['stubborn'],$p['mayorOpinion']); unset($p);
$warnings=[]; $round=decodeWorld(encode($world));
check(array_key_exists('mayor',$round) && $round['mayor']===null && array_key_exists('election',$round),'decodeWorld doplní klíče');
mayorDaily($db,$round); economyDaily($db,$round); goalState($round); mayorInfo($round); foodReserve($round);
check(!$warnings,'Starý stav bez klíčů funguje bez varování '.implode('; ',$warnings));
$info=mayorInfo($round);
check($info['mayor']===null && $info['anarchyPenalty']===true && $info['adults']>=16 && is_int($info['nextElection']),'Rozhraní panelu bez starosty');
$mayorOf($round,'anna','pole'); $info=mayorInfo($round);
check($info['mayor']['id']==='anna' && in_array($info['mayor']['type'],['rozumny','vyvazeny','palicaty'],true) && $info['mayor']['policyLabel']==='Pole a dobytek' && $info['anarchyPenalty']===false,'Rozhraní panelu se starostou');
$brief=mayorBrief($round); check($brief!==null && $brief['jmeno']==='Anna Kovářová','Stručný popis pro AI');

// 13. Dlouhý provoz: volby proběhnou, starosta pracuje, bez varování a bez zablokování.
[$db,$world]=$ready(26);
$world['food']=3000.0; $world['grain']=3000.0; $world['hay']=600.0; $world['wood']=400.0;
$warnings=[];
$elected=null; $assemblies=0; $rounds=0; $maxDay=0;
for ($d=0;$d<60;$d++) {
    runDays($db,$world,1.0,false);
    if ($elected===null && $world['mayor']!==null) $elected=$d;
}
$assemblies=(int)$db->query("SELECT COUNT(*) FROM journal WHERE title='Volby starosty'")->fetchColumn();
$rounds=(int)$db->query("SELECT COUNT(*) FROM journal WHERE detail LIKE 'Úkol%' OR title='Starosta usmiřuje'")->fetchColumn();
check($elected!==null && $elected<=8,'Starosta zvolen do týdne (den '.($elected??-1).')');
check($assemblies===1,'Volby proběhly jednou');
check(!$warnings,'Bez varování: '.implode('; ',array_slice($warnings,0,3)));
check($world['mayor']['approval']>=0 && isset($world['people'][$world['mayor']['id']]['reason']),'Starosta má povahu a souhlas');
$worked=(int)$db->query("SELECT COUNT(*) FROM journal WHERE title LIKE '%dokončuje úkol%' AND detail LIKE 'Obejít ves jako starosta%'")->fetchColumn();
check($worked>=5,'Starosta za 60 dní chodí na obchůzky ('.$worked.')');
echo 'Starosta a volby OK (starosta ',$world['people'][$world['mayor']['id']]['name'],', souhlas ',$world['mayor']['approval'],' %, obchůzky ',$worked,")\n";
