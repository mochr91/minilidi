<?php
declare(strict_types=1);

// Ověří balíček činností před uložením do běžící hry.
// Použití: php balicky\over.php [cesta k cinnosti.json]
// Hra kontroluje soubor sama každých 5 minut; vadný soubor ignoruje a ponechá poslední funkční katalog.
require __DIR__.'/../activities.php';

$path = $argv[1] ?? __DIR__.'/cinnosti.json';
try {
    $actions = readActivityPack($path);
} catch (RuntimeException $error) {
    fwrite(STDERR, 'CHYBA: '.$error->getMessage()."\n");
    exit(1);
}
echo 'OK: '.count($actions)." činností\n";
foreach ($actions as $key => $def) {
    printf("  %-22s %-38s %-10s %3d min, %2d až %2d h, od %d let, %s\n", $key, $def['label'], $def['effect'],
        $def['minutes'], $def['from'], $def['until'], $def['age'], $def['season']);
}
