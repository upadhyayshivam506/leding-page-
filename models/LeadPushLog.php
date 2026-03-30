<?php

declare(strict_types=1);

namespace Models;

use Config\Database;

final class LeadPushLog
{
    public function __construct()
    {
        (new \Services\LeadMappingService())->ensureTables();
    }

    public function all(): array
    {
        $statement = Database::connection()->query(
            'SELECT id, lead_id, region, college_name, status, response, created_at
             FROM lead_api_logs
             ORDER BY created_at DESC, id DESC'
        );

        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function countAll(): int
    {
        $statement = Database::connection()->query('SELECT COUNT(*) FROM lead_api_logs');

        return (int) $statement->fetchColumn();
    }

    public function countByStatus(string $status): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM lead_api_logs WHERE status = :status'
        );
        $statement->execute([
            'status' => trim($status),
        ]);

        return (int) $statement->fetchColumn();
    }

    public function countDistinctColleges(): int
    {
        $statement = Database::connection()->query('SELECT COUNT(DISTINCT college_name) FROM lead_api_logs');

        return (int) $statement->fetchColumn();
    }
}
