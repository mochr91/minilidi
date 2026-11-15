<?php
declare(strict_types=1);

const SEXES = ['anna'=>'f','matyas'=>'m','jan'=>'m','bara'=>'f','katerina'=>'f','karel'=>'m','karolina'=>'f',
    'tomas'=>'m','marek'=>'m','tereza'=>'f','novy1'=>'m','novy2'=>'f','novy3'=>'m','novy4'=>'f',
    'vavrinec'=>'m','dorota'=>'f','matej'=>'m','anezka'=>'f','marta'=>'f'];
const INITIAL_SPOUSES = ['jan'=>'bara','bara'=>'jan','karel'=>'karolina','karolina'=>'karel','vavrinec'=>'dorota','dorota'=>'vavrinec'];
const GIRL_NAMES = ['Eliška','Anežka','Marie','Dorota','Alžběta','Johanka','Kristýna','Magdaléna','Lidmila','Barbora'];
const BOY_NAMES = ['Václav','Vít','Štěpán','Jakub','Martin','Šimon','Matěj','Havel','Ondřej','Petr'];
const CHILD_COLORS = ['#a0644a','#5f7f9a','#7a8f4e','#8b6a9c','#b08a3e','#4f8a7a'];
const PREGNANCY_DAYS = 270;
const BIRTH_INTERVAL_DAYS = 540;
const HEAVY_WORK = ['mine_ore','felling','quarrying','haul_stone','haul_wood','load_kiln','build_house','construct','saw','mow','firefight','flood_defense'];

function lifeDefaults(array $p,int $absDay): array {
    return ['sex'=>SEXES[$p['id']]??(crc32($p['id'])%2?'m':'f'),
        'birthDay'=>$absDay-$p['age']*360-crc32($p['id'])%360,
        'spouse'=>null,'pregnant'=>null,'lastBirth'=>-999999,'mother'=>null,'father'=>null];
}

function surnameFor(string $sex,string $fatherName): string {
    $parts=explode(' ',$fatherName);
    $last=end($parts);
    if ($sex==='m') return $last;
    if (str_ends_with($last,'a')) return substr($last,0,-1).'ová';
    if (str_ends_with($last,'ý')) return substr($last,0,-2).'á';
    return $last.'ová';
}

function lifeMigrate(array &$world,int $due): void {
    if (!empty($world['lifeVersion'])) return;
    if (isset($world['people']['bara']) && isset($world['people']['jan'])) {
        $world['people']['bara']['pregnant']=['since'=>$due-PREGNANCY_DAYS*1440,'due'=>$due,'father'=>'jan','revealed'=>true];
    }
    $world['lifeVersion']=1;
}

function isHeavyBlocked(array $world,array $p): bool {
    if (empty($p['pregnant']) && $world['minute']-$p['lastBirth']>=30*1440) return false;
    if (!empty($p['pregnant'])) return $p['pregnant']['due']-$world['minute']<=90*1440;
    return true;
}

function roleForAge(array $p): string {
    $female=$p['sex']==='f';
    return $p['age']<12 ? 'Dítě' : ($p['age']<15 ? 'Pomocník' : ($p['age']<18 ? 'Pomocník v hospodářství' : ($female?'Hospodyně':'Hospodář')));
}

function deathCause(array $world,array $p,int $day): ?string {
    $now=(int)$world['minute'];
    $ill=!empty($p['illUntil']) && $p['illUntil']>$now;
    $chance=0.0;
    if ($p['age']>=60) $chance+=.000085*exp(.16*($p['age']-60))*($ill?3:1);
    if ($p['age']<5) $chance+=.00004;
    if ($ill) {
        $chance+=($p['age']<3||$p['age']>=55?.0012:.00015)*(hasBuilding($world,'studna')?.5:1)*($p['hunger']>75?1.5:1);
    }
    if ($chance<=0 || weatherNoise($day,'death:'.$p['id'])>=$chance) return null;
    return $ill ? 'nemoc' : ($p['age']>=60 ? 'stáří' : 'nemoc');
}

function removePerson(PDO $db,array &$world,string $id,string $cause): void {
    if (!isset($world['people'][$id])) return;
    $p=$world['people'][$id];
    $female=$p['sex']==='f';
    $world['graveyard'][]=['id'=>$id,'name'=>$p['name'],'age'=>$p['age'],'cause'=>$cause,'minute'=>(int)$world['minute'],
        'family'=>$p['family'],'sex'=>$p['sex']];
    $world['graveyard']=array_slice($world['graveyard'],-60);
    unset($world['people'][$id],$world['plan'][$id]);
    $deaths=&$world['counters']['deaths'];
    $deaths[$cause]=($deaths[$cause]??0)+1;
    unset($deaths);
    $buried=hasBuilding($world,'hrbitov');
    foreach ($world['people'] as $oid=>&$o) {
        $bond=$o['bonds'][$id]??48;
        unset($o['bonds'][$id],$o['grudges'][$id]);
        if (($o['crush']??null)===$id) unset($o['crush']);
        $spouse=($o['spouse']??null)===$id || ($o['betrothed']??null)===$id;
        if (($o['spouse']??null)===$id) $o['spouse']=null;
        if (($o['betrothed']??null)===$id) { $o['betrothed']=null; unset($o['wedding']); }
        if ($spouse || $bond>=65 || $o['family']===$p['family']) {
            $grief=($spouse?30:($o['family']===$p['family']?24:14))*($buried?.8:1);
            $o['mood']=clamp($o['mood']-$grief);
            $o['fun']=clamp($o['fun']-$grief*.6);
            $o['faith']=clamp($o['faith']+3);
            $o['fear']=clamp($o['fear']+($buried?1:2));
            rememberEvent($db,$world,$oid,'ztráta','Zemřel'.($female?'a':'').' '.$p['name'].' ('.$cause.').',5);
        }
    }
    unset($o);
    foreach ($world['religions'] as $i=>&$group) {
        $group['members']=array_values(array_diff($group['members'],[$id]));
        if ($group['leader']===$id && $group['members']) {
            usort($group['members'],fn($a,$b)=>$world['people'][$b]['faith']<=>$world['people'][$a]['faith']);
            $group['leader']=$group['members'][0];
        }
        $group['strength']=min(100,10*count($group['members']));
    }
    unset($group);
    $world['religions']=array_values(array_filter($world['religions'],fn($g)=>count($g['members'])>=2));
    if (!empty($world['sign']['witnesses'])) $world['sign']['witnesses']=array_values(array_diff($world['sign']['witnesses'],[$id]));
    $db->prepare('DELETE FROM queue WHERE person=?')->execute([$id]);
    journal($db,$world,'','Zemřel'.($female?'a':'').' '.$p['name'],'Ve věku '.$p['age'].' let ('.$cause.'). '.($buried?'Pochován'.($female?'a':'').' na vesnickém hřbitově.':'Vesnice se loučí.'),'Příběh');
}

function flushDeaths(PDO $db,array &$world): void {
    $pending=$world['pendingDeaths']??[];
    $world['pendingDeaths']=[];
    foreach ($pending as [$id,$cause]) removePerson($db,$world,$id,$cause);
}

function maybeInjure(PDO $db,array &$world,string $id,string $key): void {
    $factor=['felling'=>1,'quarrying'=>1.4,'saw'=>1.2,'construct'=>1,'build_house'=>1,'haul_stone'=>.6,'haul_wood'=>.5,'load_kiln'=>.8,'mine_ore'=>1.5,'smelt_iron'=>.5,'mow'=>.5,
        'firefight'=>12,'flood_defense'=>8][$key]??0;
    if ($factor<=0) return;
    $p=&$world['people'][$id];
    $now=(int)$world['minute'];
    if (!empty($p['illUntil']) && $p['illUntil']>$now) return;
    if (weatherNoise($now,'injury:'.$id.':'.$key)>=.00025*$factor) return;
    $p['illUntil']=$now+5*1440;
    $p['fatigue']=clamp($p['fatigue']+25);
    $female=$p['sex']==='f';
    journal($db,$world,$id,$p['name'].' utrpěl'.($female?'a':'').' úraz','Při práci se zranil'.($female?'a':'').'. Několik dní bude pracovat hůř a potřebuje klid.','Příběh');
    rememberEvent($db,$world,$id,'krize','Úraz při práci.',5);
    $world['counters']['injuries']=($world['counters']['injuries']??0)+1;
    if (weatherNoise($now,'injury-fatal:'.$id)<.03) $world['pendingDeaths'][]=[$id,'úraz'];
}

function newChild(array $world,string $sex,array $mother,array $father,int $n): array {
    $names=$sex==='f'?GIRL_NAMES:BOY_NAMES;
    $id='d'.$n;
    $name=$names[crc32($id)%count($names)].' '.surnameFor($sex,$father['name']);
    $p=['id'=>$id,'name'=>$name,'family'=>$father['family'],'home'=>homeOf($mother),'age'=>0,'role'=>'Dítě','work'=>'yard',
        'color'=>CHILD_COLORS[$n%count(CHILD_COLORS)],'trait'=>'Novorozeně, hodně spí a pláče.','skill'=>30,'fatigue'=>10,
        'mood'=>90,'trust'=>50,'faith'=>$mother['faith'],'fear'=>5,'hunger'=>12,'fun'=>100.0,'task'=>null,'place'=>'home',
        'activity'=>'Spí v kolébce','bonds'=>[],'grudges'=>[],'charisma'=>50+crc32($id)%30,'lastPrayer'=>-1440,
        'resistUntil'=>0,'illUntil'=>0,'awayUntil'=>0];
    $p+=lifeDefaults($p,intdiv((int)$world['minute'],1440));
    $p['birthDay']=intdiv((int)$world['minute'],1440);
    $p['sex']=$sex;
    $p['skills']=inheritedSkills($mother,$father);
    $p['mother']=$mother['id'];
    $p['father']=$father['id'];
    return $p;
}

// Dvojčata: samostatný kanál šumu s promíchaným číslem (stejné číslo v jiných kanálech by bylo se šumem narození svázané).
function twinBirth(int $n): bool {
    return weatherNoise($n*104729+31,'twin-roll')<TWIN_CHANCE;
}

function giveBirth(PDO $db,array &$world,string $motherId): void {
    $mother=$world['people'][$motherId];
    $now=(int)$world['minute'];
    $fatherId=$mother['pregnant']['father']??null;
    $father=$world['people'][$fatherId]??null;
    $world['people'][$motherId]['pregnant']=null;
    $world['people'][$motherId]['lastBirth']=$now;
    $world['people'][$motherId]['fatigue']=clamp($mother['fatigue']+25);
    $world['people'][$motherId]['mood']=clamp($mother['mood']+12);
    if ($father===null) $father=$mother;
    $n=($world['nextChild']??0)+1;
    // Dvojčata (TWIN_CHANCE): druhé dítě dostane další číslo; porod dvojčat je pro matku rizikovější.
    $count=twinBirth($n) ? 2 : 1;
    $world['nextChild']=$n+$count-1;
    $world['counters']['births']=($world['counters']['births']??0)+$count;
    if ($count>1) $world['counters']['twins']=($world['counters']['twins']??0)+1;
    $motherDies=weatherNoise($n,'mother-death')<.01*($count>1?3:1);
    $born=[];
    for ($k=0;$k<$count;$k++) {
        $cn=$n+$k;
        $sex=weatherNoise($cn,'child-sex')<.5?'f':'m';
        $survives=weatherNoise($cn,'newborn-death')>=.02;
        if ($survives) {
            $child=newChild($world,$sex,$mother,$father,$cn);
            foreach ($world['people'] as $oid=>&$o) {
                $family=$o['family']===$child['family'];
                $child['bonds'][$oid]=$family?78:52;
                $o['bonds'][$child['id']]=$family?76:54;
                if ($oid===$fatherId && $k===0) $o['mood']=clamp($o['mood']+8);
            }
            unset($o);
            $world['people'][$child['id']]=$child;
            foreach ($world['people'] as $oid=>$o) {
                if ($oid!==$child['id'] && ($o['family']===$child['family'] || ($o['bonds'][$motherId]??48)>=65)) {
                    rememberEvent($db,$world,$oid,'příběh','Narodil'.($sex==='f'?'a':'').' se '.$child['name'].'.',4);
                }
            }
            $born[]=$child;
        } else {
            $world['people'][$motherId]['mood']=clamp($world['people'][$motherId]['mood']-30);
            $world['counters']['deaths']['porod']=($world['counters']['deaths']['porod']??0)+1;
            journal($db,$world,$motherId,'Dítě se narodilo mrtvé','Rodiče '.$mother['name'].' a '.$father['name'].' truchlí.','Příběh');
            $world['graveyard'][]=['id'=>'d'.$cn,'name'=>'Novorozenec rodiny '.$father['family'],'age'=>0,'cause'=>'porod','minute'=>$now,'family'=>$father['family'],'sex'=>$sex];
        }
    }
    if (count($born)===2) {
        journal($db,$world,$motherId,'Narodila se dvojčata '.$born[0]['name'].' a '.$born[1]['name'],
            'Rodiče '.$mother['name'].' a '.$father['name'].' mají dvojčata. Vesnice se raduje, ale v domě bude těsněji.','Příběh');
    } elseif (count($born)===1) {
        $child=$born[0];
        journal($db,$world,$motherId,'Narodil'.($child['sex']==='f'?'a':'').' se '.$child['name'],
            ($child['sex']==='f'?'Dcera':'Syn').' rodičů '.$mother['name'].' a '.($father['name']).'. Vesnice se raduje.'.($count>1?' Druhé dvojče se nenarodilo živé.':''),'Příběh');
    }
    if ($motherDies) removePerson($db,$world,$motherId,'porod');
}

function checkConception(PDO $db,array &$world,int $day): void {
    $now=(int)$world['minute'];
    if (count($world['people'])>=housingCapacity($world) || foodReserve($world)<dailyFoodNeed($world)*8) return;
    $houses=houseList($world);
    foreach ($world['people'] as $id=>&$p) {
        if ($p['sex']!=='f' || $p['age']<18 || $p['age']>41 || !empty($p['pregnant']) || empty($p['spouse'])) continue;
        // Ve zcela přeplněném domě se další dítě nezačne (jeden člověk navíc se ještě snese).
        $house=homeOf($p);
        if (isset($houses[$house]) && count(residentsOf($world,$house))>$houses[$house]['capacity']) continue;
        $spouse=$world['people'][$p['spouse']]??null;
        if (!$spouse || $now-$p['lastBirth']<BIRTH_INTERVAL_DAYS*1440 || $p['mood']<40 || (!empty($p['illUntil']) && $p['illUntil']>$now)) continue;
        $ageFactor=$p['age']<30?1.0:($p['age']<36?.8:($p['age']<=38?.45:.12));
        $chance=.0035*$ageFactor*($world['harvestUntil']>$now?.6:1);
        if (weatherNoise($day,'conceive:'.$id)>=$chance) continue;
        $length=PREGNANCY_DAYS-5+crc32($id.$day)%11;
        $p['pregnant']=['since'=>$now,'due'=>$now+$length*1440,'father'=>$p['spouse'],'revealed'=>false];
    }
    unset($p);
}

function checkRomance(PDO $db,array &$world,int $day): void {
    $people=$world['people'];
    foreach ($people as $id=>$p) {
        if ($p['age']<16 || !empty($p['spouse']) || !empty($p['betrothed'])) continue;
        foreach ($people as $oid=>$o) {
            if ($o['age']<16 || !empty($o['spouse']) || !empty($o['betrothed']) || $o['sex']===$p['sex'] || $o['family']===$p['family'] ||
                abs($o['age']-$p['age'])>14 || ($p['bonds'][$oid]??48)<62 || ($o['bonds'][$id]??48)<62) continue;
            $bothAdult=$p['age']>=18 && $o['age']>=18;
            if (($p['crush']??null)===$oid || ($o['crush']??null)===$id) {
                if ($bothAdult && ($p['bonds'][$oid]??48)>=70 && ($o['bonds'][$id]??48)>=70 &&
                    weatherNoise($day,'wedding:'.$id.':'.$oid)<.04) { betroth($db,$world,$id,$oid); return; }
            } elseif (empty($p['crush']) && weatherNoise($day,'crush:'.$id.':'.$oid)<.01) {
                $world['people'][$id]['crush']=$oid;
                journal($db,$world,$id,$p['name'].($p['sex']==='f'?' se zamilovala':' se zamiloval'),
                    'Mezi '.$p['name'].' a '.$o['name'].' vzniká sympatie.','Příběh');
                return;
            }
        }
    }
}

function marry(PDO $db,array &$world,string $a,string $b,string $where=''): void {
    $groom=$world['people'][$a]['sex']==='m'?$a:$b;
    $bride=$groom===$a?$b:$a;
    $world['people'][$groom]['spouse']=$bride;
    $world['people'][$bride]['spouse']=$groom;
    $world['people'][$bride]['family']=$world['people'][$groom]['family'];
    foreach ([$groom,$bride] as $id) {
        $world['people'][$id]['mood']=clamp($world['people'][$id]['mood']+20);
        $world['people'][$id]['fun']=clamp($world['people'][$id]['fun']+15);
        unset($world['people'][$id]['crush']);
        rememberEvent($db,$world,$id,'příběh','Svatba: '.$world['people'][$groom]['name'].' a '.$world['people'][$bride]['name'].'.',5);
    }
    $world['people'][$groom]['bonds'][$bride]=clamp(($world['people'][$groom]['bonds'][$bride]??48)+10);
    $world['people'][$bride]['bonds'][$groom]=clamp(($world['people'][$bride]['bonds'][$groom]??48)+10);
    $world['counters']['weddings']=($world['counters']['weddings']??0)+1;
    journal($db,$world,$groom,'Svatba: '.$world['people'][$groom]['name'].' a '.$world['people'][$bride]['name'],
        ($where!=='' ? 'Obřad byl '.$where.'. ' : '').'Vesnice slaví první společné posvícení nového páru.','Příběh');
}

function lifeDaily(PDO $db,array &$world): void {
    $now=(int)$world['minute'];
    $day=intdiv($now,1440);
    foreach ($world['people'] as $id=>&$p) {
        $age=intdiv($day-$p['birthDay'],360);
        if ($age>$p['age']) {
            $p['age']=$age;
            $p['role']=in_array($age,[12,15,18],true)?roleForAge($p):$p['role'];
            if ($age===12) $p['skill']=max($p['skill'],40);
            if ($age===12) $p['skills']=array_map(fn($level)=>max($level,12.0),skillsOf($p));
            if ($age===15) $p['skill']=max($p['skill'],50);
            if ($age===18) $p['skill']=max($p['skill'],58);
            if ($age>=60 && $age%5===0) $p['skill']=max(35,$p['skill']-3);
            journal($db,$world,$id,$p['name'].' slaví narozeniny',($p['sex']==='f'?'Je jí ':'Je mu ').$age.' let.'.
                (in_array($age,[12,15,18],true)?' Přebírá větší povinnosti.':''),'Příběh');
        }
        if (!empty($p['pregnant']) && empty($p['pregnant']['revealed']) && $now-$p['pregnant']['since']>=60*1440) {
            $p['pregnant']['revealed']=true;
            journal($db,$world,$id,$p['name'].' čeká dítě','Rodina se raduje a zbytek vsi pomáhá, jak může.','Příběh');
            rememberEvent($db,$world,$id,'příběh','Čeká dítě.',4);
        }
    }
    unset($p);
    foreach ($world['people'] as $id=>$p) {
        if (!empty($p['pregnant']) && $now>=$p['pregnant']['due']) giveBirth($db,$world,$id);
    }
    foreach ($world['people'] as $id=>$p) {
        if (!isset($world['people'][$id])) continue;
        $cause=deathCause($world,$p,$day);
        if ($cause!==null) removePerson($db,$world,$id,$cause);
    }
    if ($world['people']) {
        checkRomance($db,$world,$day);
        checkConception($db,$world,$day);
        householdDaily($db,$world);
    }
}
