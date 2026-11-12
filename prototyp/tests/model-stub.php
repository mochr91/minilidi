<?php
declare(strict_types=1);

$body = json_decode(file_get_contents('php://input'),true);
$schema = $body['format'] ?? $body['response_format']['json_schema']['schema'] ?? null;
$mode = $body['model'] ?? 'valid';
if ($mode==='timeout') usleep(600000);

function stubValue(array $schema): mixed {
    if (isset($schema['enum'])) {
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
foreach ($result->plan??[] as $id=>$item) {
    $item->thought='Mám svůj úkol, '.$id.'.';
    $item->second='';
}
if ($mode==='mixed') {
    $id=array_key_last((array)$result->plan);
    $result->plan->$id->task='nepovoleny_ukol';
}
if ($mode==='invalid') $result=['plan'=>['anna'=>['task'=>'nepovoleny_ukol']]];
$content=json_encode($result,JSON_UNESCAPED_UNICODE);
header('Content-Type: application/json');
echo json_encode(isset($body['format'])?[
    'message'=>['content'=>$content],'total_duration'=>7500000,'load_duration'=>2500000,'eval_count'=>18,
]:['choices'=>[['message'=>['content'=>$content]]],'usage'=>['completion_tokens'=>18]]);
