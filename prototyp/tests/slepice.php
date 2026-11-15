<?php
declare(strict_types=1);

// Řetězec slepic a vajec: kurník osadí hejno, hejno žere obilí, snáší vejce, vejce se sbírají, kazí a jedí jako první.
// Použití: php tests/slepice.php   (bez modelu)
require __DIR__.'/fixture.php';

$ready=function(bool $kurnik=true): array {
    [$db,$world]=freshWorld();
    $world['buildings']=$kurnik ? ['statek'=>1,'kurnik'=>1] : ['statek'=>1];
    $world['grain']=40.0; $world['food']=300.0; $world['eggs']=0.0; $world['coopEggs']=0.0; $world['hens']=null;
    $world['fenceUntil']=(int)$world['minute']+10*1440;
    return [$db,$world];
};
$dayStart=function(array &$world): void { $world['minute']=(float)((intdiv((int)$world['minute'],1440)+1)*1440); };

// 1. Bez kurníku nic nevzniká; s kurníkem se hejno osadí a kronika to zapíše.
[$db,$world]=$ready(false);
$dayStart($world); henDaily($db,$world);
check($world['hens']===null && $world['coopEggs']==0.0,'Bez kurníku nejsou slepice ani vejce');
[$db,$world]=$ready();
$dayStart($world); henDaily($db,$world);
check($world['hens']>=HEN_START-.001 && $world['coopEggs']>0,'Kurník osadí hejno a snesou se vejce');
check((int)$db->query("SELECT COUNT(*) FROM journal WHERE title='Do kurníku přibylo hejno'")->fetchColumn()===1,'Kronika zapíše osazení kurníku');
$grain0=40.0;
check($world['grain']<$grain0 && abs(($grain0-$world['grain'])-HEN_START*HEN_FEED)<.01,'Hejno sežere obilí (6 × '.HEN_FEED.')');

// 2. Dokončení stavby kurníku osadí hejno hned (a jen jednou).
[$db,$world]=$ready();
$world['buildings']=['statek'=>1]; $world['project']=['key'=>'kurnik','label'=>'Kurník','progress'=>19.9,'work'=>20,'paid'=>true,'since'=>0,'hours'=>[]];
$world['people']['anna']['task']=['action'=>'construct','start'=>$world['minute']-120,'end'=>$world['minute']];
completeWork($db,$world,'anna');
check(hasBuilding($world,'kurnik') && $world['hens']===(float)HEN_START,'Dokončený kurník má hned šest slepic');

// 3. Sezóna: v létě se snáší víc než v zimě.
$layIn=function(string $season) use ($ready,$dayStart): float {
    [$db,$world]=$ready();
    $world['hens']=(float)HEN_MAX;
    $dayStart($world);
    // posunout počátek kalendáře tak, aby dnes bylo požadované roční období
    for ($shift=0;$shift<360;$shift+=15) {
        $world['calendarOriginDay']=intdiv((int)$world['minute'],1440)-$shift;
        if (calendarAt((int)$world['minute'],$world['calendarOriginDay'])['season']===$season) break;
    }
    $before=$world['coopEggs']; henDaily($db,$world);
    return $world['coopEggs']-$before;
};
$summer=$layIn('Léto'); $winter=$layIn('Zima');
check($summer>$winter*2.5 && $winter>0,'V létě se snáší víc než v zimě ('.round($summer,1).' vs '.round($winter,1).')');

// 4. Sbírání: péče o zvířata odnese vejce ze slepičince do zásob a ukáže to v textu.
[$db,$world]=$ready();
$dayStart($world); henDaily($db,$world);
$waiting=$world['coopEggs']; $world['people']['anna']['task']=['action'=>'livestock','start'=>$world['minute']-60,'end'=>$world['minute']];
$world['people']['anna']['place']='pasture';
$result=completeWork($db,$world,'anna');
check($world['coopEggs']==0.0 && abs($world['eggs']-$waiting)<1e-9 && str_contains($result,'vajec'),'Péče o zvířata sebere vejce ('.round($waiting,1).') a zapíše to');
// Nesebraná vejce se kazí a zásoba ve slepičinci je omezená.
$world['eggs']=0.0; $world['coopEggs']=0.0;
for ($i=0;$i<6;$i++) { $dayStart($world); henDaily($db,$world); }
check($world['coopEggs']<=$world['hens']*2+1e-9,'Nesebraná vejce přesáhnou jen omezenou zásobu');
// 5. Kažení zásoby vajec a jejich spotřeba jako první.
[$db,$world]=$ready();
$world['eggs']=10.0; $world['food']=50.0;
$dayStart($world); henDaily($db,$world);
check(abs($world['eggs']-10*EGG_KEEP)<1e-9,'Zásoba vajec se za den zkazí o 7 %');
$world['eggs']=10.0; $world['food']=50.0;
check(abs(foodReserve($world)-(50+10*EGG_FOOD+$world['grain']+$world['flour']*HEARTH_FLOUR_FOOD))<1e-9,'Vejce se počítají do rezervy jídla');
check(drawFood($world,4.0) && abs($world['eggs']-(10-4/EGG_FOOD))<1e-9 && $world['food']===50.0,'Jídlo se bere nejdřív z vajec');
check(drawFood($world,10.0) && $world['eggs']==0.0 && $world['food']<50.0,'Zbytek se doplní z ostatních zásob');
// 6. Hlad: bez obilí slepice méně snášejí a hejno řídne, kronika to řekne jednou.
[$db,$world]=$ready();
$world['grain']=0.0;
$dayStart($world); henDaily($db,$world); $a=$world['hens'];
for ($i=0;$i<5;$i++) { $dayStart($world); henDaily($db,$world); }
check($world['hens']<$a-.5,'Bez obilí hejno řídne');
check((int)$db->query("SELECT COUNT(*) FROM journal WHERE title='Slepice hladoví'")->fetchColumn()===1,'Kronika zapíše hlad slepic jen jednou');
$oneDay=function(float $grain) use ($ready,$dayStart): float {
    [$db,$world]=$ready();
    $world['hens']=8.0; $world['grain']=$grain;
    $dayStart($world); henDaily($db,$world);
    return $world['coopEggs'];
};
check($oneDay(0.0)<$oneDay(100.0)*.6,'Hladové slepice snáší jen část ('.round($oneDay(0.0),2).' vs '.round($oneDay(100.0),2).')');
// 7. Liška: bez plotu je riziko, s plotem ne.
$foxes=function(bool $fence) use ($ready,$dayStart): int {
    [$db,$world]=$ready();
    $world['hens']=(float)HEN_MAX; $world['grain']=2000.0;
    $count=0;
    for ($i=0;$i<120;$i++) {
        $world['fenceUntil']=$fence ? (int)$world['minute']+3*1440 : 0;
        $before=$world['hens']; $world['hens']=(float)HEN_MAX;
        $dayStart($world); $world['hens']=(float)HEN_MAX; henDaily($db,$world);
        if ($world['hens']<HEN_MAX-.5) ++$count;
        $world['eggs']=0.0; $world['coopEggs']=0.0;
    }
    return $count;
};
$withFence=$foxes(true); $withoutFence=$foxes(false);
check($withFence===0 && $withoutFence>=3 && $withoutFence<=25,'Liška chodí jen bez plotu ('.$withoutFence.' z 120 dní)');
// 8. Prázdný kurník vesnice po čase znovu osadí.
[$db,$world]=$ready();
$world['hens']=0.0;
for ($i=0;$i<HEN_RESTOCK_DAYS;$i++) { $dayStart($world); henDaily($db,$world); }
check($world['hens']>=3.0,'Prázdný kurník se po '.HEN_RESTOCK_DAYS.' dnech osadí');
// 9. Starý uložený stav bez nových klíčů.
[$db,$world]=$ready();
unset($world['eggs'],$world['hens'],$world['coopEggs'],$world['henEmptyDays'],$world['henHungry']);
$old=decodeWorld(encode($world));
check($old['eggs']===0.0 && $old['hens']===null && $old['coopEggs']===0.0,'Starý stav převezme nové klíče');
$dayStart($old); henDaily($db,$old);
check($old['hens']>=HEN_START-.001,'Existující kurník ze starého stavu dostane hejno při dalším dni');
// 10. Dlouhý běh: slepice drží rovnováhu, vejce se jedí a zásoby jídla nehladoví.
$seen=[]; set_error_handler(function($no,$msg,$file,$line) use (&$seen){ $seen[]=basename($file).':'.$line.' '.$msg; return true; });
[$db,$world]=$ready();
$world['fenceUntil']=0;
runDays($db,$world,120.0);
restore_error_handler();
check(!$seen,'Bez varování PHP: '.implode('; ',array_slice($seen,0,3)));
check($world['hens']>=1 && ($world['counters']['produced']['eggs']??0)>5,'Za 120 dní slepice snesly a lidé vejce sebrali ('.round($world['counters']['produced']['eggs']??0,1).')');
// 8. Mlýn nechá slepicím obilí na zimu (jinak se vše semele na chléb a hejno vyhladoví).
[$db,$world]=$ready();
$world['buildings']['mlyn']=1; $world['hens']=12.0;
$reserve=henGrainReserve($world);
check(abs($reserve-12*HEN_FEED*HEN_GRAIN_DAYS)<1e-9,'Rezerva obilí pro hejno ('.round($reserve,1).')');
$world['grain']=$reserve+1.0;
check(!workAvailable($world,'anna','mill'),'Mlýn nemele rezervu pro slepice');
$world['grain']=$reserve+30.0;
check(workAvailable($world,'anna','mill'),'Nad rezervou se mele');
assignWork($world,'anna','mill',false); completeWork($db,$world,'anna'); $world['people']['anna']['task']=null;
check($world['grain']>=$reserve-1e-9 && $world['flour']>0,'Semletí neklesne pod rezervu ('.round($world['grain'],1).')');
$world['hens']=null;
check(henGrainReserve($world)===0.0,'Bez hejna žádná rezerva');
echo "Slepice a vejce OK\n";
