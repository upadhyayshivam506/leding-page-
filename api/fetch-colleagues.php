<?php

declare(strict_types=1);

require_once __DIR__ . '/code.php';

header('Content-Type: application/json');

try {
    ensure_api_authenticated();

    echo json_encode([
        'success' => true,
        'data' => all_colleagues(),
    ]);
} catch (RuntimeException $exception) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $throwable->getMessage() !== '' ? $throwable->getMessage() : 'Unable to fetch colleagues.',
    ]);
}
