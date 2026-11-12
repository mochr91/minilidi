<?php
declare(strict_types=1);

function stopRequested(): bool {
    $path = getenv('MINILIDI_STOP_FILE');
    if (!$path) return false;
    clearstatcache(true, $path);
    return is_file($path);
}
