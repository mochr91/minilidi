<?php
declare(strict_types=1);

// Odliší shodu činností vesnic z denního rytmu od situace, kdy plán AI nic neovlivňuje (KOORDINACE.md bod 9).
// Použití: php tests/shoda.php <port falešného modelu>   (model-stub.php musí běžet)
require __DIR__.'/fixture.php';

$stub=(int)($argv[1]??0);
check($stub>0,'Předej port testovacího modelu (php -S 127.0.0.1:<port> tests/model-stub.php).');
putenv('MINILIDI_NO_AI=0'); putenv('MINILIDI_AI=ollama'); putenv('MINILIDI_AI_PORT='.$stub); putenv('MINILIDI_BATCH_TIMEOUT_MS=3000');

function dayPart(int $hour): string {
    return match(true) {$hour>=21||$hour<5=>'noc', $hour===12||$hour===18=>'pauza', ($hour>=7&&$hour<12)||($hour>=13&&$hour<18)=>'práce', default=>'ráno a večer'};
}

function stepBranch(PDO $db,array &$world,bool $ai,string $model): void {
    putenv('MINILIDI_MODEL='.$model);
    if ($world['minute'] >= $world['nextAutoMinute']) {
        applyVillagePlan($db,$world,prepareVillagePlan($db,$world,$ai),$ai);
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

// Dvě větve ze stejného stavu. Vrací shodu činností: celkovou i podmíněnou (jen osobohodiny, kde některá větev má úkol).
function twin(bool $aiA,string $modelA,bool $aiB,string $modelB,int $days): array {
    [$dbA,$A]=freshWorld(); [$dbB,$B]=freshWorld();
    $s=[];
    for ($i=0;$i<$days*96;$i++) {
        stepBranch($dbA,$A,$aiA,$modelA);
        stepBranch($dbB,$B,$aiB,$modelB);
        $part=dayPart(intdiv((int)$A['minute']%1440,60));
        foreach ($A['people'] as $id=>$pa) {
            $pb=$B['people'][$id]??null;
            if (!$pb) continue;
            $ta=$pa['task']['action']??'-'; $tb=$pb['task']['action']??'-';
            $same=$pa['activity']===$pb['activity'] && $ta===$tb;
            $s[$part]['n']=($s[$part]['n']??0)+1;
            $s[$part]['same']=($s[$part]['same']??0)+($same?1:0);
            if ($ta!=='-'||$tb!=='-') {
                $s[$part]['tn']=($s[$part]['tn']??0)+1;
                $s[$part]['tsame']=($s[$part]['tsame']??0)+($same?1:0);
            }
        }
    }
    return [$s,$dbA,$A];
}
function share(array $s,string $part,string $num,string $den): float { return ($s[$part][$den]??0)>0 ? ($s[$part][$num]??0)/$s[$part][$den] : 1.0; }

// 1. Základ: dvě větve bez AI se shodují vždy a úplně.
[$s]=twin(false,'valid',false,'valid',3);
foreach ($s as $part=>$v) check($v['same']===$v['n'],'Základ bez AI musí být 100 %: '.$part);
echo "Základ (pravidla × pravidla): shoda 100 % ve všech částech dne OK\n";

// 2. Kontrafaktuál: model, který bere vždy poslední povolenou volbu, proti pravidlům.
[$s,$dbA,$A]=twin(true,'last',false,'valid',3);
$night=share($s,'noc','same','n');
$day=0; $dayN=0; $dayAll=0; $dayAllN=0;
foreach (['ráno a večer','práce','pauza'] as $part) { $day+=$s[$part]['tsame']??0; $dayN+=$s[$part]['tn']??0; $dayAll+=$s[$part]['same']??0; $dayAllN+=$s[$part]['n']??0; }
$conditional=$dayN?$day/$dayN:1.0; $global=$dayAllN?$dayAll/$dayAllN:1.0;
check($night>=.99,sprintf('V noci se musí větve shodovat (%.1f %%)',100*$night));
check($conditional<.60,sprintf('Podmíněná shoda ve dne je při perverzním modelu příliš vysoká (%.1f %%): plán AI nic neovlivňuje',100*$conditional));
check($global>$conditional+.1,'Celková shoda má být výrazně vyšší než podmíněná, jinak test nic neodliší');
printf("Perverzní model: noc %.1f %%, den celkem %.1f %%, den jen hodiny s úkolem %.1f %% OK\n",100*$night,100*$global,100*$conditional);

// 3. Atribuce dávky z tabulky plan_audit (AI větev předchozího kroku).
$rows=$dbA->query('SELECT minute,why FROM plan_audit')->fetchAll(PDO::FETCH_ASSOC);
check(count($rows)>0,'Dávky ukládají audit rozhodnutí');
$byHour=[];
foreach ($rows as $r) { $h=intdiv((int)$r['minute']%1440,60); $byHour[$h][$r['why']]=($byHour[$h][$r['why']]??0)+1; $byHour[$h]['n']=($byHour[$h]['n']??0)+1; }
foreach ([6,12] as $h) {
    $changed=($byHour[$h]['model']??0)/max(1,$byHour[$h]['n']??1);
    check($changed>=.30,sprintf('V dávce %02d:00 má model změnit výsledek aspoň u 30 %% osob (%.0f %%)',$h,100*$changed));
}
$nightAgree=($byHour[0]['agree']??0)/max(1,$byHour[0]['n']??1);
check($nightAgree>=.99,sprintf('Dávka 00:00 má jedinou možnost (odpočinek), shoda %.0f %%',100*$nightAgree));
check(array_sum(array_map(fn($v)=>array_sum(array_diff_key($v,['n'=>1])),$byHour))===count($rows),'Každý záznam auditu má důvod');
echo "Atribuce dávek: změna výsledku v 06:00 a 12:00, shoda v 00:00 OK\n";

// 4. Čítače důvodů jsou ve stavu světa.
check(array_sum($A['counters']['planWhy']??[])>0,'Čítač planWhy ve stavu světa');
$stats=planStats($dbA,$A);
check($stats['total']>0 && isset($stats['byWhy']) && count($stats['batches'])>0,'planStats vrací souhrn za 24 herních hodin');
echo "Čítače a planStats OK\n";
echo "Vše prošlo\n";
