<?php

declare(strict_types=1);

namespace Models;

use Config\Database;
use PDO;

final class Lead
{
    private const REGIONS = ['North', 'South', 'East', 'West / Others'];

    public function all(): array
    {
        $statement = Database::connection()->query(
            'SELECT id, batch_id, lead_id, name, email, phone, course, specialization, campus, college_name, city, state, region, source_file, created_at
             FROM leads
             ORDER BY created_at DESC, id DESC'
        );

        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function paginate(int $limit, int $offset): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, batch_id, lead_id, name, email, phone, course, specialization, campus, college_name, city, state, region, source_file, created_at
             FROM leads
             ORDER BY created_at DESC, id DESC
             LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function countAll(): int
    {
        $statement = Database::connection()->query('SELECT COUNT(*) FROM leads');

        return (int) $statement->fetchColumn();
    }

    public function insertMany(array $rows, string $batchId, ?string $sourceFile = null): int
    {
        if ($rows === []) {
            return 0;
        }

        $connection = Database::connection();
        $statement = $connection->prepare(
            'INSERT INTO leads (
                batch_id, lead_id, name, email, phone, course, specialization, campus, college_name, city, state, region, source_file
             ) VALUES (
                :batch_id, :lead_id, :name, :email, :phone, :course, :specialization, :campus, :college_name, :city, :state, :region, :source_file
             )'
        );

        $inserted = 0;
        $connection->beginTransaction();

        try {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $statement->execute([
                    'batch_id' => $batchId,
                    'lead_id' => $this->stringOrNull($row['lead_id'] ?? null),
                    'name' => $this->stringOrNull($row['name'] ?? null),
                    'email' => $this->stringOrNull($row['email'] ?? null),
                    'phone' => $this->stringOrNull($row['phone'] ?? null),
                    'course' => $this->stringOrNull($row['course'] ?? null),
                    'specialization' => $this->stringOrNull($row['specialization'] ?? null),
                    'campus' => $this->stringOrNull($row['campus'] ?? null),
                    'college_name' => $this->stringOrNull($row['college_name'] ?? null),
                    'city' => $this->stringOrNull($row['city'] ?? null),
                    'state' => $this->stringOrNull($row['state'] ?? null),
                    'region' => $this->normalizeRegion((string) ($row['region'] ?? 'West / Others')),
                    'source_file' => $this->stringOrNull($sourceFile),
                ]);
                $inserted++;
            }

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();
            throw $throwable;
        }

        return $inserted;
    }

    public function findByBatch(string $batchId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT lead_id, name, email, phone, course, specialization, campus, college_name, city, state, region
             FROM leads
             WHERE batch_id = :batch_id
             ORDER BY id ASC'
        );
        $statement->execute(['batch_id' => $batchId]);

        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function countByBatch(string $batchId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM leads
             WHERE batch_id = :batch_id'
        );
        $statement->execute(['batch_id' => $batchId]);

        return (int) $statement->fetchColumn();
    }

    public function findByBatchPaginated(string $batchId, int $limit, int $offset): array
    {
        $statement = Database::connection()->prepare(
            'SELECT lead_id, name, email, phone, course, specialization, campus, college_name, city, state, region
             FROM leads
             WHERE batch_id = :batch_id
             ORDER BY id ASC
             LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue(':batch_id', $batchId, PDO::PARAM_STR);
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function countDistinctBatches(): int
    {
        $statement = Database::connection()->query('SELECT COUNT(DISTINCT batch_id) FROM leads');

        return (int) $statement->fetchColumn();
    }

    public function summarizeByBatch(string $batchId): array
    {
        $summary = array_fill_keys(self::REGIONS, 0);
        $statement = Database::connection()->prepare(
            'SELECT region, COUNT(*) AS total
             FROM leads
             WHERE batch_id = :batch_id
             GROUP BY region'
        );
        $statement->execute(['batch_id' => $batchId]);

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $region = $this->normalizeRegion((string) ($row['region'] ?? 'West / Others'));
            $summary[$region] = (int) ($row['total'] ?? 0);
        }

        $cards = [];
        foreach (self::REGIONS as $region) {
            $cards[] = [
                'region' => $region,
                'total' => $summary[$region] ?? 0,
            ];
        }

        return $cards;
    }

    public function groupedByRegionForBatch(string $batchId): array
    {
        $grouped = array_fill_keys(self::REGIONS, []);

        foreach ($this->findByBatch($batchId) as $row) {
            $region = $this->normalizeRegion((string) ($row['region'] ?? 'West / Others'));
            $grouped[$region][] = $row;
        }

        return $grouped;
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function normalizeRegion(string $value): string
    {
        $region = strtolower(trim($value));

        return match ($region) {
            'north' => 'North',
            'south' => 'South',
            'east' => 'East',
            default => 'West / Others',
        };
    }
}

