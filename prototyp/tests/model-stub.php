<?php
declare(strict_types=1);

$body = json_decode(file_get_contents('php://input'),true);
$schema = $body['format'] ?? $body['response_format']['json_schema']['schema'] ?? null;
$mode = $body['model'] ?? 'valid';
$context = json_decode($body['messages'][1]['content'] ?? '',true);
if ($mode==='timeout') usleep(600000);

// Režimy "first" a "last" berou vždy první nebo poslední povolenou volbu (test vlivu plánu na činnosti, tests/shoda.php).
function stubValue(array $schema): mixed {
    global $mode;
    if (isset($schema['enum'])) {
        if ($mode==='first') return $schema['enum'][0];
        if ($mode==='last') { $options=array_values(array_filter($schema['enum'],fn($v)=>$v!=='')); return $options ? end($options) : $schema['enum'][0]; }
        foreach (['firewood','rest','talk','chapel'] as $pick) if (in_array($pick,$schema['enum'],true)) return $pick;
        return $schema['enum'][0];
    }
    return match ($schema['type']) {
        'object'=>(object)array_map('stubValue',$schema['properties']),
        'array'=>[],
        default=>'Teď se do toho pustím.',
    };
}
$result = stubValue($schema);
$truncated=false;
foreach ($result->plan??[] as $id=>$item) {
    if (!property_exists($item,'thought')) continue;
    if ($mode==='truncated' && isset($context['nabozenstvi'])) $truncated=true;
    $item->thought='Mám svůj úkol, '.$id.'.';
    $item->second='';
    if ($mode==='bad-text') {
        $item->thought=str_repeat('x',220);
        $item->reason=[];
        $item->second='nepovoleny_ukol';
    }
}
if ($mode==='mixed') {
    $id=array_key_last((array)$result->plan);
    $result->plan->$id->task='nepovoleny_ukol';
}
if ($mode==='invalid') $result=['plan'=>['anna'=>['task'=>'nepovoleny_ukol']]];
$content=$truncated?'{"plan":{':json_encode($result,JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json');
echo json_encode(isset($body['format'])?[
    'message'=>['content'=>$content],'done_reason'=>$truncated?'length':'stop','total_duration'=>7500000,'load_duration'=>2500000,'eval_count'=>18,
]:['choices'=>[['message'=>['content'=>$content]]],'usage'=>['completion_tokens'=>18]]);
