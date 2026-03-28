<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Config\\' => __DIR__,
        'Controllers\\' => dirname(__DIR__) . '/controllers',
        'Models\\' => dirname(__DIR__) . '/models',
        'Services\\' => dirname(__DIR__) . '/services',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . '/' . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_file($file)) {
            require_once $file;
        }
    }
});

require_once __DIR__ . '/helpers.php';
require_once dirname(__DIR__) . '/helpers/region-helper.php';

Config\Env::load(dirname(__DIR__) . '/.env');

$sessionPath = env('SESSION_SAVE_PATH');
if ($sessionPath === null || trim($sessionPath) === '') {
    $sessionPath = dirname(__DIR__) . '/uploads/sessions';
}

if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0775, true);
}

if (is_dir($sessionPath) && is_writable($sessionPath)) {
    session_save_path($sessionPath);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
