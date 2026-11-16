<?php
declare(strict_types=1);

const SOCIAL_INTERVAL = 90;

function villageCandidates(array $world, array $queued=[]): array {
    $calendar=calendarAt($world['minute'],$world['calendarOriginDay']);
    $weather=villageWeather($world);
    $hour=$calendar['hour'];
    $night=$hour<$calendar['sunrise'] || $hour>=$calendar['sunset'];
    $foodShort=foodReserve($world)<2*dailyFoodNeed($world);
    $foodPlenty=foodReserve($world)>12*dailyFoodNeed($world);
    $woodShort=$world['wood']<3*$weather['woodNeed'];
    $needs=materialNeeds($world);
    $crisis=crisisAction($world);
    $toolNeed=max(1,(int)ceil(adultCount($world)/3));
    $catalog=activityCatalog($world);
    $candidates=[];
    foreach ($world['people'] as $id=>$p) {
        if ($p['age']<12 || in_array($id,$queued,true)) continue;
        if ($p['task'] && empty($p['task']['automatic'])) continue;
        $doctrine=doctrineFor($world,$id);
        $ritual=$doctrine!==null && $p['lastPrayer']<=$world['minute']-1440 && $hour>=6 && $hour<22;
        $holiday=$doctrine==='holiday' && ($calendar['day']%7===1) && $hour>=10 && $hour<16;
        $actions=match(true) {
            $p['fatigue']>=80 || $p['illUntil']>$world['minute']=>['rest','visit'],
            $night || $holiday=>['rest','visit'],
            $weather['heat'] && $hour>=11 && $hour<15=>['rest','visit','bring_water','deliver_meal','firewood'],
            default=>array_diff(array_keys($catalog),['manure']),
        };
        if ($night && !$holiday && $p['fatigue']<80 && $p['illUntil']<=$world['minute']) {
            $actions=array_merge($actions,array_keys(array_filter($world['activityPack']??[],fn($def)=>$def['effect']==='games')));
        }
        if ($hour<6 || $hour>=22) $actions=['rest'];
        if ($ritual) $actions[]='prayer';
        if ($crisis!==null) $actions[]=$crisis;
        foreach (array_unique($actions) as $key) {
            if ($key==='prayer' && !$ritual) continue;
            if (!workAvailable($world,$id,$key)) continue;
            $action=$catalog[$key];
            $resource=$action['resource']??'';
            $score=productivity($p)*.2+($action['place']===$p['work']?12:0);
            $score+=match($resource) {
                'food','grain'=>$foodShort?27:($foodPlenty?-4:7),
                'wood'=>$woodShort?32:(isset($needs['wood'])?22:($world['wood']>60?-6:6)),
                'stone'=>isset($needs['stone']) && $world['quarryStone']<$needs['stone']?23:1,
                default=>$key==='market'?(100-$p['fun'])*.3:3,
            };
            $score+=match($key) {
                'rest'=>$p['fatigue']*.40,'haul_stone'=>isset($needs['stone'])?31:3,'build_house'=>40,
                'livestock'=>($world['coopEggs']??0)>=3?14:0,
                'fix_fence'=>hasBuilding($world,'kurnik')&&$world['fenceUntil']<=$world['minute']?16:12,'deliver_meal'=>$foodShort?3:13,'gather_neighbors'=>(100-$p['fun'])*.26,
                'visit'=>(100-$p['fun'])*.26+(100-$p['mood'])*.15,'prayer'=>52,
                'withhold_tools'=>18,'fertilize'=>9,
                'construct'=>38,'saw'=>isset($needs['planks'])?28:($world['planks']<8?10:2),
                'mill'=>$world['grain']>=4?26:8,'bake'=>$world['flour']>=3?36:10,
                'craft'=>$world['tools']<$toolNeed?22:4,'mow'=>($world['hay']<25?24:8)+winterScore($world,'mow'),
                'firefight','flood_defense'=>120,default=>winterScore($world,$key)+forestScore($world,$key)+beerScore($world,$key)+forgeScore($world,$key)+wineScore($world,$key)+craftScore($world,$key)+beesScore($world,$key)+husbandryScore($world,$key)+bricksScore($world,$key)+bathScore($world,$key),
            };
            if ($key===($p['lastPlanAction']??null)) $score-=9;
            $score+=(skillFactor($p,actionCategory($world,$key))-.82)*15;
            $score+=$action['score']??0;
            $candidates[$id][$key]=$score;
        }
        if (isset($candidates[$id])) arsort($candidates[$id]);
    }
    return $candidates;
}

function forecastFor(array $world): array {
    $forecast=[];
    foreach ([0,240,480,720] as $offset) {
        $future=$world;
        $future['minute']+=$offset;
        $weather=villageWeather($future);
        $forecast[]=[$offset,$weather['condition'],$weather['temperature'],$weather['wind'],$weather['ground']];
    }
    return $forecast;
}

const TASK_STEMS = [
    'fieldwork'=>'pol[ei]|obil|úrod|sklid|setb', 'felling'=>'kác|strom', 'firewood'=>'štíp|palív', 'quarrying'=>'lom[eu]?\b|těž',
    'haul_stone'=>'trakař|odvez', 'fishing'=>'ryb|řek[auy]|síť', 'livestock'=>'zvíř|stád|chlév|krm[ie]|ovc|krav',
    'mill'=>'mlýn|mlít|semlít|mouk', 'bake'=>'chlé?b|pec[ei]|péct|upéct', 'saw'=>'\bpil[auy]|prkn|řez', 'craft'=>'nářad|dílnu|dílny',
    'mow'=>'seno|sena|sen[aě]m|kosit|kosí', 'construct'=>'stavb|stavět|staví', 'build_house'=>'\bdům|\bdomu|nový dům',
    'rest'=>'odpoč|oddech|spát|vyspat', 'visit'=>'návštěv|povíd|sousedy', 'prayer'=>'modl|kapl|obřad',
    'bring_water'=>'studn|přinést vodu|nést vodu', 'gather_neighbors'=>'posezen', 'market'=>'\btrh|na návsi',
    'fix_fence'=>'plot|ohrad', 'fertilize'=>'hnoj|hnůj', 'firefight'=>'hasi|požár', 'flood_defense'=>'povod|břeh|záplav',
    'sow_garden'=>'zahrad|záhon|sáz|sét|zelenin', 'tend_garden'=>'zahrad|okop|záhon|zalí|zalév', 'harvest_garden'=>'zahrad|sklid|záhon|zelenin|hrách|čočk',
    'haul_wood'=>'dřev|svézt|svoz|vůz|vozem', 'build_cart'=>'vůz|vozy|vozem|dílnu|dílny', 'load_kiln'=>'milíř|milíře|uhlí|hranic',
    'watch_kiln'=>'milíř|milíře|uhlí|hlíd', 'hunt_small'=>'lov|loví|zajíc|zajíci|králík|bažant|léčk', 'smoke_meat'=>'udír|uzen|zvěřin',
    'tend_hops'=>'chmel|tyč|chmelnic', 'harvest_hops'=>'chmel|sklid|česat|trhat', 'make_malt'=>'slad|ječm|sladovn', 'brew_beer'=>'piv[oaeu]|pivovar|uvař|vařit',
    'mine_ore'=>'\brud[auy]|\bdůl|\bdolu|štol', 'smelt_iron'=>'\btav[ií]|taven|železo|železa', 'forge_tools'=>'kovárn|\bkout|\bkuj[eiu]|železn',
    'tend_vines'=>'vinic|réva|révu|révy', 'harvest_grapes'=>'hrozn|vinic|česat', 'press_wine'=>'\blis[ue]?\b|lisov|víno|vína', 'make_barrel'=>'\bsud[yu]?\b|bečk',
    'tan_hides'=>'kůž[eiíou]|useň|koželuh|koželužn', 'make_boots'=>'\bbot[ayěu]|ševc|šít boty|obuv', 'spin_weave'=>'tkal|\btká|\btkát|\bpříst|\blen\b|lnu|látk',
    'make_cheese'=>'sýr|mlék|mlékárn', 'harvest_pond'=>'rybník|rybníka|kapr|výlov|lovit ryb', 'teach'=>'uč[ií]|škol|fara|faru|fary|dět[ií] čt',
    'tend_bees'=>'včel|úl[ey]?\b|úlů|včelín', 'harvest_honey'=>'\bmed\b|medu|medem|včel|vytoč|úl[ey]?\b', 'prepare_hives'=>'zazimov|úl[ey]?\b|úlů|včel', 'make_candles'=>'svíč|vosk',
    'dig_clay'=>'hlín|cihel|cihelna|cihelny|hliniště', 'fire_bricks'=>'cihl|tašk|pálit|pálí|výpal|cihelna|cihelny', 'reroof'=>'střech|došk|tašk',
    'bathe'=>'lázn|lázeň|koupel|koupat|koupá',
    'slaughter_pig'=>'zabija|vepř|prase|prasat|chlív|chlév|porážk', 'shear_sheep'=>'ovc|ovčín|stříh|střiž|vln', 'tend_sheep'=>'ovc|ovčín|jehn|pastv',
    'pickle'=>'zelí|nakl|sklep', 'gather_herbs'=>'bylin|léč|bylink', 'dry_herbs'=>'bylin|suš|usuš', 'nurse'=>'ošetř|nemoc|odvar|bylin|nemocn',
];
const BAD_WORDS = '/\b(?:rádám|rádá|doněla|odpocítn\p{L}*|peknout|mlýnit|nečum)\b/iu';

function planTextConsistent(string $text,?array $person,array $tasks,array $extraStems=[]): bool {
    if (preg_match('/[\p{Cyrillic}\p{Han}\p{Arabic}\p{Greek}\p{Hiragana}\p{Katakana}]/u',$text)) return false;
    if (preg_match('/(\p{L})\1{3,}/u',$text) || preg_match('/\p{L}{25,}/u',$text) || preg_match('/\p{L}\d|\d\p{L}/u',$text)) return false;
    if (preg_match(BAD_WORDS,$text)) return false;
    if (preg_match('/\b(?:upiju|upiji|piju|piji|vypiju|vypiji)(?:\s+(?!a\b)\p{L}+){0,2}\s+chl[eé]b\b/iu',$text)) return false;
    $sex=$person['sex']??null;
    $after='(?:abych|bych|jsem|bys)\s+(?:se\s+|si\s+|tak\s+|už\s+)?';
    if ($sex==='f' && (preg_match('/\b(?:rád|byl|chtěl|musel|šel|měl|potřeboval|mohl)\b/iu',$text) ||
        preg_match('/\b'.$after.'\p{L}{2,}l\b/iu',$text))) return false;
    if ($sex==='m' && (preg_match('/\b(?:ráda|byla|chtěla|musela|šla|měla|potřebovala|mohla)\b/iu',$text) ||
        preg_match('/\b'.$after.'\p{L}{2,}la\b/iu',$text))) return false;
    if ($tasks) {
        $chosen=array_filter(array_map(fn($k)=>TASK_STEMS[$k]??($extraStems[$k]??null),$tasks));
        $own=$chosen && preg_match('/(?:'.implode('|',$chosen).')/iu',$text);
        $others=array_diff_key(TASK_STEMS,array_flip($tasks));
        if (!$own && $others && preg_match('/(?:'.implode('|',$others).')/iu',$text)) return false;
    }
    return true;
}

function validPlanText(mixed $text,array $world,?array $person=null,array $tasks=[]): bool {
    if (!narrativeText($text,140)) return false;
    $season=calendarAt($world['minute'],$world['calendarOriginDay'])['season'];
    $contradictions=['Zima'=>'zimní počasí|je zima','Jaro'=>'jarní počasí|je jaro',
        'Léto'=>'letní počasí|je léto','Podzim'=>'podzimní počasí|je podzim'];
    unset($contradictions[$season]);
    return !preg_match('/\b(?:'.implode('|',$contradictions).')\b/iu',trim($text)) && planTextConsistent(trim($text),$person,$tasks,array_map('packStems',array_intersect_key($world['activityPack']??[],array_flip($tasks))));
}

const AI_PLAN_CHUNK = 8;

function shortPlanText(string $text, int $limit): string {
    preg_match('/^.{0,'.$limit.'}/us', $text, $match);
    return $match[0] ?? '';
}
const PLAN_INSTRUCTION = 'Plánuješ šest hodin v české vsi kolem roku 1550. Vrať JSON podle schématu. Pro každé ID vyber povolený úkol podle potřeb, zásob a schopností. Bez druhého úkolu vyplň prázdný řetězec. Při krizi zasahují schopní. Únava volá po odpočinku, nízká nálada po návštěvě. Důvod i myšlenka česky do 60 znaků, myšlenka v první osobě. Ostatní texty piš krátce a jen podle dodaných faktů. Vzpomínky shrň v dodaném pořadí.';

function bondBrief(array $p,array $people,int $limit=4): array {
    $bonds=array_filter($p['bonds'],fn($k)=>isset($people[$k]),ARRAY_FILTER_USE_KEY);
    arsort($bonds);
    $top=array_slice($bonds,0,max(1,$limit-1),true);
    $low=array_slice($bonds,-1,1,true);
    $out=[];
    foreach ($top+$low as $k=>$v) $out[$k]=(int)$v;
    return $out;
}

function developmentBrief(array $world,array $needs): array {
    $project=$world['project'];
    $goal=goalMain($world);
    return ['sklad'=>['jidlo'=>round($world['food'],1),'obili'=>round($world['grain'],1),'mouka'=>round($world['flour'],1),
            'prkna'=>round($world['planks'],1),'seno'=>round($world['hay'],1),'naradi'=>round($world['tools'],1),
            'drevo_u_lesa'=>round($world['forestWood'],1),'dreveny_uhli'=>round($world['charcoal'],1),'zverina'=>round($world['game'],1),
            'uzene_maso'=>round($world['meatSmoked'],1),'jecmen'=>round($world['barley'],1),'slad'=>round($world['malt'],1),'chmel'=>round($world['hops'],1),'pivo'=>round($world['beer']+$world['beerYoung'],1),'ruda'=>round($world['ore'],1),'zelezo'=>round($world['iron'],1),'zelezne_naradi'=>round($world['ironTools'],1),'sul'=>round($world['salt'],1),'latka'=>round($world['cloth'],1),'vino'=>round(wineStock($world),1),'vozy'=>(int)$world['carts'],'milir'=>$world['kiln']['state'],
            'zelenina'=>round($world['veg'],1),'kysele_zeli'=>round($world['kraut'],1),'lusteniny'=>round($world['legumes'],1),
            'byliny'=>round($world['herbs']+$world['herbsDry'],1),'zimni_priprava_procent'=>round(100*$world['winterPrep']),
            'pestra_strava_procent'=>round(100*$world['variety']),'vejce'=>round($world['eggs'],1),'slepice'=>round((float)($world['hens']??0)),'vejce_ve_kurniku'=>round((float)($world['coopEggs']??0),1)],
        'budovy'=>array_keys($world['buildings']),
        'projekt'=>$project?[$project['label'],round($project['progress'],1),$project['work'],$project['paid']]:null,
        'chybi_na_stavbu'=>array_map(fn($v)=>round($v,1),$needs),
        'cil'=>$goal?$goal['label'].' '.$goal['current'].'/'.$goal['target']:null,
        'krize'=>$world['crisis']['label']??null,
        'nedostatek_sena'=>$world['hayShort']];
}

function prepareVillagePlan(PDO $db,array $world,bool $withAi): array {
    $queued=$db->query("SELECT person FROM queue WHERE kind='inspiration'")->fetchAll(PDO::FETCH_COLUMN);
    $options=villageCandidates($world,$queued);
    $events=memoryDay($db,$world);
    $scene=$world['minute'] >= $world['nextSocialMinute'] ? socialOptions($world) : null;
    $candidate=religionCandidate($world);
    $sign=$world['sign'] && !$world['sign']['told'] ? $world['sign'] : null;
    $response=null; $detail='Rozhodují pravidla hry'; $metrics=null;
    if ($withAi && !modelPaused($db) && ($options || $events || $scene || $candidate || $sign)) {
        $calendar=calendarAt($world['minute'],$world['calendarOriginDay']);
        $memories=memoryContext($db,array_keys($world['people']));
        $needs=materialNeeds($world);
        $ids=array_keys($options);
        $planningIds=array_merge($ids,array_values(array_diff(array_keys($events),$ids)));
        $chunks=$planningIds ? array_chunk($planningIds,AI_PLAN_CHUNK) : [[]];
        $shared=[
            'datum'=>[$calendar['day'],$calendar['month'],$calendar['season'],$calendar['year'],$calendar['hour'],$calendar['minute']],
            'pocasi_za_minut_stav_stupne_vitr_puda'=>forecastFor($world),
            'zasoby_jidlo_drevo_kamen_u_lomu'=>[round($world['food'],1),round($world['wood'],1),round($world['stone'],1),round($world['quarryStone'],1)],
            'denni_potreba_jidla_dreva'=>[dailyFoodNeed($world),villageWeather($world)['woodNeed']],
            'rozvoj'=>developmentBrief($world,$needs),
            'poradi_udaju'=>['jméno','věk','role','povaha','hlad','únava','zábava','nálada','víra','důvěra','strach','práce','vztahy','zamilovanost','paměť','místo','nemoc'],
            'nabozenstvi'=>$world['religions'],'neopakuj'=>array_slice($world['recentLines'],-2),
        ];
        $merged=['plan'=>[],'memories'=>[]];
        $total=null; $detail=null;
        $remaining=static fn(int $deadline): int => (int)(($deadline-hrtime(true))/1e6);
        $deadline=hrtime(true)+80_000_000_000;
        $retried=false;
        foreach ($chunks as $index=>$chunk) {
            if ($remaining($deadline)<100) { $detail='Vypršel čas na plánování vesnice'; break; }
            $first=$index===0;
            $people=[]; $planFields=[]; $tasks=[]; $chunkEvents=[];
            foreach ($chunk as $id) {
                $p=$world['people'][$id];
                $memory=$memories[$id][0][1]??'';
                $people[$id]=[$p['name'],$p['age'],$p['role'],shortPlanText($p['trait'],70),(int)$p['hunger'],(int)$p['fatigue'],
                    (int)$p['fun'],(int)$p['mood'],(int)$p['faith'],(int)$p['trust'],(int)$p['fear'],
                    $p['task']['action']??null,bondBrief($p,$world['people'],2),$p['crush']??null,shortPlanText($memory,80),
                    $p['place'],!empty($p['illUntil']) && $p['illUntil']>$world['minute']];
                if (isset($events[$id])) $chunkEvents[$id]=array_slice($events[$id],0,2);
                if (!isset($options[$id])) continue;
                $allowed=array_keys($options[$id]);
                $planFields[$id]=schemaObject([
                    'task'=>['type'=>'string','enum'=>$allowed],'second'=>['type'=>'string','enum'=>['',...$allowed]],
                    'reason'=>['type'=>'string'],'thought'=>['type'=>'string'],
                ]);
                foreach ($allowed as $key) $tasks[$key]=activityDefinition($world,$key)['label'];
            }
            $memoryInput=array_map(fn($items)=>array_map(fn($event)=>[$event['type'],shortPlanText($event['text'],90)],$items),$chunkEvents);
            $fields=['plan'=>schemaObject($planFields)];
            if ($chunkEvents) $fields['memories']=schemaObject(array_map(fn($items)=>['type'=>'array','maxItems'=>count($items),'items'=>['type'=>'string']],$chunkEvents));
            if ($first && $scene) $fields['scene']=schemaObject(['type'=>['type'=>'string','enum'=>$scene['types']],'text'=>['type'=>'string']]);
            if ($first && $candidate) $fields['religion']=schemaObject([
                'doctrine'=>['type'=>'string','enum'=>array_keys(DOCTRINES)],'name'=>['type'=>'string'],'text'=>['type'=>'string'],
            ]);
            if ($first && $sign) $fields['sign']=['type'=>'string'];
            $context=$shared+[
                'lide'=>$people,'ukoly'=>$tasks,'doporuceni'=>array_map(fn($a)=>array_slice(array_keys($a),0,3),array_intersect_key($options,$people)),
                'katalog_doktrin'=>$first && $candidate?DOCTRINES:[],'navrh_hnuti'=>$first?$candidate:null,
                'znameni'=>$first?$sign:null,'setkani'=>$first?$scene:null,
                'udalosti_vcera'=>$memoryInput,
            ];
            if (!$first) unset($context['nabozenstvi']);
            [$part,$partDetail,$partMetrics]=requestModel($db,$context,schemaObject($fields),PLAN_INSTRUCTION,'plan',true,$remaining($deadline));
            $attempts=[$partMetrics];
            if (!$retried && $planFields && ($partMetrics['retryable']??false) && $remaining($deadline)>=2000) {
                $retried=true;
                $shortFields=array_map(fn($field)=>schemaObject(['task'=>$field['properties']->task]),$planFields);
                $shortContext=[
                    'datum'=>$shared['datum'],'zasoby_jidlo_drevo_kamen_u_lomu'=>$shared['zasoby_jidlo_drevo_kamen_u_lomu'],
                    'potreby_hlad_unava_nalada'=>array_map(fn($p)=>[$p[4],$p[5],$p[7]],array_intersect_key($people,$planFields)),
                    'ukoly'=>$tasks,'doporuceni'=>$context['doporuceni'],
                ];
                [$part,$retryDetail,$partMetrics]=requestModel($db,$shortContext,schemaObject(['plan'=>schemaObject($shortFields)]),
                    'Vrať pouze JSON podle schématu. Pro každé ID vyber jeden povolený úkol podle potřeb a zásob.',
                    'plan',true,min(15000,$remaining($deadline)));
                $attempts[]=$partMetrics;
                $partDetail=$part!==null ? $retryDetail.' (zkrácené zadání)' : $partDetail.'; opakování: '.$retryDetail;
            }
            foreach (array_filter($attempts) as $partMetrics) {
                $total??=['type'=>'plan','at'=>$partMetrics['at'],'elapsed'=>0.0,'total'=>0.0,'load'=>0.0,'tokens'=>0,'interrupted'=>false,'calls'=>0];
                $total['elapsed']+=$partMetrics['elapsed'];
                $total['total']+=$partMetrics['total']??0;
                $total['load']+=$partMetrics['load']??0;
                $total['tokens']+=$partMetrics['tokens']??0;
                $total['interrupted']=$total['interrupted'] || $partMetrics['interrupted'];
                ++$total['calls'];
            }
            if ($detail===null || ($part===null && str_starts_with($detail,'Odpověděl'))) $detail=$partDetail;
            if (is_array($part)) {
                foreach ($part['plan']??[] as $id=>$entry) $merged['plan'][$id]=$entry;
                foreach ($part['memories']??[] as $id=>$items) $merged['memories'][$id]=$items;
                if ($first) $merged+=array_diff_key($part,['plan'=>1,'memories'=>1]);
                $response=$merged;
            }
            if ($partMetrics['interrupted']??false) break;
        }
        $metrics=$total;
    }
    return compact('options','events','scene','candidate','sign','response','detail','metrics');
}

function guardTask(array $p,array $options): ?string {
    foreach (['firefight','flood_defense'] as $emergency) if (isset($options[$emergency]) && $p['fatigue']<=92) return $emergency;
    if ($p['fatigue']>80 && isset($options['rest'])) return 'rest';
    if (($p['fun']<25 || $p['mood']<25) && isset($options['visit'])) return 'visit';
    if (isset($options['prayer'])) return 'prayer';
    return null;
}

function applyVillagePlan(PDO $db,array &$world,array $batch,bool $withAi): void {
    ['options'=>$options,'events'=>$events,'scene'=>$scene,'response'=>$response,'detail'=>$detail,'metrics'=>$metrics]=$batch;
    $world['nextAutoMinute']=(int)$world['minute']+AUTO_INTERVAL;
    $world['plan']=[];
    $validCount=0; $fallbackCount=0;
    $audit=[]; $whyCounts=[];
    $chosen=[];
    $foodNeeded=foodReserve($world)<dailyFoodNeed($world);
    $foodSlots=0;
    $foodKinds=['food','grain'];
    chooseProject($db,$world);
    $needs=materialNeeds($world);
    $logistics=null;
    if (projectReady($world)) $logistics='construct';
    elseif (isset($needs['stone'])) $logistics=$world['quarryStone']>=1?'haul_stone':'quarrying';
    elseif (isset($needs['wood'])) $logistics=($world['forestWood']??0)>=3?'haul_wood':'felling';
    elseif (isset($needs['planks']) && hasBuilding($world,'pila')) $logistics='saw';
    elseif (houseWorkAvailable($world)) $logistics='build_house';
    $buildingNeed=$logistics!==null && !$foodNeeded;
    $logisticsSlots=$logistics==='construct'?BUILDERS_PLANNED:($logistics==='build_house'?2:1);
    foreach ($options as $id=>$scores) {
        $p=$world['people'][$id];
        $entry=$response['plan'][$id]??null;
        $first=$entry['task']??null;
        $second=$entry['second']??'';
        $valid=is_string($first) && isset($scores[$first]);
        $second=$valid && is_string($second) && isset($scores[$second]) ? $second : '';
        $ranked=$scores;
        foreach ($ranked as $key=>&$score) {
            $score-=($chosen[$key]??0)*match($key) {'rest','visit'=>2,'quarrying','haul_stone','build_house'=>30,default=>12};
        }
        unset($score);
        arsort($ranked);
        $rulesKey=array_key_first($ranked);
        $why=$entry===null||!is_string($first)?'missing':($valid?'':'invalid');
        $key=$valid?$first:$rulesKey;
        $guard=guardTask($p,$scores);
        if ($guard!==null && $key!==$guard) {
            $second=$key;
            $key=$guard;
            $valid=false;
            $why='guard';
        }
        if ($guard===null) {
            $resource = activityDefinition($world,$key)['resource']??null;
            if ($foodNeeded && $foodSlots<3 && !in_array($resource,$foodKinds,true)) {
                $food = array_filter($ranked,fn($task)=>in_array(activityDefinition($world,$task)['resource']??null,$foodKinds,true),ARRAY_FILTER_USE_KEY);
                if ($food) { $key=array_key_first($food); $valid=false; $why='food'; }
            } elseif ($buildingNeed && ($chosen[$logistics]??0)<$logisticsSlots && isset($scores[$logistics])) {
                if ($key!==$logistics) { $key=$logistics; $valid=false; $why='logistics'; }
            }
        }
        if ($why==='') $why=$key===$rulesKey?'agree':'model';
        if ($withAi) {
            $audit[]=[(int)$world['minute'],$id,is_string($first)?$first:null,$rulesKey,$key,$why,$valid?'AI':'Hra'];
            $whyCounts[$why]=($whyCounts[$why]??0)+1;
        }
        if (in_array(activityDefinition($world,$key)['resource']??null,$foodKinds,true)) ++$foodSlots;
        if (!$valid && ($guard===null || $second==='' || !is_string($second) || !isset($scores[$second]))) {
            $second=$key==='rest'||$key==='visit' ? '' : 'rest';
        }
        if ($key===$second) $second='';
        $chosen[$key]=($chosen[$key]??0)+1;
        $valid?$validCount++:$fallbackCount++;
        $planned=array_values(array_filter([$key,$second]));
        $reason=$valid && validPlanText($entry['reason']??null,$world,$p,$planned) ? trim($entry['reason']) : match($key) {
            'visit'=>'Pomůže společnost a oddech.', 'rest'=>'Je čas nabrat sílu.',
            'prayer'=>'Je čas na obřad hnutí.', default=>'Úkol odpovídá zásobám, práci a potřebám postavy.',
        };
        $thought=$valid && validPlanText($entry['thought']??null,$world,$p,$planned) &&
            !in_array(trim($entry['thought']),$world['recentLines'],true) ? trim($entry['thought']) : '';
        if ($thought!=='') $world['recentLines']=array_slice([...$world['recentLines'],$thought],-18);
        $world['plan'][$id]=['tasks'=>array_values(array_filter([$key,$second])),'second'=>$second,
            'source'=>$valid?'AI':'Hra','reason'=>$reason,'thought'=>$thought,'first'=>$key];
    }
    if ($scene!==null) {
        applyMeeting($db,$world,$scene,is_array($response['scene']??null)?$response['scene']:null);
        $world['nextSocialMinute']=(int)$world['minute']+SOCIAL_INTERVAL;
    }
    saveMemories($db,$world,$events,is_array($response['memories']??null)?$response['memories']:[]);
    if ($batch['sign'] && validPlanText($response['sign']??null,$world)) {
        faithEvent($db,$world,'Výklad znamení',trim($response['sign']),'výklad','AI');
    }
    if ($batch['sign']) $world['sign']['told']=true;
    $religion=$batch['candidate']===religionCandidate($world) && is_array($response['religion']??null) ? $response['religion'] : null;
    foundReligion($db,$world,$religion);
    $world['counters']['planValid']+=$validCount;
    $world['counters']['planFallback']+=$fallbackCount;
    if ($audit) {
        $insert=$db->prepare('INSERT INTO plan_audit (minute,person,model,rules,final,why,source) VALUES (?,?,?,?,?,?,?)');
        foreach ($audit as $row) $insert->execute($row);
        $db->exec('DELETE FROM plan_audit WHERE minute < '.((int)$world['minute']-14*MINUTES_PER_DAY));
        foreach ($whyCounts as $why=>$count) $world['counters']['planWhy'][$why]=($world['counters']['planWhy'][$why]??0)+$count;
    }
    if ($metrics && !$validCount && $response && $options) {
        $names=['guard'=>'pojistka','food'=>'jídlo','logistics'=>'stavba','invalid'=>'neplatný úkol','missing'=>'bez odpovědi','agree'=>'shoda','model'=>'model'];
        $detail='Návrh modelu nepřežil: '.implode(', ',array_map(fn($k,$v)=>($names[$k]??$k).' '.$v,array_keys($whyCounts),$whyCounts));
    }
    recordModel($db,$metrics,$validCount>0 || (!$options && $response!==null),$fallbackCount>0 || $response===null,$detail);
    $world['ai']=['status'=>$validCount?'Odpověděla AI':($withAi?'Rozhodla hra':'AI vypnutá'),
        'model'=>$withAi?modelName():'','detail'=>$detail];
    startPlannedWork($db,$world);
}

function startPlannedWork(PDO $db,array &$world): void {
    foreach ($world['plan'] as $id=>&$plan) {
        $p=$world['people'][$id];
        if ($p['task'] || !$plan['tasks']) continue;
        $requested=array_shift($plan['tasks']);
        $key=$requested;
        $nowAllowed=villageCandidates($world)[$id]??[];
        $guard=guardTask($p,$nowAllowed);
        if ($guard!==null) $key=$guard;
        if (!isset($nowAllowed[$key])) {
            $key=array_key_first($nowAllowed);
            if ($key===null) continue;
        }
        assignWork($world,$id,$key,true);
        $world['people'][$id]['lastPlanAction']=$key;
        $planned=$key===$requested;
        $fromPlan=$planned && $requested===$plan['first'];
        $source=$planned && ($fromPlan || $requested===($plan['second']??null))?$plan['source']:'Hra';
        $note='';
        if ($fromPlan) $note=' · '.$plan['reason'].($plan['thought']!==''?' „'.$plan['thought'].'“':'');
        elseif (!$planned) $note=' · '.match($guard) {
            'firefight','flood_defense'=>'krize', 'rest'=>'únava', 'visit'=>'nízká nálada nebo zábava', 'prayer'=>'obřad hnutí',
            default=>'původní úkol už není dostupný',
        };
        journal($db,$world,$id,$p['name'].' začíná úkol',activityDefinition($world,$key)['label'].$note,$source);
    }
    unset($plan);
    $world['plan']=array_filter($world['plan'],fn($plan)=>$plan['tasks']!==[]);
}
