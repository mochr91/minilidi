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
