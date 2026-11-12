<?php
declare(strict_types=1);

require_once __DIR__ . '/lifecycle.php';

$port = (int)(getenv('MINILIDI_PORT') ?: 8093);
$url = 'http://127.0.0.1:' . $port . '/';
$data = getenv('MINILIDI_DB') ? dirname(getenv('MINILIDI_DB')) : __DIR__ . '/data';
if (!is_dir($data) && !mkdir($data, 0777, true) && !is_dir($data)) {
    fwrite(STDERR, "Nelze vytvořit složku pro uloženou vesnici.\n");
    exit(1);
}
$log = $data . '/provoz-chyby.log';
$legacyLog = $data . '/provoz.log';
function limitLog(string $file): void {
    clearstatcache(true,$file);
    if (!is_file($file) || filesize($file)<1024*1024) return;
    if (is_file($file.'.1')) unlink($file.'.1');
    rename($file,$file.'.1');
}
$children = [];

function localGet(string $url): ?array {
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT_MS=>400, CURLOPT_TIMEOUT_MS=>1000]);
    $body = curl_exec($curl);
    return is_string($body) && curl_getinfo($curl, CURLINFO_RESPONSE_CODE) === 200 ? json_decode($body, true) : null;
}

function spawn(array $argv, string $log): mixed {
    $process = proc_open($argv, [
        0=>['file','NUL','r'],
        1=>['file',$log,'a'],
        2=>['file',$log,'a'],
    ], $pipes, __DIR__, null, ['bypass_shell'=>true]);
    if (!is_resource($process)) throw new RuntimeException('Proces se nepodařilo spustit: ' . basename($argv[0]));
    return $process;
}

function openBrowser(string $url): void {
    if (getenv('MINILIDI_NO_BROWSER') === '1') return;
    // URL obsahuje pouze pevnou adresu a číselný port.
    pclose(popen('start "" "' . $url . '"', 'r'));
}

function requireFreePort(int $port): void {
    $socket = @stream_socket_server('tcp://127.0.0.1:' . $port);
    if (!$socket) throw new RuntimeException('Místní port ' . $port . ' je obsazený.');
    fclose($socket);
}

$lock = fopen($data . '/spoustec-' . $port . '.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    $running = localGet($url . 'api.php');
    if (!getenv('MINILIDI_STOP_FILE') && !empty($running['running'])) {
        openBrowser($url);
        exit(0);
    }
    fwrite(STDERR, !empty($running['running'])
        ? "Minilidi už běží v jiném spouštěči. Nejdřív jej ukonči.\n"
        : "Minilidi se již spouští.\n");
    exit(1);
}

$stop = false;
if (function_exists('sapi_windows_set_ctrl_handler')) {
    sapi_windows_set_ctrl_handler(function () use (&$stop): void { $stop = true; });
}
$stopFile = getenv('MINILIDI_STOP_FILE');
$readyFile = $stopFile ? $stopFile . '.ready' : null;
$exit = 0;
try {
    requireFreePort($port);
    limitLog($log);
    limitLog($legacyLog);
    $privateOllamaPort = (int)getenv('MINILIDI_PRIVATE_OLLAMA_PORT');
    if ($privateOllamaPort && getenv('MINILIDI_NO_AI') !== '1') {
        requireFreePort($privateOllamaPort);
        $ollama = rtrim((string)getenv('LOCALAPPDATA'), '\\/') . '/Programs/Ollama/ollama.exe';
        if (!is_file($ollama)) throw new RuntimeException('Ollama nebyla nalezena. Hru lze spustit bez AI.');
        putenv('OLLAMA_HOST=127.0.0.1:' . $privateOllamaPort);
        putenv('MINILIDI_AI=ollama');
        putenv('MINILIDI_AI_PORT=' . $privateOllamaPort);
        $children['ollama'] = spawn([$ollama,'serve'], $data.'/ollama-provoz.log');
        $ollamaReady = false;
        for ($attempt = 0; $attempt < 25 && !stopRequested(); ++$attempt) {
            if (!proc_get_status($children['ollama'])['running']) break;
            if (localGet('http://127.0.0.1:' . $privateOllamaPort . '/api/tags') !== null) {
                $ollamaReady = true;
                break;
            }
            usleep(200000);
        }
        if (stopRequested()) return;
        if (!$ollamaReady) throw new RuntimeException('Ollamu pro Minilidi se nepodařilo spustit.');
    } elseif (getenv('MINILIDI_AI') !== 'openai' && !getenv('MINILIDI_AI_PORT') &&
        localGet('http://127.0.0.1:11434/api/tags') === null && getenv('MINILIDI_NO_AI') !== '1') {
        $ollama = rtrim((string)getenv('LOCALAPPDATA'), '\\/') . '/Programs/Ollama/ollama.exe';
        if (is_file($ollama)) $children['ollama'] = spawn([$ollama,'serve'], $data.'/ollama-provoz.log');
    }
    $extensionDir = dirname(PHP_BINARY) . '/ext';
    $php = [PHP_BINARY, '-n', '-d','extension_dir=' . $extensionDir, '-d','extension=pdo_sqlite', '-d','extension=curl'];
    $errors = [...$php,'-d','log_errors=1','-d','display_errors=0','-d','error_log='.$log];
    $children['worker'] = spawn([...$errors, __DIR__ . '/worker.php'], 'NUL');
    $children['web'] = spawn([...$errors,'-S','127.0.0.1:' . $port,'-t',__DIR__ . '/public'], 'NUL');
    for ($attempt = 0; !$stop && !stopRequested() && $attempt < 40; ++$attempt) {
        if (!proc_get_status($children['worker'])['running'] || !proc_get_status($children['web'])['running']) {
            throw new RuntimeException('Server nebo simulace se zastavily.');
        }
        if (!empty(localGet($url . 'api.php')['running'])) break;
        usleep(200000);
    }
    if (!$stop && !stopRequested() && $attempt === 40) throw new RuntimeException('Server neodpovídá.');
    if (!$stop && !stopRequested()) {
        if ($readyFile && file_put_contents($readyFile, $url) === false) {
            throw new RuntimeException('Stav spouštěče se nepodařilo zapsat.');
        }
        echo "Minilidi bezi na " . $url . "\n";
        echo "Pro ukonceni stiskni Ctrl+C. Zavreni prohlizece hru nezastavi.\n";
        openBrowser($url);
    }
    $nextLogCheck = time()+60;
    while (!$stop && !stopRequested()) {
        if (time()>=$nextLogCheck) { limitLog($log); $nextLogCheck=time()+60; }
        if (!proc_get_status($children['worker'])['running'] || !proc_get_status($children['web'])['running']) {
            throw new RuntimeException('Server nebo simulace se zastavily.');
        }
        if ($privateOllamaPort && isset($children['ollama']) && !proc_get_status($children['ollama'])['running']) {
            throw new RuntimeException('Ollama pro Minilidi se zastavila.');
        }
        usleep(500000);
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\nPodrobnosti: " . $log . "\n");
    $exit = 1;
} finally {
    if ($stopFile && isset($children['worker'])) {
        for ($wait = 0; $wait < 30 && proc_get_status($children['worker'])['running']; ++$wait) usleep(100000);
    }
    foreach (array_reverse($children, true) as $name => $process) {
        $status = proc_get_status($process);
        if ($status['running'] && $name === 'ollama') {
            // Ollama spouští model v potomkovi, kterého je také potřeba ukončit.
            $killer = proc_open(['taskkill.exe','/PID',(string)$status['pid'],'/T','/F'],
                [0=>['file','NUL','r'],1=>['file','NUL','a'],2=>['file','NUL','a']], $pipes, __DIR__, null, ['bypass_shell'=>true]);
            if (is_resource($killer)) proc_close($killer);
        }
        if (proc_get_status($process)['running']) proc_terminate($process);
        proc_close($process);
    }
    if ($readyFile && is_file($readyFile)) unlink($readyFile);
    fclose($lock);
}
exit($exit);
