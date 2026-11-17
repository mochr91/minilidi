<?php
declare(strict_types=1);

// Fáze K: starosta a volby. Povaha jako dvě čísla (rozvaha, paličatost), hromadná schůze (`assembly`),
// volba každé 2 roky, zásada starosty, souhlas obce, odvolání a bezvládí. Rozhodují pravidla; AI jen vypráví.

const MAYOR_MIN_ADULTS = 8;           // od tolika dospělých se volí
const MAYOR_TERM_DAYS = 720;          // 2 herní roky
const MAYOR_ASSEMBLY_HOUR = 11;
const MAYOR_MIN_ATTEND = 5;           // nejméně tolik lidí a půlka dospělých, jinak se schůze odloží
const MAYOR_MAX_POSTPONE = 3;
const MAYOR_CAND_MIN_AGE = 25;
const MAYOR_CAND_MAX_AGE = 70;
const MAYOR_MAX_CANDIDATES = 3;
const MAYOR_TYPE_GAP = 15;            // rozdíl paličatosti a rozvahy pro typ rozumný/paličatý
const MAYOR_RECALL_APPROVAL = 30;     // souhlas obce v procentech
const MAYOR_RECALL_DAYS = 30;
const MAYOR_RECALL_SHARE = .55;
const MAYOR_GRACE_DAYS = 90;          // po zvolení nebo neúspěšném odvolání nelze odvolat
const MAYOR_VACANCY_DAYS = 14;        // po smrti nebo odchodu starosty se volí znovu nejdřív po tolika dnech
const MAYOR_ANARCHY_ADULTS = 16;      // od tolika dospělých bezvládí škodí
const MAYOR_ANARCHY_QUARREL = 1.15;
const MAYOR_MOOD = 1.0;               // denní posun nálady podle souhlasu s úřadem (±)
const MAYOR_POLICY_DAYS = 90;         // rozumný přezkoumá zásadu po tolika dnech
const MAYOR_ROUND_MINUTES = 60;
const MAYOR_BASE_SUPPORT = 6.0;       // lidé úřad obecně přijímají
const MAYOR_FIT_BONUS = 10.0;         // zásada vyhovuje oboru člověka
const MAYOR_FIT_MALUS = 8.0;          // zásada jeho obor míjí

const MAYOR_TYPES = [
    'rozumny'=>['label'=>'rozumný','bonus'=>5,'yield'=>0.0,'mood'=>0.0,'quarrel'=>.75,'crisis'=>0,'reconcile'=>8,'needs'=>1.0,'own'=>.2],
    'vyvazeny'=>['label'=>'vyvážený','bonus'=>7,'yield'=>.02,'mood'=>1.0,'quarrel'=>1.0,'crisis'=>4,'reconcile'=>6,'needs'=>.7,'own'=>.5],
    'palicaty'=>['label'=>'paličatý','bonus'=>10,'yield'=>.04,'mood'=>2.0,'quarrel'=>1.25,'crisis'=>10,'reconcile'=>3,'needs'=>.25,'own'=>1.0],
];

// `keys` = činnosti, které zásada podporuje; `works` = místa práce lidí, jimž zásada vyhovuje.
const MAYOR_POLICIES = [
    'zasoby'=>['label'=>'Zásoby jídla','works'=>['river','yard'],
        'keys'=>['fieldwork','fishing','gathering','livestock','hunt_small','sow_garden','tend_garden','harvest_garden','pickle','smoke_meat','mill','bake','slaughter_pig','harvest_pond','make_cheese']],
    'stavba'=>['label'=>'Stavba a doprava','works'=>['forest','quarry','building','sawmill'],
        'keys'=>['construct','build_house','haul_stone','quarrying','saw','felling','haul_wood','reroof','dig_clay','fire_bricks','craft','build_cart']],
    'remesla'=>['label'=>'Řemesla a obchod','works'=>['workshop','mill','oven'],
        'keys'=>['forge_tools','smelt_iron','mine_ore','tan_hides','make_boots','spin_weave','brew_beer','make_malt','press_wine','make_barrel','make_candles','load_kiln','watch_kiln']],
    'pole'=>['label'=>'Pole a dobytek','works'=>['field','pasture'],
        'keys'=>['fieldwork','fertilize','livestock','mow','fix_fence','tend_bees','harvest_honey','shear_sheep','tend_sheep','tend_hops','harvest_hops','tend_vines','harvest_grapes']],
    'klid'=>['label'=>'Víra a klid','works'=>['market','chapel','barn','tavern','well'],
        'keys'=>['prayer','visit','gather_neighbors','bathe','nurse','teach','market','bring_water','deliver_meal']],
];
const MAYOR_FOOD_KEYS = ['fieldwork','fishing','gathering','livestock','hunt_small'];
const MAYOR_TASKS = ['assembly','mayor_round'];

// --- Povaha ---

// Rozvaha a paličatost 5 až 95: z textu povahy, ze zrnka podle id, z věku a u dětí z rodičů. Uložená hodnota má přednost.
function personTemper(array $world,array $p): array {
    if (isset($p['reason'],$p['stubborn'])) return [(float)$p['reason'],(float)$p['stubborn']];
    $h=crc32('temper:'.($p['id']??''));
    $age=(int)($p['age']??30);
    $parents=[];
    foreach ([$p['mother']??null,$p['father']??null] as $pid) {
        $parent=is_string($pid) ? ($world['people'][$pid]??null) : null;
        if ($parent!==null && isset($parent['reason'],$parent['stubborn'])) $parents[]=$parent;
    }
    if ($parents) {
        $r=array_sum(array_column($parents,'reason'))/count($parents)+($h%21)-10;
        $s=array_sum(array_column($parents,'stubborn'))/count($parents)+(intdiv($h,21)%21)-10;
    } else {
        $r=35+$h%31+max(-6,min(12,($age-30)*.35));
        $s=35+intdiv($h,31)%31;
        $trait=(string)($p['trait']??'');
        // Povaha popsaná slovy rozhoduje: výrazně rozvážný nebo paličatý člověk má jasný odstup od druhé hodnoty.
        $calm=(bool)preg_match('/rozvážn|rozumn|klidn|vyrovnan|trpěliv|moudr/iu',$trait);
        $hot=(bool)preg_match('/paličat|tvrdohlav|vznětliv|prudk|hádav|svéhlav|umíněn|zlostn|výbušn/iu',$trait);
        if ($calm && !$hot) { $r=max($r,72+$h%15); $s=min($s,45); }
        if ($hot && !$calm) { $s=max($s,72+$h%15); $r=min($r,45); }
    }
    return [(float)round(max(5,min(95,$r))),(float)round(max(5,min(95,$s)))];
}

function temperType(float $reason,float $stubborn): string {
    $gap=$stubborn-$reason;
    return $gap>=MAYOR_TYPE_GAP ? 'palicaty' : ($gap<=-MAYOR_TYPE_GAP ? 'rozumny' : 'vyvazeny');
}

// Uloží rozvahu a paličatost všem, kdo je ještě nemají (nově příchozí, děti).
function mayorSetTempers(array &$world): void {
    foreach ($world['people'] as $id=>$p) {
        if (isset($p['reason'],$p['stubborn'])) continue;
        [$r,$s]=personTemper($world,$p);
        $world['people'][$id]['reason']=$r;
        $world['people'][$id]['stubborn']=$s;
    }
}

function mayorAdults(array $world): array {
    $ids=[];
    foreach ($world['people'] as $id=>$p) if ($p['age']>=18) $ids[]=$id;
    return $ids;
}

function mayorPerson(array $world): ?array {
    $id=$world['mayor']['id']??null;
    return is_string($id) ? ($world['people'][$id]??null) : null;
}

function mayorType(array $world): ?string {
    $p=mayorPerson($world);
    if ($p===null) return null;
    [$r,$s]=personTemper($world,$p);
    return temperType($r,$s);
}

function mayorPolicyKeys(array $world): array {
    return MAYOR_POLICIES[$world['mayor']['policy']??'']['keys']??[];
}

function nextSundayAfter(int $day): int {
    return $day+((6-$day%7)+7)%7;
}

// --- Účinky starosty na hru ---

// Bonus ke skóre činnosti v plánovači; bez starosty (bezvládí) žádný.
function mayorScore(array $world,string $key): int {
    $type=mayorType($world);
    if ($type===null) return 0;
    $t=MAYOR_TYPES[$type];
    $score=0;
    if (in_array($key,mayorPolicyKeys($world),true)) $score+=$t['bonus'];
    if (in_array($key,['firefight','flood_defense'],true)) $score+=$t['crisis'];
    if (!empty($world['mayor']['foodShort']) && in_array($key,MAYOR_FOOD_KEYS,true)) $score+=$t['crisis'];
    return $score;
}

// Skóre činností starosty a schůze (do plánovače jen pro starostu).
function mayorActionScore(array $world,string $key): int {
    return $key==='mayor_round' ? 34 : 0;
}

// Výnos práce v oboru zásady (paličatý +4 %, vyvážený +2 %, rozumný 0).
function mayorYield(array $world,string $key): float {
    $type=mayorType($world);
    if ($type===null || !in_array($key,mayorPolicyKeys($world),true)) return 1.0;
    return 1.0+MAYOR_TYPES[$type]['yield'];
}

// Násobek práhu vztahu, pod nímž se mohou lidé pohádat: rozumný starosta 0,75, paličatý 1,25, bezvládí od 16 dospělých 1,15.
function mayorQuarrelFactor(array $world): float {
    $type=mayorType($world);
    if ($type!==null) return MAYOR_TYPES[$type]['quarrel'];
    return count(mayorAdults($world))>=MAYOR_ANARCHY_ADULTS ? MAYOR_ANARCHY_QUARREL : 1.0;
}

// Krátký popis pro prompt AI a panel.
function mayorBrief(array $world): ?array {
    $p=mayorPerson($world);
    if ($p===null) return null;
    return ['jmeno'=>$p['name'],'povaha'=>MAYOR_TYPES[mayorType($world)]['label'],'zasada'=>MAYOR_POLICIES[$world['mayor']['policy']]['label']??''];
}

// null = činnost tento modul nezná, jinak jestli je teď dostupná (schůzi svolává jen `mayorHour`).
function mayorWorkAvailable(array $world,string $id,string $key): ?bool {
    if (!in_array($key,MAYOR_TASKS,true)) return null;
    if ($key==='assembly') return false;
    $m=$world['mayor']??null;
    if (!is_array($m) || ($m['id']??null)!==$id) return false;
    $day=intdiv((int)$world['minute'],1440);
    $hour=(int)(((int)$world['minute']%1440)/60);
    return ($m['lastRound']??-1)<$day && $hour>=7 && $hour<18 && empty($world['crisis']) && taskCount($world,'mayor_round')<1;
}

// --- Volby ---

function mayorStress(array $world,array $p): float {
    $stress=0.0;
    if (!empty($world['crisis'])) $stress+=.5;
    if (!empty($world['mayor']['foodShort']) || foodReserve($world)<3*dailyFoodNeed($world)) $stress+=.3;
    if (!empty($world['hayShort'])) $stress+=.2;
    if ($p['fear']>55) $stress+=.2;
    if ($p['hunger']>60) $stress+=.2;
    if ($p['mood']<40) $stress+=.2;
    return min(1.0,$stress);
}

function voteScore(array $world,string $voter,string $cand,int $day): float {
    $v=$world['people'][$voter];
    $c=$world['people'][$cand];
    $score=((float)($v['bonds'][$cand]??48)-48)*.9+.4*skillLevel($c,'neighbors');
    if ($v['family']===$c['family']) $score+=15;
    if (($v['grudges'][$cand]??0)>$world['minute']) $score-=20;
    if ($voter===$cand) $score+=20;
    if (($world['mayor']['id']??null)===$cand) {
        $approval=(float)($world['mayor']['approval']??50);
        $score+=$approval>60 ? 10 : ($approval<40 ? -10 : 0);
    }
    [$r,$s]=personTemper($world,$c);
    $lean=($s-$r)/100;
    $stress=mayorStress($world,$v);
    $score+=$lean*(10*$stress-8*(1-$stress));
    $score+=(weatherNoise($day,'vote:'.$voter.':'.$cand)-.5)*12;
    return $score;
}

function mayorCandidates(array $world,array $present,int $day): array {
    $scored=[];
    foreach ($present as $id) {
        $p=$world['people'][$id];
        if ($p['age']<MAYOR_CAND_MIN_AGE || $p['age']>MAYOR_CAND_MAX_AGE) continue;
        [$r,$s]=personTemper($world,$p);
        $ambition=($p['charisma']??54)*.5+skillLevel($p,'neighbors')*.3+$s*.2+(weatherNoise($day,'cand:'.$id)-.5)*20;
        if (($world['mayor']['id']??null)===$id) $ambition+=15;
        $scored[$id]=$ambition;
    }
    arsort($scored);
    $picked=array_keys(array_filter($scored,fn($a)=>$a>=45));
    if (!$picked && $scored && max($scored)>=35) $picked=[array_key_first($scored)];
    return array_slice($picked,0,MAYOR_MAX_CANDIDATES);
}

// Zásada podle potřeb vsi (rozumný) nebo podle vlastního oboru (paličatý); vyvážený obojí napůl.
function mayorChoosePolicy(array $world,array $p,int $day): string {
    $adults=max(1,count(mayorAdults($world)));
    $need=max(1.0,dailyFoodNeed($world));
    $needs=['zasoby'=>0.0,'stavba'=>0.0,'remesla'=>0.0,'pole'=>0.0,'klid'=>0.0];
    $needs['zasoby']=max(0,1-foodReserve($world)/(14*$need))*40+(!empty($world['hayShort'])?15:0);
    $needs['stavba']=(materialNeeds($world)?18:0)+(settlersNeedHouse($world)?20:0)+(houseWorkAvailable($world)?10:0);
    $needs['remesla']=($world['tools']<ceil($adults/3)?25:0)+(($world['ironTools']??0)<2?8:0);
    $needs['pole']=($world['hay']<hayTarget($world)*.5?15:0)+($world['grain']<$need*20?25:4);
    $avgMood=array_sum(array_column($world['people'],'mood'))/max(1,count($world['people']));
    $needs['klid']=max(0,55-$avgMood)*1.2;
    $own=array_fill_keys(array_keys($needs),0.0);
    foreach (MAYOR_POLICIES as $key=>$def) if (in_array($p['work']??'',$def['works'],true)) $own[$key]=40.0;
    if (!array_filter($own)) {
        $best=null; $level=-1;
        foreach (['field'=>'pole','craft'=>'remesla','build'=>'stavba','forest'=>'zasoby','home'=>'klid','neighbors'=>'klid','faith'=>'klid'] as $cat=>$key) {
            if (skillLevel($p,$cat)>$level) { $level=skillLevel($p,$cat); $best=$key; }
        }
        $own[$best]=40.0;
    }
    [$r,$s]=personTemper($world,$p);
    $t=MAYOR_TYPES[temperType($r,$s)];
    $bestKey='zasoby'; $bestScore=-INF;
    foreach ($needs as $key=>$value) {
        $score=$value*$t['needs']+$own[$key]*$t['own']+(weatherNoise($day,'policy:'.($p['id']??'').':'.$key)-.5)*6;
        if ($score>$bestScore) { $bestScore=$score; $bestKey=$key; }
    }
    return $bestKey;
}

function genderEnd(array $p,string $m,string $f): string {
    return ($p['sex']??'m')==='f' ? $f : $m;
}

function runElection(PDO $db,array &$world,array $present,int $day): string {
    $e=&$world['election'];
    $adults=count(mayorAdults($world));
    $candidates=mayorCandidates($world,$present,$day);
    if (!$candidates) {
        $e['next']=$day+7;
        journal($db,$world,'','Volby starosty se nekonají','Na návsi se sešlo '.count($present).' dospělých, ale nikdo se starostou být nechtěl. Vesnice se sejde znovu za týden.','Příběh');
        return 'Nikdo nekandidoval.';
    }
    $tally=array_fill_keys($candidates,0);
    $choice=[];
    $voters=0;
    foreach ($present as $voter) {
        $best=null; $bestScore=-INF;
        foreach ($candidates as $cand) {
            $score=voteScore($world,$voter,$cand,$day);
            if ($score>$bestScore) { $bestScore=$score; $best=$cand; }
        }
        if ($best===null || $bestScore<-12) continue;
        ++$tally[$best];
        $choice[$voter]=$best;
        ++$voters;
    }
    if ($voters===0 || (count($candidates)===1 && $tally[$candidates[0]]<.4*count($present))) {
        $e['next']=$day+7;
        journal($db,$world,'','Volby starosty se nekonají','Na návsi se sešlo '.count($present).' dospělých, ale nikdo nezískal důvěru. Vesnice se sejde znovu za týden.','Příběh');
        return 'Nikdo nezískal důvěru.';
    }
    uksort($tally,function($a,$b) use ($tally,$world) {
        return [$tally[$b],$world['people'][$b]['charisma']??54,$b]<=>[$tally[$a],$world['people'][$a]['charisma']??54,$a];
    });
    $ids=array_keys($tally);
    $winner=$ids[0];
    $second=$ids[1]??null;
    $w=$world['people'][$winner];
    [$r,$s]=personTemper($world,$w);
    $type=temperType($r,$s);
    $previous=$world['mayor']??null;
    $terms=(($previous['id']??null)===$winner) ? ((int)($previous['terms']??1)+1) : 1;
    $world['mayor']=['id'=>$winner,'since'=>$day,'until'=>$day+MAYOR_TERM_DAYS,'policy'=>mayorChoosePolicy($world,$w,$day),
        'policyDay'=>$day,'approval'=>55,'lowDays'=>0,'graceUntil'=>$day+MAYOR_GRACE_DAYS,'lastRound'=>-1,'terms'=>$terms,'foodShort'=>false];
    $e['next']=$day+MAYOR_TERM_DAYS;
    $e['kind']='election';
    $e['tries']=0;
    $world['anarchySince']=null;
    $names=[];
    foreach ($tally as $id=>$votes) $names[]=['id'=>$id,'name'=>$world['people'][$id]['name'],'votes'=>$votes];
    $e['last']=['day'=>$day,'kind'=>'election','turnout'=>count($present),'adults'=>$adults,'voted'=>$voters,'winner'=>$winner,'tally'=>$names];
    $close=$second!==null && ($tally[$winner]-$tally[$second])/max(1,$voters)<.15;
    if ($close) {
        foreach ($choice as $voter=>$cand) {
            if ($cand===$winner || $voter===$winner) continue;
            $world['people'][$voter]['bonds'][$winner]=clamp(($world['people'][$voter]['bonds'][$winner]??48)-2);
        }
    }
    foreach ($present as $voter) {
        $world['people'][$voter]['fun']=clamp($world['people'][$voter]['fun']+10);
        $world['people'][$voter]['mood']=clamp($world['people'][$voter]['mood']+2);
    }
    $line=[];
    foreach (array_slice($names,0,3) as $row) $line[]=$row['name'].' '.$row['votes'];
    journal($db,$world,$winner,'Volby starosty','Na návsi se sešlo '.count($present).' z '.$adults.' dospělých. Hlasy: '.implode(', ',$line).'. '
        .'Starost'.genderEnd($w,'ou','kou').' je zvolen'.genderEnd($w,'','a').' '.$w['name'].' ('.MAYOR_TYPES[$type]['label'].'), zásadou na dva roky je '
        .lowerFirstCs(MAYOR_POLICIES[$world['mayor']['policy']]['label']).'.'.($close?' Výsledek byl těsný a ves se rozdělila.':''),'Příběh');
    return 'Zvolen'.genderEnd($w,'','a').' '.$w['name'].'.';
}

function lowerFirstCs(string $text): string {
    $first=mb_substr_cs($text);
    return strtr($first,['Z'=>'z','S'=>'s','Ř'=>'ř','P'=>'p','V'=>'v','Z'=>'z','Č'=>'č']).substr($text,strlen($first));
}

// První znak v UTF-8 (mbstring v tomto běhu není).
function mb_substr_cs(string $text): string {
    return preg_match('/^./us',$text,$m) ? $m[0] : '';
}

function runRecall(PDO $db,array &$world,array $present,int $day): string {
    $e=&$world['election'];
    $mid=$world['mayor']['id']??null;
    if (!is_string($mid) || !isset($world['people'][$mid])) { $e['kind']='election'; return 'Starosta už není.'; }
    mayorOpinions($world);
    $voters=0; $against=0;
    foreach ($present as $voter) {
        if ($voter===$mid) continue;
        ++$voters;
        if (($world['people'][$voter]['mayorOpinion']??0)<-10) ++$against;
    }
    $m=$world['people'][$mid];
    $share=$voters>0 ? $against/$voters : 0.0;
    $e['last']=['day'=>$day,'kind'=>'recall','turnout'=>count($present),'adults'=>count(mayorAdults($world)),'voted'=>$voters,'against'=>$against,'winner'=>$mid,'tally'=>[]];
    if ($voters>0 && $share>=MAYOR_RECALL_SHARE) {
        $world['mayor']=null;
        $world['anarchySince']=$day;
        $e['kind']='election';
        $e['next']=$day+7;
        $e['tries']=0;
        journal($db,$world,$mid,'Starosta odvolán'.genderEnd($m,'','a'),'Na mimořádné schůzi hlasovalo proti '.$against.' z '.$voters.' dospělých. '.$m['name'].' přestává být starost'.genderEnd($m,'ou','kou').', vesnice se za týden sejde na nových volbách.','Příběh');
        return 'Starosta byl odvolán.';
    }
    $world['mayor']['lowDays']=0;
    $world['mayor']['graceUntil']=$day+MAYOR_GRACE_DAYS;
    $e['kind']='election';
    $e['next']=(int)$world['mayor']['until'];
    journal($db,$world,$mid,'Starosta obstál','Na mimořádné schůzi hlasovalo proti '.$against.' z '.$voters.' dospělých, to nestačí. '.$m['name'].' zůstává starost'.genderEnd($m,'ou','kou').'.','Příběh');
    return 'Starosta zůstává.';
}

// Zjistí účast: kdo má úkol `assembly` (včetně toho, kdo ho právě dokončuje).
function runAssembly(PDO $db,array &$world,string $callerId): string {
    $day=intdiv((int)$world['minute'],1440);
    $e=&$world['election'];
    if (!is_array($e) || ($e['heldDay']??-1)===$day) return 'Schůze už skončila.';
    $e['heldDay']=$day;
    $present=[];
    foreach ($world['people'] as $id=>$p) {
        if (($p['task']['action']??null)==='assembly' && $p['age']>=18) $present[]=$id;
    }
    if (!in_array($callerId,$present,true) && ($world['people'][$callerId]['age']??0)>=18) $present[]=$callerId;
    if (count($present)<3) { $e['next']=$day+1; return 'Na schůzi nikdo nedorazil.'; }
    return ($e['kind']??'election')==='recall' ? runRecall($db,$world,$present,$day) : runElection($db,$world,$present,$day);
}

// Každou hodinu: ve 11 hodin svolá schůzi, je-li čas. Ostatní hodiny jen obnoví příznak nedostatku jídla pro plánovač.
function mayorHour(PDO $db,array &$world): void {
    $now=(int)$world['minute'];
    if (!empty($world['mayor'])) $world['mayor']['foodShort']=foodReserve($world)<2*dailyFoodNeed($world);
    if ((int)(($now%1440)/60)!==MAYOR_ASSEMBLY_HOUR || !empty($world['crisis'])) return;
    $day=intdiv($now,1440);
    $e=$world['election']??null;
    if (!is_array($e) || $day<(int)$e['next'] || ($e['heldDay']??-1)===$day) return;
    $adults=mayorAdults($world);
    if (count($adults)<MAYOR_MIN_ADULTS) return;
    $eligible=[];
    foreach ($adults as $id) {
        $p=$world['people'][$id];
        if (($p['awayUntil']??0)>$now || (!empty($p['illUntil']) && $p['illUntil']>$now)) continue;
        if ($p['task'] && empty($p['task']['automatic'])) continue;
        $eligible[]=$id;
    }
    $tries=(int)($e['tries']??0);
    if (count($eligible)<MAYOR_MIN_ATTEND || count($eligible)<.5*count($adults)) {
        if ($tries<MAYOR_MAX_POSTPONE || count($eligible)<3) {
            $world['election']['tries']=$tries+1;
            $world['election']['next']=$day+1;
            return;
        }
    }
    foreach ($eligible as $id) assignWork($world,$id,'assembly',true);
}

function completeMayorWork(PDO $db,array &$world,string $id,string $key): ?string {
    if (!in_array($key,MAYOR_TASKS,true)) return null;
    if ($key==='assembly') return runAssembly($db,$world,$id);
    // mayor_round: starosta obejde ves a usmíří nejhorší dvojici
    $m=$world['mayor']??null;
    if (!is_array($m) || ($m['id']??null)!==$id) return 'Obchůzka se nekonala.';
    $world['mayor']['lastRound']=intdiv((int)$world['minute'],1440);
    $t=MAYOR_TYPES[mayorType($world)];
    $worst=null; $low=42.0;
    $adults=mayorAdults($world);
    foreach ($adults as $i=>$a) {
        foreach (array_slice($adults,$i+1) as $b) {
            $bond=min((float)($world['people'][$a]['bonds'][$b]??48),(float)($world['people'][$b]['bonds'][$a]??48));
            if ($bond<$low) { $low=$bond; $worst=[$a,$b]; }
        }
    }
    if ($worst===null) return 'Starosta obešel'.genderEnd($world['people'][$id],'','a').' ves, mezi sousedy je klid.';
    [$a,$b]=$worst;
    $soft=softSkill($world['people'][$id],'neighbors');
    foreach ([[$a,$b],[$b,$a]] as [$x,$y]) {
        $world['people'][$x]['bonds'][$y]=clamp(($world['people'][$x]['bonds'][$y]??48)+$t['reconcile']*$soft);
        unset($world['people'][$x]['grudges'][$y]);
        $world['people'][$x]['mood']=clamp($world['people'][$x]['mood']+2);
    }
    journal($db,$world,$id,'Starosta usmiřuje',$world['people'][$id]['name'].' ('.$t['label'].') přivedl'.genderEnd($world['people'][$id],'','a').' k rozmluvě '.$world['people'][$a]['name'].' a '.$world['people'][$b]['name'].'.','Příběh');
    return 'Usmířeni '.$world['people'][$a]['name'].' a '.$world['people'][$b]['name'].'.';
}

// --- Souhlas obce ---

// Přepočítá názor každého dospělého na starostu (−100 až +100) a vrátí podíl příznivců v procentech.
function mayorOpinions(array &$world): float {
    $mid=$world['mayor']['id']??null;
    if (!is_string($mid) || !isset($world['people'][$mid])) return 0.0;
    $day=intdiv((int)$world['minute'],1440);
    $m=$world['people'][$mid];
    [$mr,$ms]=personTemper($world,$m);
    $type=temperType($mr,$ms);
    $works=MAYOR_POLICIES[$world['mayor']['policy']]['works']??[];
    $hungry=($world['counters']['hungryMealsToday']??0)>0;
    $avgMood=array_sum(array_column($world['people'],'mood'))/max(1,count($world['people']));
    $foodShort=foodReserve($world)<3*dailyFoodNeed($world);
    $honey=max(0,((int)$world['mayor']['since']+60-$day)/60)*10;
    $support=0.0; $count=0;
    foreach ($world['people'] as $id=>&$p) {
        if ($p['age']<18 || $id===$mid) { continue; }
        [$r,$s]=personTemper($world,$p);
        // Vztah ke starostovi se měří proti tomu, jak má člověk rád sousedy obecně (ve spokojené vsi mají všichni vztahy vysoké).
        $bonds=array_filter($p['bonds']??[],'is_numeric');
        $typical=$bonds ? array_sum($bonds)/count($bonds) : 48.0;
        $op=MAYOR_BASE_SUPPORT+((float)($p['bonds'][$mid]??48)-$typical)*1.1;
        if ($p['family']===$m['family']) $op+=20;
        if (($p['grudges'][$mid]??0)>$world['minute']) $op-=25;
        $op+=in_array($p['work']??'',$works,true) ? MAYOR_FIT_BONUS : -MAYOR_FIT_MALUS;
        if ($type==='palicaty' && $r>$s+10) $op-=10;
        if ($type==='rozumny' && $s>$r+15) $op-=6;
        if ($type==='palicaty' && !empty($world['crisis'])) $op+=10;
        if ($foodShort) $op-=10;
        if (!empty($world['hayShort'])) $op-=6;
        if ($hungry) $op-=8;
        if ($avgMood<45) $op-=(45-$avgMood)*.5;
        $op+=$honey;
        $p['mayorOpinion']=(int)round(max(-100,min(100,$op)));
        ++$count;
        if ($p['mayorOpinion']>0) $support+=1; elseif ($p['mayorOpinion']===0) $support+=.5;
    }
    unset($p);
    return $count>0 ? round(100*$support/$count,1) : 50.0;
}

function mayorDaily(PDO $db,array &$world): void {
    $day=intdiv((int)$world['minute'],1440);
    mayorSetTempers($world);
    if (!is_array($world['election']??null)) {
        $world['election']=['next'=>nextSundayAfter($day+1),'kind'=>'election','heldDay'=>-1,'last'=>null,'tries'=>0];
    }
    $e=&$world['election'];
    $adults=count(mayorAdults($world));
    $mayor=$world['mayor']??null;
    if (is_array($mayor)) {
        $mid=$mayor['id'];
        if (!isset($world['people'][$mid])) {
            $world['mayor']=null;
            $world['anarchySince']=$day;
            $e['kind']='election';
            $e['next']=$day+MAYOR_VACANCY_DAYS;
            $e['tries']=0;
            journal($db,$world,'','Vesnice je bez starosty','Starosta už mezi sousedy není. Do nových voleb zbývá '.MAYOR_VACANCY_DAYS.' dní, do té doby je ves bez vedení.','Příběh');
            return;
        }
        $approval=mayorOpinions($world);
        $world['mayor']['approval']=$approval;
        foreach ($world['people'] as $id=>&$p) {
            if (!isset($p['mayorOpinion']) || $p['age']<18 || $id===$mid) continue;
            $t=MAYOR_TYPES[mayorType($world)];
            $delta=max(-1,min(1,$p['mayorOpinion']/50))*MAYOR_MOOD*.5-($p['mayorOpinion']<0 ? $t['mood']*.5 : 0);
            $p['mood']=clamp($p['mood']+$delta);
        }
        unset($p);
        if ($approval<MAYOR_RECALL_APPROVAL) ++$world['mayor']['lowDays']; else $world['mayor']['lowDays']=0;
        if ($world['mayor']['lowDays']>=MAYOR_RECALL_DAYS && $day>=(int)$world['mayor']['graceUntil'] && $adults>=MAYOR_MIN_ADULTS && ($e['kind']??'')!=='recall') {
            $e['kind']='recall';
            $e['next']=$day;
            $e['tries']=0;
            journal($db,$world,$mid,'Svolána schůze o odvolání starosty','Souhlas s úřadem je dlouho pod '.MAYOR_RECALL_APPROVAL.' %, vesnice se sejde a rozhodne, jestli starosta zůstane.','Příběh');
        }
        $policyDay=(int)($world['mayor']['policyDay']??$day);
        if ($day-$policyDay>=MAYOR_POLICY_DAYS && temperType(...personTemper($world,$world['people'][$mid]))==='rozumny') {
            $new=mayorChoosePolicy($world,$world['people'][$mid],$day);
            $world['mayor']['policyDay']=$day;
            if ($new!==$world['mayor']['policy']) {
                $world['mayor']['policy']=$new;
                journal($db,$world,$mid,'Starosta mění zásadu','Podle potřeb vsi se nově zaměřuje na: '.MAYOR_POLICIES[$new]['label'].'.','Příběh');
            }
        }
    } elseif ($adults>=MAYOR_MIN_ADULTS && empty($world['anarchySince'])) {
        $world['anarchySince']=$day;
    }
}

// Údaje pro panel Obec.
function mayorInfo(array $world): array {
    $day=intdiv((int)$world['minute'],1440);
    $p=mayorPerson($world);
    $e=$world['election']??null;
    $info=['mayor'=>null,'anarchySince'=>$world['anarchySince']??null,'adults'=>count(mayorAdults($world)),
        'nextElection'=>is_array($e)?(int)$e['next']:null,'kind'=>$e['kind']??'election','last'=>$e['last']??null,'day'=>$day,
        'anarchyPenalty'=>$p===null && count(mayorAdults($world))>=MAYOR_ANARCHY_ADULTS];
    if ($p!==null) {
        [$r,$s]=personTemper($world,$p);
        $type=temperType($r,$s);
        $info['mayor']=['id'=>$p['id'],'name'=>$p['name'],'sex'=>$p['sex']??'m','type'=>$type,'typeLabel'=>MAYOR_TYPES[$type]['label'],
            'reason'=>(int)$r,'stubborn'=>(int)$s,'policy'=>$world['mayor']['policy'],'policyLabel'=>MAYOR_POLICIES[$world['mayor']['policy']]['label']??'',
            'since'=>(int)$world['mayor']['since'],'until'=>(int)$world['mayor']['until'],'approval'=>(float)($world['mayor']['approval']??0),
            'terms'=>(int)($world['mayor']['terms']??1),'lowDays'=>(int)($world['mayor']['lowDays']??0)];
    }
    return $info;
}
