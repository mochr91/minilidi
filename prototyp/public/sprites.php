<?php
declare(strict_types=1);

// Seznam obrázků ve složce sprites/ pro mapu: cesta -> čas poslední změny, plus volitelný manifest.json.
// Mapa si seznam každé 4 s načte znovu, takže přepsaný nebo přidaný PNG se ve hře objeví sám.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$root = __DIR__.DIRECTORY_SEPARATOR.'sprites';
$files = [];
foreach (['buildings','houses','trees','props'] as $kind) {
    foreach (glob($root.DIRECTORY_SEPARATOR.$kind.DIRECTORY_SEPARATOR.'*.png') ?: [] as $path) {
        $name = basename($path);
        if (!preg_match('/^[a-z0-9_]+(@\d+)?\.png$/',$name)) continue;
        $files[$kind.'/'.$name] = (int)filemtime($path);
    }
}
ksort($files);
$manifest = new stdClass();
$manifestPath = $root.DIRECTORY_SEPARATOR.'manifest.json';
if (is_file($manifestPath)) {
    $decoded = json_decode((string)file_get_contents($manifestPath));
    if ($decoded instanceof stdClass) $manifest = $decoded;
}
echo json_encode(['files'=>(object)$files,'manifest'=>$manifest],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
