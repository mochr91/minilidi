<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Prague');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

const CATEGORIES = ['concept', 'gameplay', 'world', 'ai', 'ui', 'tech', 'parking'];
const STATUSES = ['idea', 'discuss', 'agreed', 'later', 'todo', 'doing', 'blocked', 'done'];
const PRIORITIES = ['none', 'high', 'medium', 'low'];
const KINDS = ['task', 'think', 'idea', 'decision'];
const ASSIGNEES = ['claude', 'codex', 'user', 'none'];
const ASSIGNEE_NAMES = ['claude' => 'CLAUDE', 'codex' => 'CODEX', 'user' => 'JÁ (uživatel)', 'none' => 'bez řešitele'];
const STATUS_NAMES = ['todo' => 'k zpracování', 'doing' => 'rozpracováno', 'blocked' => 'čeká', 'done' => 'hotovo',
    'idea' => 'nápad', 'discuss' => 'k diskusi', 'agreed' => 'dohodnuto', 'later' => 'odloženo'];

function respond(int $code, array $value): never {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function textOf(mixed $value, int $max, int $min = 0): ?string {
    if (!is_string($value)) return null;
    $value = trim($value);
    return preg_match('/^.{' . $min . ',' . $max . '}$/usD', $value) === 1 ? $value : null;
}

// Vrátí očištěnou hodnotu pole, nebo null, když je neplatná.
function cleanField(string $key, mixed $value): mixed {
    return match ($key) {
        'title' => textOf($value, 120, 1),
        'body' => textOf($value, 6000),
        'accept' => textOf($value, 2000),
        'result' => textOf($value, 3000),
        'block' => textOf($value, 80),
        'category' => in_array($value, CATEGORIES, true) ? $value : null,
        'status' => in_array($value, STATUSES, true) ? $value : null,
        'priority' => in_array($value, PRIORITIES, true) ? $value : null,
        'kind' => in_array($value, KINDS, true) ? $value : null,
        'assignee' => in_array($value, ASSIGNEES, true) ? $value : null,
        'order' => (is_int($value) || (is_string($value) && preg_match('/^\d{1,3}$/D', $value))) && (int)$value >= 0 && (int)$value <= 999 ? (int)$value : null,
        default => null,
    };
}

function defaultKind(string $status): string {
    return match ($status) {'agreed' => 'decision', 'idea', 'later' => 'idea', 'discuss' => 'think', default => 'task'};
}

function normalize(array $item): array {
    $item += ['category' => 'concept', 'status' => 'idea', 'priority' => 'none', 'title' => '', 'body' => ''];
    $item['kind'] ??= defaultKind((string)$item['status']);
    return $item + ['assignee' => 'none', 'block' => '', 'order' => 0, 'accept' => '', 'result' => '', 'updated' => ''];
}

function markdownFor(array $items, ?string $assignee, ?string $kind): string {
    $list = array_values(array_filter($items, fn($i) =>
        ($assignee === null || $i['assignee'] === $assignee) && ($kind === null || $i['kind'] === $kind) &&
        in_array($i['kind'], ['task', 'think'], true) && $i['status'] !== 'done'));
    usort($list, fn($a, $b) => [$a['kind'] === 'task' ? 0 : 1, $a['block'], $a['order']] <=> [$b['kind'] === 'task' ? 0 : 1, $b['block'], $b['order']]);
    $who = $assignee === null ? 'všechny řešitele' : ASSIGNEE_NAMES[$assignee];
    $out = "# Zadání z tabule Minilidí: " . $who . "\n\n";
    $out .= "Stav k " . date('j. n. Y H:i') . ". Před prací přečti AGENTS.md a KOORDINACE.md. Po dokončení zapiš výsledek do KOORDINACE.md (bod 5) a přepni kartu:\n";
    $out .= "`POST http://127.0.0.1:8091/api.php` s `{\"id\":\"<id>\",\"patch\":{\"status\":\"done\",\"result\":\"co se změnilo a jak je to ověřené\"}}`.\n\n";
    if (!$list) return $out . "Žádný otevřený úkol.\n";
    $block = null;
    foreach ($list as $i) {
        $heading = $i['kind'] === 'task' ? ($i['block'] !== '' ? $i['block'] : 'Bez bloku') : 'K promyšlení';
        if ($heading !== $block) { $block = $heading; $out .= "## " . $heading . "\n\n"; }
        $out .= "### " . $i['title'] . "\n";
        $out .= "- id: `" . $i['id'] . "` · řešitel: " . ASSIGNEE_NAMES[$i['assignee']] . " · stav: " . STATUS_NAMES[$i['status']] .
            ($i['priority'] !== 'none' ? " · priorita: " . $i['priority'] : '') . "\n\n";
        if ($i['body'] !== '') $out .= $i['body'] . "\n\n";
        if ($i['accept'] !== '') $out .= "**Hotovo, když:** " . $i['accept'] . "\n\n";
        if ($i['result'] !== '') $out .= "**Dosavadní výsledek:** " . $i['result'] . "\n\n";
    }
    return $out;
}

$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    respond(405, ['error' => 'Tato operace není dostupná.']);
}

$mode = null;      // 'create', 'replace' nebo 'patch'
$incoming = [];
$id = null;
$clean = [];
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
    $id = $incoming['id'] ?? null;
    if ($id !== null && (!is_string($id) || !preg_match('/^[a-z0-9-]{1,50}$/D', $id))) respond(422, ['error' => 'Neplatné ID karty.']);
    if (is_array($incoming['patch'] ?? null)) {
        if ($id === null) respond(400, ['error' => 'Úprava vyžaduje ID karty.']);
        $mode = 'patch';
        $source = $incoming['patch'];
        if (!$source) respond(422, ['error' => 'Úprava je prázdná.']);
    } else {
        $mode = $id === null ? 'create' : 'replace';
        $source = $incoming;
        foreach (['title', 'body', 'category', 'status', 'priority'] as $key) {
            if (!is_string($incoming[$key] ?? null)) respond(400, ['error' => 'Chybí pole formuláře.']);
        }
    }
    foreach ($source as $key => $value) {
        if ($key === 'id' || $key === 'patch' || $key === 'updated') continue;
        $cleaned = cleanField((string)$key, $value);
        if ($cleaned === null) respond(422, ['error' => 'Zkontroluj pole „' . $key . '“ a zvolené hodnoty.']);
        $clean[$key] = $cleaned;
    }
    if ($mode !== 'patch') {
        foreach (['title', 'body', 'category', 'status', 'priority'] as $key) {
            if (!array_key_exists($key, $clean)) respond(422, ['error' => 'Zkontroluj název, popis a zvolené hodnoty.']);
        }
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
        $stamp = date('c');
        if ($mode === 'create') {
            array_unshift($result, normalize(['id' => bin2hex(random_bytes(12))] + $clean) + ['updated' => $stamp]);
            $result[0]['updated'] = $stamp;
        } else {
            $index = array_search($id, array_column($result, 'id'), true);
            if ($index === false) {
                $code = 404;
                $result = ['error' => 'Tento nápad už v tabuli není.'];
            } else {
                $old = normalize($result[$index]);
                $merged = array_replace($old, $clean);
                $merged['updated'] = $stamp;
                $result[$index] = ['id' => $id] + $merged;
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
if ($code === 200 && array_is_list($result)) $result = array_map('normalize', $result);
if ($method === 'GET' && ($_GET['format'] ?? '') === 'md' && $code === 200) {
    $assignee = isset($_GET['assignee']) && in_array($_GET['assignee'], ASSIGNEES, true) ? $_GET['assignee'] : null;
    $kind = isset($_GET['kind']) && in_array($_GET['kind'], KINDS, true) ? $_GET['kind'] : null;
    header('Content-Type: text/markdown; charset=utf-8');
    echo markdownFor($result, $assignee, $kind);
    exit;
}
respond($code, $result);
