<?php

declare(strict_types=1);

namespace Models;

use Config\Database;
use PDO;

final class LeadPushLog
{
    public function __construct()
    {
        (new \Services\LeadMappingService())->ensureTables();
    }

    public function all(): array
    {
        $statement = Database::connection()->query(
            'SELECT id, batch_id, lead_id, name, email, phone, course, specialization, campus, college_name, city, state, region, source_file, status, response, attempt_no, created_at
             FROM lead_api_logs
             ORDER BY created_at DESC, id DESC'
        );

        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function paginate(int $limit, int $offset): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, batch_id, lead_id, name, email, phone, course, specialization, campus, college_name, city, state, region, source_file, status, response, attempt_no, created_at
             FROM lead_api_logs
             ORDER BY created_at DESC, id DESC
             LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function findByBatch(string $batchId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, batch_id, lead_id, name, email, phone, course, specialization, campus, college_name, city, state, region, source_file, status, response, attempt_no, created_at
             FROM lead_api_logs
             WHERE batch_id = :batch_id
             ORDER BY created_at DESC, id DESC'
        );
        $statement->execute([
            'batch_id' => $batchId,
        ]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

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
