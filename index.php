<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

$router = require __DIR__ . '/routes.php';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$scriptName = dirname($_SERVER['SCRIPT_NAME'] ?? '') ?: '';
if ($scriptName !== '/' && str_starts_with($path, $scriptName)) {
    $path = substr($path, strlen($scriptName)) ?: '/';
}

$path = '/' . ltrim($path, '/');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$routeKey = $method . ' ' . $path;

if (!isset($router[$routeKey])) {
    http_response_code(404);
    echo 'Page not found.';
    exit;
}

[$controllerClass, $action] = $router[$routeKey];
$controller = new $controllerClass();
$controller->$action();
