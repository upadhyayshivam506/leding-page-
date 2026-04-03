<?php

declare(strict_types=1);

use Controllers\AuthController;
use Controllers\DashboardController;

return [
    'GET /' => [AuthController::class, 'showLogin'],
    'GET /login' => [AuthController::class, 'showLogin'],
    'POST /login' => [AuthController::class, 'login'],
    'POST /logout' => [AuthController::class, 'logout'],
    'POST /system-config/change-password' => [AuthController::class, 'changePassword'],
    'GET /dashboard' => [DashboardController::class, 'index'],
    'GET /api/dashboard/upload-history' => [DashboardController::class, 'dashboardUploadHistory'],
    'GET /dashboard/uploads/view' => [DashboardController::class, 'viewUploadedFile'],
    'GET /dashboard/uploads/retry' => [DashboardController::class, 'retryUploadedFilePush'],
    'GET /dashboard/uploads/download-log' => [DashboardController::class, 'downloadUploadedFileLog'],
    'GET /leads' => [DashboardController::class, 'leads'],
    'GET /api/leads' => [DashboardController::class, 'leadsApi'],
    'GET /api/leads/export' => [DashboardController::class, 'exportLeadsCsv'],
    'POST /api/leads/send-to-college' => [DashboardController::class, 'sendSelectedLeadsToCollege'],
    'GET /api/lead-push-job-status' => [DashboardController::class, 'leadPushJobStatus'],
    'GET /lead-push-logs' => [DashboardController::class, 'leadPushLogs'],
    'POST /leads/upload' => [DashboardController::class, 'uploadLeadFile'],
    'GET /leads/mapping' => [DashboardController::class, 'mapping'],
    'GET /leads/mapping/region' => [DashboardController::class, 'mappingRegion'],
    'GET /leads/mapping/region/courses-convert' => [DashboardController::class, 'mappingRegionCoursesConvert'],
    'POST /leads/mapping/region/courses-convert/confirm.php' => [DashboardController::class, 'confirmCoursesConvert'],
    'GET /leads/mapping/region/courses-convert/api-duration' => [DashboardController::class, 'mappingRegionCoursesConvertApiDuration'],
    'POST /leads/mapping/region/courses-convert/send.php' => [DashboardController::class, 'sendCoursesConvertLeads'],
    'POST /leads/mapping/confirm-assignments.php' => [DashboardController::class, 'confirmRegionAssignments'],
    'GET /leads/mapping/api-duration' => [DashboardController::class, 'mappingApiDuration'],
    'POST /leads/mapping/save-duration-settings.php' => [DashboardController::class, 'saveDurationSettings'],
    'GET /leads/mapping/region/api-colleagues' => [DashboardController::class, 'mappingApiColleagues'],
    'GET /system-config' => [DashboardController::class, 'systemConfig'],
];
