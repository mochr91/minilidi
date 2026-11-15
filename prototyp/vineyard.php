<?php
declare(strict_types=1);

// Vinice a víno (fáze E rozšíření ekonomiky, návrh v ROZSIRENI-EKONOMIKY.md):
// vinice na jihovýchodním svahu (první úroda po dvou letech, riziko pozdního mrazu v dubnu), lis, sudy z dílny,
// víno kvasí 60 dní ve sklepě. Použití: hospoda o nedělních večerech, obřady v kostele a obchod.

const VINE_YIELD = 30.0;             // hroznů za podzim při plné péči
const VINE_MATURE_DAYS = 720;        // první úroda po dvou herních letech od stavby
const VINE_FROST_CHANCE = .25;       // šance pozdního mrazu v dubnu, který zničí květy
const VINE_TEND_TASKS = 6;
const VINE_HARVEST_PER_TASK = 6.0;
const GRAPE_KEEP = .97;              // hrozny se kazí 3 % denně
const WINE_KEEP = .999;              // víno 0,1 % denně
const PRESS_GRAPES = 30.0;
const WINE_YIELD = 20.0;             // vína z plného lisu hroznů
const FERMENT_DAYS = 60;
const BARREL_WORK = 20.0;            // člověkohodin na jeden sud
const BARREL_PLANKS = 3.0;
const WINE_TASKS = ['tend_vines','harvest_grapes','press_wine','make_barrel'];
const WINE_FEAST_RATION = .1;        // dávka vína na návštěvníka při nedělním večeru v hospodě
const WINE_MASS = .1;                // víno při obřadu v kostele

function wineStock(array $world): float {
    return ($world['wine']??0)+(($world['wineBatch']['state']??'')==='fermenting' ? (float)$world['wineBatch']['yield'] : 0.0);
}

// null = činnost tento modul nezná, jinak jestli je teď dostupná.
function wineWorkAvailable(array $world,string $id,string $key): ?bool {
    if (!in_array($key,WINE_TASKS,true)) return null;
    $m=calendarMonth($world);
    $weather=villageWeather($world);
    $open=!$weather['frozen'] && $weather['ground']!=='snow';
    $yard=$world['vineyard']??[];
    $batch=$world['wineBatch']??['state'=>'idle'];
    return match ($key) {
        'tend_vines'=>hasBuilding($world,'vinice') && $m>=1 && $m<=5 && $open && ($yard['care']??0)<.999,
        'harvest_grapes'=>hasBuilding($world,'vinice') && ($m===6 || $m===7) && $open && ($yard['crop']??0)>=1,
        'press_wine'=>hasBuilding($world,'lis') && hasBuilding($world,'sklep') && $batch['state']==='idle' && ($world['grapes']??0)>=10
            && (($world['grapes']??0)>=PRESS_GRAPES*.9 || ($yard['crop']??0)<1)
            && ($world['barrels']??0)>=1 && taskCount($world,'press_wine')<1,
        'make_barrel'=>hasBuilding($world,'lis') && ($world['barrels']??0)+($world['barrelWork']??0)/BARREL_WORK<1
            && $m>=2 && $m<=7 && $world['planks']>=BARREL_PLANKS+6 && taskCount($world,'make_barrel')<2,
    };
}

// Doplňkové body pro plánovač.
function wineScore(array $world,string $key): int {
    return match ($key) {
        'tend_vines'=>13,
        'harvest_grapes'=>32,
        'press_wine'=>34,
        'make_barrel'=>16,
        default=>0,
    };
}

function completeWineWork(PDO $db,array &$world,string $id,string $key): ?string {
    if (!in_array($key,WINE_TASKS,true)) return null;
    $person=$world['people'][$id];
    $task=$person['task']??[];
    $skill=taskSkillFactor($world,$id,$key);
    $now=(int)$world['minute'];
    switch ($key) {
        case 'tend_vines':
            $world['vineyard']['care']=min(1.0,($world['vineyard']['care']??0)+$skill/VINE_TEND_TASKS);
            return 'Réva je ošetřená z '.round($world['vineyard']['care']*100).' %.';
        case 'harvest_grapes':
            $n=min($world['vineyard']['crop']??0,VINE_HARVEST_PER_TASK*$skill);
            if ($n<=0) return 'Na vinici nezbylo co česat.';
            $world['vineyard']['crop']-=$n;
            $world['grapes']=($world['grapes']??0)+$n;
            countProduced($world,'grapes',$n);
            return 'Natrháno '.fmtAmount($n).' hroznů.';
        case 'press_wine':
            if (($world['wineBatch']['state']??'idle')!=='idle' || ($world['barrels']??0)<1 || ($world['grapes']??0)<10) return 'Lis se nepodařilo naplnit.';
            $used=min($world['grapes'],PRESS_GRAPES);
            $world['grapes']-=$used;
            $world['barrels']-=1;
            $yield=round(WINE_YIELD*$used/PRESS_GRAPES,1);
            $world['wineBatch']=['state'=>'fermenting','end'=>$now+FERMENT_DAYS*1440,'yield'=>$yield];
            journal($db,$world,$id,'Víno kvasí',$person['name'].' vylisoval'.(($person['sex']??'m')==='f'?'a':'').' '.fmtAmount($used).' hroznů. Za šedesát dní bude ve sklepě asi '.fmtAmount($yield).' vína.','Hra');
            return 'Hrozny jsou vylisované a víno kvasí v sudu ve sklepě.';
        case 'make_barrel':
            $hours=max(0,(int)($task['end']??0)-(int)($task['start']??0))/60;
            $power=max(.5,productivity($person)/70)*$skill;
            if (($world['barrelWork']??0)<=0) {
                if ($world['planks']<BARREL_PLANKS) return 'Na sud chybí prkna.';
                consumeStock($world,'planks',BARREL_PLANKS);
            }
            $world['barrelWork']=($world['barrelWork']??0)+$hours*min(1.5,$power/BUILD_NORM_POWER);
            if ($world['barrelWork']>=BARREL_WORK) {
                $world['barrelWork']=0.0;
                $world['barrels']=($world['barrels']??0)+1;
                countProduced($world,'barrels',1);
                return 'Sud je hotový.';
            }
            return 'Sud postoupil ('.fmtAmount($world['barrelWork']).' z '.fmtAmount(BARREL_WORK).' člověkohodin).';
    }
    return null;
}

// Jednou denně: kažení, kvašení, vinice (péče, mráz v dubnu, úroda v září).
function wineDaily(PDO $db,array &$world): void {
    $now=(int)$world['minute'];
    $calendar=calendarAt($now,$world['calendarOriginDay']);
    $m=$calendar['monthIndex'];
    $world['grapes']=($world['grapes']??0)*GRAPE_KEEP;
    $world['wine']=($world['wine']??0)*WINE_KEEP;
    foreach (['grapes','wine'] as $key) if ($world[$key]<.05) $world[$key]=0.0;
    $batch=&$world['wineBatch'];
    if (($batch['state']??'idle')==='fermenting' && $now>=$batch['end']) {
        $world['wine']+=$batch['yield'];
        countProduced($world,'wine',(float)$batch['yield']);
        journal($db,$world,'','Víno dozrálo','Ve sklepě je '.fmtAmount((float)$batch['yield']).' nového vína.','Hra');
        $batch=['state'=>'idle','end'=>0,'yield'=>0.0];
    }
    unset($batch);
    $yard=&$world['vineyard'];
    if (hasBuilding($world,'vinice')) {
        if (($yard['season']??-1)!==$calendar['year'] && $m<=5) {
            $yard=['season'=>$calendar['year'],'care'=>0.0,'crop'=>0.0,'done'=>false,'frost'=>false];
        }
        if ($m===1 && $calendar['day']>=25 && empty($yard['frostChecked']) && ($yard['season']??-1)===$calendar['year']) {
            $yard['frostChecked']=true;
            if (weatherNoise($calendar['year']*37+11,'vine-frost')<VINE_FROST_CHANCE) {
                $yard['frost']=true;
                journal($db,$world,'','Pozdní mráz poškodil révu','Po teplém jaru přišel v dubnu mráz a květy zmrzly. Letos nebude úroda.','Příběh');
            }
        }
        if (!($yard['done']??true) && $m>=6 && ($yard['season']??-1)===$calendar['year']) {
            $mature=$now-(int)$world['buildings']['vinice']>=VINE_MATURE_DAYS*1440;
            $yard['crop']=($mature && empty($yard['frost'])) ? round(VINE_YIELD*(.4+.6*($yard['care']??0))*pollination($world),1) : 0.0;
            $yard['done']=true;
            if ($yard['crop']>=1) journal($db,$world,'','Réva dozrála','Hrozny je třeba česat do konce října, pak shnijí.','Hra');
        }
        if ($m>=2 && $m<=5) $yard['care']=max(0.0,($yard['care']??0)*.985);
        if ($m>=8 && ($yard['crop']??0)>0) $yard['crop']=$yard['crop']<1?0.0:$yard['crop']*.85;
    }
    unset($yard);
}
