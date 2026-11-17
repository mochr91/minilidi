<?php
declare(strict_types=1);

const SOCIAL_TYPES = [
    'talk'=>'Rozhovor', 'comfort'=>'Útěcha', 'quarrel'=>'Hádka', 'gossip'=>'Drb',
    'share'=>'Sdílení jídla', 'court'=>'Namlouvání', 'reconcile'=>'Smíření', 'pray'=>'Modlitba',
];

function rememberEvent(PDO $db,array $world,string $id,string $type,string $text,int $weight): void {
    $write=$db->prepare('INSERT INTO memory_events (person,minute,type,text,weight) VALUES (?,?,?,?,?)');
    $write->execute([$id,(int)$world['minute'],$type,$text,$weight]);
}

function memoryContext(PDO $db,array $ids): array {
    $query=$db->prepare('SELECT type,text,weight,minute FROM memories WHERE person=? ORDER BY minute DESC,weight DESC LIMIT 5');
    $result=[];
    foreach ($ids as $id) {
        $query->execute([$id]);
        $result[$id]=$query->fetchAll(PDO::FETCH_NUM);
    }
    return $result;
}

function memoryDay(PDO $db,array $world): array {
    $day=intdiv((int)$world['minute'],1440)-1;
    if ($day<0 || $day<=$world['memoryDay']) return [];
    $query=$db->prepare('SELECT person,type,text,weight FROM memory_events WHERE minute>=? AND minute<? ORDER BY weight DESC,minute DESC');
    $query->execute([$day*1440,($day+1)*1440]);
    $events=[];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $event) {
        $id=$event['person'];
        if (count($events[$id]??[])>=5) continue;
        unset($event['person']);
        $events[$id][]=$event;
    }
    return $events;
}

function saveMemories(PDO $db,array &$world,array $events,mixed $suggestions): void {
    $day=intdiv((int)$world['minute'],1440)-1;
    if ($day<0 || $day<=$world['memoryDay']) return;
    $write=$db->prepare('INSERT INTO memories (person,minute,type,text,weight) VALUES (?,?,?,?,?)');
    foreach ($events as $id=>$items) {
        $known=[];
        foreach ($items as $i=>$event) {
            $text=$suggestions[$id][$i]??null;
            $text=narrativeText($text,140) ? trim($text) : $event['text'];
            if (isset($known[$text])) continue;
            $known[$text]=true;
            $write->execute([$id,$day*1440+1439,$event['type'],$text,$event['weight']]);
        }
    }
    $world['memoryDay']=$day;
    $db->exec('DELETE FROM memory_events WHERE minute < '.($day*1440));
    $db->exec("DELETE FROM memories WHERE type <> 'příběh' AND minute < ".(($day-30)*1440));
}

function commonPlace(array $p): string {
    return $p['place']==='home' ? 'home:'.$p['family'] : $p['place'];
}

function socialOptions(array $world): ?array {
    $hour=calendarAt($world['minute'],$world['calendarOriginDay'])['hour'];
    if ($hour<6 || $hour>=22) return null;
    $present=[];
    foreach ($world['people'] as $id=>$p) {
        $place=commonPlace($p);
        if (!in_array($place,['market','chapel','barn','tavern'],true) && !str_starts_with($place,'home:')) continue;
        if ($place==='tavern' && (!tavernOpen($world,$hour) || $p['age']<18)) continue;
        if ($p['activity']==='Spí') continue;
        $present[$place][]=$id;
    }
    $cutoff=(int)$world['minute']-5*MINUTES_PER_DAY;
    $history=array_values(array_filter($world['pairHistory'],fn($entry)=>$entry[1]>=$cutoff));
    $pairCounts=array_count_values(array_column($history,0));
    $meetings=count($history);
    $pairs=[];
    foreach ($present as $place=>$ids) {
        sort($ids);
        foreach ($ids as $i=>$id) foreach (array_slice($ids,$i+1) as $other) {
            $key=$id.':'.$other;
            $used=$pairCounts[$key]??0;
            if ($meetings>=5 && ($used+1)/($meetings+1)>.40) continue;
            $a=$world['people'][$id];
            $b=$world['people'][$other];
            $story=($a['crush']??null)===$other || ($b['crush']??null)===$id;
            $score=$used*60+($a['fun']+$b['fun'])/4-($story?24:0)-($place==='tavern'?16:0);
            $pairs[]=compact('id','other','key','place','score');
        }
    }
    if (!$pairs) return null;
    usort($pairs,fn($a,$b)=>$a['score']<=>$b['score']);
    $pair=$pairs[0];
    ['id'=>$id,'other'=>$other,'place'=>$place]=$pair;
    $a=$world['people'][$id]; $b=$world['people'][$other];
    $bond=min($a['bonds'][$other]??48,$b['bonds'][$id]??48);
    $types=['talk','comfort','gossip'];
    if ($bond<42*mayorQuarrelFactor($world) && doctrineFor($world,$id)!=='peace' && doctrineFor($world,$other)!=='peace') $types[]='quarrel';
    if ($bond<48) $types[]='reconcile';
    if ($world['food']>=count($world['people'])+1) $types[]='share';
    if ($a['age']>=18 && $b['age']>=18 && $a['family']!==$b['family'] &&
        $bond>=55 && (($a['crush']??null)===$other || ($b['crush']??null)===$id)) $types[]='court';
    if ($place==='chapel' || ($place==='barn' && (doctrineFor($world,$id)==='tree' || doctrineFor($world,$other)==='tree'))) $types[]='pray';
    return $pair+['types'=>$types,'group'=>$present[$place]];
}

function applyMeeting(PDO $db,array &$world,array $scene,?array $model): void {
    ['id'=>$id,'other'=>$other,'key'=>$key,'place'=>$place,'types'=>$types]=$scene;
    $a=&$world['people'][$id];
    $b=&$world['people'][$other];
    if (commonPlace($a)!==$place || commonPlace($b)!==$place) return;
    $valid=in_array($model['type']??null,$types,true) && narrativeText($model['text']??null,220) &&
        !in_array(trim($model['text']),$world['recentLines'],true);
    $type=$valid?$model['type']:match(true) {
        in_array('pray',$types,true) && max($a['faith'],$b['faith'])>=60=>'pray',
        in_array('reconcile',$types,true)=>'reconcile',
        min($a['mood'],$b['mood'])<35=>'comfort',
        in_array('share',$types,true) && max($a['hunger'],$b['hunger'])>=45=>'share',
        default=>'talk',
    };
    [$bond,$mood,$fun]=match($type) {
        'quarrel'=>[-6,-4,0], 'comfort'=>[4,7,4], 'reconcile'=>[7,5,5], 'share'=>[5,5,6],
        'court'=>[4,6,8], 'gossip'=>[-1,2,7], 'pray'=>[2,5,3], default=>[3,3,7],
    };
    // Zkušenost ve Víře (obřad) a v Sousedech (rozhovory, smíření, sdílení) zesiluje kladné účinky o několik procent.
    $soft=in_array($type,['quarrel','gossip'],true) ? 1.0 : max(softSkill($a,'neighbors'),softSkill($b,'neighbors'));
    if ($type==='pray') $soft=max(array_map(fn($m)=>softSkill($world['people'][$m],'faith'),$scene['group']));
    $bond=$bond>0 ? $bond*$soft : $bond;
    $mood=$mood>0 ? $mood*$soft : $mood;
    $fun=$fun>0 ? $fun*$soft : $fun;
    if ($type==='share') consumeStock($world,'food',1);
    if ($type==='reconcile') { unset($a['grudges'][$other],$b['grudges'][$id]); }
    $members=$type==='pray' ? $scene['group'] : [$id,$other];
    foreach ($members as $person) {
        $p=&$world['people'][$person];
        $p['mood']=clamp($p['mood']+$mood);
        $p['fun']=clamp($p['fun']+$fun);
        if ($type==='share') $p['hunger']=clamp($p['hunger']-14);
        if ($type==='pray') {
            $p['faith']=clamp($p['faith']+2*$soft);
            $p['fear']=clamp($p['fear']-1*$soft);
            if ($p['trust']>=50) $p['trust']=clamp($p['trust']+1);
            $p['lastPrayer']=(int)$world['minute'];
        }
        unset($p);
    }
    // Rozhovory a obřady zároveň cvičí příslušnou zkušenost (půl hodiny).
    foreach ($members as $person) trainSkill($db,$world,$person,$type==='pray'?'faith':'neighbors',30);
    $a['bonds'][$other]=clamp(($a['bonds'][$other]??48)+$bond);
    $b['bonds'][$id]=clamp(($b['bonds'][$id]??48)+$bond);
    $title=SOCIAL_TYPES[$type].' · '.$a['name'].' a '.$b['name'];
    $text=$valid ? trim($model['text']) : match($place) {
        'market'=>'Setkání na návsi.', 'chapel'=>'Setkání v kapli.', 'barn'=>'Setkání pod střechou chléva.',
        'tavern'=>'Setkání v hospodě u společného stolu.', default=>'Setkání doma.',
    };
    $source=$valid?'AI':'Hra';
    if ($valid) $world['recentLines']=array_slice([...$world['recentLines'],$text],-18);
    $cutoff=(int)$world['minute']-5*MINUTES_PER_DAY;
    $world['pairHistory']=array_values(array_filter($world['pairHistory'],fn($entry)=>$entry[1]>=$cutoff));
    $world['pairHistory'][]=[$key,(int)$world['minute']];
    foreach ($members as $person) rememberEvent($db,$world,$person,$type,$title.'. '.$text,$type==='talk'?2:4);
    if ($type==='pray') faithEvent($db,$world,$title,$text,'obřad',$source);
    else journal($db,$world,$id,$title,$text,$source);
}
