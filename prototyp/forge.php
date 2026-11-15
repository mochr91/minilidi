<?php
declare(strict_types=1);

// Kovárna a důl (fáze D rozšíření ekonomiky, návrh v ROZSIRENI-EKONOMIKY.md):
// z dolu u lomu je železná ruda, v kovárně se z ní a z dřevěného uhlí taví železo a kuje železné nářadí, které zrychlí práci.
// Obchodník a úrok vrchnosti jsou v trade.php.

const ORE_TASK = 4.0;                 // rudy na jeden úkol těžby
const ORE_TARGET = 24.0;              // do této zásoby se těží
const SMELT_ORE = 4.0;                // ruda na jednu tavbu
const SMELT_CHARCOAL = 2.0;
const SMELT_IRON = 1.0;               // železa z jedné taveb
const FORGE_IRON = 1.0;               // železo na jeden kus železného nářadí
const FORGE_CHARCOAL = 2.0;
const IRON_TOOLS_KEEP = .998;         // železné nářadí se opotřebuje pomalu (0,2 % denně)
const IRON_STOCK_MAX = 4.0;           // tolik železa se drží (zbytek rudy čeká)
const IRON_TOOL_BONUS = .08;          // o kolik zrychlí práci, když železné nářadí pokryje potřebu vesnice
const FORGE_TASKS = ['mine_ore','smelt_iron','forge_tools'];

// Kolik kusů železného nářadí vesnice chce mít (dvakrát potřeba běžného nářadí).
function ironToolTarget(array $world): float {
    return 2.0*max(1,(int)ceil(adultCount($world)/3));
}

function ironWanted(array $world): bool {
    return ($world['ironTools']??0)<ironToolTarget($world);
}

// Dřevěného uhlí se pálí víc, když kovárna stojí.
function charcoalTarget(array $world): float {
    return hasBuilding($world,'kovarna') ? 24.0 : CHARCOAL_TARGET;
}

// null = činnost tento modul nezná, jinak jestli je teď dostupná.
function forgeWorkAvailable(array $world,string $id,string $key): ?bool {
    if (!in_array($key,FORGE_TASKS,true)) return null;
    return match ($key) {
        'mine_ore'=>hasBuilding($world,'dul') && ($world['ore']??0)<ORE_TARGET && ironWanted($world) && taskCount($world,'mine_ore')<2,
        'smelt_iron'=>hasBuilding($world,'kovarna') && ($world['ore']??0)>=SMELT_ORE && ($world['charcoal']??0)>=SMELT_CHARCOAL
            && ($world['iron']??0)<IRON_STOCK_MAX && ironWanted($world) && taskCount($world,'smelt_iron')<1,
        'forge_tools'=>hasBuilding($world,'kovarna') && ($world['iron']??0)>=FORGE_IRON && ($world['charcoal']??0)>=FORGE_CHARCOAL
            && ironWanted($world) && taskCount($world,'forge_tools')<1,
    };
}

// Doplňkové body pro plánovač.
function forgeScore(array $world,string $key): int {
    return match ($key) {
        'mine_ore'=>($world['ore']??0)<SMELT_ORE ? 20 : 10,
        'smelt_iron'=>22,
        'forge_tools'=>28,
        default=>0,
    };
}

function completeForgeWork(PDO $db,array &$world,string $id,string $key): ?string {
    if (!in_array($key,FORGE_TASKS,true)) return null;
    $skill=taskSkillFactor($world,$id,$key);
    switch ($key) {
        case 'mine_ore':
            $n=round(ORE_TASK*$skill,1);
            $world['ore']=($world['ore']??0)+$n;
            countProduced($world,'ore',$n);
            return 'Z dolu je '.fmtAmount($n).' železné rudy.';
        case 'smelt_iron':
            if (($world['ore']??0)<SMELT_ORE || ($world['charcoal']??0)<SMELT_CHARCOAL || !hasBuilding($world,'kovarna')) return 'Na tavbu chybí ruda nebo uhlí.';
            $world['ore']-=SMELT_ORE;
            consumeStock($world,'charcoal',SMELT_CHARCOAL);
            $world['iron']=($world['iron']??0)+SMELT_IRON;
            countProduced($world,'iron',SMELT_IRON);
            return 'Z rudy je '.fmtAmount(SMELT_IRON).' železa.';
        case 'forge_tools':
            if (($world['iron']??0)<FORGE_IRON || ($world['charcoal']??0)<FORGE_CHARCOAL || !hasBuilding($world,'kovarna')) return 'Na kování chybí železo nebo uhlí.';
            $world['iron']-=FORGE_IRON;
            consumeStock($world,'charcoal',FORGE_CHARCOAL);
            $world['ironTools']=($world['ironTools']??0)+1;
            countProduced($world,'ironTools',1);
            return 'Kovárna vyrobila kus železného nářadí.';
    }
    return null;
}

function forgeDaily(PDO $db,array &$world): void {
    $world['ironTools']=($world['ironTools']??0)*IRON_TOOLS_KEEP;
    if ($world['ironTools']<.01) $world['ironTools']=0.0;
    // Kožky, které nikdo nekoupí, časem sežerou moli (2 % denně).
    $world['hides']=($world['hides']??0)*.98;
    if ($world['hides']<.05) $world['hides']=0.0;
}
