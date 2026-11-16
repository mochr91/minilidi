<?php
declare(strict_types=1);

// Fáze H rozšíření ekonomiky (návrh v ROZSIRENI-EKONOMIKY.md bod 13): růst vesnice.
// Přistěhovalci přicházejí podle prosperity (jídlo, dřevo, nálada, volné bydlení, bez hladu), rodina při hladu odchází,
// hodnost vesnice (osada, ves, větší ves, městys) zkracuje návštěvy obchodníka a městys má sobotní trh.

const POP_CAP_MAX = 64;
const SETTLER_MIN_INTERVAL = 14;
const SETTLER_MAX_INTERVAL = 45;
const SETTLER_FOOD_DAYS = 40;         // rezerva jídla ve dnech, aby někdo přišel
const SETTLER_MOOD = 55;              // průměrná nálada
const SETTLER_QUIET_DAYS = 30;        // tolik dní před příchodem nesmí být hlad
const SETTLER_LEAVE_STREAK = 7;       // dní hladu po sobě, po kterých odejde rodina
const SETTLER_LEAVE_GAP = 60;         // nejdřív po tolika dnech může odejít další
const SETTLER_TYPES = ['single'=>35,'couple'=>25,'family'=>30,'widow'=>10];
const RANKS = ['osada'=>'Osada','ves'=>'Ves','vetsi_ves'=>'Větší ves','mestys'=>'Městys'];
const TRADE_INTERVALS = ['osada'=>30,'ves'=>21,'vetsi_ves'=>14,'mestys'=>14];
const MARKET_DAY_MOOD = 2.0;          // městys: sobotní trh zvedne všem náladu

const SETTLER_BOY_NAMES = ['Václav','Vít','Štěpán','Jakub','Martin','Šimon','Matěj','Havel','Ondřej','Petr','Jan','Jiří','Tomáš','Lukáš','Adam','Mikuláš','Prokop','Bartoloměj','Filip','Řehoř','Vojtěch','Kryštof','Zdeněk','Bohumil'];
const SETTLER_GIRL_NAMES = ['Eliška','Anežka','Marie','Dorota','Alžběta','Johanka','Kristýna','Magdaléna','Lidmila','Barbora','Kateřina','Zuzana','Ludmila','Markéta','Veronika','Apolena','Salomena','Rozálie','Hana','Dobromila','Mariana','Terezie','Vendula','Zdenka'];
// Příjmení v mužském tvaru a tvar rodiny na mapě (ženy dostanou tvar přes `surnameFor`).
const SETTLER_SURNAMES = [['Beneš','Benešovi'],['Novotný','Novotní'],['Svoboda','Svobodovi'],['Dvořák','Dvořákovi'],['Černý','Černí'],
    ['Procházka','Procházkovi'],['Kučera','Kučerovi'],['Horák','Horákovi'],['Němec','Němcovi'],['Pospíšil','Pospíšilovi'],['Pokorný','Pokorní'],
    ['Hájek','Hájkovi'],['Jelínek','Jelínkovi'],['Růžička','Růžičkovi'],['Fiala','Fialovi'],['Sedlák','Sedlákovi'],['Zeman','Zemanovi'],
    ['Kolář','Kolářovi'],['Janda','Jandovi'],['Marek','Markovi'],['Bartoš','Bartošovi'],['Vlček','Vlčkovi'],['Kopecký','Kopečtí'],['Urban','Urbanovi'],
    ['Šimek','Šimkovi'],['Blažek','Blažkovi'],['Soukup','Soukupovi'],['Tichý','Tichí'],['Kadlec','Kadlecovi'],['Holub','Holubovi']];
// Místa práce: [muž, žena] podle toho, kde je ve vsi nejméně lidí.
const SETTLER_WORKS = ['field'=>['Rolník','Rolnice'],'forest'=>['Dřevorubec','Sběračka'],'river'=>['Rybář','Rybářka'],
    'yard'=>['Pomocník','Hospodyně'],'market'=>['Pomocník na návsi','Prodavačka na návsi']];
const SETTLER_TRAITS = ['Přišla z kraje, kde zdražilo, a hledá stálé místo.','Přišli za prací, protože slyšeli o dobré vsi s farou a mlýnem.','Poutníci, kteří se rozhodli zůstat přes zimu.',
    'Utekli před nedostatkem v rodné vsi a jsou vděční za střechu nad hlavou.','Řemeslný původ, rádi se pustí do každé práce.','Tichá a pracovitá rodina bez rozepří.',
    'Zvědaví a družní, hned se ptají sousedů na zvyky.','Zdraví a pracovití, slibují pomoc při žních.'];
const SETTLER_COLORS = ['#856146','#76425d','#5d6b4a','#7a5a76','#6e7f5a','#8a6a43','#5f7f9a','#a0644a','#4f8a7a','#b08a3e'];

function villageRank(array $world): string {
    $pop=count($world['people']);
    if ($pop>=61 && count($world['buildings'])>=30) return 'mestys';
    if ($pop>=41) return 'vetsi_ves';
    if ($pop>=21) return 'ves';
    return 'osada';
}

function popCap(array $world): int {
    return min(POP_CAP_MAX,housingCapacity($world));
}

// 0 až 1: jak se vesnici daří (jídlo, nálada, postavené budovy). Ovlivňuje, jak často přicházejí noví lidé.
function settlerProsperity(array $world): float {
    $people=max(1,count($world['people']));
    $reserve=foodReserve($world)/max(1.0,dailyFoodNeed($world));
    $mood=array_sum(array_column($world['people'],'mood'))/$people;
    $score=(min(1.0,max(0.0,($reserve-SETTLER_FOOD_DAYS)/60))+min(1.0,max(0.0,($mood-SETTLER_MOOD)/30))+min(1.0,count($world['buildings'])/33))/3;
    return round($score,3);
}

function settlerInterval(array $world): int {
    return (int)round(SETTLER_MAX_INTERVAL-(SETTLER_MAX_INTERVAL-SETTLER_MIN_INTERVAL)*settlerProsperity($world));
}

// Proč dnes nikdo nepřijde (null = může přijít skupina).
function settlersBlocked(array $world,int $day): ?string {
    $s=$world['settlers']??[];
    if ($day<(int)($s['nextDay']??0)) return 'rozestup';
    if (calendarMonth($world)>=9) return 'zima';
    if (!empty($world['crisis'])) return 'krize';
    $people=count($world['people']);
    if ($people>=popCap($world)) return 'strop';
    if (foodReserve($world)<dailyFoodNeed($world)*SETTLER_FOOD_DAYS) return 'jidlo';
    if ($world['wood']<3*villageWeather($world)['woodNeed']) return 'drevo';
    if (array_sum(array_column($world['people'],'mood'))/max(1,$people)<SETTLER_MOOD) return 'nalada';
    if ($day-(int)($s['lastHunger']??-999)<SETTLER_QUIET_DAYS) return 'hlad';
    return null;
}

// Nejvolnější dům pro skupinu: prázdný dům, jinak dům s dostatkem místa.
function settlerHouse(array $world,int $size): ?string {
    $free=freeHouses($world,$size);
    if ($free) return $free[0];
    return houseFor($world,$size,[],null,0);
}

// Nejvíc volných míst v jednom domě (rodina o čtyřech lidech se vejde, když je aspoň 4).
function maxHouseSpace(array $world): int {
    $best=0;
    foreach (houseList($world) as $id=>$house) $best=max($best,$house['capacity']-count(residentsOf($world,$id)));
    return $best;
}

// Vesnice roste (zásoby jsou, lidé přicházejí), ale žádný dům nepojme rodinu: čas postavit další dům.
function settlersNeedHouse(array $world): bool {
    return $world['newcomers']>=count(NEWCOMERS) && count($world['people'])<POP_CAP_MAX
        && foodReserve($world)>=dailyFoodNeed($world)*SETTLER_FOOD_DAYS && maxHouseSpace($world)<4;
}

function settlerWork(array $world,string $sex,float $noise): array {
    $counts=array_fill_keys(array_keys(SETTLER_WORKS),0);
    foreach ($world['people'] as $p) if (isset($counts[$p['work']??''])) ++$counts[$p['work']];
    asort($counts);
    $min=reset($counts);
    $best=array_keys(array_filter($counts,fn($c)=>$c===$min));
    $place=$best[(int)floor($noise*count($best))%count($best)];
    return [$place,SETTLER_WORKS[$place][$sex==='m'?0:1]];
}

// Složení skupiny podle šumu dne: [typ, dospělí [[pohlaví, věk]], děti [[pohlaví, věk]]].
function settlerPlan(string $type,int $day,int $n): array {
    $r=fn(string $ch)=>weatherNoise($day*13+$n,'settler:'.$ch);
    $age=fn(float $noise,int $from,int $to)=>$from+(int)floor($noise*($to-$from+1));
    $sexOf=fn(float $noise)=>$noise<.5?'m':'f';
    switch ($type) {
        case 'single':
            return [[[ $sexOf($r('sex')),$age($r('age'),20,35) ]],[]];
        case 'couple':
            return [[['m',$age($r('am'),22,36)],['f',$age($r('af'),20,33)]],[]];
        case 'family':
            $kids=[];
            $count=2+($r('kids')<.5?1:0);
            for ($i=0;$i<$count;$i++) $kids[]=[$sexOf($r('ks'.$i)),$age($r('ka'.$i),1,12)];
            return [[['m',$age($r('am'),28,40)],['f',$age($r('af'),26,36)]],$kids];
        default:   // widow
            return [[[$r('sex')<.7?'f':'m',$age($r('age'),28,45)]],[[$sexOf($r('ks')),$age($r('ka'),3,11)]]];
    }
}

function pickSettlerType(float $noise): string {
    $total=array_sum(SETTLER_TYPES);
    $x=$noise*$total;
    foreach (SETTLER_TYPES as $type=>$weight) { if ($x<$weight) return $type; $x-=$weight; }
    return 'single';
}

function settlerSurname(array $world,float $noise): array {
    $used=array_column($world['people'],'family');
    $count=count(SETTLER_SURNAMES);
    $start=(int)floor($noise*$count);
    for ($i=0;$i<$count;$i++) {
        $candidate=SETTLER_SURNAMES[($start+$i)%$count];
        if (!in_array($candidate[1],$used,true)) return $candidate;
    }
    return SETTLER_SURNAMES[$start%$count];
}

function settlerPerson(string $id,string $name,string $family,string $sex,int $age,string $home,string $place,string $role,int $absDay,float $noise,string $color,string $trait): array {
    $adult=$age>=18;
    $p=['id'=>$id,'name'=>$name,'family'=>$family,'home'=>$home,'age'=>$age,'role'=>$role,'work'=>$adult?$place:'yard','color'=>$color,
        'charisma'=>46+(int)floor($noise*20),'lastPrayer'=>-1440,'resistUntil'=>0,'illUntil'=>0,'awayUntil'=>0,'trait'=>$trait,
        'skill'=>$adult?40+(int)floor($noise*35):($age>=12?40:30),'fatigue'=>30,'mood'=>66,'trust'=>46,'faith'=>42,'fear'=>12,'hunger'=>26,'fun'=>62,
        'place'=>'home','activity'=>'Zabydluje se','task'=>null,'bonds'=>[],'grudges'=>[],'sex'=>$sex,
        'birthDay'=>$absDay-$age*360-(int)floor($noise*350),'arrivedDay'=>$absDay];
    $p+=lifeDefaults($p,$absDay);
    return $p;
}

// Přivede jednu skupinu. Vrací popis, nebo null, když pro ni není dům.
function settlersArrive(PDO $db,array &$world,int $day): ?string {
    $s=&$world['settlers'];
    $n=(int)$s['arrived']+1;
    $r=fn(string $ch)=>weatherNoise($day*13+$n,'settler:'.$ch);
    $type=pickSettlerType($r('type'));
    [$adults,$kids]=settlerPlan($type,$day,$n);
    $house=settlerHouse($world,count($adults)+count($kids));
    if ($house===null && $type!=='single') {
        $type='single';
        [$adults,$kids]=settlerPlan($type,$day,$n);
        $house=settlerHouse($world,1);
    }
    if ($house===null) { unset($s); return null; }
    [$surnameMale,$family]=settlerSurname($world,$r('surname'));
    $absDay=$day;
    $members=[];
    $idStart=(int)($s['nextId']??1);
    $next=function() use (&$idStart) { return 'host'.($idStart++); };
    $firstName=fn(string $sex,string $ch)=>($sex==='m'?SETTLER_BOY_NAMES:SETTLER_GIRL_NAMES)[(int)floor($r($ch)*24)%24];
    $fatherName=null;
    $adultIds=[];
    foreach ($adults as $i=>[$sex,$age]) {
        $id=$next();
        $name=$firstName($sex,'fn'.$i).' '.($sex==='m'?$surnameMale:surnameFor('f',$surnameMale));
        $tmp=$world; $tmp['people']=$world['people']+$members;
        [$place,$role]=settlerWork($tmp,$sex,$r('work'.$i));
        $members[$id]=settlerPerson($id,$name,$family,$sex,$age,$house,$place,$role,$absDay,$r('stat'.$i),SETTLER_COLORS[(int)floor($r('col'.$i)*10)%10],SETTLER_TRAITS[(int)floor($r('trait'.$i)*count(SETTLER_TRAITS))]);
        $adultIds[$sex]=$id;
        if ($sex==='m') $fatherName=$name;
    }
    if (isset($adultIds['m'],$adultIds['f'])) {
        $members[$adultIds['m']]['spouse']=$adultIds['f'];
        $members[$adultIds['f']]['spouse']=$adultIds['m'];
    }
    $parentM=$adultIds['f']??null;
    $parentF=$adultIds['m']??null;
    $lone=array_values($adultIds)[0]??null;
    foreach ($kids as $i=>[$sex,$age]) {
        $id=$next();
        $name=$firstName($sex,'kn'.$i).' '.surnameFor($sex,$surnameMale);
        $kid=settlerPerson($id,$name,$family,$sex,$age,$house,'yard',$age<12?'Dítě':'Pomocník',$absDay,$r('kstat'.$i),SETTLER_COLORS[(int)floor($r('kcol'.$i)*10)%10],'Dítě z přistěhovalé rodiny, zvědavé na nové sousedy.');
        $kid['mother']=$parentM??($members[$lone]['sex']==='f'?$lone:null);
        $kid['father']=$parentF??($members[$lone]['sex']==='m'?$lone:null);
        $members[$id]=$kid;
    }
    $s['nextId']=$idStart;
    foreach ($members as $id=>$p) {
        foreach ($world['people'] as $other=>&$neighbor) {
            $p['bonds'][$other]=48;
            $neighbor['bonds'][$id]=48;
        }
        unset($neighbor);
        foreach ($members as $other=>$m) if ($other!==$id) $p['bonds'][$other]=78;
        $members[$id]=$p;
    }
    foreach ($members as $id=>$p) $world['people'][$id]=$p;
    foreach ($members as $id=>$p) foreach ($members as $other=>$m) if ($other!==$id) $world['people'][$id]['bonds'][$other]=78;
    ++$s['arrived'];
    $s['nextDay']=$day+settlerInterval($world);
    $s['log']=array_slice(array_merge($s['log']??[],[['day'=>$day,'type'=>$type,'family'=>$family,'size'=>count($members)]]),-8);
    $names=implode(', ',array_map(fn($m)=>$m['name'],$members));
    $houses=houseList($world);
    $label=['single'=>'Do vsi přišel nový obyvatel','couple'=>'Do vsi přišel mladý pár','family'=>'Do vsi se přistěhovala rodina','widow'=>'Do vsi přišel rodič s dítětem'][$type];
    journal($db,$world,array_key_first($members),$label.': '.$names,'Vesnice měla volný dům a zásoby na přijetí hostů. Usadili se v domě: '.houseLabel($house,$houses).'.','Příběh');
    unset($s);
    return $type;
}

// Rodina, která přišla naposled, při dlouhém hladu odejde (bez úmrtí, jen odchod).
function settlersDeparture(PDO $db,array &$world,int $day): bool {
    $groups=[];
    foreach ($world['people'] as $id=>$p) if (isset($p['arrivedDay']) && $p['arrivedDay']>0) $groups[$p['family']][]=$id;
    uasort($groups,fn($a,$b)=>$world['people'][$b[0]]['arrivedDay']<=>$world['people'][$a[0]]['arrivedDay']);
    foreach ($groups as $family=>$members) {
        // Nikdo mimo skupinu nesmí být s ní spojený (manžel nebo dítě), jinak se nechá.
        $ok=true;
        foreach ($members as $id) {
            $p=$world['people'][$id];
            if (!empty($p['spouse']) && !in_array($p['spouse'],$members,true)) $ok=false;
            foreach ($world['people'] as $oid=>$o) {
                if (in_array($oid,$members,true)) continue;
                if (in_array($o['mother']??null,$members,true) || in_array($o['father']??null,$members,true)) $ok=false;
            }
        }
        if (!$ok) continue;
        $names=implode(', ',array_map(fn($id)=>$world['people'][$id]['name'],$members));
        foreach ($members as $id) {
            unset($world['people'][$id],$world['plan'][$id]);
            $db->prepare('DELETE FROM queue WHERE person=?')->execute([$id]);
        }
        foreach ($world['people'] as $oid=>&$o) {
            foreach ($members as $id) {
                unset($o['bonds'][$id],$o['grudges'][$id]);
                if (($o['crush']??null)===$id) unset($o['crush']);
                if (($o['betrothed']??null)===$id) { $o['betrothed']=null; unset($o['wedding']); }
            }
            $o['mood']=clamp($o['mood']-2);
        }
        unset($o);
        foreach ($world['religions'] as &$group) {
            $group['members']=array_values(array_diff($group['members'],$members));
            if (in_array($group['leader'],$members,true) && $group['members']) $group['leader']=$group['members'][0];
            $group['strength']=min(100,10*count($group['members']));
        }
        unset($group);
        $world['religions']=array_values(array_filter($world['religions'],fn($g)=>count($g['members'])>=2));
        $world['counters']['departures']=($world['counters']['departures']??0)+1;
        journal($db,$world,'','Rodina odešla z vsi',$names.' se po dlouhém hladu odstěhovali za lepším živobytím.','Příběh');
        return true;
    }
    return false;
}

// Denní chod: hlad, příchod nové skupiny, odliv při hladu, hodnost vesnice, sobotní trh městyse.
function settlersDaily(PDO $db,array &$world): void {
    $day=intdiv((int)$world['minute'],1440);
    $s=&$world['settlers'];
    // Pevný seznam čtyř prvních přistěhovalců končí; co nepřišlo, nahradí generovaní osadníci.
    if ($world['newcomers']<count(NEWCOMERS) && $day>=NEWCOMER_FIRST_DAY+NEWCOMER_GAP_DAYS*count(NEWCOMERS)) $world['newcomers']=count(NEWCOMERS);
    if (($world['counters']['hungryMealsToday']??0)>0) { $s['lastHunger']=$day; $s['hungerStreak']=(int)($s['hungerStreak']??0)+1; }
    else $s['hungerStreak']=0;
    $arrive=$world['newcomers']>=count(NEWCOMERS) && settlersBlocked($world,$day)===null;
    if ($arrive) settlersArrive($db,$world,$day);
    if (($s['hungerStreak']??0)>=SETTLER_LEAVE_STREAK && $day-(int)($s['lastLeave']??-999)>=SETTLER_LEAVE_GAP) {
        if (settlersDeparture($db,$world,$day)) { $s['lastLeave']=$day; $s['nextDay']=max((int)$s['nextDay'],$day+SETTLER_MAX_INTERVAL); }
    }
    unset($s);
    $rank=villageRank($world);
    if (($world['rank']??'osada')!==$rank) {
        $old=$world['rank']??'osada';
        $world['rank']=$rank;
        if (array_search($rank,array_keys(RANKS),true)>array_search($old,array_keys(RANKS),true)) {
            journal($db,$world,'','Vesnice povýšila: '.RANKS[$rank],'Ve vsi žije '.count($world['people']).' lidí a stojí '.count($world['buildings']).' budov. Obchodník bude jezdit častěji.','Hra');
        }
    }
    // Městys: sobotní trh zvedne všem náladu.
    if ($rank==='mestys' && $day%7===BATH_SATURDAY) {
        foreach ($world['people'] as &$p) $p['mood']=clamp($p['mood']+MARKET_DAY_MOOD);
        unset($p);
        $world['counters']['marketDays']=($world['counters']['marketDays']??0)+1;
    }
}
