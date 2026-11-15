<?php
declare(strict_types=1);

require __DIR__.'/fixture.php';

[$db,$world]=freshWorld();
$control=$world;
refreshActivityPack($world,$control);
check($world['activityPack']===$control['activityPack'],'Obě větve mají nové činnosti');
$id='anna';
$person=&$world['people'][$id];
$person['fatigue']=45;
$person['fun']=40;
$person['mood']=50;
$person['skill']=60;
$person['hunger']=30;
$home=$world['food'];
foreach (['pack_leisure','pack_study'] as $key) {
    check(workAvailable($world,$id,$key),$key.' je dostupný');
    check(isset(villageCandidates($world)[$id][$key]),$key.' je v nabídce plánovače');
}
assignWork($world,$id,'pack_leisure',true);
completeWork($db,$world,$id);
check($person['fatigue']===39.0 && $person['fun']===45.0,'Lenošení obnoví energii a zábavu');
assignWork($world,$id,'pack_study',true);
completeWork($db,$world,$id);
check($person['skill']===60.5 && $world['food']===$home,'Učení zvýší dovednost a nevytváří zásoby');
$person['skill']=85;
check(!workAvailable($world,$id,'pack_study'),'Na stropu dovednosti se učení nenabízí');

$world['minute']=3*60;
check(!workAvailable($world,$id,'pack_walk') && !workAvailable($world,$id,'pack_leisure'),'V noci se volnočasové úkoly nenabízejí');
$dry=$wet=false;
for ($d=31;$d<90;$d++) {
    $world['minute']=$d*1440+600;
    $weather=villageWeather($world);
    if ($weather['precip']!=='none') {
        check(!workAvailable($world,$id,'pack_walk'),'Za srážek se procházka nenabízí');
        $wet=true;
    } elseif (!$weather['heat'] && $weather['wind']<30 && !$dry) {
        check(workAvailable($world,$id,'pack_walk'),'Za sucha a slabého větru je procházka dostupná');
        check(isset(villageCandidates($world)[$id]['pack_walk']),'Procházka je v nabídce plánovače');
        assignWork($world,$id,'pack_walk',true);
        completeWork($db,$world,$id);
        check($person['fun']===54.0 && $person['mood']===55.0,'Procházka zvedne zábavu a náladu');
        $dry=true;
    }
    if ($dry && $wet) break;
}
check($dry && $wet,'Test našel suchý i deštivý den');
echo "Lenošení, procházka, učení a omezení nabídky OK\n";
