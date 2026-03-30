<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use Services\LeadMappingService;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$jobToken = isset($argv[1]) ? trim((string) $argv[1]) : '';
if ($jobToken === '') {
    exit(1);
}

ignore_user_abort(true);
set_time_limit(0);

try {
    (new LeadMappingService())->runQueuedJob($jobToken);
} catch (Throwable $throwable) {
    error_log('Background lead mapping job failed: ' . $throwable->getMessage());
    exit(1);
}

exit(0);
