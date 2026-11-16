<?php
declare(strict_types=1);
require dirname(__DIR__) . '/world.php';
require dirname(__DIR__) . '/ai.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function response(int $status, array $data): never {
    http_response_code($status);
    echo encode($data);
    exit;
}

function queueCommand(PDO $db, string $kind, string $person, string $value): ?string {
    $world = readWorld($db);
    if ($kind === 'miracle' && !miracleAvailable($world,$value)) return 'Zázrak čeká na obnovu přízně nebo vypršení prodlevy.';
    if ($kind !== 'miracle' && !isset($world['people'][$person])) return 'Tato postava ve vsi nežije.';
    if ($kind === 'inspiration') {
        if (empty($world['directorVersion']) && in_array($value,['visit','prayer'],true)) return 'Tento úkol čeká na restart simulace.';
        $task = $world['people'][$person]['task'];
        if ($task && empty($task['automatic'])) return 'Postava ještě má rozdělané vnuknutí.';
        if (!workAvailable($world,$person,$value)) return 'V této vsi teď nejsou splněné podmínky.';
    }
    $put = $db->prepare('INSERT INTO queue (kind,person,value) SELECT ?,?,?
        WHERE (SELECT COUNT(*) FROM queue) < 16 AND NOT EXISTS (SELECT 1 FROM queue WHERE kind=? AND person=?)');
    $put->execute([$kind,$person,$value,$kind,$person]);
    return $put->rowCount()===1 ? null : 'Předchozí příkaz ještě čeká na zpracování.';
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $branch = ($_GET['branch']??'')==='control'?'control':'ai';
    $db = db($method==='GET'?$branch:'ai');
    if ($method === 'GET') {
        $db->beginTransaction();
        $record = $db->query('SELECT * FROM village WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        $world = decodeWorld($record['state']);
        if (($_GET['view']??'')==='building') {
            // Okénko budovy po kliknutí na mapě: co to je, co dělá a jak na tom je.
            $info = buildingInfo($world,(string)($_GET['key']??''));
            if ($info===null) response(404,['error'=>'Taková budova ve vsi není.']);
            response(200,$info);
        }
        $catalog = activityCatalog($world);
        if (empty($world['directorVersion'])) $catalog=array_diff_key($catalog,array_flip(['visit','prayer']));
        foreach ($world['people'] as $id=>&$person) {
            $person['availableActions'] = array_keys(array_filter($catalog,fn($action,$key)=>workAvailable($world,$id,$key),ARRAY_FILTER_USE_BOTH));
            $person['food'] = 100 - $person['hunger'];
            $person['energy'] = 100 - $person['fatigue'];
            $person['productivity'] = productivity($person);
            $person['skills'] = skillsOf($person);
            $person['willingness'] = willingness($person);
        }
        unset($person);
        $world['branch'] = $branch;
        $world['calendar'] = calendarAt($world['minute'],$world['calendarOriginDay']);
        $world['weather'] = villageWeather($world);
        $world['running'] = time() - $record['heartbeat'] <= (isset($world['directorVersion'])?85:33);
        $world['journal'] = $db->query('SELECT * FROM journal ORDER BY id DESC LIMIT 60')->fetchAll(PDO::FETCH_ASSOC);
        $world['pending'] = $db->query('SELECT kind,person FROM queue ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $world['actions'] = catalogByCategory($catalog);
        $world['categories'] = CATEGORIES;
        $world['dailyNeed'] = ['food'=>dailyFoodNeed($world),'wood'=>$world['weather']['woodNeed'],'stone'=>0];
        $world['goalsState'] = goalState($world);
        $world['score'] = villageScore($world);
        $world['housingCapacity'] = housingCapacity($world);
        $world['housing'] = housingSummary($world);
        $world['roofMap'] = roofMap($world);
        $world['rankLabel'] = RANKS[villageRank($world)];
        $world['popCap'] = popCap($world);
        $world['settlerInterval'] = settlerInterval($world);
        $world['settlerBlocked'] = settlersBlocked($world,intdiv((int)$world['minute'],1440));
        $world['reserveDays'] = round(foodReserve($world)/max(1,dailyFoodNeed($world)),1);
        foreach ($world['people'] as &$person) {
            if (!empty($person['pregnant']) && empty($person['pregnant']['revealed'])) $person['pregnant'] = ['revealed'=>false];
        }
        unset($person);
        if (($_GET['view'] ?? '') === 'overview') {
            $samples = $db->prepare('SELECT minute,values_json FROM village_samples WHERE minute >= ? ORDER BY minute');
            $samples->execute([max($world['comparisonStart']??0,(int)$world['minute']-48*60)]);
            $world['history'] = array_map(fn($row)=>['minute'=>(int)$row['minute']] + json_decode($row['values_json'],true,16,JSON_THROW_ON_ERROR),
                $samples->fetchAll(PDO::FETCH_ASSOC));
        }
        if (($_GET['view']??'')==='development') {
            $world['buildingDefs'] = BUILDINGS;
            $world['goalDefs'] = GOALS;
            $world['resourceLabels'] = RESOURCES;
            $world['projectOptions'] = projectOptions($world);
            $world['planStats'] = $branch === 'ai' ? planStats($db,$world) : null;
            $world['weekly'] = [];
            try {
                $weeks = $db->query('SELECT data FROM weekly ORDER BY week DESC LIMIT 26')->fetchAll(PDO::FETCH_COLUMN);
                $world['weekly'] = array_map(fn($row)=>json_decode($row,true),$weeks);
            } catch (Throwable) {}
        }
        if (($_GET['view']??'')==='faith') {
            $world['miracles'] = MIRACLES;
            $world['doctrines'] = DOCTRINES;
            $world['aiPaused'] = true;
            $world['aiForcedOff'] = getenv('MINILIDI_NO_AI')==='1';
            $world['modelMetrics'] = [];
            if ($branch==='ai' && !empty($world['directorVersion'])) {
                $world['aiPaused'] = modelPaused($db);
                $world['modelMetrics'] = $db->query('SELECT * FROM ai_metrics ORDER BY id DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        $db->commit();
        response(200, $world);
    }
    if ($method !== 'POST') response(405, ['error'=>'Tato operace není dostupná.']);
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && !preg_match('~^http://(?:127\.0\.0\.1|localhost):[0-9]+$~D', $origin)) response(403, ['error'=>'Příkaz musí přijít z místní hry.']);
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
        response(415, ['error'=>'Očekávám příkaz ve formátu JSON.']);
    }
    $input = json_decode(file_get_contents('php://input', false, null, 0, 4096), true);
    $kind = $input['kind'] ?? null;
    $value = $input['value'] ?? null;
    $person = $input['person'] ?? '';
    if (!is_string($kind) || !is_string($value) || !is_string($person)) response(422, ['error'=>'Neplatný příkaz.']);
    $valid = match ($kind) {
        'inspiration'=>$person !== '' && (isset(ACTIONS[$value]) ||
            (str_starts_with($value,'pack_') && isset(readWorld($db)['activityPack'][$value]))),
        'judgment'=>$person !== '' && in_array($value,['reward','reprimand'],true),
        'miracle'=>$person==='' && isset(MIRACLES[$value]),
        'ai_pause'=>$person==='' && in_array($value,['0','1'],true),
        default=>false,
    };
    if (!$valid) response(422,['error'=>'Neplatný příkaz.']);
    if (in_array($kind,['ai_pause','miracle'],true) && empty(readWorld($db)['directorVersion'])) {
        response(409,['error'=>'Tato změna čeká na restart simulace.']);
    }
    if ($kind==='ai_pause') {
        if (getenv('MINILIDI_NO_AI')==='1') response(409,['error'=>'AI je vypnutá nastavením spouštěče.']);
        $save=$db->prepare("INSERT INTO settings (key,value) VALUES ('ai_paused',?) ON CONFLICT(key) DO UPDATE SET value=excluded.value");
        $save->execute([$value]);
        response(200,['paused'=>$value==='1']);
    }
    $control=db('control');
    $result = ['ai'=>queueCommand($db,$kind,$person,$value),'control'=>queueCommand($control,$kind,$person,$value)];
    if ($result['ai']!==null && $result['control']!==null) response(409,['error'=>'S AI: '.$result['ai'].' Bez AI: '.$result['control']]);
    response(202,['queued'=>true,'branches'=>$result]);
} catch (Throwable $error) {
    error_log($error->getMessage());
    response(503, ['error'=>'Vesnice se ještě spouští nebo je simulace zastavená.']);
}
