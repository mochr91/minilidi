<?php
declare(strict_types=1);

// Bydlení, zasnoubení a svatby. `family` je rodina (příbuzenství, příjmení), `home` je dům, ve kterém člověk bydlí.
// Dům má šest míst (rodina se čtyřmi lidmi a prarodiči, nebo šestičlenná rodina), domek čtyři.
// Když se v domě zúží (narodí se dítě), odstěhují se prarodiče nebo mladý pár do volného domu, do domu s místem,
// případně se postaví domek či dům. Pár se bere až po zasnoubení, v kapli nebo v kostele, a jen když má kde bydlet.

const HOUSE_SITES = ['Kovářovi'=>[2,6],'Doležalovi'=>[4,6],'Hruškovi'=>[6,6],'Novákovi'=>[15,6],'Královi'=>[2,11],'Veselí'=>[4,11]];
const HOUSE_PLOTS = [[14,11],[12,12],[8,3],[13,3]];      // pořadí odpovídá `plots` v map.js (dum1 až dum4)
const HOUSE_ALIASES = ['Mlynářovi'=>'dum2','Hrubí'=>'dum3','Sedláčkovi'=>'dum4'];
const HOUSE_CAPACITY = 6;
const COTTAGE_CAPACITY = 4;
const COTTAGE_WORK = 30;
const WEDDING_LEAD_DAYS = 3;
const WEDDING_HOUR = 10;
const TWIN_CHANCE = .015;
const ELDER_AGE = 55;
const NEWCOMER_FIRST_DAY = 40;       // příchozí osamělí dospělí se objevují od tohoto dne a dál po NEWCOMER_GAP_DAYS dnech
const NEWCOMER_GAP_DAYS = 20;

function homeOf(array $p): string {
    return (string)($p['home']??$p['family']);
}

// Stojící domy: šest pevných a postavené (dum1 až dum4, dům nebo domek).
function houseList(array $world): array {
    $houses=[];
    foreach (HOUSE_SITES as $id=>$tile) $houses[$id]=['id'=>$id,'kind'=>'static','capacity'=>HOUSE_CAPACITY,'tile'=>$tile,'built'=>0];
    foreach (array_values($world['homesBuilt']??[]) as $i=>$minute) {
        $kind=$world['houseKinds'][$i]??'dum';
        $houses['dum'.($i+1)]=['id'=>'dum'.($i+1),'kind'=>$kind,'capacity'=>$kind==='domek'?COTTAGE_CAPACITY:HOUSE_CAPACITY,
            'tile'=>HOUSE_PLOTS[$i]??[0,0],'built'=>(int)$minute];
    }
    return $houses;
}

function houseLabel(string $id,array $houses): string {
    $house=$houses[$id]??null;
    if ($house===null) return 'dům';
    if ($house['kind']==='static') return 'dům rodiny '.$id;
    return ($house['kind']==='domek'?'nový domek č. ':'nový dům č. ').substr($id,3);
}

function housingCapacity(array $world): int {
    return array_sum(array_column(houseList($world),'capacity'));
}

function residentsOf(array $world,string $house): array {
    $ids=[];
    foreach ($world['people'] as $id=>$p) if (homeOf($p)===$house) $ids[]=$id;
    return $ids;
}

function houseSpace(array $world,array $houses,string $house): int {
    return ($houses[$house]['capacity']??0)-count(residentsOf($world,$house));
}

// Volné domy pro nové obyvatele: nejdřív naposledy postavené, pevné domy nakonec.
function freeHouses(array $world,int $size=1): array {
    $houses=houseList($world);
    uasort($houses,fn($a,$b)=>$b['built']<=>$a['built']);
    $free=[];
    foreach ($houses as $id=>$house) {
        if (!residentsOf($world,$id) && $house['capacity']>=$size) $free[]=$id;
    }
    return $free;
}

// Dům pro skupinu `$size` lidí: volný dům, jinak dům s dost místa (nejvolnější, příbuzní přednostně).
function houseFor(array $world,int $size,array $related=[],?string $exclude=null,int $tolerance=0): ?string {
    $houses=houseList($world);
    $best=null; $bestScore=-INF;
    foreach ($houses as $id=>$house) {
        if ($id===$exclude) continue;
        $space=$house['capacity']+$tolerance-count(residentsOf($world,$id));
        if ($space<$size) continue;
        $residents=residentsOf($world,$id);
        $score=($residents?0:30)+$space*2+$house['built']/1e7;
        foreach ($residents as $rid) {
            $p=$world['people'][$rid];
            foreach ($related as $kin) {
                if (($world['people'][$kin]['family']??null)===$p['family'] || ($p['mother']??null)===$kin || ($p['father']??null)===$kin ||
                    ($world['people'][$kin]['mother']??null)===$rid || ($world['people'][$kin]['father']??null)===$rid) $score+=50;   // příbuzní mají přednost před prázdným domem
            }
        }
        if ($score>$bestScore) { $best=$id; $bestScore=$score; }
    }
    return $best;
}

// Dům pro nově příchozí rodinu: pevný dům jejího příjmení, když stojí a je prázdný, jinak volný dům.
function homeForNewFamily(array $world,string $family,int $size): ?string {
    if (isset(HOUSE_SITES[$family]) && !residentsOf($world,$family)) return $family;
    $free=freeHouses($world,$size);
    return $free[0]??null;
}

function moveHome(PDO $db,array &$world,array $ids,string $house,string $why): void {
    $houses=houseList($world);
    $names=[];
    foreach ($ids as $id) {
        if (!isset($world['people'][$id])) continue;
        $world['people'][$id]['home']=$house;
        $names[]=$world['people'][$id]['name'];
    }
    if (!$names) return;
    journal($db,$world,(string)$ids[0],$why,implode(', ',$names).' se stěhují do domu: '.houseLabel($house,$houses).'.','Příběh');
    foreach ($ids as $id) if (isset($world['people'][$id])) rememberEvent($db,$world,$id,'příběh',$why.'.',3);
}

function homeMigrate(array &$world): void {
    foreach ($world['people'] as $id=>&$p) {
        if (isset($p['home'])) continue;
        $guess=HOUSE_ALIASES[$p['family']]??$p['family'];
        $p['home']=$guess;
    }
    unset($p);
    $houses=houseList($world);
    foreach ($world['people'] as $id=>&$p) {
        if (isset($houses[$p['home']])) continue;
        // Rodina, jejíž dům ještě nestojí: nejvolnější dům (zpravidla se doplní při příchodu).
        $free=freeHouses($world,1);
        $p['home']=$free[0]??houseFor($world,1)??array_key_first($houses);
    }
    unset($p);
}

function housingSummary(array $world): array {
    $houses=houseList($world);
    $out=[];
    foreach ($houses as $id=>$house) {
        $out[]=['id'=>$id,'label'=>houseLabel($id,$houses),'kind'=>$house['kind'],'capacity'=>$house['capacity'],'residents'=>residentsOf($world,$id)];
    }
    return $out;
}

// --- Zasnoubení a svatba ---

function betroth(PDO $db,array &$world,string $a,string $b): void {
    foreach ([[$a,$b],[$b,$a]] as [$x,$y]) {
        $world['people'][$x]['betrothed']=$y;
        unset($world['people'][$x]['crush'],$world['people'][$x]['wedding']);
        rememberEvent($db,$world,$x,'příběh','Zasnoubení: '.$world['people'][$a]['name'].' a '.$world['people'][$b]['name'].'.',5);
    }
    $world['counters']['betrothals']=($world['counters']['betrothals']??0)+1;
    journal($db,$world,$a,'Zasnoubení: '.$world['people'][$a]['name'].' a '.$world['people'][$b]['name'],
        'Mladý pár se domluvil. Svatba bude v kapli, až bude kde bydlet.','Příběh');
}

// Nezletilí, kteří by po odchodu `$leaving` zůstali v domě bez dospělého, jdou s nimi.
function orphansIfLeft(array $world,string $house,array $leaving): array {
    $rest=array_diff(residentsOf($world,$house),$leaving);
    foreach ($rest as $rid) if ($world['people'][$rid]['age']>=18) return [];
    return array_values(array_filter($rest,fn($rid)=>$world['people'][$rid]['age']<18));
}

function withOrphans(array $world,array $movers): array {
    $group=$movers;
    $byHouse=[];
    foreach ($movers as $m) $byHouse[homeOf($world['people'][$m])][]=$m;
    foreach ($byHouse as $house=>$leaving) $group=array_merge($group,orphansIfLeft($world,(string)$house,$leaving));
    return array_values(array_unique($group));
}

// Kde bude pár bydlet: u rodičů ženicha, u rodičů nevěsty, ve volném domě, jinde s místem. null = nikde.
// Kdo by po odchodu zůstal v domě sám (děti bez dospělého), stěhuje se s nimi.
function newlywedHome(array $world,string $groom,string $bride,int $tolerance): ?array {
    $houses=houseList($world);
    $gh=homeOf($world['people'][$groom]);
    $bh=homeOf($world['people'][$bride]);
    foreach ([[$gh,[$bride]],[$bh,[$groom]]] as [$house,$movers]) {
        if (!isset($houses[$house])) continue;
        $group=withOrphans($world,$movers);
        $need=count(array_filter($group,fn($m)=>homeOf($world['people'][$m])!==$house));
        if ($houses[$house]['capacity']+$tolerance-count(residentsOf($world,$house))>=$need) return ['house'=>$house,'movers'=>$group];
    }
    $group=withOrphans($world,[$groom,$bride]);
    $free=freeHouses($world,count($group));
    if ($free) return ['house'=>$free[0],'movers'=>$group];
    $other=houseFor($world,count($group),[$groom,$bride],null,0);
    if ($other!==null) return ['house'=>$other,'movers'=>$group];
    return null;
}
function planWeddings(PDO $db,array &$world,array &$needs): void {
    $now=(int)$world['minute'];
    $day=intdiv($now,1440);
    $limit=count($world['homesBuilt']??[])>=HOME_LIMIT ? 1 : 0;
    foreach ($world['people'] as $id=>$p) {
        $partner=$p['betrothed']??null;
        if ($partner===null) continue;
        if (!isset($world['people'][$partner]) || ($world['people'][$partner]['betrothed']??null)!==$id) {
            $world['people'][$id]['betrothed']=null;
            unset($world['people'][$id]['wedding']);
            continue;
        }
        if ($id>$partner) continue;      // každý pár jednou
        $groom=$p['sex']==='m'?$id:$partner;
        $bride=$groom===$id?$partner:$id;
        $plan=$world['people'][$id]['wedding']??null;
        if ($plan!==null) {
            $houses=houseList($world);
            $ok=isset($houses[$plan['house']]) && !array_filter($plan['movers'],fn($m)=>!isset($world['people'][$m])) && array_sum(array_map(fn($m)=>homeOf($world['people'][$m])===$plan['house']?0:1,$plan['movers']))<=
                $houses[$plan['house']]['capacity']+$limit-count(residentsOf($world,$plan['house']));
            if ($ok) continue;
            unset($world['people'][$id]['wedding'],$world['people'][$partner]['wedding']);
        }
        $home=newlywedHome($world,$groom,$bride,$limit);
        if ($home===null) { $needs[]=2; continue; }
        $plan=['day'=>$day+WEDDING_LEAD_DAYS,'house'=>$home['house'],'movers'=>$home['movers']];
        $world['people'][$id]['wedding']=$plan;
        $world['people'][$partner]['wedding']=$plan;
        journal($db,$world,$groom,'Svatba se chystá','Svatba '.$world['people'][$groom]['name'].' a '.$world['people'][$bride]['name'].' bude za '.WEDDING_LEAD_DAYS.' dny a mladí budou bydlet v domě: '.houseLabel($home['house'],houseList($world)).'.','Příběh');
    }
}

// Každý den v 10 hodin: pár, který má termín a bydlení, jde na obřad (kaple nebo kostel).
function weddingHour(PDO $db,array &$world): void {
    $now=(int)$world['minute'];
    if ((int)(($now%1440)/60)!==WEDDING_HOUR || !empty($world['crisis'])) return;
    $day=intdiv($now,1440);
    foreach ($world['people'] as $id=>$p) {
        $plan=$p['wedding']??null;
        $partner=$p['betrothed']??null;
        if ($plan===null || $partner===null || $id>$partner || !isset($world['people'][$partner]) || $plan['day']>$day) continue;
        $busy=false;
        foreach ([$id,$partner] as $who) {
            $q=$world['people'][$who];
            if (($q['awayUntil']??0)>$now || (!empty($q['illUntil']) && $q['illUntil']>$now) || ($q['task'] && empty($q['task']['automatic']))) $busy=true;
        }
        if ($busy) {
            $plan['day']=$day+1;
            $world['people'][$id]['wedding']=$plan;
            $world['people'][$partner]['wedding']=$plan;
            continue;
        }
        foreach ([$id,$partner] as $who) {
            assignWork($world,$who,'wedding',true);
            $world['people'][$who]['task']['other']=$who===$id?$partner:$id;
        }
    }
}

function completeWedding(PDO $db,array &$world,string $id): string {
    $partner=$world['people'][$id]['task']['other']??null;
    if ($partner===null || !isset($world['people'][$partner]) || !empty($world['people'][$id]['spouse'])) return 'Svatba už proběhla.';
    $plan=$world['people'][$id]['wedding']??null;
    $groom=$world['people'][$id]['sex']==='m'?$id:$partner;
    $bride=$groom===$id?$partner:$id;
    $where=hasBuilding($world,'kostel') ? 'v kostele'
        : ((doctrineFor($world,$groom)==='tree' && doctrineFor($world,$bride)==='tree') ? 'u posvátného stromu' : 'v kapli');
    marry($db,$world,$groom,$bride,$where);
    foreach ([$groom,$bride] as $who) {
        unset($world['people'][$who]['betrothed'],$world['people'][$who]['wedding']);
        $world['people'][$who]['faith']=clamp($world['people'][$who]['faith']+(hasBuilding($world,'kostel')?3:2));
    }
    // Hosté: kdo má k jednomu z novomanželů vztah, má radost.
    foreach ($world['people'] as $gid=>&$guest) {
        if ($gid===$groom || $gid===$bride) continue;
        if (max($guest['bonds'][$groom]??48,$guest['bonds'][$bride]??48)>=55 || $guest['family']===$world['people'][$groom]['family']) {
            $guest['mood']=clamp($guest['mood']+4);
            $guest['fun']=clamp($guest['fun']+6);
        }
    }
    unset($guest);
    $house=$plan['house']??null;
    if ($house!==null && isset(houseList($world)[$house])) {
        $movers=array_values(array_filter($plan['movers']??[],fn($m)=>isset($world['people'][$m]) && homeOf($world['people'][$m])!==$house));
        if ($movers) moveHome($db,$world,$movers,$house,'Novomanželé se stěhují');
    }
    return 'Svatba '.$where.' proběhla.';
}

// --- Těsno v domě ---

// Kdo se při přeplnění odstěhuje: prarodiče, jinak mladý pár s dětmi, jinak (v domku) celá domácnost.
function crowdingGroup(array $world,array $houses,string $house,array $residents): ?array {
    $people=$world['people'];
    $minorsOf=function(array $adults) use ($people,$residents): array {
        $kids=[];
        foreach ($residents as $rid) {
            if ($people[$rid]['age']>=18 || in_array($rid,$adults,true)) continue;
            if (in_array($people[$rid]['mother']??'',$adults,true) || in_array($people[$rid]['father']??'',$adults,true)) $kids[]=$rid;
        }
        return $kids;
    };
    $elders=array_values(array_filter($residents,fn($rid)=>$people[$rid]['age']>=ELDER_AGE));
    usort($elders,fn($a,$b)=>$people[$b]['age']<=>$people[$a]['age']);
    if ($elders) {
        $group=[$elders[0]];
        $spouse=$people[$elders[0]]['spouse']??null;
        if ($spouse && in_array($spouse,$residents,true)) $group[]=$spouse;
        $rest=array_diff($residents,$group);
        $adultsLeft=array_filter($rest,fn($rid)=>$people[$rid]['age']>=18 && $people[$rid]['age']<ELDER_AGE);
        if ($adultsLeft) return $group;
    }
    // Mladý pár s dětmi: každý pár kromě nejstaršího dospělého páru.
    $adults=array_values(array_filter($residents,fn($rid)=>$people[$rid]['age']>=18));
    usort($adults,fn($a,$b)=>$people[$b]['age']<=>$people[$a]['age']);
    $headCouple=$adults ? array_filter([$adults[0],$people[$adults[0]]['spouse']??null],fn($v)=>$v!==null) : [];
    foreach ($adults as $rid) {
        $spouse=$people[$rid]['spouse']??null;
        if (in_array($rid,$headCouple,true) || $spouse===null || !in_array($spouse,$residents,true) || $rid>$spouse) continue;
        $couple=[$rid,$spouse];
        return array_merge($couple,$minorsOf($couple));
    }
    if (($houses[$house]['kind']??'')==='domek') return $residents;
    return null;
}

function resolveCrowding(PDO $db,array &$world,array &$needs): void {
    $limit=count($world['homesBuilt']??[])>=HOME_LIMIT ? 1 : 0;
    foreach (houseList($world) as $house=>$info) {
        $residents=residentsOf($world,$house);
        if (count($residents)<=$info['capacity']) continue;
        $houses=houseList($world);
        $group=crowdingGroup($world,$houses,$house,$residents);
        if ($group===null) continue;
        $target=houseFor($world,count($group),$group,$house,0);
        if ($target===null) {
            if (!$limit) $needs[]=count($group);
            continue;
        }
        $elder=$world['people'][$group[0]]['age']>=ELDER_AGE;
        moveHome($db,$world,$group,$target,$elder?'Prarodiče se stěhují (v domě je těsno)':'Rodina se stěhuje (v domě je těsno)');
    }
}

// Děti, které zůstaly v domě bez dospělého (rodiče zemřeli nebo odešli): stěhují se k příbuzným, jinak do domu s místem.
function resolveOrphans(PDO $db,array &$world): void {
    foreach (houseList($world) as $house=>$info) {
        $residents=residentsOf($world,$house);
        if (!$residents) continue;
        $adult=false;
        foreach ($residents as $rid) if ($world['people'][$rid]['age']>=18) $adult=true;
        if ($adult) continue;
        // Jen do domu, kde bydlí dospělý (jinak by se děti stěhovaly z prázdného domu do prázdného); příbuzní mají přednost.
        $target=null; $best=-INF;
        foreach (houseList($world) as $hid=>$h) {
            if ($hid===$house) continue;
            $there=residentsOf($world,$hid);
            if (!array_filter($there,fn($rid)=>$world['people'][$rid]['age']>=18)) continue;
            $space=$h['capacity']+1-count($there);
            if ($space<count($residents)) continue;
            $score=$space;
            foreach ($there as $rid) foreach ($residents as $kid) {
                $a=$world['people'][$rid]; $k=$world['people'][$kid];
                if ($a['family']===$k['family'] || ($k['mother']??null)===$rid || ($k['father']??null)===$rid) $score+=50;
            }
            if ($score>$best) { $best=$score; $target=$hid; }
        }
        if ($target!==null) moveHome($db,$world,$residents,$target,'Děti bez dospělého se stěhují');
    }
}
function householdDaily(PDO $db,array &$world): void {
    $needs=[];
    planWeddings($db,$world,$needs);
    resolveCrowding($db,$world,$needs);
    resolveOrphans($db,$world);
    $wanted=null;
    if ($needs && count($world['homesBuilt']??[])<HOME_LIMIT) $wanted=max($needs)>COTTAGE_CAPACITY ? 'dum' : 'domek';
    if (!empty($world['houseProject']['kind'])) $wanted=$world['houseProject']['kind'];
    $world['houseWanted']=$wanted;
}
