<?php
declare(strict_types=1);

const MINUTES_PER_DAY = 1440;
const DAYS_PER_MONTH = 30;
const DAY_SECONDS = 1200;
const WEATHER_SLOT = 240;

const CALENDAR_MONTHS = [
    ['březen','Jaro',4.0,38,6,18], ['duben','Jaro',9.2,31,6,19], ['květen','Jaro',13.8,64,5,20],
    ['červen','Léto',17.2,77,5,21], ['červenec','Léto',19.0,79,5,21], ['srpen','Léto',18.6,72,6,20],
    ['září','Podzim',13.7,48,6,19], ['říjen','Podzim',8.7,41,7,18], ['listopad','Podzim',4.0,36,7,17],
    ['prosinec','Zima',.4,36,8,16], ['leden','Zima',-.6,33,8,16], ['únor','Zima',.4,28,7,17],
];

function calendarAt(float $minute, int $originDay): array {
    $absoluteDay = intdiv((int)$minute,MINUTES_PER_DAY);
    $day = max(0,$absoluteDay-$originDay);
    $month = intdiv($day,DAYS_PER_MONTH)%12;
    $dayMinute = (int)$minute%MINUTES_PER_DAY;
    [$name,$season,,,$sunrise,$sunset] = CALENDAR_MONTHS[$month];
    return [
        'originDay'=>$originDay,'monthIndex'=>$month,'month'=>$name,'season'=>$season,
        'day'=>$day%DAYS_PER_MONTH+1,'year'=>intdiv($day,360)+1,'hour'=>intdiv($dayMinute,60),
        'minute'=>$dayMinute%60,'dayProgress'=>$dayMinute/MINUTES_PER_DAY,
        'yearProgress'=>($day%360+$dayMinute/MINUTES_PER_DAY)/360,'sunrise'=>$sunrise,'sunset'=>$sunset,
    ];
}

function weatherNoise(int $index, string $channel): float {
    return crc32('minilidi-v1:' . $channel . ':' . $index)/4294967295;
}

function weatherBand(float $day): array {
    $month = (int)floor($day/DAYS_PER_MONTH);
    $offset = $day/DAYS_PER_MONTH-$month;
    $a = CALENDAR_MONTHS[($month%12+12)%12];
    $b = CALENDAR_MONTHS[(($month+1)%12+12)%12];
    return [$a[2]+($b[2]-$a[2])*$offset,$a[3]+($b[3]-$a[3])*$offset];
}

function weatherRaw(int $slot): array {
    static $cache = [];
    if (isset($cache[$slot])) return $cache[$slot];
    if (count($cache)>80) $cache=[];
    $localDay = (int)floor($slot/6);
    $hour = (($slot%6+6)%6)*4;
    $spell = (int)floor($localDay/3);
    $blend = ($localDay-$spell*3)/3;
    $shift = (weatherNoise($spell,'season')*(1-$blend)+weatherNoise($spell+1,'season')*$blend-.5)*60;
    [$average,$rain] = weatherBand($localDay+$shift-15);
    $diurnal = [-4,-5,0,6,5,-1][intdiv($hour,4)];
    $anomaly = (weatherNoise($localDay,'temperature')-.5)*12;
    if (weatherNoise($spell,'extreme')>.90) $anomaly += $average>12?6:-6;
    $wet = weatherNoise((int)floor($slot/2),'wet') < .13 + $rain*.0038;
    $temperature = (int)round($average+$diurnal+$anomaly-($wet?1:0));
    $wind = (int)round(4+weatherNoise($slot,'wind')**1.8*31+($wet?6:0));
    $precip = !$wet ? 'none' : ($temperature<=0?'snow':($temperature<=2?'sleet':'rain'));
    return $cache[$slot] = compact('temperature','wind','precip');
}

function villageWeather(array $world): array {
    static $cache = [];
    $minute = (int)$world['minute']-($world['calendarOriginDay']??0)*MINUTES_PER_DAY;
    $slot = (int)floor($minute/WEATHER_SLOT);
    if (isset($cache[$slot])) return calmWeather($world,$cache[$slot]);
    if (count($cache)>40) $cache=[];
    $now = weatherRaw($slot);
    $snow = 0;
    $mud = 0;
    for ($past=$slot-12;$past<=$slot;$past++) {
        $p = weatherRaw($past);
        $mud = max(0,$mud-1);
        if ($p['precip']==='snow') $snow=min(15,$snow+3);
        if ($p['precip']==='sleet') $snow=min(15,$snow+1);
        if ($p['temperature']>2 && $snow>0) {
            $snow=max(0,$snow-($p['temperature']>6?3:1));
            $mud=min(6,$mud+2);
        }
        if (in_array($p['precip'],['rain','sleet'],true)) $mud=min(6,$mud+3);
    }
    $daySlot = (int)floor($slot/6)*6;
    $nightLow = min(weatherRaw($daySlot)['temperature'],weatherRaw($daySlot+1)['temperature']);
    $ground = $snow>=3?'snow':($mud>=2?'mud':'dry');
    $frozen = $now['temperature']<=-2 || ($now['temperature']<=1 && $nightLow<=-4);
    $heat = $now['temperature']>=30;
    $woodNeed = $nightLow<=-7?4:($nightLow<5?3:DAILY_WOOD);
    $condition = match ($now['precip']) {'rain'=>'Déšť','snow'=>'Sněžení','sleet'=>'Déšť se sněhem',default=>null};
    $condition ??= $heat?'Vedro':($frozen?'Mráz':($now['temperature']>=20?'Teplo':'Beze srážek'));
    $base = $cache[$slot] = $now + compact('ground','snow','frozen','heat','woodNeed','condition');
    return calmWeather($world,$base);
}

function calmWeather(array $world,array $weather): array {
    if (($world['calmUntil']??0) <= $world['minute']) return $weather;
    return array_replace($weather,['precip'=>'none','wind'=>min(8,$weather['wind']),'condition'=>'Utišené počasí']);
}

function weatherAllows(array $world, string $action): bool {
    $weather = villageWeather($world);
    $season = calendarAt($world['minute'],$world['calendarOriginDay']??0)['season'];
    return match ($action) {
        'fieldwork','fertilize' => $season!=='Zima' && !$weather['frozen'] && $weather['ground']!=='snow'
            && ($world['fieldBlockedUntil']??0) <= $world['minute'],
        'gathering' => $season!=='Zima' && $weather['ground']!=='snow',
        'fishing' => $weather['wind']<35 && !($weather['frozen'] && $weather['snow']>=5),
        'build_house','fix_fence','construct' => $weather['wind']<37,
        'mow' => in_array(calendarAt($world['minute'],$world['calendarOriginDay']??0)['monthIndex'],[2,3,4,5,6],true)
            && $weather['ground']!=='snow' && !$weather['frozen'] && $weather['precip']==='none',
        default => true,
    };
}

function weatherYield(array $world, string $action): float {
    $weather = villageWeather($world);
    $harvest = ($world['harvestUntil']??0) > $world['minute'] && in_array($action,['fieldwork','mow'],true) ? ($world['harvestFactor']??1) : 1;
    if (in_array($action,['fieldwork','fertilize'],true) && !weatherAllows($world,$action)) return .25*$harvest;
    if ($action==='fieldwork' && $weather['ground']==='mud') return .8*$harvest;
    if ($harvest!==1) return (float)$harvest;
    if ($action==='fishing' && $weather['wind']>=28) return .72;
    return 1;
}

function weatherWorkMinutes(array $world, string $action, int $minutes): int {
    if ($action!=='haul_stone') return $minutes;
    return match (villageWeather($world)['ground']) {
        'mud'=>(int)ceil($minutes*1.25), 'snow'=>(int)ceil($minutes*1.4), default=>$minutes,
    };
}

function weatherFatigue(array $weather, string $place, float $base): float {
    return $base>0 && $weather['heat'] && in_array($place,['field','forest','quarry','building','pasture'],true)
        ? $base*1.25 : $base;
}
