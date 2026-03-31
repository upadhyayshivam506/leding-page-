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
    'GET /leads/mapping/region/courses-mapping' => [DashboardController::class, 'mappingRegionCoursesMapping'],
    'POST /leads/mapping/generate-preview.php' => [DashboardController::class, 'generateCourseMappingPreview'],
    'POST /leads/mapping/confirm-mapping.php' => [DashboardController::class, 'confirmCourseMapping'],
    'GET /leads/mapping/mapping-courses-specialization' => [DashboardController::class, 'mappingCoursesSpecialization'],
    'POST /leads/mapping/confirm-assignments.php' => [DashboardController::class, 'confirmRegionAssignments'],
    'GET /leads/mapping/api-duration' => [DashboardController::class, 'mappingApiDuration'],
    'POST /leads/mapping/save-duration-settings.php' => [DashboardController::class, 'saveDurationSettings'],
    'GET /leads/mapping/region/api-colleagues' => [DashboardController::class, 'mappingApiColleagues'],
    'GET /system-config' => [DashboardController::class, 'systemConfig'],
];
