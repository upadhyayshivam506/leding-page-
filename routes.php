<?php

declare(strict_types=1);

use Controllers\AuthController;
use Controllers\DashboardController;

return [
    'GET /' => [AuthController::class, 'showLogin'],
    'GET /login' => [AuthController::class, 'showLogin'],
    'POST /login' => [AuthController::class, 'login'],
    'POST /logout' => [AuthController::class, 'logout'],
    'GET /dashboard' => [DashboardController::class, 'index'],
    'GET /leads' => [DashboardController::class, 'leads'],
    'POST /leads/upload' => [DashboardController::class, 'uploadLeadFile'],
    'GET /leads/mapping' => [DashboardController::class, 'mapping'],
    'GET /api-settings' => [DashboardController::class, 'apiSettings'],
    'GET /system-config' => [DashboardController::class, 'systemConfig'],
];
