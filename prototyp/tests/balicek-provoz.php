<?php
declare(strict_types=1);

// Provozní vlastnosti načítání balíčku činností: BOM, stav pro uživatele, jedno hlášení chyby, změny,
// kontrola textů k činnostem z balíčku, ověřovací skript. Použití: php tests/balicek-provoz.php
require __DIR__.'/fixture.php';

[$db,$world]=freshWorld();
$control=$world;
$dir=sys_get_temp_dir().'/minilidi-provoz-'.getmypid();
mkdir($dir);
$path=$dir.'/cinnosti.json';
$log=$dir.'/chyby.log';
ini_set('log_errors','1'); ini_set('error_log',$log);
$source=json_decode(file_get_contents(__DIR__.'/../balicky/cinnosti.json'),true,12,JSON_THROW_ON_ERROR);
try {
    // 1. První načtení a stav.
    file_put_contents($path,encode($source));
    refreshActivityPack($world,$control,$path);
    $status=$world['activityPackStatus'];
    check($status['ok'] && $status['count']===count($source['activities']+$source['extensions']) && $status['error']===null,'Stav po načtení: '.json_encode($status));
    check(str_contains($status['change'],'+pack_orchard') && $status['loadedAt']>0 && $status['file']==='cinnosti.json','Stav obsahuje změny, čas a soubor');
    check(!isset($control['activityPackStatus']),'Stav je jen ve světě s AI (větve zůstávají stejné)');
    $loadedAt=$status['loadedAt'];

    // 2. Beze změny souboru zůstává záznam o poslední změně.
    refreshActivityPack($world,$control,$path);
    check($world['activityPackStatus']['change']===$status['change'] && $world['activityPackStatus']['loadedAt']===$loadedAt,'Beze změny se záznam o změně nemaže');

    // 3. Soubor s BOM z Windows PowerShellu se načte.
    file_put_contents($path,"\xEF\xBB\xBF".encode($source));
    refreshActivityPack($world,$control,$path);
    check($world['activityPackStatus']['ok'],'Soubor s BOM se načte: '.($world['activityPackStatus']['error']??''));

    // 4. Změna a odebrání činnosti jsou vidět ve stavu.
    $edited=$source;
    $edited['activities']['pack_orchard']['label']='Sklidit ovoce';
    unset($edited['activities']['pack_mushrooms']);
    $edited['activities']['pack_nova']=$source['activities']['pack_orchard'];
    $edited['activities']['pack_nova']['label']='Opravit studánku';
    file_put_contents($path,encode($edited));
    refreshActivityPack($world,$control,$path);
    $change=$world['activityPackStatus']['change'];
    check(str_contains($change,'~pack_orchard') && str_contains($change,'−pack_mushrooms') && str_contains($change,'+pack_nova'),'Přehled změn: '.$change);

    // 5. Vadný soubor: poslední katalog zůstane, chyba je vidět a do logu se zapíše jednou.
    file_put_contents($log,'');
    file_put_contents($path,'{ "version": 1, ');
    for ($i=0;$i<3;$i++) refreshActivityPack($world,$control,$path);
    $status=$world['activityPackStatus'];
    check(!$status['ok'] && str_contains($status['error'],'JSON') && $status['count']===count($source['activities']+$source['extensions']),'Vadný JSON: '.json_encode($status,JSON_UNESCAPED_UNICODE));
    check(isset($world['activityPack']['pack_nova']) && $control['activityPack']===$world['activityPack'],'Po chybě zůstává poslední funkční katalog v obou větvích');
    check(substr_count((string)file_get_contents($log),'Neplatný JSON')===1,'Stejná chyba se zapíše do logu jen jednou');
    $bad=$edited;
    $bad['activities']['pack_nova']['minutes']=999;
    file_put_contents($path,encode($bad));
    refreshActivityPack($world,$control,$path);
    check(str_contains($world['activityPackStatus']['error'],'pack_nova') && str_contains($world['activityPackStatus']['error'],'minutes'),'Chyba jmenuje činnost a pole: '.$world['activityPackStatus']['error']);
    check(substr_count((string)file_get_contents($log),'balíček činností')===2,'Jiná chyba se zapíše zvlášť');

    // 6. Oprava souboru vrátí stav do pořádku.
    file_put_contents($path,encode($edited));
    refreshActivityPack($world,$control,$path);
    check($world['activityPackStatus']['ok'] && $world['activityPackStatus']['error']===null,'Po opravě je stav v pořádku');
    check(str_contains((string)file_get_contents($log),'znovu načten'),'Obnovení se zapíše do logu');
    unlink($path);
    refreshActivityPack($world,$control,$path);
    check(!$world['activityPackStatus']['ok'] && isset($world['activityPack']['pack_nova']),'Chybějící soubor ponechá poslední katalog');

    // 7. Interval a cesta z prostředí.
    putenv('MINILIDI_PACK_INTERVAL=1'); check(activityPackInterval()===2,'Interval má dolní mez 2 s');
    putenv('MINILIDI_PACK_INTERVAL=99999'); check(activityPackInterval()===3600,'Interval má horní mez 3600 s');
    putenv('MINILIDI_PACK_INTERVAL=45'); check(activityPackInterval()===45,'Interval z prostředí');
    putenv('MINILIDI_PACK_INTERVAL'); check(activityPackInterval()===300,'Výchozí interval 300 s');
    putenv('MINILIDI_PACK_FILE='.$path); check(activityPackPath()===$path,'Cesta z prostředí');
    putenv('MINILIDI_PACK_FILE');

    // 8. Texty od modelu k činnostem z balíčku nejsou zamítány kvůli cizím kořenům slov.
    file_put_contents($path,encode($source));
    refreshActivityPack($world,$control,$path);
    $person=$world['people']['anna'];
    check(packStems($world['activityPack']['pack_sick_visit'])!==null,'Kořeny slov z názvu činnosti');
    check(validPlanText('Zajdu navštívit nemocného souseda.',$world,$person,['pack_sick_visit']),'Text k návštěvě nemocného projde');
    check(validPlanText('Pomůžu sousedovi se sklizní.',$world,$person,['pack_harvest']),'Text ke sklizni projde');
    check(!validPlanText('Půjdu odpočívat a spát.',$world,$person,['pack_harvest']),'Text o jiném úkolu se dál zamítá');
    check(!validPlanText('Dnes jsem navštívila nemocného.',$world,['sex'=>'m']+$person,['pack_sick_visit']),'Rod se u činností z balíčku dál kontroluje');

    // 9. Ověřovací skript.
    $php=escapeshellarg(PHP_BINARY).' -n -d extension_dir='.escapeshellarg((string)ini_get('extension_dir'));
    exec($php.' '.escapeshellarg(__DIR__.'/../balicky/over.php').' '.escapeshellarg(__DIR__.'/../balicky/cinnosti.json'),$out,$code);
    check($code===0 && str_contains($out[0],('OK: '.count($source['activities']+$source['extensions']))),'over.php potvrdí platný balíček: '.implode(' | ',$out));
    file_put_contents($path,'{');
    $out=[]; exec($php.' '.escapeshellarg(__DIR__.'/../balicky/over.php').' '.escapeshellarg($path).' 2>&1',$out,$code);
    check($code===1 && str_contains(implode(' ',$out),'CHYBA'),'over.php ohlásí vadný soubor: '.implode(' | ',$out));
    echo "Balíček činností: BOM, stav, jedno hlášení chyby, přehled změn, texty a over.php OK\n";
} finally {
    ini_set('error_log','');
    foreach (glob($dir.'/*') ?: [] as $file) @unlink($file);
    @rmdir($dir);
}
