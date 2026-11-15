<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/world.php';
require_once dirname(__DIR__).'/ai.php';
require_once dirname(__DIR__).'/auto.php';

function check(bool $ok,string $test): void {
    if (!$ok) throw new RuntimeException($test);
}

function freshWorld(int $people=0,bool $migrate=true): array {
    $db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    init($db);
    $world=readWorld($db);
    $world['minute']=720.0;
    if ($people>count($world['people'])) addPeople($world,$people);
    if ($migrate) lifeMigrate($world,(intdiv((int)$world['minute'],1440)+90)*1440);
    return [$db,$world];
}

function addPeople(array &$world,int $count): void {
    $template=array_values(array_filter($world['people'],fn($p)=>$p['age']>=18));
    $absDay=intdiv((int)$world['minute'],1440);
    $i=0;
    while (count($world['people'])<$count) {
        ++$i;
        $src=$template[$i%count($template)];
        $id='x'.$i;
        $p=$src;
        $p['id']=$id;
        $p['name']='Osoba '.$i;
        $p['age']=12+($i*7)%48;
        $p['family']='Rod '.(intdiv($i,3));
        $p['bonds']=[];
        $p['task']=null;
        $p['pregnant']=null;
        $p['spouse']=null;
        unset($p['crush'],$p['home'],$p['betrothed'],$p['wedding']);
        $p['sex']=$i%2?'m':'f';
        $p['birthDay']=$absDay-$p['age']*360-crc32($id)%360;
        $p['lastBirth']=-999999;
        $world['people'][$id]=$p;
    }
    foreach ($world['people'] as $id=>&$p) {
        foreach ($world['people'] as $other=>$o) if ($other!==$id) $p['bonds'][$other]??=48;
    }
    unset($p);
}

function runDays(PDO $db,array &$world,float $days,bool $withAi=false): void {
    $steps=(int)round($days*96);
    for ($i=0;$i<$steps;$i++) {
        if ($world['minute'] >= $world['nextAutoMinute']) {
            applyVillagePlan($db,$world,prepareVillagePlan($db,$world,$withAi),$withAi);
        } else {
            if ($world['minute'] >= $world['nextSocialMinute']) {
                $scene=socialOptions($world);
                if ($scene) applyMeeting($db,$world,$scene,null);
                $world['nextSocialMinute']=(int)$world['minute']+SOCIAL_INTERVAL;
            }
            startPlannedWork($db,$world);
        }
        advance($db,$world,15);
    }
}
