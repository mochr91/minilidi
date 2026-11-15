<?php
declare(strict_types=1);

// Skutečný worker na izolované databázi: balíček činností se převezme za běhu bez restartu, vadný soubor
// se odmítne a oprava ho vrátí. Používá vlastní složku v systémovém temp, živá data se nedotýká.
// Použití: php tests/balicek-worker.php
require __DIR__.'/fixture.php';

$dir=sys_get_temp_dir().'/minilidi-worker-'.getmypid();
mkdir($dir);
$pack=$dir.'/cinnosti.json';
$stopFile=$dir.'/stop';
$dbFile=$dir.'/vesnice.sqlite';
$source=json_decode(file_get_contents(__DIR__.'/../balicky/cinnosti.json'),true,12,JSON_THROW_ON_ERROR);
file_put_contents($pack,encode($source));

$env=getenv()+[];
$env['MINILIDI_DB']=$dbFile; $env['MINILIDI_PACK_FILE']=$pack; $env['MINILIDI_PACK_INTERVAL']='2';
$env['MINILIDI_STOP_FILE']=$stopFile; $env['MINILIDI_NO_AI']='1';
$command=[PHP_BINARY,'-n','-d','extension_dir='.ini_get('extension_dir'),'-d','extension=pdo_sqlite','-d','extension=curl',
    '-d','error_log='.$dir.'/chyby.log','-d','log_errors=1',dirname(__DIR__).'/worker.php'];
$process=proc_open($command,[0=>['pipe','r'],1=>['file',$dir.'/out.log','w'],2=>['file',$dir.'/err.log','w']],$pipes,dirname(__DIR__),$env);
check(is_resource($process),'Worker se nespustil');

function savedStatus(string $dbFile): ?array {
    try {
        $db=new PDO('sqlite:'.$dbFile,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $db->exec('PRAGMA busy_timeout=3000');
        $state=$db->query('SELECT state FROM village WHERE id=1')->fetchColumn();
        $world=is_string($state) ? json_decode($state,true) : null;
        return is_array($world) ? ['status'=>$world['activityPackStatus']??null,'pack'=>$world['activityPack']??null] : null;
    } catch (Throwable $e) {
        return null;
    }
}
function waitFor(callable $condition,int $seconds): bool {
    for ($i=0;$i<$seconds*4;$i++) { if ($condition()) return true; usleep(250000); }
    return false;
}

try {
    check(waitFor(fn()=>(savedStatus($dbFile)['status']['ok']??false)===true,25),'Worker načte balíček při startu');
    check(count(savedStatus($dbFile)['pack'])===count($source['activities']+$source['extensions']),'Po startu je uložen celý katalog činností');

    $edited=$source; $edited['activities']['pack_orchard']['label']='Sklidit ovoce za běhu';
    file_put_contents($pack,encode($edited));
    check(waitFor(fn()=>(savedStatus($dbFile)['pack']['pack_orchard']['label']??'')==='Sklidit ovoce za běhu',15),'Změna se převezme bez restartu do 15 s');
    check(str_contains(savedStatus($dbFile)['status']['change']??'','~pack_orchard'),'Stav ukazuje změněnou činnost');

    file_put_contents($pack,'{ rozbitý');
    check(waitFor(fn()=>(savedStatus($dbFile)['status']['ok']??true)===false,15),'Vadný soubor se odmítne a stav ukáže chybu');
    check(($saved=savedStatus($dbFile))['pack']['pack_orchard']['label']==='Sklidit ovoce za běhu' && count($saved['pack'])===count($source['activities']+$source['extensions']),'Po vadném souboru zůstane poslední funkční katalog');

    file_put_contents($pack,"\xEF\xBB\xBF".encode($source));
    check(waitFor(fn()=>(savedStatus($dbFile)['status']['ok']??false)===true && (savedStatus($dbFile)['pack']['pack_orchard']['label']??'')==='Očesat sad',15),'Oprava souboru (i s BOM) se převezme');
    $status=proc_get_status($process);
    check($status['running'],'Worker za celou dobu nespadl: '.(string)@file_get_contents($dir.'/err.log'));
    echo "Worker: změna balíčku za běhu, vadný soubor odmítnut, oprava převzata bez restartu OK\n";
} finally {
    file_put_contents($stopFile,'stop');
    $deadline=microtime(true)+15;
    while (proc_get_status($process)['running'] && microtime(true)<$deadline) usleep(200000);
    if (proc_get_status($process)['running']) proc_terminate($process);
    proc_close($process);
    foreach (glob($dir.'/*') ?: [] as $file) @unlink($file);
    @rmdir($dir);
}
