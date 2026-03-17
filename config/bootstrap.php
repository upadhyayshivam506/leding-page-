<?php

declare(strict_types=1);

session_start();

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Config\\' => __DIR__,
        'Controllers\\' => dirname(__DIR__) . '/controllers',
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

Config\Env::load(dirname(__DIR__) . '/.env');
