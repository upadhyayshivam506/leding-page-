<?php

declare(strict_types=1);

require_once __DIR__ . '/code.php';

header('Content-Type: application/json');
ini_set('display_errors', '0');
error_reporting(E_ALL);

try {
    ensure_api_authenticated();

    $rawBody = file_get_contents('php://input');
    $payload = json_decode(is_string($rawBody) ? $rawBody : '', true);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Invalid JSON payload.');
    }

    $batchId = trim((string) ($payload['batch_id'] ?? ''));
    if ($batchId === '') {
        throw new InvalidArgumentException('Batch ID is required.');
    }

    $result = process_batch_region_push_to_all_colleges($batchId);

    echo json_encode([
        'status' => 'success',
        'data' => $result,
    ]);
} catch (Throwable $e) {
    if ($e instanceof RuntimeException) {
        http_response_code(401);
    } elseif ($e instanceof InvalidArgumentException) {
        http_response_code(422);
    } else {
        http_response_code(500);
    }

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Upload failed',
    ]);
}

exit;
