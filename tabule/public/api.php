<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(int $code, array $value): never {
    http_response_code($code);
    echo json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    respond(405, ['error' => 'Tato operace není dostupná.']);
}
if ($method === 'POST') {
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
        respond(415, ['error' => 'Očekávám data ve formátu JSON.']);
    }
    try {
        $incoming = json_decode(file_get_contents('php://input', false, null, 0, 50000), true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        respond(400, ['error' => 'Formulář neobsahuje platná data.']);
    }
    if (!is_array($incoming)) respond(400, ['error' => 'Formulář neobsahuje platná data.']);
    foreach (['title', 'body', 'category', 'status', 'priority'] as $key) {
        if (!is_string($incoming[$key] ?? null)) respond(400, ['error' => 'Chybí pole formuláře.']);
        $note[$key] = trim($incoming[$key]);
    }
    $id = $incoming['id'] ?? null;
    if (($id !== null && (!is_string($id) || !preg_match('/^[a-z0-9-]{1,50}$/D', $id))) ||
        !preg_match('/^.{1,120}$/usD', $note['title']) ||
        !preg_match('/^.{0,6000}$/usD', $note['body']) ||
        !in_array($note['category'], ['concept', 'gameplay', 'world', 'ai', 'ui', 'tech', 'parking'], true) ||
        !in_array($note['status'], ['idea', 'discuss', 'agreed', 'later'], true) ||
        !in_array($note['priority'], ['none', 'high', 'medium', 'low'], true)) {
        respond(422, ['error' => 'Zkontroluj název, popis a zvolené hodnoty.']);
    }
}

$path = getenv('MINILIDI_BOARD_DATA') ?: dirname(__DIR__) . '/data/napady.json';
$lock = null;
$tmp = null;
$code = 200;
try {
    $lock = fopen($path . '.lock', 'c');
    if (!$lock || !flock($lock, $method === 'POST' ? LOCK_EX : LOCK_SH)) throw new RuntimeException('Lock unavailable');
    $raw = file_get_contents($path);
    if ($raw === false) throw new RuntimeException('Read failed');
    $result = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($result) || !array_is_list($result)) throw new RuntimeException('Invalid store');
    if ($method === 'POST') {
        if ($id === null) {
            array_unshift($result, ['id' => bin2hex(random_bytes(12))] + $note);
        } else {
            $index = array_search($id, array_column($result, 'id'), true);
            if ($index === false) {
                $code = 404;
                $result = ['error' => 'Tento nápad už v tabuli není.'];
            } else {
                $result[$index] = ['id' => $id] + $note;
            }
        }
        if ($code === 200) {
            $tmp = $path . '.tmp-' . bin2hex(random_bytes(6));
            $encoded = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
            if (file_put_contents($tmp, $encoded) !== strlen($encoded) || !rename($tmp, $path)) {
                throw new RuntimeException('Write failed');
            }
        }
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    $code = 500;
    $result = ['error' => 'Soubor s nápady se nepodařilo načíst nebo uložit.'];
} finally {
    if ($tmp !== null && is_file($tmp)) unlink($tmp);
    if (is_resource($lock)) fclose($lock);
}
respond($code, $result);
