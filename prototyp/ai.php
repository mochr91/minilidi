<?php
declare(strict_types=1);

require_once __DIR__ . '/lifecycle.php';

const AI_CONTEXT = 4096;
const AI_BATCH_TIMEOUT = 60000;
const AI_FAST_TIMEOUT = 12000;
const AI_KEEP_ALIVE = '5s';

function narrativeText(mixed $text,int $limit): bool {
    return is_string($text) && trim($text)!=='' && preg_match('/\A[^\r\n]{1,'.$limit.'}\z/u',trim($text))===1;
}

function schemaObject(array $properties): array {
    return ['type'=>'object','properties'=>(object)$properties,'required'=>array_keys($properties),'additionalProperties'=>false];
}

function modelPaused(PDO $db): bool {
    if (getenv('MINILIDI_NO_AI')==='1') return true;
    return $db->query("SELECT value FROM settings WHERE key='ai_paused'")->fetchColumn()==='1';
}

function playerPending(PDO $db): bool {
    return (bool)$db->query("SELECT 1 FROM queue WHERE kind IN ('inspiration','judgment','miracle') LIMIT 1")->fetchColumn();
}

function vramAllowsModel(): bool {
    $threshold=(int)getenv('MINILIDI_MIN_FREE_VRAM_MIB');
    if ($threshold<=0) return true;
    $output=[];
    $null=PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null';
    exec('nvidia-smi --query-gpu=memory.free --format=csv,noheader,nounits 2>'.$null,$output,$status);
    return $status===0 && isset($output[0]) && preg_match('/^\d+$/D',trim($output[0]))===1 && (int)$output[0]>=$threshold;
}

function requestModel(PDO $db,array $context,array $schema,string $instruction,string $type,bool $withAi,?int $timeBudgetMs=null): array {
    if (!$withAi || modelPaused($db)) return [null,'Rozhodují pravidla hry',null];
    $openai=aiBackend()==='openai';
    if (!$openai && !vramAllowsModel()) return [null,'Pauza AI kvůli volné VRAM',null];
    $port=filter_var(getenv('MINILIDI_AI_PORT') ?: ($openai?'1234':'11434'),FILTER_VALIDATE_INT,
        ['options'=>['min_range'=>1,'max_range'=>65535]]);
    if (!$port || !modelName()) return [null,'Chybí nastavení místního modelu',null];
    $batch=$type==='plan';
    $timeout=(int)(getenv($batch?'MINILIDI_BATCH_TIMEOUT_MS':'MINILIDI_FAST_TIMEOUT_MS') ?: ($batch?AI_BATCH_TIMEOUT:AI_FAST_TIMEOUT));
    $timeout=max(100,min($timeout,$batch?AI_BATCH_TIMEOUT:AI_FAST_TIMEOUT));
    if ($timeBudgetMs!==null) $timeout=max(100,min($timeout,$timeBudgetMs));
    $body=['model'=>modelName(),'stream'=>false,'messages'=>[
        ['role'=>'system','content'=>$instruction],['role'=>'user','content'=>encode($context)],
    ]];
    if ($openai) {
        $body+=['max_tokens'=>$batch?2400:160,'temperature'=>.28,'response_format'=>[
            'type'=>'json_schema','json_schema'=>['name'=>'minilidi_'.$type,'strict'=>true,'schema'=>$schema],
        ]];
    } else {
        $body+=['think'=>false,'format'=>$schema,'keep_alive'=>AI_KEEP_ALIVE,'options'=>[
            'num_ctx'=>max(AI_CONTEXT,min(8192,(int)(getenv('MINILIDI_NUM_CTX')?:AI_CONTEXT))),
            'num_predict'=>$batch?2400:160,'temperature'=>.28,
        ]];
    }
    $curl=curl_init('http://127.0.0.1:'.$port.($openai?'/v1/chat/completions':'/api/chat'));
    curl_setopt_array($curl,[
        CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>encode($body),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT_MS=>800,CURLOPT_TIMEOUT_MS=>$timeout,
    ]);
    $start=hrtime(true);
    $multi=curl_multi_init();
    curl_multi_add_handle($multi,$curl);
    $interrupted=false;
    do {
        $status=curl_multi_exec($multi,$running);
        if (!$running || $status!==CURLM_OK) break;
        if (stopRequested() || ($batch && (playerPending($db) || modelPaused($db)))) { $interrupted=true; break; }
        if (curl_multi_select($multi,.20)===-1) usleep(20000);
    } while (true);
    $transfer=curl_multi_info_read($multi);
    $http=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
    $decoded=json_decode((string)curl_multi_getcontent($curl),true);
    curl_multi_remove_handle($multi,$curl);
    curl_multi_close($multi);
    $message=$openai?($decoded['choices'][0]['message']['content']??null):($decoded['message']['content']??null);
    $decision=is_string($message)?json_decode($message,true):null;
    $detail=match(true) {
        $interrupted=>'Dávku předběhl hráč nebo pauza AI',
        ($transfer['result']??null)===CURLE_OPERATION_TIMEDOUT=>'Vypršel čas na odpověď',
        $http===0=>'Místní server AI není dostupný',
        $http!==200=>'Model neodpověděl (HTTP '.$http.')',
        !is_array($decision) && (($decoded['done_reason']??null)==='length' ||
            ($decoded['choices'][0]['finish_reason']??null)==='length')=>'Odpověď modelu skončila na limitu délky',
        !is_array($decision)=>'Model neposlal platná data',
        default=>'Odpověděl '.modelName(),
    };
    $metrics=[
        'type'=>$type,'at'=>time(),'elapsed'=>round((hrtime(true)-$start)/1e6,2),
        'total'=>isset($decoded['total_duration'])?round($decoded['total_duration']/1e6,2):null,
        'load'=>isset($decoded['load_duration'])?round($decoded['load_duration']/1e6,2):null,
        'tokens'=>$decoded['eval_count']??$decoded['usage']['completion_tokens']??null,'interrupted'=>$interrupted,
        'retryable'=>$http===200 && !$interrupted && !is_array($decision),
    ];
    return [$http===200 && !$interrupted && is_array($decision)?$decision:null,$detail,$metrics];
}

function recordModel(PDO $db,?array $metrics,bool $success,bool $fallback,string $detail): void {
    if (!$metrics) return;
    $q=$db->prepare('INSERT INTO ai_metrics (type,at,elapsed_ms,total_ms,load_ms,tokens,success,fallback,detail) VALUES (?,?,?,?,?,?,?,?,?)');
    $q->execute([$metrics['type'],$metrics['at'],$metrics['elapsed'],$metrics['total'],$metrics['load'],$metrics['tokens'],(int)$success,(int)$fallback,$detail]);
    $db->exec('DELETE FROM ai_metrics WHERE id <= (SELECT COALESCE(MAX(id),0)-1500 FROM ai_metrics)');
}

function contextFor(PDO $db,array $person,array $people=[]): array {
    return [
        'jmeno'=>$person['name'],'vek'=>$person['age'],'role'=>$person['role'],'povaha'=>$person['trait'],
        'energie'=>(int)(100-$person['fatigue']),'jidlo'=>(int)(100-$person['hunger']),
        'zabava'=>(int)$person['fun'],'nalada'=>(int)$person['mood'],
        'duvera'=>(int)$person['trust'],'vira'=>(int)$person['faith'],'strach'=>(int)$person['fear'],
        'vztahy'=>bondBrief($person,$people?:[$person['id']=>$person],6),
        'vzpominky'=>memoryContext($db,[$person['id']])[$person['id']],
        'zamilovanost'=>isset($person['crush'])?($people[$person['crush']]['name']??null):null,
    ];
}

function inspirationChoices(array $person,string $action,int $minute): array {
    if ($person['resistUntil']>$minute || ($person['fear']>=85 && $person['trust']<35)) return ['odmitnout'];
    if ($person['fatigue']>=82 && $action!=='rest') return ['odlozit','odmitnout'];
    return ['prijmout','odlozit','odmitnout'];
}

function resolveInspiration(PDO $db,array $world,array $job,bool $withAi=true): array {
    $person=$world['people'][$job['person']];
    $action=$job['value'];
    $definition=activityDefinition($world,$action);
    $score=willingness($person)+religiousInfluence($person)
        + match($action) {'manure'=>-24,'market'=>17,'rest'=>$person['fatigue']/2,default=>$definition['appeal']??0};
    if (isset($definition['resource']) && $person['work']===$definition['place']) $score+=8;
    $other=neighborFor($world,$person['id'],$action);
    if ($other && in_array($action,['help','bring_water','deliver_meal'],true)) $score+=(($person['bonds'][$other]??48)-48)*.3;
    $allowed=inspirationChoices($person,$action,(int)$world['minute']);
    if ($score<32) $allowed=array_values(array_intersect($allowed,['odmitnout']));
    elseif ($score<52) $allowed=array_values(array_intersect($allowed,['odlozit','odmitnout']));
    $fallback=match(true) {
        $allowed===['odmitnout']=>['choice'=>'odmitnout','reason'=>'Teď na to nemám sílu ani chuť.'],
        !in_array('prijmout',$allowed,true)=>['choice'=>'odlozit','reason'=>'Potřebuji si nejdřív odpočinout.'],
        default=>['choice'=>'prijmout','reason'=>'Dobrá, pustím se do toho.'],
    };
    $model=null; $detail='Rozhodla hra'; $metrics=null;
    if (count($allowed)>1) {
        [$model,$detail,$metrics]=requestModel($db,contextFor($db,$person,$world['people'])+[
            'ukol'=>$definition['label'],'kalendar'=>calendarAt($world['minute'],$world['calendarOriginDay']),
            'pocasi'=>villageWeather($world),'povolene_volby'=>$allowed,
        ],schemaObject(['choice'=>['type'=>'string','enum'=>$allowed],'reason'=>['type'=>'string']]),
        'Jsi obyvatel české vesnice kolem roku 1550. Reaguj česky v první osobě do 140 znaků, drž se stavu postavy a data.', 'inspiration',$withAi);
    }
    $valid=in_array($model['choice']??null,$allowed,true) && narrativeText($model['reason']??null,140);
    if ($model && !$valid) $detail='Model neposlal platnou reakci';
    recordModel($db,$metrics,$valid,!$valid,$detail);
    return [$valid?$model:$fallback,$valid?'AI':'Hra',$detail];
}

function applyInspiration(PDO $db, array &$world, array $job, array $result): void {
    [$decision,$source,$detail] = $result;
    $p = &$world['people'][$job['person']];
    $action = $job['value'];
    $label = activityDefinition($world,$action)['label'];
    $choice = $decision['choice'];
    $response = ['prijmout'=>'přijímá vnuknutí', 'odlozit'=>'odkládá vnuknutí', 'odmitnout'=>'odmítá vnuknutí'][$choice];
    $p['faith'] = clamp($p['faith'] + 2);
    $p['trust'] = clamp($p['trust'] + match ($action) {'manure'=>-5,'market'=>4,'rest'=>5,default=>1});
    $p['mood'] = clamp($p['mood'] + match ($action) {'manure'=>-7,'market'=>5,'rest'=>4,default=>1});
    if ($choice !== 'odmitnout') {
        unset($world['plan'][$p['id']]);
        assignWork($world,$p['id'],$action,false,$choice==='odlozit'?120:0);
    } elseif (in_array($action,['help','deliver_meal','bring_water'],true)) {
        $other = neighborFor($world,$p['id'],$action);
        if ($other !== null) rememberHarm($db,$world,$p['id'],$other,$p['name'].' odmítl/a nabídnout pomoc.');
    }
    rememberEvent($db,$world,$p['id'],'vnuknutí',$label.': '.$response,4);
    $world['ai'] = ['status'=>$source === 'AI' ? 'Odpověděla AI' : 'Rozhodla hra', 'model'=>modelName(), 'detail'=>$detail];
    journal($db, $world, $p['id'], $p['name'] . ' ' . $response, $label . '. „' . trim($decision['reason']) . '“', $source);
}

