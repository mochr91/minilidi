<?php
declare(strict_types=1);

// Štítky zdroje a kontrola češtiny v záznamech plánu (úkol A3).
// Použití: php tests/zaznamy.php <port falešného modelu>   (model-stub.php musí běžet)
require __DIR__.'/fixture.php';

$stub=(int)($argv[1]??0);
check($stub>0,'Předej port testovacího modelu.');

// 1. Pojistka, která opakuje první úkol, nesmí dostat text od AI a štítek Hra zároveň.
[$db,$world]=freshWorld();
$world['minute']=720.0;
$world['people']['marek']['fun']=10.0;           // pojistka vynutí návštěvu
$world['people']['marek']['task']=null;
$world['plan']=['marek'=>['tasks'=>['visit','rest'],'second'=>'rest','source'=>'AI','reason'=>'Potřebuje společnost.','thought'=>'Půjdu mezi lidi.','first'=>'visit']];
startPlannedWork($db,$world);
$first=$db->query("SELECT source,detail FROM journal WHERE person='marek' AND title LIKE '%začíná úkol' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check($first['source']==='AI' && str_contains($first['detail'],'Půjdu mezi lidi'),'První úkol z plánu: štítek AI s textem od AI');
$world['people']['marek']['task']=null;           // první úkol skončil, pojistka zase vybere návštěvu
$world['people']['marek']['fun']=10.0;
startPlannedWork($db,$world);
$again=$db->query("SELECT source,detail FROM journal WHERE person='marek' AND title LIKE '%začíná úkol' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check($again['source']==='Hra','Úkol vynucený pojistkou má štítek Hra: '.$again['source']);
check(!str_contains($again['detail'],'Půjdu mezi lidi') && str_contains($again['detail'],'nízká nálada nebo zábava'),'Text od AI se u vynuceného úkolu neukazuje a je uveden důvod: '.$again['detail']);
echo "Štítek zdroje při opakování úkolu pojistkou OK\n";

// 2. Kontrola češtiny a souladu s osobou a úkolem.
$f=$world['people']['anna']; $m=$world['people']['jan'];
check($f['sex']==='f' && $m['sex']==='m','Předpoklad pohlaví postav');
$cases=[
    [true,'Dnes si odpočinu, abych se uklidnila.',$f,['rest']],
    [true,'Musím nakácet dřevo na zimu.',$m,['felling']],
    [true,'Dnes je potřeba pomoc ve vsi.',$f,['visit']],
    [false,'Mám rád klid doma.',$f,['rest']],                              // mužský tvar u ženy
    [false,'Dnes si odpočinu, abych se uklidnil.',$f,['rest']],            // mužské příčestí u ženy
    [false,'Rád bych se setkala s lidmi na návsi.',$f,['visit']],          // smíšený rod
    [false,'Mám ráda klid u vody.',$m,['rest']],                           // ženský tvar u muže
    [false,'Dnes se budu kácet stromy, abych doplnila dřevo.',$m,['felling']],
    [false,'Rádám si klid u vody, dnes si odpočinu.',$f,['rest']],         // překlep z živého běhu
    [false,'Půjdu odpočívat a spát.',$m,['felling']],                      // text o jiném úkolu
    [false,'Просто работа.',$f,['rest']],                                  // cizí písmo
    [false,'Jooooo to bude dobré.',$f,['rest']],                           // opakované písmeno
    [false,'Upiju krásný chléb pro všechny.',$f,['bake']],                 // doložené vadné výstupy modelu (patch češtiny)
    [false,'Dnes peknout chleba.',$f,['bake']],
    [false,'Chci se naučit mlýnit.',$f,['mill']],
    [false,'Rádá si popovídá.',$f,['visit']],
    [true,'Upeču chléb pro sousedy.',$f,['bake']],                         // platné věty, které se nesmějí zamítnout
    [true,'V mlýně semelu obilí.',$f,['mill']],
    [true,'Piju vodu a potom upeču chléb.',$f,['bake']],
];
foreach ($cases as $i=>[$expected,$text,$person,$tasks]) {
    check(validPlanText($text,$world,$person,$tasks)===$expected,'Případ '.($i+1).' ('.($expected?'platný':'zamítnutý').'): '.$text);
}
check(validPlanText('Dnes jdu na pole.',$world)===true,'Bez osoby a úkolu zůstává původní kontrola');
check(validPlanText('Je zimní počasí.',$world,$f,[])===false || calendarAt($world['minute'],$world['calendarOriginDay'])['season']==='Zima','Rozpor s ročním obdobím se dál zamítá');
echo "Kontrola textů (".count($cases)." případů) OK\n";

// 3. Vadný text od modelu: úkol od AI projde, text nahradí hra, štítek zůstane pravdivý.
putenv('MINILIDI_NO_AI=0'); putenv('MINILIDI_AI=ollama'); putenv('MINILIDI_AI_PORT='.$stub);
putenv('MINILIDI_BATCH_TIMEOUT_MS=3000'); putenv('MINILIDI_MODEL=bad-text');
[$db,$world]=freshWorld();
$batch=prepareVillagePlan($db,$world,true);
applyVillagePlan($db,$world,$batch,true);
$rows=$db->query("SELECT source,detail FROM journal WHERE title LIKE '%začíná úkol' AND source='AI'")->fetchAll(PDO::FETCH_ASSOC);
check(count($rows)>0,'Úkoly vybrané modelem mají štítek AI i při vadném textu');
foreach ($rows as $r) check(!str_contains($r['detail'],'xxxxxxxx'),'Vadný text se nezobrazí: '.$r['detail']);
$audit=$db->query('SELECT why,COUNT(*) FROM plan_audit GROUP BY why')->fetchAll(PDO::FETCH_KEY_PAIR);
check(array_sum($audit)===count($batch['options']),'Audit obsahuje každou osobu právě jednou');
echo "Vadný text od modelu: úkol od AI, text hry OK\n";
echo "Vše prošlo\n";
