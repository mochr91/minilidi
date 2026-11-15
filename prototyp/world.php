<?php
declare(strict_types=1);
require_once __DIR__ . '/weather.php';
require_once __DIR__ . '/jobs.php';
require_once __DIR__ . '/family.php';
require_once __DIR__ . '/faith.php';
require_once __DIR__ . '/social.php';
require_once __DIR__ . '/economy.php';
require_once __DIR__ . '/winter.php';
require_once __DIR__ . '/forest.php';
require_once __DIR__ . '/beer.php';
require_once __DIR__ . '/household.php';
require_once __DIR__ . '/forge.php';
require_once __DIR__ . '/trade.php';
require_once __DIR__ . '/vineyard.php';
require_once __DIR__ . '/crafts.php';
require_once __DIR__ . '/life.php';
require_once __DIR__ . '/crisis.php';
require_once __DIR__ . '/goals.php';
require_once __DIR__ . '/summary.php';
require_once __DIR__ . '/skills.php';
require_once __DIR__ . '/activities.php';

const MEAL_HOURS = [7, 18];
const DAILY_WOOD = 2;
const AUTO_INTERVAL = 360;

const ACTIONS = [
    'manure' => ['label' => 'Vyklidit hnůj', 'place' => 'yard'],
    'market' => ['label' => 'Zajít na trh', 'place' => 'market'],
    'help' => ['label' => 'Pomoct sousedovi', 'place' => 'field'],
    'fieldwork' => ['label' => 'Pracovat na poli', 'place' => 'field', 'work' => 'Pracuje na poli',
        'minutes' => 90, 'fatigue' => 1.8, 'resource' => 'grain', 'yield' => 4.6, 'appeal' => -4],
    'felling' => ['label' => 'Kácet stromy', 'place' => 'forest', 'work' => 'Kácí stromy',
        'minutes' => 90, 'fatigue' => 2.6, 'resource' => 'wood', 'yield' => 5.5, 'appeal' => -12],
    'fishing' => ['label' => 'Chytat ryby', 'place' => 'river', 'work' => 'Chytá ryby',
        'minutes' => 60, 'fatigue' => 1.0, 'resource' => 'food', 'yield' => 3.1, 'appeal' => 2],
    'livestock' => ['label' => 'Obstarat zvířata', 'place' => 'pasture', 'work' => 'Stará se o zvířata',
        'minutes' => 60, 'fatigue' => 1.4, 'resource' => 'food', 'yield' => 3.4, 'appeal' => 0],
    'gathering' => ['label' => 'Nasbírat lesní plody', 'place' => 'forest', 'work' => 'Sbírá lesní plody',
        'minutes' => 60, 'fatigue' => 1.1, 'resource' => 'food', 'yield' => 2.5, 'appeal' => 4],
    'quarrying' => ['label' => 'Těžit kámen', 'place' => 'quarry', 'work' => 'Těží kámen',
        'minutes' => 90, 'fatigue' => 2.8, 'resource' => 'stone', 'yield' => 5.0, 'appeal' => -14],
    'haul_stone' => ['label'=>'Přivézt kámen trakařem','place'=>'quarry','work'=>'Veze kámen trakařem','minutes'=>75,'fatigue'=>1.8,'appeal'=>-2,'group'=>'Doprava a stavba'],
    'build_house' => ['label'=>'Postavit dům','place'=>'building','work'=>'Staví dům','minutes'=>120,'fatigue'=>2.7,'appeal'=>-6,'group'=>'Doprava a stavba'],
    'bring_water' => ['label'=>'Přinést sousedovi vodu','place'=>'well','work'=>'Nabírá vodu u studny','minutes'=>30,'fatigue'=>.5,'appeal'=>5,'group'=>'Sousedé'],
    'fertilize' => ['label'=>'Rozvézt hnůj na pole','place'=>'field','work'=>'Hnojí pole','minutes'=>60,'fatigue'=>1.6,'appeal'=>-10,'group'=>'Práce pro vesnici'],
    'firewood' => ['label'=>'Naštípat palivo','place'=>'yard','work'=>'Štípe palivo','minutes'=>60,'fatigue'=>1.8,'resource'=>'wood','yield'=>1.8,'appeal'=>-4],
    'fix_fence' => ['label'=>'Opravit plot u pastviny','place'=>'pasture','work'=>'Opravuje plot','minutes'=>75,'fatigue'=>1.7,'appeal'=>-3,'group'=>'Doprava a stavba'],
    'deliver_meal' => ['label'=>'Donést sousedovi jídlo','place'=>'home','work'=>'Nese jídlo sousedovi','minutes'=>45,'fatigue'=>.3,'appeal'=>7,'group'=>'Sousedé'],
    'gather_neighbors' => ['label'=>'Uspořádat posezení','place'=>'market','work'=>'Pořádá posezení na návsi','minutes'=>75,'fatigue'=>.6,'appeal'=>9,'group'=>'Sousedé'],
    'withhold_tools' => ['label'=>'Odepřít sousedovi nářadí','place'=>'yard','work'=>'Odkládá nářadí','minutes'=>30,'fatigue'=>.4,'appeal'=>4,'group'=>'Sousedé'],
    'visit' => ['label'=>'Pobýt se sousedy','place'=>'barn','work'=>'Tráví čas se sousedy','minutes'=>90,'fatigue'=>-.9,'group'=>'Sousedé'],
    'prayer' => ['label'=>'Modlit se','place'=>'chapel','work'=>'Účastní se obřadu','minutes'=>60,'fatigue'=>-.5,'group'=>'Víra'],
    'wedding' => ['label'=>'Svatba','place'=>'chapel','work'=>'Má svatbu','minutes'=>90,'fatigue'=>-.5,'group'=>'Víra'],
    'mill' => ['label'=>'Semlít obilí na mouku','place'=>'mill','work'=>'Mele obilí','minutes'=>75,'fatigue'=>1.4,'appeal'=>-3,'group'=>'Ekonomika'],
    'bake' => ['label'=>'Upéct chléb','place'=>'oven','work'=>'Peče chléb','minutes'=>75,'fatigue'=>1.2,'appeal'=>3,'group'=>'Ekonomika'],
    'saw' => ['label'=>'Řezat prkna','place'=>'sawmill','work'=>'Řeže prkna','minutes'=>90,'fatigue'=>2.4,'appeal'=>-8,'group'=>'Ekonomika'],
    'craft' => ['label'=>'Vyrobit nářadí','place'=>'workshop','work'=>'Vyrábí nářadí','minutes'=>90,'fatigue'=>1.6,'appeal'=>-2,'group'=>'Ekonomika'],
    'mow' => ['label'=>'Kosit a sušit seno','place'=>'pasture','work'=>'Kosí seno','minutes'=>90,'fatigue'=>2.2,'appeal'=>-8,'group'=>'Ekonomika'],
    'construct' => ['label'=>'Stavět budovu','place'=>'building','work'=>'Staví budovu','minutes'=>120,'fatigue'=>2.6,'appeal'=>-6,'group'=>'Doprava a stavba'],
    'firefight' => ['label'=>'Hasit požár','place'=>'building','work'=>'Hasí požár','minutes'=>60,'fatigue'=>3.2,'appeal'=>10,'group'=>'Krize'],
    'flood_defense' => ['label'=>'Bránit břeh před povodní','place'=>'river','work'=>'Zpevňuje břeh','minutes'=>60,'fatigue'=>3.0,'appeal'=>10,'group'=>'Krize'],
    'sow_garden' => ['label'=>'Sázet záhony','place'=>'field','work'=>'Sází záhony','minutes'=>90,'fatigue'=>1.6,'appeal'=>-2,'group'=>'Zima a zásoby'],
    'tend_garden' => ['label'=>'Okopat a zalít zahradu','place'=>'field','work'=>'Okopává zahradu','minutes'=>75,'fatigue'=>1.5,'appeal'=>-3,'group'=>'Zima a zásoby'],
    'harvest_garden' => ['label'=>'Sklidit zahradu','place'=>'field','work'=>'Sklízí zahradu','minutes'=>90,'fatigue'=>1.8,'appeal'=>1,'group'=>'Zima a zásoby'],
    'pickle' => ['label'=>'Nakládat zelí do sklepa','place'=>'home','work'=>'Nakládá zelí','minutes'=>75,'fatigue'=>1.0,'appeal'=>2,'group'=>'Zima a zásoby'],
    'gather_herbs' => ['label'=>'Sbírat byliny','place'=>'forest','work'=>'Sbírá byliny','minutes'=>75,'fatigue'=>1.2,'appeal'=>5,'group'=>'Zima a zásoby'],
    'dry_herbs' => ['label'=>'Usušit byliny na zimu','place'=>'home','work'=>'Suší byliny','minutes'=>45,'fatigue'=>.6,'appeal'=>3,'group'=>'Zima a zásoby'],
    'haul_wood' => ['label'=>'Svézt dřevo z lesa','place'=>'forest','work'=>'Veze dřevo z lesa','minutes'=>75,'fatigue'=>1.9,'appeal'=>-2,'group'=>'Doprava a stavba'],
    'build_cart' => ['label'=>'Vyrobit vůz','place'=>'workshop','work'=>'Vyrábí vůz','minutes'=>120,'fatigue'=>2.0,'appeal'=>-2,'group'=>'Doprava a stavba'],
    'load_kiln' => ['label'=>'Naskládat milíř na dřevěné uhlí','place'=>'forest','work'=>'Skládá milíř','minutes'=>120,'fatigue'=>2.6,'appeal'=>-6,'group'=>'Les a lov'],
    'watch_kiln' => ['label'=>'Hlídat milíř','place'=>'forest','work'=>'Hlídá milíř','minutes'=>90,'fatigue'=>.9,'appeal'=>-1,'group'=>'Les a lov'],
    'hunt_small' => ['label'=>'Nastražit léčky na zajíce a bažanty','place'=>'forest','work'=>'Loví drobnou zvěř','minutes'=>90,'fatigue'=>1.4,'appeal'=>5,'group'=>'Les a lov'],
    'smoke_meat' => ['label'=>'Vyudit zvěřinu v udírně','place'=>'yard','work'=>'Udí zvěřinu','minutes'=>90,'fatigue'=>1.0,'appeal'=>2,'group'=>'Zima a zásoby'],
    'tend_hops' => ['label'=>'Uvázat chmel na tyče','place'=>'hops','work'=>'Váže chmel na tyče','minutes'=>75,'fatigue'=>1.4,'appeal'=>0,'group'=>'Pivo'],
    'harvest_hops' => ['label'=>'Česat chmel','place'=>'hops','work'=>'Trhá chmel','minutes'=>90,'fatigue'=>1.5,'appeal'=>2,'group'=>'Pivo'],
    'make_malt' => ['label'=>'Sladovat ječmen','place'=>'maltings','work'=>'Sladuje ječmen','minutes'=>150,'fatigue'=>1.6,'appeal'=>-2,'group'=>'Pivo'],
    'brew_beer' => ['label'=>'Vařit pivo','place'=>'brewery','work'=>'Vaří pivo','minutes'=>180,'fatigue'=>1.8,'appeal'=>4,'group'=>'Pivo'],
    'mine_ore' => ['label'=>'Těžit železnou rudu v dole','place'=>'mine','work'=>'Těží rudu','minutes'=>120,'fatigue'=>2.8,'appeal'=>-12,'group'=>'Kovárna'],
    'smelt_iron' => ['label'=>'Tavit železo','place'=>'forge','work'=>'Taví železo','minutes'=>180,'fatigue'=>2.4,'appeal'=>-6,'group'=>'Kovárna'],
    'forge_tools' => ['label'=>'Kout železné nářadí','place'=>'forge','work'=>'Kuje nářadí','minutes'=>120,'fatigue'=>2.0,'appeal'=>-2,'group'=>'Kovárna'],
    'tend_vines' => ['label'=>'Ošetřit révu na vinici','place'=>'vineyard','work'=>'Ošetřuje révu','minutes'=>75,'fatigue'=>1.5,'appeal'=>1,'group'=>'Víno'],
    'harvest_grapes' => ['label'=>'Česat hrozny','place'=>'vineyard','work'=>'Česá hrozny','minutes'=>90,'fatigue'=>1.6,'appeal'=>4,'group'=>'Víno'],
    'tan_hides' => ['label'=>'Vydělat kůže','place'=>'tannery','work'=>'Činí kůže','minutes'=>150,'fatigue'=>2.0,'appeal'=>-5,'group'=>'Řemesla'],
    'make_boots' => ['label'=>'Šít boty','place'=>'tannery','work'=>'Šije boty','minutes'=>180,'fatigue'=>1.6,'appeal'=>0,'group'=>'Řemesla'],
    'spin_weave' => ['label'=>'Příst a tkát látku','place'=>'weaver','work'=>'Tká látku','minutes'=>200,'fatigue'=>1.5,'appeal'=>1,'group'=>'Řemesla'],
    'make_cheese' => ['label'=>'Sýřit mléko','place'=>'dairy','work'=>'Dělá sýr','minutes'=>120,'fatigue'=>1.4,'appeal'=>2,'group'=>'Řemesla'],
    'harvest_pond' => ['label'=>'Vylovit rybník','place'=>'pond','work'=>'Loví kapry','minutes'=>180,'fatigue'=>2.2,'appeal'=>3,'group'=>'Řemesla'],
    'teach' => ['label'=>'Učit děti u fary','place'=>'parsonage','work'=>'Učí děti','minutes'=>90,'fatigue'=>1.0,'appeal'=>2,'group'=>'Řemesla'],
    'press_wine' => ['label'=>'Lisovat víno','place'=>'press','work'=>'Lisuje víno','minutes'=>120,'fatigue'=>2.0,'appeal'=>3,'group'=>'Víno'],
    'make_barrel' => ['label'=>'Vyrobit sud na víno','place'=>'workshop','work'=>'Dělá sud','minutes'=>240,'fatigue'=>1.8,'appeal'=>-2,'group'=>'Víno'],
    'nurse' => ['label'=>'Ošetřit nemocného bylinami','place'=>'home','work'=>'Ošetřuje nemocného','minutes'=>45,'fatigue'=>.8,'appeal'=>6,'group'=>'Sousedé'],
    'rest' => ['label' => 'Odpočinout si', 'place' => 'home'],
];

function databasePath(string $branch = 'ai'): string {
    $main = getenv('MINILIDI_DB') ?: __DIR__ . '/data/vesnice.sqlite';
    return $branch === 'control' ? $main . '.bez-ai.sqlite' : $main;
}

function db(string $branch = 'ai'): PDO {
    $file = databasePath($branch);
    $db = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA busy_timeout=3000');
    return $db;
}

function init(PDO $db, ?array $initial = null): void {
    $db->exec("PRAGMA journal_mode=WAL;
        CREATE TABLE IF NOT EXISTS village (id INTEGER PRIMARY KEY, state TEXT NOT NULL, heartbeat INTEGER NOT NULL);
        CREATE TABLE IF NOT EXISTS queue (id INTEGER PRIMARY KEY, kind TEXT NOT NULL, person TEXT NOT NULL, value TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS journal (id INTEGER PRIMARY KEY, minute INTEGER NOT NULL, person TEXT NOT NULL,
            title TEXT NOT NULL, detail TEXT NOT NULL, source TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS village_samples (minute INTEGER PRIMARY KEY, values_json TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS memories (person TEXT NOT NULL, minute INTEGER NOT NULL, type TEXT NOT NULL, text TEXT NOT NULL, weight INTEGER NOT NULL);
        CREATE INDEX IF NOT EXISTS memories_person_minute ON memories(person,minute);
        CREATE TABLE IF NOT EXISTS memory_events (person TEXT NOT NULL, minute INTEGER NOT NULL, type TEXT NOT NULL, text TEXT NOT NULL, weight INTEGER NOT NULL);
        CREATE INDEX IF NOT EXISTS memory_events_minute ON memory_events(minute);
        CREATE TABLE IF NOT EXISTS weekly (week INTEGER PRIMARY KEY, data TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS plan_audit (id INTEGER PRIMARY KEY, minute INTEGER NOT NULL, person TEXT NOT NULL,
            model TEXT, rules TEXT NOT NULL, final TEXT NOT NULL, why TEXT NOT NULL, source TEXT NOT NULL);
        CREATE INDEX IF NOT EXISTS plan_audit_minute ON plan_audit(minute);
        CREATE TABLE IF NOT EXISTS ai_metrics (id INTEGER PRIMARY KEY, type TEXT NOT NULL, at INTEGER NOT NULL, elapsed_ms REAL NOT NULL,
            total_ms REAL, load_ms REAL, tokens INTEGER, success INTEGER NOT NULL, fallback INTEGER NOT NULL, detail TEXT NOT NULL)");
    $save = $db->prepare('INSERT OR IGNORE INTO village VALUES (1, ?, 0)');
    $save->execute([encode($initial ?? initialWorld())]);
}

function encode(mixed $value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function initialWorld(): array {
    $people = [
        ['anna', 'Anna Kovářová', 34, 'Kovářovi', 'Rolnice', 'Rozvážná, ochotná pomoci. Nesnáší, když práce padá pořád na ni.', 82, 35, 72, 55, 38, 'field', '#b65242'],
        ['matyas', 'Matyáš Kovář', 15, 'Kovářovi', 'Pomocník', 'Zvědavý a družný. Raději chodí na trh, než uklízí dvůr.', 64, 24, 83, 42, 40, 'yard', '#326b8b'],
        ['jan', 'Jan Doležal', 41, 'Doležalovi', 'Dřevorubec', 'Pracovitý a paličatý. Vážně bere spravedlivé dělení práce.', 89, 62, 55, 35, 25, 'forest', '#7b5b37'],
        ['bara', 'Barbora Doležalová', 39, 'Doležalovi', 'Rybářka', 'Přátelská a samostatná. Má ráda klid u vody.', 77, 30, 77, 48, 34, 'river', '#54704c'],
        ['katerina', 'Kateřina Králová', 64, 'Královi', 'Hospodyně', 'Pamatuje si laskavosti i křivdy. Ráda si popovídá na návsi.', 60, 48, 66, 65, 60, 'market', '#745780'],
    ];
    $villagers = [];
    foreach ($people as [$id,$name,$age,$family,$role,$trait,$skill,$fatigue,$mood,$trust,$faith,$work,$color]) {
        $villagers[$id] = compact('id','name','age','family','role','trait','skill','fatigue','mood','trust','faith','work','color');
        $villagers[$id] += ['hunger'=>24, 'fun'=>62.0, 'fear'=>12, 'task'=>null, 'place'=>'home', 'activity'=>'Doma', 'bonds'=>[], 'grudges'=>[]];
    }
    foreach ($villagers as &$person) {
        foreach ($villagers as $other) {
            if ($other['id'] !== $person['id']) $person['bonds'][$other['id']] = $person['family'] === $other['family'] ? 72 : 48;
        }
    }
    unset($person);
    $world = ['minute'=>360.0,'calendarOriginDay'=>0,'food'=>28.0,'wood'=>18.0,'stone'=>0.0,'quarryStone'=>0.0,'fieldCare'=>0,'fenceUntil'=>0,'homesBuilt'=>[],'newcomers'=>0,'usedToday'=>['food'=>0,'wood'=>0],'people'=>$villagers,'ai'=>['status'=>'Čeká na první rozhodnutí', 'model'=>modelName(), 'detail'=>''], 'socialIndex'=>0];
    addHruskaFamily($world);
    return $world;
}

function aiBackend(): string {
    return getenv('MINILIDI_AI') === 'openai' ? 'openai' : 'ollama';
}

function modelName(): string {
    return getenv('MINILIDI_MODEL') ?: (aiBackend() === 'ollama' ? 'gemma4:e4b' : '');
}

function readWorld(PDO $db): array {
    return decodeWorld($db->query('SELECT state FROM village WHERE id=1')->fetchColumn());
}

function decodeWorld(string $state): array {
    $world = json_decode($state, true, 32, JSON_THROW_ON_ERROR);
    $world += ['stone'=>0.0,'quarryStone'=>0.0,'fieldCare'=>0,'fenceUntil'=>0,'homesBuilt'=>[],'newcomers'=>0];
    $world['calendarOriginDay'] ??= intdiv((int)($world['comparisonStart']??0),MINUTES_PER_DAY);
    unset($world['mode']);
    $world['nextAutoMinute'] ??= (intdiv((int)$world['minute'], AUTO_INTERVAL) + 1) * AUTO_INTERVAL;
    addHruskaFamily($world);
    $world += ['plan'=>[], 'nextSocialMinute'=>(int)$world['minute'],'pairHistory'=>[], 'memoryDay'=>-1,'recentLines'=>[],
        'grace'=>60,'miracleAfter'=>0,'calmUntil'=>0,'protectedUntil'=>0,'religions'=>[],'faithEvents'=>[],'sign'=>null,'arrivedFamilies'=>[]];
    $world += economyDefaults() + ['crisis'=>null,'crisisCooldown'=>0,'graveyard'=>[],'pendingDeaths'=>[],'nextChild'=>0,
        'goals'=>['done'=>[]],'winters'=>0,'winterBad'=>false,'winterChecked'=>0,'lastWeek'=>0,'counters'=>newCounters()];
    $world['counters']+=newCounters();
    $absDay=intdiv((int)$world['minute'],1440);
    foreach ($world['people'] as &$person) {
        $person += ['fun'=>62.0,'grudges'=>[],'faith'=>40,'fear'=>12,'trust'=>50,'lastPrayer'=>-1440,
            'resistUntil'=>0,'illUntil'=>0,'awayUntil'=>0,'charisma'=>match($person['id']) {'karel'=>85,'katerina'=>76,'matyas'=>68,default=>54}];
        $person += lifeDefaults($person,$absDay);
        if (($person['spouse']??null)===null && isset(INITIAL_SPOUSES[$person['id']]) && isset($world['people'][INITIAL_SPOUSES[$person['id']]])) {
            $person['spouse']=INITIAL_SPOUSES[$person['id']];
        }
    }
    unset($person);
    homeMigrate($world);
    return $world;
}

function saveWorld(PDO $db, array $world): void {
    $write = $db->prepare('UPDATE village SET state=?,heartbeat=? WHERE id=1');
    $write->execute([encode($world), time()]);
}

function journal(PDO $db, array $world, string $person, string $title, string $detail, string $source): void {
    $insert = $db->prepare('INSERT INTO journal (minute,person,title,detail,source) VALUES (?,?,?,?,?)');
    $insert->execute([(int)$world['minute'],$person,$title,$detail,$source]);
}

function sampleVillage(PDO $db, array $world): void {
    $people = array_values($world['people']);
    $count = max(1,count($people));
    $average = fn(string $key) => array_sum(array_column($people,$key))/$count;
    $data = ['food'=>round($world['food'],2),'wood'=>round($world['wood'],2),'stone'=>round($world['stone'],2),
        'satiation'=>round(100-$average('hunger'),1),'energy'=>round(100-$average('fatigue'),1),'fun'=>round($average('fun'),1),
        'population'=>count($people),'houses'=>count($world['homesBuilt']),'grain'=>round($world['grain'],1),
        'flour'=>round($world['flour'],1),'planks'=>round($world['planks'],1),'hay'=>round($world['hay'],1),
        'tools'=>round($world['tools'],1),'forestWood'=>round($world['forestWood'],1),'charcoal'=>round($world['charcoal'],1),
        'meatSmoked'=>round($world['meatSmoked'],1),'carts'=>(int)$world['carts'],'veg'=>round($world['veg'],1),'kraut'=>round($world['kraut'],1),
        'legumes'=>round($world['legumes'],1),'herbs'=>round($world['herbs']+$world['herbsDry'],1),'prep'=>round(100*$world['winterPrep']),
        'variety'=>round(100*$world['variety']),'eggs'=>round($world['eggs'],1),'hens'=>round((float)($world['hens']??0),1),
        'ore'=>round($world['ore'],1),'iron'=>round($world['iron'],1),'ironTools'=>round($world['ironTools'],1),'salt'=>round($world['salt'],1),'cloth'=>round($world['cloth'],1),'wine'=>round(wineStock($world),1),'cheese'=>round($world['cheese']??0,1),'boots'=>round($world['boots']??0,1),'leather'=>round($world['leather']??0,1),'flax'=>round($world['flax']??0,1),'milk'=>round($world['milk']??0,1),'carp'=>round(pondFish($world),1),
        'barley'=>round($world['barley'],1),'malt'=>round($world['malt'],1),'hops'=>round($world['hops'],1),'beer'=>round($world['beer']+$world['beerYoung'],1),
        'buildings'=>count($world['buildings']),'score'=>villageScore($world)];
    $insert = $db->prepare('INSERT OR IGNORE INTO village_samples (minute,values_json) VALUES (?,?)');
    $insert->execute([(int)$world['minute'],encode($data)]);
    $trim = $db->prepare('DELETE FROM village_samples WHERE minute < ?');
    $trim->execute([(int)$world['minute'] - 7*1440]);
}

function clamp(float $n): float { return max(0, min(100, $n)); }

function willingness(array $person): float {
    return clamp(55 + $person['mood'] * .40 - $person['fatigue'] * .62 - $person['hunger'] * .22 + ($person['fun'] - 60) * .12);
}

function productivity(array $person): float {
    $health = empty($person['illUntil']) ? 1 : .65;
    $resistance = max(.52,1-max(0,$person['fear']-55)*.011);
    return clamp($health * $resistance * ($person['toolFactor'] ?? 1) * $person['skill'] * (1 - .65 * $person['fatigue'] / 100) * (1 - .45 * $person['hunger'] / 100) * (empty($person['toolPenaltyUntil']) ? 1 : .65));
}

function tick(PDO $db, array &$world): void {
    $now = (int)$world['minute'];
    $calendar = calendarAt($now,$world['calendarOriginDay']);
    $hour = $calendar['hour'];
    $weather = villageWeather($world);
    $daylight = $hour >= $calendar['sunrise'] && $hour < $calendar['sunset'];
    $commonPlace = $weather['precip']==='none' && !$weather['frozen'] && $daylight?'market':'barn';
    $communalTime = ($hour===12 || $hour===18);
    crisisTick($db,$world);
    if ($now % 60 === 0) {
        faithHour($db,$world);
        crisisHour($db,$world);
        hourlySample($world);
    }
    if ($now % 1440 === 0) {
        $woodUsed = min($weather['woodNeed'], $world['wood']);
        $world['wood'] -= $woodUsed;
        // Když dřeva nestačí, zatopí se dřevěným uhlím (jedno uhlí zahřeje jako tři dřeva).
        $coalUsed = min($world['charcoal'], max(0.0,$weather['woodNeed']-$woodUsed)/3);
        $world['charcoal'] -= $coalUsed;
        $world['usedToday'] = ['food'=>0,'wood'=>$woodUsed];
        journal($db, $world, '', 'Začal další den', 'Topení a vaření spotřebovalo ' . number_format($woodUsed,1,',',' ') . ' dřeva z denní potřeby ' . $weather['woodNeed'] . '.', 'Hra');
        lifeDaily($db,$world);
        economyDaily($db,$world);
        goalsDaily($db,$world);
        weeklyClose($db,$world);
    }
    $tavernOpen = tavernOpen($world,$hour);
    $tools = toolFactor($world);
    foreach ($world['people'] as &$p) {
        if (($p['toolPenaltyUntil']??PHP_INT_MAX) <= $now) unset($p['toolPenaltyUntil']);
        if ($p['illUntil'] && $p['illUntil'] <= $now) $p['illUntil'] = 0;
    }
    unset($p);
    foreach ($world['people'] as $id=>&$p) {
        if ($p['awayUntil']>$now) {
            $p['hunger']=clamp($p['hunger']+.8);
            $p['fatigue']=clamp($p['fatigue']-1);
            continue;
        }
        if ($p['awayUntil']!==0) {
            $p['awayUntil']=0;
            journal($db,$world,$id,$p['name'].' se vrací do vsi','Už je znovu mezi sousedy.','Hra');
        }
        if ($p['task'] && $now >= $p['task']['end']) {
            $action = activityDefinition($world,$p['task']['action'],$p['task']);
            $detail = trim(($action['label']??'Ukončená činnost') . '. ' . completeWork($db,$world,$id));
            journal($db,$world,$p['id'],$p['name'].' dokončuje úkol',$detail,'Hra');
            if (isset($action['resource']) || in_array($p['task']['action'],['build_house','deliver_meal','withhold_tools','construct'],true)) {
                rememberEvent($db,$world,$id,'práce',$detail,in_array($p['task']['action'],['build_house','construct'],true)?5:1);
            }
            countTask($world,$p['task']['action']);
            $p['task'] = null;
            crisisChain($world,$id);
        }
        $p['toolFactor'] = $tools;
        $cost = mealCost($p);
        if ($cost > 0) {
            $p['hunger'] = clamp($p['hunger'] + .8);
            if (in_array($hour, MEAL_HOURS, true) && $now % 60 === 0) {
                if (drawFood($world,$cost)) {
                    if (isset($world['usedToday'])) $world['usedToday']['food'] += $cost;
                    $world['counters']['eaten'] += $cost;
                    $p['hunger'] = clamp($p['hunger'] - 42);
                } else {
                    ++$world['counters']['hungryMeals'];
                    ++$world['counters']['hungryMealsToday'];
                }
            }
        } else {
            $p['hunger'] = min($p['hunger'],12);
            $p['place'] = 'home';
            $p['activity'] = 'Spí v kolébce';
            $p['fatigue'] = 10;
            $p['mood'] = max($p['mood'],85);
            $p['fun'] = 100.0;
            continue;
        }
        $funChange = 0.0;
        if ($p['task'] && $now >= $p['task']['start']) {
            $action = activityDefinition($world,$p['task']['action'],$p['task']);
            $shelteredLivestock = $p['task']['action']==='livestock' && ($weather['frozen']||$weather['ground']==='snow');
            $p['place'] = match ($p['task']['action']) {
                'visit'=>$tavernOpen && $p['age']>=18 ? 'tavern' : $commonPlace,
                'prayer'=>doctrineFor($world,$id)==='tree'?'barn':'chapel',
                default=>$shelteredLivestock?'barn':($action['place']??'home'),
            };
            $p['activity'] = $shelteredLivestock?'Krmí zvířata v chlévě':($action['work'] ?? $action['label'] ?? 'Odpočívá doma');
            $baseFatigue = $action['fatigue'] ?? (($action['place']??'home') === 'home' ? -4 : 1.25);
            // Zkušený v domácnosti si odpočine líp (jen při zotavování).
            if ($baseFatigue < 0 && actionCategory($world,(string)$p['task']['action'],$p['task']) === 'home') $baseFatigue *= softSkill($p,'home');
            $p['fatigue'] = clamp($p['fatigue'] + weatherFatigue($weather,$p['place'],$baseFatigue));
            $funChange = match ($p['task']['action']) {
                'market'=>2.2, 'visit'=>4.3, 'prayer'=>2.4, 'wedding'=>3.2, 'rest'=>.65, 'fishing'=>.25, default=>-.65,
            };
            if ($p['task']['action']==='visit') $p['mood']=clamp($p['mood']+2.6*softSkill($p,'home'));
            if ($p['task']['action'] === 'help') $world['food'] += productivity($p) / 270 * skillFactor($p,'neighbors');
        } elseif ($hour < max(5,$calendar['sunrise']-1) || $hour >= min(23,$calendar['sunset']+3)) {
            $p['place'] = 'home';
            $p['activity'] = 'Spí';
            $p['fatigue'] = clamp($p['fatigue'] - 2.7);
        } elseif ($tavernOpen && $p['age']>=18 && willingness($p)>=42) {
            $p['place'] = 'tavern';
            $p['activity'] = 'Povídá si v hospodě';
            $p['fatigue'] = clamp($p['fatigue'] - 1.2);
            $funChange = 2.6;
        } elseif ($communalTime) {
            $p['place'] = $commonPlace;
            $p['activity'] = 'Tráví přestávku se sousedy';
            $p['fatigue'] = clamp($p['fatigue']-1.2);
            $funChange = 2.1;
        } elseif ($p['age'] < 12) {
            $outside = $daylight && !$weather['frozen'] && $weather['precip']==='none';
            $p['place'] = $outside?'yard':'home';
            $p['activity'] = $outside?'Hraje si na dvoře':'Hraje si doma';
            $p['fatigue'] = clamp($p['fatigue'] + ($outside?.25:-.5));
            $funChange = 1.1;
            if ($p['mood']<25) $p['mood'] = clamp($p['mood']+.9);
        } elseif ($daylight && (($hour>=7 && $hour<12)||($hour>=13 && $hour<18)) && !($weather['heat'] && $hour>=11 && $hour<15)) {
            $routine = match ($p['work']) {'field'=>'fieldwork','river'=>'fishing',default=>null};
            $blocked = $routine !== null && !weatherAllows($world,$routine);
            $p['place'] = $blocked?'yard':$p['work'];
            $p['activity'] = $blocked?'Pracuje pod střechou':['field'=>'Pracuje na poli','forest'=>'Sbírá dřevo','river'=>'Rybaří','yard'=>'Pomáhá na dvoře','market'=>'Hospodaří na návsi'][$p['work']];
            $routineCategory = workCategory((string)$p['work']);
            $gain = productivity($p) / 100 * ($routine===null?1:weatherYield($world,$routine)) * skillFactor($p,$routineCategory);
            if (!$blocked) trainSkill($db,$world,$id,$routineCategory,15);
            if ($p['work'] === 'forest') $world['forestWood'] += $gain * .27;
            if (!$blocked && $p['work'] === 'field') {
                addFieldGrain($world,$gain * .30);
            } elseif (!$blocked && in_array($p['work'], ['river','yard'], true)) {
                $world['food'] += $gain * .30;
                countProduced($world,'food',$gain * .30);
            }
            $p['fatigue'] = clamp($p['fatigue'] + weatherFatigue($weather,$p['place'],1.3));
            $funChange = -.55;
        } else {
            $p['place'] = $daylight && $hour>=16 && $hour<20 && $weather['precip']==='none' && willingness($p)>=42?'market':'home';
            $p['activity'] = $p['place'] === 'market' ? 'Povídá si na návsi' : 'Odpočívá doma';
            $p['fatigue'] = clamp($p['fatigue'] - 1.2);
            $funChange = $p['place'] === 'market' ? 1.7 : .25;
        }
        if ($p['place']==='tavern') {
            // Pivo z pivovaru: kdo se napije, má plný účinek hospody, bez piva jen 40 %.
            $funChange *= $funChange>0 ? tavernServe($world,$p,$now) : 1.0;
            if (($p['task']['activity']['effect']??null)!=='games') $p['activity']='Povídá si v hospodě';
        }
        $p['fun'] = clamp($p['fun'] + $funChange);
        $faithSupport = max(0,$p['faith']-50)*.007;
        $fearBurden = max(0,$p['fear']-60)*.011;
        $p['mood'] = clamp($p['mood'] + $faithSupport - $fearBurden + ($p['fatigue'] > 60 ? -.55 : .13) - ($p['hunger'] > 62 ? .7 : 0)
            + ($p['fun'] < 30 ? -.3 : ($p['fun'] > 75 ? .2 : 0)));
    }
    unset($p);
    flushDeaths($db,$world);
    if ($now % 60 === 0) {
        welcomeNewcomer($db,$world);
        welcomeMillerFamily($db,$world);
        weddingHour($db,$world);
        sampleVillage($db,$world);
    }
}

function advance(PDO $db, array &$world, float $gameMinutes): void {
    $target = $world['minute'] + $gameMinutes;
    for ($next = (floor($world['minute'] / 15) + 1) * 15; $next <= $target; $next += 15) {
        $world['minute'] = $next;
        tick($db, $world);
    }
    $world['minute'] = $target;
}
