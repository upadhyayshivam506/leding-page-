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
    'GET /lead-push-logs' => [DashboardController::class, 'leadPushLogs'],
    'POST /leads/upload' => [DashboardController::class, 'uploadLeadFile'],
    'GET /leads/mapping' => [DashboardController::class, 'mapping'],
    'GET /leads/mapping/region' => [DashboardController::class, 'mappingRegion'],
    'GET /leads/mapping/region/api-colleagues' => [DashboardController::class, 'mappingApiColleagues'],
    'GET /api-settings' => [DashboardController::class, 'apiSettings'],
    'GET /system-config' => [DashboardController::class, 'systemConfig'],
];
