<?php
declare(strict_types=1);

// Měření náročnosti simulace pro různý počet obyvatel.
// Použití: php tests/zatez.php <počty oddělené čárkou> [ai]   (ai = zavolá skutečný místní model)
require __DIR__.'/fixture.php';

$counts=array_map('intval',explode(',',$argv[1]??'10,15,50'));
$withAi=($argv[2]??'')==='ai';
if ($withAi) {
    putenv('MINILIDI_NO_AI=0'); putenv('MINILIDI_AI=ollama'); putenv('MINILIDI_MODEL=gemma4:e4b');
    putenv('MINILIDI_AI_PORT=11434'); putenv('MINILIDI_NUM_CTX=6144'); putenv('MINILIDI_BATCH_TIMEOUT_MS=60000');
}

foreach ($counts as $n) {
    [$db,$world]=freshWorld($n);
    $world['food']=400.0; $world['wood']=300.0; $world['stone']=30.0;
    $people=count($world['people']);
    $days=3;
    $t=hrtime(true);
    runDays($db,$world,$days);
    $simMs=(hrtime(true)-$t)/1e6;
    $stepMs=$simMs/($days*96);
    $t=hrtime(true);
    for ($i=0;$i<20;$i++) villageCandidates($world);
    $candMs=(hrtime(true)-$t)/1e6/20;
    $t=hrtime(true);
    for ($i=0;$i<20;$i++) socialOptions($world);
    $socMs=(hrtime(true)-$t)/1e6/20;
    $t=hrtime(true);
    $state=encode($world);
    for ($i=0;$i<20;$i++) { $db->prepare('UPDATE village SET state=?,heartbeat=? WHERE id=1')->execute([encode($world),time()]); }
    $saveMs=(hrtime(true)-$t)/1e6/21;
    $row=sprintf("N=%d: tick %.2f ms, den %.0f ms, kandidáti %.1f ms, setkání %.1f ms, uložení %.1f ms, stav %.0f kB, paměť %.0f MB",
        $people,$stepMs,$stepMs*96,$candMs,$socMs,$saveMs,strlen($state)/1024,memory_get_peak_usage(true)/1048576);
    echo $row."\n";
    if (!$withAi) continue;
    // Jedna plánovací dávka se skutečným modelem.
    $batch=prepareVillagePlan($db,$world,true);
    $m=$batch['metrics']??null;
    $valid=0;
    foreach ($batch['options'] as $id=>$scores) {
        $e=$batch['response']['plan'][$id]??null;
        if (is_array($e) && isset($scores[$e['task']??'']) && validPlanText($e['reason']??null,$world)) $valid++;
    }
    if ($m) echo sprintf("   model: %d volání, %.1f s celkem (načtení %.1f s), %d tokenů, platných %d z %d, detail: %s\n",
        $m['calls'],$m['elapsed']/1000,$m['load']/1000,$m['tokens'],$valid,count($batch['options']),$batch['detail']);
    else echo "   model: bez odpovědi (".$batch['detail'].")\n";
}
