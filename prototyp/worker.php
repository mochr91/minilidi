<?php
declare(strict_types=1);
require __DIR__ . '/world.php';
require __DIR__ . '/ai.php';
require __DIR__ . '/auto.php';

$lock = fopen(databasePath() . '.worker', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Simulace už běží.\n");
    exit(1);
}
$db = db();
init($db);
$world = readWorld($db);

$firstComparison = !isset($world['comparisonStart']);
$world['comparisonStart'] ??= (int)$world['minute'];
$comparison = db('control');
$baseline = $world;
$baseline['ai'] = ['status'=>'AI vypnutá','model'=>'','detail'=>'Pouze pravidla hry'];
init($comparison,$baseline);
$control = readWorld($comparison);
$world['directorVersion'] = $control['directorVersion'] = 2;
$lifeDue = (intdiv((int)max($world['minute'],$control['minute']),1440)+90)*1440;
lifeMigrate($world,$lifeDue);
lifeMigrate($control,$lifeDue);
foreach ([$db,$comparison] as $branch) {
    $branch->exec("INSERT INTO memories (person,minute,type,text,weight)
        SELECT person,minute,'příběh',title || '. ' || detail,10 FROM journal
        WHERE source='Příběh' AND person<>'' AND NOT EXISTS (
            SELECT 1 FROM memories m WHERE m.type='příběh' AND m.person=journal.person AND m.minute=journal.minute
        )");
}
$world['comparisonStart'] = $control['comparisonStart'] ?? $world['comparisonStart'];
$control['comparisonStart'] = $world['comparisonStart'];
$control['calendarOriginDay'] = $world['calendarOriginDay'];
if ($firstComparison) {
    $comparison->beginTransaction();
    $copy = $comparison->prepare('INSERT OR IGNORE INTO queue (id,kind,person,value) VALUES (?,?,?,?)');
    foreach ($db->query("SELECT id,kind,person,value FROM queue WHERE kind<>'mode'")->fetchAll(PDO::FETCH_NUM) as $job) {
        $copy->execute($job);
    }
    sampleVillage($comparison,$control);
    saveWorld($comparison,$control);
    $comparison->commit();
    $db->beginTransaction();
    sampleVillage($db,$world);
    saveWorld($db,$world);
    $db->commit();
}

function advanceBranch(PDO $db, array &$state, float $target): void {
    $db->beginTransaction();
    advance($db,$state,max(0,$target-$state['minute']));
    saveWorld($db,$state);
    $db->commit();
}

function runBranch(PDO $db, array &$state, bool $withAi): void {
    $job = $db->query('SELECT * FROM queue ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!$job) {
        $batch = $state['minute'] >= $state['nextAutoMinute'] ? prepareVillagePlan($db,$state,$withAi) : null;
        if (stopRequested()) return;
        if ($batch && (playerPending($db) || !empty($batch['metrics']['interrupted']))) {
            recordModel($db,$batch['metrics'],false,true,'Dávku předběhl hráč nebo pauza AI');
            return;
        }
        $socialDue = $state['minute'] >= $state['nextSocialMinute'];
        $ready=array_diff_key($state['plan'],array_filter($state['people'],fn($p)=>$p['task']!==null));
        if (!$batch && !$socialDue && !$ready) return;
        $db->beginTransaction();
        if ($batch) {
            applyVillagePlan($db,$state,$batch,$withAi);
        } else {
            if ($socialDue) {
                $social = socialOptions($state);
                if ($social) applyMeeting($db,$state,$social,null);
                $state['nextSocialMinute']=(int)$state['minute']+SOCIAL_INTERVAL;
            }
            startPlannedWork($db,$state);
        }
        saveWorld($db,$state);
        $db->commit();
        return;
    }
    $id = $job['person'];
    $kind = $job['kind'];
    $hasWork = !empty($state['people'][$id]['task']) && empty($state['people'][$id]['task']['automatic']);
    $canInspire = $kind==='inspiration' && isset($state['people'][$id],ACTIONS[$job['value']]) &&
        !$hasWork && workAvailable($state,$id,$job['value']);
    $decision = $canInspire ? resolveInspiration($db,$state,$job,$withAi) : null;
    if (stopRequested()) return;
    $db->beginTransaction();
    if ($decision !== null) {
        applyInspiration($db,$state,$job,$decision);
    } elseif ($kind === 'inspiration') {
        journal($db,$state,$id,'Vnuknutí už nejde provést',ACTIONS[$job['value']]['label']??'Úkol není dostupný.','Hra');
    } elseif ($kind === 'miracle') {
        applyMiracle($db,$state,$job['value']);
    } elseif ($kind === 'judgment' && isset($state['people'][$id])) {
        $person = &$state['people'][$id];
        $reward = $job['value']==='reward';
        $person['trust'] = clamp($person['trust']+($reward?7:-8));
        $person['fear'] = clamp($person['fear']+($reward?-2:10));
        $person['faith'] = clamp($person['faith']+2);
        $person['mood'] = clamp($person['mood']+($reward?9:-10));
        journal($db,$state,$id,$person['name'].($reward?' získává odměnu':' dostává pokárání'),
            $reward?'Laskavost posiluje důvěru v boha.':'Pokárání zvyšuje strach a oslabuje důvěru.','Hráč');
        unset($person);
    }
    $delete = $db->prepare('DELETE FROM queue WHERE id=?');
    $delete->execute([$job['id']]);
    saveWorld($db,$state);
    $db->commit();
}

$last = hrtime(true);
$checkpointHour = intdiv((int)$world['minute'],60);
$quit = false;
if (function_exists('sapi_windows_set_ctrl_handler')) {
    sapi_windows_set_ctrl_handler(function () use (&$quit): void { $quit = true; });
}

while (!$quit && !stopRequested()) {
    $now = hrtime(true);
    $elapsed = ($now - $last) / 1e9;
    $last = $now;
    $gameMinutes = $elapsed > 85 ? 0 : $elapsed * MINUTES_PER_DAY / DAY_SECONDS;
    $target = max($world['minute'],$control['minute']) + $gameMinutes;
    advanceBranch($db,$world,$target);
    advanceBranch($comparison,$control,$target);
    runBranch($db,$world,true);
    if (!stopRequested()) runBranch($comparison,$control,false);
    $hour = intdiv((int)$world['minute'],60);
    if ($hour > $checkpointHour) {
        foreach ([$db,$comparison] as $branch) {
            $branch->exec('PRAGMA busy_timeout=100');
            $branch->query('PRAGMA wal_checkpoint(TRUNCATE)')->fetchAll();
            $branch->exec('PRAGMA busy_timeout=3000');
        }
        $checkpointHour = $hour;
    }
    usleep(800000);
}
