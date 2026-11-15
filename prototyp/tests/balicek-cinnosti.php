<?php
declare(strict_types=1);

require __DIR__.'/fixture.php';

[$db,$world]=freshWorld();
$control=$world;
$path=tempnam(sys_get_temp_dir(),'minilidi-pack-');
try {
    $pack=json_decode(file_get_contents(__DIR__.'/../balicky/cinnosti.json'),true,12,JSON_THROW_ON_ERROR);
    file_put_contents($path,encode($pack));
    refreshActivityPack($world,$control,$path);
    check(count($world['activityPack'])===count($pack['activities']+$pack['extensions']) && $world['activityPack']===$control['activityPack'],'Společný katalog obou větví');
    check(!workAvailable($world,'anna','pack_orchard'),'Na jaře se sad nečeše');
    for ($day=180;$day<270;$day++) {
        $world['minute']=$day*1440+600;
        if (workAvailable($world,'anna','pack_orchard')) break;
    }
    check(workAvailable($world,'anna','pack_mushrooms'),'Podzimní sběr hub');
    check(isset(villageCandidates($world)['anna']['pack_orchard']),'Sad je mezi volbami plánovače');
    assignWork($world,'anna','pack_orchard',true);
    $snapshot=$world['people']['anna']['task']['activity'];
    $pack['activities']['pack_orchard']['label']='Sklidit ovoce';
    $pack['activities']['pack_orchard']['result']='Nové znění.';
    file_put_contents($path,encode($pack));
    refreshActivityPack($world,$control,$path);
    check(activityDefinition($world,'pack_orchard')['label']==='Sklidit ovoce','Změna se načetla ve stejném procesu');
    file_put_contents($path,'{');
    refreshActivityPack($world,$control,$path);
    check(activityDefinition($world,'pack_orchard')['label']==='Sklidit ovoce','Vadný katalog nemění poslední funkční stav');
    unset($pack['activities']['pack_orchard']);
    file_put_contents($path,encode($pack));
    refreshActivityPack($world,$control,$path);
    check(!workAvailable($world,'anna','pack_orchard'),'Odebraná činnost se už nenabízí');
    $world=decodeWorld(encode($world));
    $food=$world['food'];
    check(completeWork($db,$world,'anna')===$snapshot['result'] && $world['food']>$food,'Rozdělaný úkol doběhne i po odebrání a načtení uloženého stavu');

    $world['people']['jan']['illUntil']=$world['minute']+300;
    check(workAvailable($world,'anna','pack_sick_visit'),'Návštěva je dostupná při nemoci');
    assignWork($world,'anna','pack_sick_visit',true);
    $mood=$world['people']['jan']['mood'];
    completeWork($db,$world,'anna');
    check($world['people']['jan']['mood']>$mood,'Návštěva zvedne náladu nemocnému');
    check(workAvailable($world,'anna','pack_harvest'),'Společná sklizeň');
    assignWork($world,'anna','pack_harvest',true);
    $grain=$world['grain'];
    completeWork($db,$world,'anna');
    check($world['grain']>$grain,'Společná sklizeň přidá obilí');

    $world['people']['jan']['grudges']['bara']=$world['minute']+600;
    $world['people']['jan']['bonds']['anna']=$world['people']['bara']['bonds']['anna']=65;
    check(workAvailable($world,'anna','pack_reconcile'),'Dva sousedé věří prostředníkovi');
    assignWork($world,'anna','pack_reconcile',true);
    completeWork($db,$world,'anna');
    check(!isset($world['people']['jan']['grudges']['bara']),'Spor byl urovnán');

    $world['minute']=intdiv((int)$world['minute'],1440)*1440+1140;
    check(!workAvailable($world,'anna','pack_tavern_games'),'Bez hospody není kde hrát');
    $world['buildings']['hospoda']=1;
    $world['people']['karel']['place']='tavern';
    check(workAvailable($world,'anna','pack_tavern_games'),'Večerní hra v hospodě');
    assignWork($world,'anna','pack_tavern_games',true);
    $fun=$world['people']['karel']['fun'];
    completeWork($db,$world,'anna');
    check($world['people']['karel']['fun']>$fun,'Hra pobaví i spoluhráče');
    echo "Činnosti, změna katalogu, vadná aktualizace a dokončení rozdělaného úkolu OK\n";
} finally {
    unlink($path);
}
