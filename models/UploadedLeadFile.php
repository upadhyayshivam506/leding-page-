<?php

declare(strict_types=1);

namespace Models;

use Config\Database;
use PDO;
use PDOException;

final class UploadedLeadFile
{
    private static bool $schemaEnsured = false;

    public function __construct()
    {
        $this->ensureSchema();
        $this->backfillFromLeads();
    }

    public function create(string $batchId, string $fileName, string $storedName, int $totalLeads, int $fileSize = 0, string $status = 'Uploaded'): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO uploaded_lead_files (
                batch_id, file_name, stored_name, total_leads, file_size, status, upload_date
             ) VALUES (
                :batch_id, :file_name, :stored_name, :total_leads, :file_size, :status, NOW()
             )
             ON DUPLICATE KEY UPDATE
                file_name = VALUES(file_name),
                stored_name = VALUES(stored_name),
                total_leads = VALUES(total_leads),
                file_size = VALUES(file_size),
                status = VALUES(status),
                updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'batch_id' => $batchId,
            'file_name' => $fileName,
            'stored_name' => $storedName,
            'total_leads' => max(0, $totalLeads),
            'file_size' => max(0, $fileSize),
            'status' => $this->normalizeStatus($status),
        ]);
    }

    public function updateStatus(
        string $batchId,
        string $status,
        ?string $jobToken = null,
        ?int $processedRequests = null,
        ?int $successCount = null,
        ?int $failedCount = null
    ): void {
        $statement = Database::connection()->prepare(
            'UPDATE uploaded_lead_files
             SET status = :status,
                 last_job_token = COALESCE(:last_job_token, last_job_token),
                 processed_requests = COALESCE(:processed_requests, processed_requests),
                 success_count = COALESCE(:success_count, success_count),
                 failed_count = COALESCE(:failed_count, failed_count),
                 updated_at = CURRENT_TIMESTAMP
             WHERE batch_id = :batch_id'
        );
        $statement->execute([
            'batch_id' => $batchId,
            'status' => $this->normalizeStatus($status),
            'last_job_token' => $jobToken !== null && trim($jobToken) !== '' ? trim($jobToken) : null,
            'processed_requests' => $processedRequests,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
        ]);
    }

    public function findByBatch(string $batchId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM uploaded_lead_files
             WHERE batch_id = :batch_id
             LIMIT 1'
        );
        $statement->execute([
            'batch_id' => $batchId,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function latest(int $limit = 5): array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM uploaded_lead_files
             ORDER BY upload_date DESC, id DESC
             LIMIT :limit'
        );
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function countAll(): int
    {
        $statement = Database::connection()->query('SELECT COUNT(*) FROM uploaded_lead_files');

        return (int) $statement->fetchColumn();
    }

    public function countByStatus(string $status): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM uploaded_lead_files
             WHERE status = :status'
        );
        $statement->execute([
            'status' => $this->normalizeStatus($status),
        ]);

        return (int) $statement->fetchColumn();
    }

    public function countsByStatus(): array
    {
        $statuses = ['Uploaded', 'Processing', 'Completed', 'Failed', 'Partial'];
        $counts = array_fill_keys($statuses, 0);
        $statement = Database::connection()->query(
            'SELECT status, COUNT(*) AS total
             FROM uploaded_lead_files
             GROUP BY status'
        );

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = $this->normalizeStatus((string) ($row['status'] ?? 'Uploaded'));
            $counts[$status] = (int) ($row['total'] ?? 0);
        }

        return $counts;
    }

    public function uploadCountsByDay(int $days = 7): array
    {
        $days = max(1, $days);
        $startDate = (new \DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days');
        $statement = Database::connection()->prepare(
            'SELECT DATE(upload_date) AS upload_day, COUNT(*) AS total
             FROM uploaded_lead_files
             WHERE upload_date >= :start_date
             GROUP BY DATE(upload_date)
             ORDER BY upload_day ASC'
        );
        $statement->execute([
            'start_date' => $startDate->format('Y-m-d 00:00:00'),
        ]);

        $indexed = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $indexed[(string) ($row['upload_day'] ?? '')] = (int) ($row['total'] ?? 0);
        }

        $series = [];
        for ($offset = 0; $offset < $days; $offset++) {
            $date = $startDate->modify('+' . $offset . ' days');
            $key = $date->format('Y-m-d');
            $series[] = [
                'date' => $key,
                'label' => $date->format('D'),
                'total' => $indexed[$key] ?? 0,
            ];
        }

        return $series;
    }

    private function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        $connection = Database::connection();
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS uploaded_lead_files (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                batch_id VARCHAR(100) NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                stored_name VARCHAR(255) DEFAULT NULL,
                total_leads INT NOT NULL DEFAULT 0,
                file_size BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                status VARCHAR(50) NOT NULL DEFAULT "Uploaded",
                last_job_token VARCHAR(120) DEFAULT NULL,
                processed_requests INT NOT NULL DEFAULT 0,
                success_count INT NOT NULL DEFAULT 0,
                failed_count INT NOT NULL DEFAULT 0,
                upload_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uploaded_lead_files_batch_id_unique (batch_id),
                KEY uploaded_lead_files_status_index (status),
                KEY uploaded_lead_files_upload_date_index (upload_date),
                KEY uploaded_lead_files_last_job_token_index (last_job_token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        try {
            $columns = $connection->query('SHOW COLUMNS FROM uploaded_lead_files')->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            self::$schemaEnsured = true;

            return;
        }

        $existing = [];
        foreach (is_array($columns) ? $columns : [] as $column) {
            $name = (string) ($column['Field'] ?? '');
            if ($name !== '') {
                $existing[$name] = true;
            }
        }

        $definitions = [
            'last_job_token' => 'ALTER TABLE uploaded_lead_files ADD COLUMN last_job_token VARCHAR(120) DEFAULT NULL AFTER status',
            'file_size' => 'ALTER TABLE uploaded_lead_files ADD COLUMN file_size BIGINT(20) UNSIGNED NOT NULL DEFAULT 0 AFTER total_leads',
            'processed_requests' => 'ALTER TABLE uploaded_lead_files ADD COLUMN processed_requests INT NOT NULL DEFAULT 0 AFTER last_job_token',
            'success_count' => 'ALTER TABLE uploaded_lead_files ADD COLUMN success_count INT NOT NULL DEFAULT 0 AFTER processed_requests',
            'failed_count' => 'ALTER TABLE uploaded_lead_files ADD COLUMN failed_count INT NOT NULL DEFAULT 0 AFTER success_count',
            'upload_date' => 'ALTER TABLE uploaded_lead_files ADD COLUMN upload_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER failed_count',
        ];

        foreach ($definitions as $column => $sql) {
            if (isset($existing[$column])) {
                continue;
            }

            try {
                $connection->exec($sql);
            } catch (PDOException) {
                // Best effort migration for existing installs.
            }
        }

        self::$schemaEnsured = true;
    }

    private function backfillFromLeads(): void
    {
        try {
            Database::connection()->exec(
                'INSERT INTO uploaded_lead_files (
                    batch_id, file_name, stored_name, total_leads, file_size, status, upload_date, created_at, updated_at
                 )
                 SELECT
                    leads.batch_id,
                    COALESCE(NULLIF(MAX(leads.source_file), \'\'), CONCAT(leads.batch_id, \'.csv\')) AS file_name,
                    NULL AS stored_name,
                    COUNT(*) AS total_leads,
                    0 AS file_size,
                    \'Uploaded\' AS status,
                    MIN(leads.created_at) AS upload_date,
                    MIN(leads.created_at) AS created_at,
                    MAX(leads.updated_at) AS updated_at
                 FROM leads
                 LEFT JOIN uploaded_lead_files uploads
                    ON uploads.batch_id = leads.batch_id
                 WHERE uploads.batch_id IS NULL
                 GROUP BY leads.batch_id'
            );
        } catch (PDOException) {
            // Best effort for environments that do not have the leads table yet.
        }
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return match ($normalized) {
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'partial' => 'Partial',
            default => 'Uploaded',
        };
    }
}
