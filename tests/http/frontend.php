<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/frameworks.js' || $path === '/devtools-bridge.js') {
    header('Content-Type: text/javascript');
    readfile($path === '/frameworks.js' ? getenv('FIXTURE_BUNDLE') : __DIR__ . '/devtools-bridge.js');
    return;
}
if ($path === '/client.js') {
    header('Content-Type: text/javascript');
    readfile(__DIR__ . '/../../web/ajax-profiler.js');
    return;
}

header('Content-Type: text/html; charset=utf-8');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; connect-src 'self' http://127.0.0.1:8781 http://127.0.0.1:8783 http://127.0.0.1:8784 http://127.0.0.1:1");
echo '<!doctype html><html><head><title>Separate-origin transport fixture</title>';
echo '<script src="/client.js"></script></head><body><h1>Common client transport fixture</h1><div id="app"></div>';
if ($path === '/vue' || $path === '/react') {
    echo '<script src="/devtools-bridge.js"></script><script src="/frameworks.js"></script>';
}
echo '</body></html>';
