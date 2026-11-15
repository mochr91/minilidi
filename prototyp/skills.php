<?php
declare(strict_types=1);

// Kategorie činností a dovednosti. Každá osoba má zkušenost 0 až 100 v každé kategorii (`skills`).
// Zkušenost násobí výnos práce: nezkušený dělá 50 %, mistr (kolem 77) 100 %, plná zkušenost 115 %.
// Staré uložené hry `skills` nemají, hodnoty se odvodí z věku a místa práce (`defaultSkills`).

const CATEGORIES = [
    'field'=>'Pole a dobytek', 'forest'=>'Les, voda a sběr', 'craft'=>'Řemesla', 'build'=>'Kámen a stavby',
    'neighbors'=>'Sousedé', 'home'=>'Domov a společnost', 'faith'=>'Víra', 'crisis'=>'Krize',
];

const ACTION_CATEGORY = [
    'fieldwork'=>'field','fertilize'=>'field','manure'=>'field','livestock'=>'field','mow'=>'field',
    'sow_garden'=>'field','tend_garden'=>'field','harvest_garden'=>'field','pickle'=>'home','gather_herbs'=>'forest','dry_herbs'=>'home','nurse'=>'neighbors',
    'haul_wood'=>'forest','build_cart'=>'craft','load_kiln'=>'craft','watch_kiln'=>'craft','hunt_small'=>'forest','smoke_meat'=>'craft',
    'tend_hops'=>'field','harvest_hops'=>'field','make_malt'=>'craft','brew_beer'=>'craft',
    'mine_ore'=>'build','smelt_iron'=>'craft','forge_tools'=>'craft','tend_vines'=>'field','harvest_grapes'=>'field','press_wine'=>'craft','make_barrel'=>'craft',
    'tan_hides'=>'craft','make_boots'=>'craft','spin_weave'=>'craft','make_cheese'=>'craft','harvest_pond'=>'forest','teach'=>'home',
    'tend_bees'=>'field','harvest_honey'=>'field','prepare_hives'=>'field','make_candles'=>'craft','dig_clay'=>'build','fire_bricks'=>'craft','reroof'=>'build','bathe'=>'home',
    'felling'=>'forest','firewood'=>'forest','gathering'=>'forest','fishing'=>'forest',
    'mill'=>'craft','bake'=>'craft','saw'=>'craft','craft'=>'craft',
    'quarrying'=>'build','haul_stone'=>'build','build_house'=>'build','construct'=>'build','fix_fence'=>'build',
    'help'=>'neighbors','bring_water'=>'neighbors','deliver_meal'=>'neighbors','withhold_tools'=>'neighbors',
    'rest'=>'home','visit'=>'home','market'=>'home','gather_neighbors'=>'home',
    'prayer'=>'faith','wedding'=>'faith','firefight'=>'crisis','flood_defense'=>'crisis',
];

// Kategorie činnosti z balíčku, když ji soubor neuvádí.
const PACK_EFFECT_CATEGORY = ['food'=>'forest','harvest'=>'field','visit_sick'=>'neighbors','mediate'=>'neighbors','games'=>'home'];

// Kategorie podle místa běžné práce osoby (`work`).
const WORK_CATEGORY = ['field'=>'field','pasture'=>'field','forest'=>'forest','river'=>'forest','yard'=>'home','market'=>'home',
    'barn'=>'home','tavern'=>'home','quarry'=>'build','building'=>'build','mill'=>'craft','oven'=>'craft','sawmill'=>'craft',
    'workshop'=>'craft','well'=>'neighbors','chapel'=>'faith'];

const SKILL_RATE = 0.04;        // bodů za hodinu práce při nulové zkušenosti u dospělého (30 až 45 let)
const SKILL_FLOOR = 0.5;        // výnos nezkušeného
const SKILL_SLOPE = 0.65;       // přírůstek výnosu na 100 bodů zkušenosti

function packCategory(array $def): string {
    $category=$def['category']??null;
    if (is_string($category) && isset(CATEGORIES[$category])) return $category;
    return PACK_EFFECT_CATEGORY[$def['effect']??'']??'home';
}

function actionCategory(array $world,string $key,?array $task=null): string {
    if (isset(ACTION_CATEGORY[$key])) return ACTION_CATEGORY[$key];
    $def=$task['activity']??$world['activityPack'][$key]??null;
    return is_array($def) ? packCategory($def) : 'home';
}

function workCategory(string $work): string {
    return WORK_CATEGORY[$work]??'home';
}

// Katalog seřazený podle kategorií (pro nabídku v GUI), uvnitř kategorie ve stávajícím pořadí.
function catalogByCategory(array $catalog): array {
    $sorted=[];
    foreach (CATEGORIES as $category=>$label) {
        foreach ($catalog as $key=>$def) if (($def['category']??null)===$category) $sorted[$key]=$def;
    }
    return $sorted+$catalog;
}

// Nejzkušenější osoba v oboru (pro přehled, ohrožení řemesla).
function bestInCategory(array $world,string $category): ?array {
    $best=null;
    foreach ($world['people'] as $id=>$person) {
        if ($person['age']<14) continue;
        $level=skillLevel($person,$category);
        if ($best===null || $level>$best[1]) $best=[$id,$level];
    }
    return $best;
}

// Výchozí zkušenosti podle věku a místa práce (pro staré uložení, nově příchozí a děti při dospívání).
function defaultSkills(array $person): array {
    $age=(int)($person['age']??30);
    if ($age<12) { $base=5.0; $bonus=0.0; }
    elseif ($age<18) { $base=20.0+($age-12)*2; $bonus=10.0; }
    else { $base=40.0+min(30,$age-18)*.4; $bonus=30.0; }
    $main=workCategory((string)($person['work']??'yard'));
    $skills=[];
    foreach (CATEGORIES as $key=>$label) $skills[$key]=round(min(100.0,$base+($key===$main?$bonus:0)),1);
    return $skills;
}

function skillsOf(array $person): array {
    $stored=is_array($person['skills']??null) ? $person['skills'] : [];
    $skills=[];
    $defaults=null;
    foreach (CATEGORIES as $key=>$label) {
        if (isset($stored[$key])) { $skills[$key]=(float)$stored[$key]; continue; }
        $defaults??=defaultSkills($person);
        $skills[$key]=$defaults[$key];
    }
    return $skills;
}

function skillLevel(array $person,string $category): float {
    if (isset($person['skills'][$category])) return (float)$person['skills'][$category];
    return defaultSkills($person)[$category]??0.0;
}

// Měkký vliv zkušenosti (Víra, Domov, Sousedé): násobek 0,85 až 1,18, běžný dospělý (kolem 45 bodů) vychází na 1,0.
function softSkill(array $person,string $category): float {
    return .85+.0033*skillLevel($person,$category);
}

function skillFactor(array $person,string $category): float {
    return SKILL_FLOOR+SKILL_SLOPE*skillLevel($person,$category)/100;
}

// Učitelé: rodiče, u původních obyvatel dospělí z téže rodiny alespoň o 14 let starší.
function skillTeachers(array $world,array $person): array {
    $ids=array_filter([$person['mother']??null,$person['father']??null],fn($id)=>is_string($id) && isset($world['people'][$id]));
    if ($ids) return array_values($ids);
    foreach ($world['people'] as $id=>$other) {
        if ($id!==($person['id']??null) && $other['family']===$person['family'] && $other['age']>=$person['age']+14 && $other['age']>=25) $ids[]=$id;
    }
    return array_values($ids);
}

function skillAgeMultiplier(int $age): float {
    return $age<18 ? 2.0 : ($age<31 ? 1.4 : ($age<46 ? 1.0 : ($age<61 ? .6 : .3)));
}

// Zkušenost roste prací: čím vyšší, tím pomaleji; mladí se učí rychleji; učitel s vyšší zkušeností urychlí učení.
function trainSkill(PDO $db,array &$world,string $id,string $category,float $minutes): void {
    if (!isset($world['people'][$id]) || !isset(CATEGORIES[$category]) || $minutes<=0) return;
    $person=$world['people'][$id];
    if ($person['age']<12) return;
    $skills=skillsOf($person);
    $before=$skills[$category];
    if ($before>=100) { $world['people'][$id]['skills']=$skills; return; }
    $multiplier=skillAgeMultiplier((int)$person['age']);
    foreach (skillTeachers($world,$person) as $teacher) {
        if (skillLevel($world['people'][$teacher],$category)>=$before+10) { $multiplier*=1.4; break; }
    }
    $after=min(100.0,$before+$minutes/60*SKILL_RATE*$multiplier*(1-$before/100));
    $skills[$category]=round($after,3);
    $world['people'][$id]['skills']=$skills;
    $female=$person['sex']==='f';
    foreach ([50,80] as $tier) {
        if ($before>=$tier || $after<$tier || $person['age']<14) continue;
        $title=$tier===50
            ? $person['name'].($female?' se vyučila':' se vyučil').' v oboru '.CATEGORIES[$category]
            : $person['name'].($female?' je mistryní':' je mistrem').' v oboru '.CATEGORIES[$category];
        journal($db,$world,$id,$title,$tier===50?'Práce jde od ruky, výnos už je znatelně lepší.':'Sousedé se na '.($female?'ni':'něj').' obracejí s radou.','Příběh');
    }
}

// Dítě zdědí třetinu průměru zkušeností obou rodičů.
function inheritedSkills(array $mother,array $father): array {
    $skills=[];
    foreach (CATEGORIES as $key=>$label) {
        $skills[$key]=round(.3*(skillLevel($mother,$key)+skillLevel($father,$key))/2,1);
    }
    return $skills;
}

// Činnost osoby s přihlédnutím ke zkušenosti (úkol, rutinní práce).
function taskSkillFactor(array $world,string $id,string $key,?array $task=null): float {
    $person=$world['people'][$id]??null;
    return $person===null ? 1.0 : skillFactor($person,actionCategory($world,$key,$task));
}
