<?php
if (function_exists('opcache_get_status')) {
    $s = opcache_get_status();
    echo 'OPcache enabled: ' . ($s['opcache_enabled'] ? 'YES' : 'NO') . PHP_EOL;
    echo 'Cached files: ' . $s['opcache_statistics']['num_cached_scripts'] . PHP_EOL;
} else {
    echo 'OPcache not available';
}
