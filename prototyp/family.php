<?php
declare(strict_types=1);

function addHruskaFamily(array &$world): void {
    $members = [
        ['karel','Karel Hruška',43,'Pomocník na návsi',
            'Společenský a výřečný, rád je mezi lidmi a rád se dvoří dospělým ženám.',
            72,29,79,72,'market','#88613e'],
        ['karolina','Karolína Hrušková',41,'Hospodyně',
            'Velmi krásná a spíš domácí typ. Má ráda klid, péči o rodinu a práci kolem domu.',
            76,32,72,59,'yard','#7d4e69'],
        ['tomas','Tomáš Hruška',9,'Dítě',
            'Zvědavý a hravý. Má rád hry na dvoře, rodinu a drobnou pomoc kolem domu.',
            36,19,82,80,'yard','#976f40'],
        ['marek','Marek Hruška',15,'Pomocník v hospodářství',
            'Má chuť učit se hospodařit. Rád pomáhá na poli a chce jednou zvládnout vlastní práci.',
            61,24,71,67,'field','#4f7161'],
        ['tereza','Tereza Hrušková',18,'Pomocnice na dvoře',
            'Samostatná a všímavá. Pomáhá s domácností a ráda se učí novým věcem.',
            65,25,77,68,'yard','#446b89'],
    ];
    $added = [];
    foreach ($members as [$id,$name,$age,$role,$trait,$skill,$fatigue,$mood,$fun,$work,$color]) {
        if (isset($world['people'][$id])) continue;
        $world['people'][$id] = compact('id','name','age','role','trait','skill','fatigue','mood','fun','work','color') + [
            'family'=>'Hruškovi','hunger'=>24,'trust'=>47,'faith'=>42,'fear'=>12,'task'=>null,
            'place'=>'home','activity'=>'Doma','bonds'=>[],'grudges'=>[],
        ];
        $added[$id] = true;
    }
    if (!$added) return;
    foreach ($world['people'] as $id=>&$person) {
        foreach ($world['people'] as $other=>$neighbor) {
            if ($id === $other || (!isset($added[$id]) && !isset($added[$other]))) continue;
            $person['bonds'][$other] ??= $person['family']===$neighbor['family'] ? 76 : 48;
        }
    }
    unset($person);
}

// Rodina Mlynářových přichází jako celek, jakmile stojí druhý nový dům (a v domě je místo i zásoby).
// Přináší mlynáře: vysokou zkušenost v řemeslech, v domácnosti, v sousedských vztazích a ve víře (babička).
const MILLER_FAMILY = 'Mlynářovi';

function millerFamilyMembers(): array {
    // id, jméno, věk, role, povaha, výkon, únava, nálada, zábava, místo práce, barva, zkušenosti, matka, otec, víra
    return [
        ['vavrinec','Vavřinec Mlynář',36,'Mlynář',
            'Statný a pracovitý, mlýn zná od dětství. Mluví málo, ale na slovo se na něj dá spolehnout.',
            74,24,70,60,'yard','#8a6a43',
            ['field'=>45.0,'forest'=>50.0,'craft'=>78.0,'build'=>55.0,'neighbors'=>45.0,'home'=>45.0,'faith'=>40.0,'crisis'=>45.0],null,null,44],
        ['dorota','Dorota Mlynářová',33,'Hospodyně',
            'Pečlivá a vlídná, drží dům i sousedské vztahy v pořádku. V mlýně umí poradit i s moukou.',
            68,26,72,64,'yard','#7f5a74',
            ['field'=>50.0,'forest'=>55.0,'craft'=>52.0,'build'=>25.0,'neighbors'=>62.0,'home'=>68.0,'faith'=>48.0,'crisis'=>35.0],null,null,46],
        ['matej','Matěj Mlynář',14,'Pomocník',
            'Mlynářův syn, pomáhá otci u mlýna a rád se učí. Zatím víc hravý než důkladný.',
            52,28,74,76,'yard','#6e7f5a',['craft'=>42.0],'dorota','vavrinec',44],
        ['anezka','Anežka Mlynářová',8,'Dítě',
            'Šikovná a zvědavá, ráda běhá po mostě a dívá se na vodní kolo.',
            34,20,84,86,'yard','#a4748a',[],'dorota','vavrinec',44],
        ['marta','Marta Mlynářová',61,'Babička',
            'Mlynářova matka. Zná staré obřady i písně, ráda sedí s dětmi a vypráví.',
            48,36,66,58,'yard','#74707a',
            ['field'=>40.0,'forest'=>45.0,'craft'=>35.0,'build'=>15.0,'neighbors'=>66.0,'home'=>74.0,'faith'=>72.0,'crisis'=>30.0],null,null,66],
    ];
}

// Rodina čeká na dům, dokud nestojí druhý nový dům; vesnice kvůli tomu začne stavět dům dřív než při nedostatku bydlení.
function millerWaiting(array $world): bool {
    return empty($world['arrivedFamilies'][MILLER_FAMILY]) && count($world['homesBuilt'])<2;
}

function welcomeMillerFamily(PDO $db,array &$world): void {
    if (!empty($world['arrivedFamilies'][MILLER_FAMILY]) || count($world['homesBuilt'])<2) return;
    $members=millerFamilyMembers();
    $free=freeHouses($world,count($members));
    if ((int)$world['minute']-(int)$world['homesBuilt'][1]<120 || !$free ||
        foodReserve($world)<dailyFoodNeed($world)*10 ||
        $world['wood']<3*villageWeather($world)['woodNeed']) return;
    $home=$free[0];
    $absDay=intdiv((int)$world['minute'],1440);
    $added=[];
    foreach ($members as [$id,$name,$age,$role,$trait,$skill,$fatigue,$mood,$fun,$work,$color,$skills,$mother,$father,$faith]) {
        if (isset($world['people'][$id])) continue;
        $p=compact('id','name','age','role','trait','skill','fatigue','mood','fun','work','color','skills')+[
            'family'=>MILLER_FAMILY,'home'=>$home,'hunger'=>24,'trust'=>48,'faith'=>$faith,'fear'=>10,'task'=>null,'place'=>'home',
            'activity'=>'Zabydluje se','bonds'=>[],'grudges'=>[],'charisma'=>54,'lastPrayer'=>-1440,
            'resistUntil'=>0,'illUntil'=>0,'awayUntil'=>0];
        $p+=lifeDefaults($p,$absDay);
        $p['mother']=$mother;
        $p['father']=$father;
        $added[$id]=$p;
    }
    if (!$added) { $world['arrivedFamilies'][MILLER_FAMILY]=(int)$world['minute']; return; }
    $added['vavrinec']['spouse']='dorota';
    $added['dorota']['spouse']='vavrinec';
    foreach ($added as $id=>$p) {
        foreach ($world['people'] as $other=>&$neighbor) {
            $p['bonds'][$other]=48;
            $neighbor['bonds'][$id]=48;
        }
        unset($neighbor);
        foreach ($added as $other=>$member) if ($other!==$id) $p['bonds'][$other]=76;
        $world['people'][$id]=$p;
    }
    foreach ($added as $id=>$p) {
        foreach ($added as $other=>$member) if ($other!==$id) $world['people'][$id]['bonds'][$other]=76;
    }
    $world['arrivedFamilies'][MILLER_FAMILY]=(int)$world['minute'];
    journal($db,$world,'vavrinec','Do vsi přichází rodina Mlynářových',
        'Vavřinec Mlynář s ženou Dorotou, dětmi Matějem a Anežkou a matkou Martou se usadili v novém domě. Mlýn má konečně svého mlynáře.','Příběh');
    foreach ($world['people'] as $id=>$person) {
        if (!isset($added[$id])) rememberEvent($db,$world,$id,'příběh','Do vsi přišla rodina Mlynářových.',3);
    }
}