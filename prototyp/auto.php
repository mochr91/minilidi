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
            default=>array_diff(array_keys(ACTIONS),['manure']),
        };
        if ($hour<6 || $hour>=22) $actions=['rest'];
        if ($ritual) $actions[]='prayer';
        if ($crisis!==null) $actions[]=$crisis;
        foreach (array_unique($actions) as $key) {
            if ($key==='prayer' && !$ritual) continue;
            if (!workAvailable($world,$id,$key)) continue;
            $action=ACTIONS[$key];
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
                'fix_fence'=>12,'deliver_meal'=>$foodShort?3:13,'gather_neighbors'=>(100-$p['fun'])*.26,
                'visit'=>(100-$p['fun'])*.26+(100-$p['mood'])*.15,'prayer'=>52,
                'withhold_tools'=>18,'fertilize'=>9,
                'construct'=>38,'saw'=>isset($needs['planks'])?28:($world['planks']<8?10:2),
                'mill'=>$world['grain']>=4?26:8,'bake'=>$world['flour']>=3?36:10,
                'craft'=>$world['tools']<$toolNeed?22:4,'mow'=>$world['hay']<25?24:8,
                'firefight','flood_defense'=>120,default=>0,
            };
            if ($key===($p['lastPlanAction']??null)) $score-=9;
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

function validPlanText(mixed $text,array $world): bool {
    if (!narrativeText($text,140)) return false;
    $season=calendarAt($world['minute'],$world['calendarOriginDay'])['season'];
    $contradictions=['Zima'=>'zimní počasí|je zima','Jaro'=>'jarní počasí|je jaro',
        'Léto'=>'letní počasí|je léto','Podzim'=>'podzimní počasí|je podzim'];
    unset($contradictions[$season]);
    return !preg_match('/\b(?:'.implode('|',$contradictions).')\b/iu',trim($text));
}

const AI_PLAN_CHUNK = 14;
const PLAN_INSTRUCTION = 'Plánuješ šest hodin v české vsi kolem roku 1550. Pro každé ID zvol úkol z jeho enumu, druhý může být prázdný. Rozděl práci podle zásob a schopností. Při požáru nebo povodni zasahují všichni schopní. Hlídej jídlo, dřevo a stavby: obilí jde do mlýna a mouka do pece, dřevo do pily a prkna do dílny. Nízká zábava nebo nálada vyžaduje návštěvu, únava odpočinek. Důvod i myšlenka jsou česky, nejvýše 140 znaků, stručně, správný rod, myšlenka v 1. osobě. Vycházej výhradně ze stavu a kalendáře. Neopakuj věty a nevymýšlej fakta ani nové vztahy. Při setkání použij povolený typ a nejvýš dvě věty, nepřisuzuj druhému opětované city. Vzpomínky shrň v pořadí dodaných událostí bez nových faktů. U znamení popiš nejistý výklad, u hnutí používej katalog. Pokud je v zadání pole projekt, vyber stavbu z jeho enumu.';

function bondBrief(array $p,array $people,int $limit=4): array {
    $bonds=array_filter($p['bonds'],fn($k)=>isset($people[$k]),ARRAY_FILTER_USE_KEY);
    arsort($bonds);
    $top=array_slice($bonds,0,max(1,$limit-1),true);
    $low=array_slice($bonds,-1,1,true);
    $out=[];
    foreach ($top+$low as $k=>$v) $out[$k]=(int)$v;
    return $out;
}

function developmentBrief(array $world,array $needs,array $projectOptions): array {
    $project=$world['project'];
    $goal=goalMain($world);
    return ['sklad'=>['jidlo'=>round($world['food'],1),'obili'=>round($world['grain'],1),'mouka'=>round($world['flour'],1),
            'prkna'=>round($world['planks'],1),'seno'=>round($world['hay'],1),'naradi'=>round($world['tools'],1)],
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
    $projectOptions=$world['project']===null ? projectOptions($world) : [];
    $response=null; $detail='Rozhodují pravidla hry'; $metrics=null;
    if ($withAi && !modelPaused($db) && ($options || $events || $scene || $candidate || $sign || $projectOptions)) {
        $calendar=calendarAt($world['minute'],$world['calendarOriginDay']);
        $memories=memoryContext($db,array_keys($world['people']));
        $needs=materialNeeds($world);
        $heads=$db->query('SELECT title FROM journal ORDER BY id DESC LIMIT 4')->fetchAll(PDO::FETCH_COLUMN);
        $ids=array_keys($options);
        $chunks=$ids ? array_chunk($ids,AI_PLAN_CHUNK) : [[]];
        foreach (array_diff(array_keys($events),$ids) as $id) $chunks[0][]=$id;
        $shared=[
            'datum'=>[$calendar['day'],$calendar['month'],$calendar['season'],$calendar['year'],$calendar['hour'],$calendar['minute']],
            'pocasi_za_minut_stav_stupne_vitr_puda'=>forecastFor($world),
            'zasoby_jidlo_drevo_kamen_u_lomu'=>[round($world['food'],1),round($world['wood'],1),round($world['stone'],1),round($world['quarryStone'],1)],
            'denni_potreba_jidla_dreva'=>[dailyFoodNeed($world),villageWeather($world)['woodNeed']],
            'rozvoj'=>developmentBrief($world,$needs,$projectOptions),
            'poradi_udaju'=>['jméno','věk','role','povaha','hlad','únava','zábava','nálada','víra','důvěra','strach','práce','vztahy','zamilovanost','paměť','místo','nemoc'],
            'nabozenstvi'=>$world['religions'],'neopakuj'=>array_slice($world['recentLines'],-4),'posledni_titulky'=>$heads,
        ];
        $merged=['plan'=>[],'memories'=>[]];
        $total=null; $detail=null;
        foreach ($chunks as $index=>$chunk) {
            $first=$index===0;
            $people=[]; $planFields=[]; $tasks=[]; $chunkEvents=[];
            foreach ($chunk as $id) {
                $p=$world['people'][$id];
                $people[$id]=[$p['name'],$p['age'],$p['role'],$p['trait'],(int)$p['hunger'],(int)$p['fatigue'],
                    (int)$p['fun'],(int)$p['mood'],(int)$p['faith'],(int)$p['trust'],(int)$p['fear'],
                    $p['task']['action']??null,bondBrief($p,$world['people']),$p['crush']??null,array_slice($memories[$id],0,2),
                    $p['place'],!empty($p['illUntil']) && $p['illUntil']>$world['minute']];
                if (isset($events[$id])) $chunkEvents[$id]=$events[$id];
                if (!isset($options[$id])) continue;
                $allowed=array_keys($options[$id]);
                $planFields[$id]=schemaObject([
                    'task'=>['type'=>'string','enum'=>$allowed],'second'=>['type'=>'string','enum'=>['',...$allowed]],
                    'reason'=>['type'=>'string'],'thought'=>['type'=>'string'],
                ]);
                foreach ($allowed as $key) $tasks[$key]=ACTIONS[$key]['label'];
            }
            $memoryInput=array_map(fn($items)=>array_map(fn($event)=>[$event['type'],$event['text']],$items),$chunkEvents);
            $fields=['plan'=>schemaObject($planFields)];
            if ($chunkEvents) $fields['memories']=schemaObject(array_map(fn()=>['type'=>'array','maxItems'=>5,'items'=>['type'=>'string']],$chunkEvents));
            if ($first && $scene) $fields['scene']=schemaObject(['type'=>['type'=>'string','enum'=>$scene['types']],'text'=>['type'=>'string']]);
            if ($first && $candidate) $fields['religion']=schemaObject([
                'doctrine'=>['type'=>'string','enum'=>array_keys(DOCTRINES)],'name'=>['type'=>'string'],'text'=>['type'=>'string'],
            ]);
            if ($first && $sign) $fields['sign']=['type'=>'string'];
            if ($first && $projectOptions) $fields['project']=['type'=>'string','enum'=>['',...$projectOptions]];
            $context=$shared+[
                'lide'=>$people,'ukoly'=>$tasks,'doporuceni'=>array_map(fn($a)=>array_slice(array_keys($a),0,3),array_intersect_key($options,$people)),
                'katalog_doktrin'=>$first && $candidate?DOCTRINES:[],'navrh_hnuti'=>$first?$candidate:null,
                'znameni'=>$first?$sign:null,'setkani'=>$first?$scene:null,'mozne_projekty'=>$first?$projectOptions:[],
                'udalosti_vcera'=>$memoryInput,
            ];
            if (!$first) unset($context['nabozenstvi']);
            if (strlen(encode($context))>9500) {
                foreach ($context['lide'] as &$person) $person[14]=array_slice($person[14],0,1);
                unset($person);
                $context['udalosti_vcera']=array_map(fn($items)=>array_slice($items,0,2),$memoryInput);
            }
            [$part,$partDetail,$partMetrics]=requestModel($db,$context,schemaObject($fields),PLAN_INSTRUCTION,'plan',true);
            if ($partMetrics) {
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
    return compact('options','events','scene','candidate','sign','response','detail','metrics','projectOptions');
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
    $chosen=[];
    $foodNeeded=foodReserve($world)<dailyFoodNeed($world);
    $foodSlots=0;
    $foodKinds=['food','grain'];
    chooseProject($db,$world,is_string($response['project']??null)?$response['project']:null);
    $needs=materialNeeds($world);
    $logistics=null;
    if (projectReady($world)) $logistics='construct';
    elseif (isset($needs['stone'])) $logistics=$world['quarryStone']>=1?'haul_stone':'quarrying';
    elseif (isset($needs['wood'])) $logistics='felling';
    elseif (isset($needs['planks']) && hasBuilding($world,'pila')) $logistics='saw';
    elseif (count($world['homesBuilt'])<HOME_LIMIT && canPay($world,homeCost(count($world['homesBuilt']))) &&
        count($world['people'])>=housingCapacity($world)-4) $logistics='build_house';
    $buildingNeed=$logistics!==null && !$foodNeeded;
    $logisticsSlots=$logistics==='construct'?2:1;
    foreach ($options as $id=>$scores) {
        $p=$world['people'][$id];
        $entry=$response['plan'][$id]??null;
        $first=$entry['task']??null;
        $second=$entry['second']??'';
        $valid=is_string($first) && isset($scores[$first]) && is_string($second) &&
            ($second==='' || isset($scores[$second])) && validPlanText($entry['reason']??null,$world) &&
            validPlanText($entry['thought']??null,$world) &&
            !in_array(trim($entry['thought']),$world['recentLines'],true);
        $ranked=$scores;
        foreach ($ranked as $key=>&$score) {
            $score-=($chosen[$key]??0)*match($key) {'rest','visit'=>2,'quarrying','haul_stone','build_house'=>30,default=>12};
        }
        unset($score);
        arsort($ranked);
        $key=$valid?$first:array_key_first($ranked);
        $guard=guardTask($p,$scores);
        if ($guard!==null && $key!==$guard) {
            $second=$key;
            $key=$guard;
            $valid=false;
        }
        if ($guard===null) {
            $resource = ACTIONS[$key]['resource']??null;
            if ($foodNeeded && $foodSlots<3 && !in_array($resource,$foodKinds,true)) {
                $food = array_filter($ranked,fn($task)=>in_array(ACTIONS[$task]['resource']??null,$foodKinds,true),ARRAY_FILTER_USE_KEY);
                if ($food) { $key=array_key_first($food); $valid=false; }
            } elseif ($buildingNeed && ($chosen[$logistics]??0)<$logisticsSlots && isset($scores[$logistics])) {
                if ($key!==$logistics) { $key=$logistics; $valid=false; }
            }
        }
        if (in_array(ACTIONS[$key]['resource']??null,$foodKinds,true)) ++$foodSlots;
        if (!$valid && ($guard===null || $second==='' || !is_string($second) || !isset($scores[$second]))) {
            $second=$key==='rest'||$key==='visit' ? '' : 'rest';
        }
        if ($key===$second) $second='';
        $chosen[$key]=($chosen[$key]??0)+1;
        $valid?$validCount++:$fallbackCount++;
        $reason=$valid?trim($entry['reason']):match($key) {
            'visit'=>'Pomůže společnost a oddech.', 'rest'=>'Je čas nabrat sílu.',
            'prayer'=>'Je čas na obřad hnutí.', default=>'Pravidla zohlednila zásoby, práci a potřeby postavy.',
        };
        $thought=$valid?trim($entry['thought']):'';
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
    if ($metrics && !$validCount && $response && $options) $detail='Model neposlal platné položky plánu';
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
        $fromPlan=$key===$plan['first'];
        $source=$key===$requested && ($fromPlan || $key===($plan['second']??null))?$plan['source']:'Hra';
        journal($db,$world,$id,$p['name'].' začíná úkol',ACTIONS[$key]['label'].
            ($fromPlan?' · '.$plan['reason'].($plan['thought']!==''?' „'.$plan['thought'].'“':''):''),$source);
    }
    unset($plan);
    $world['plan']=array_filter($world['plan'],fn($plan)=>$plan['tasks']!==[]);
}
