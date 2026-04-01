<?php

declare(strict_types=1);

namespace Models;

use Config\Database;
use PDO;
use PDOException;

final class Lead
{
    private const REGIONS = ['North', 'South', 'East', 'West / Others'];
    private const FILTERABLE_COLUMNS = [
        'course',
        'state',
        'city',
        'lead_origin',
        'campaign',
        'lead_stage',
        'lead_status',
        'form_initiated',
        'paid_apps',
    ];
    private const SEARCH_COLUMNS = ['name', 'email', 'phone', 'course', 'city', 'campaign'];
    private const LIST_COLUMNS = 'id, batch_id, lead_id, name, email, phone, course, specialization, campus, college_name, city, state, region, source_file, lead_origin, campaign, lead_stage, lead_status, form_initiated, paid_apps, created_at';
    private const EXPORT_COLUMNS = 'batch_id, lead_id, name, email, phone, course, state, city, lead_origin, campaign, lead_stage, lead_status, form_initiated, paid_apps, created_at';

    private static bool $schemaEnsured = false;

    public function __construct()
    {
        $this->ensureSchema();
    }

    public function all(): array
    {
        $statement = Database::connection()->query(
            'SELECT ' . self::LIST_COLUMNS . '
             FROM leads
             ORDER BY created_at DESC, id DESC'
        );

        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function paginate(int $limit, int $offset): array
    {
        $statement = Database::connection()->prepare(
            'SELECT ' . self::LIST_COLUMNS . '
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

    public function paginateByFilters(array $filters, int $limit, int $offset): array
    {
        [$whereSql, $bindings] = $this->buildFilterQuery($filters);

        $statement = Database::connection()->prepare(
            'SELECT ' . self::LIST_COLUMNS . '
             FROM leads'
             . $whereSql
             . ' ORDER BY created_at DESC, id DESC
             LIMIT :limit OFFSET :offset'
        );

        $this->bindFilterValues($statement, $bindings);
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function exportByFilters(array $filters): array
    {
        [$whereSql, $bindings] = $this->buildFilterQuery($filters);

        $statement = Database::connection()->prepare(
            'SELECT ' . self::EXPORT_COLUMNS . '
             FROM leads'
             . $whereSql
             . ' ORDER BY created_at DESC, id DESC'
        );

        $this->bindFilterValues($statement, $bindings);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function countAll(): int
    {
        $statement = Database::connection()->query('SELECT COUNT(*) FROM leads');

        return (int) $statement->fetchColumn();
    }

    public function countByFilters(array $filters): int
    {
        [$whereSql, $bindings] = $this->buildFilterQuery($filters);
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM leads'
             . $whereSql
        );

        $this->bindFilterValues($statement, $bindings);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function filterOptions(): array
    {
        $options = [];

        foreach (self::FILTERABLE_COLUMNS as $column) {
            $statement = Database::connection()->query(
                'SELECT DISTINCT ' . $column . '
                 FROM leads
                 WHERE ' . $column . ' IS NOT NULL AND TRIM(' . $column . ') <> \'\'
                 ORDER BY ' . $column . ' ASC'
            );

            $values = $statement->fetchAll(PDO::FETCH_COLUMN);
            $options[$column] = array_values(array_filter(array_map(
                static fn ($value): string => trim((string) $value),
                is_array($values) ? $values : []
            ), static fn (string $value): bool => $value !== ''));
        }

        return $options;
    }

    public function insertMany(array $rows, string $batchId, ?string $sourceFile = null): int
    {
        if ($rows === []) {
            return 0;
        }

        $connection = Database::connection();
        $statement = $connection->prepare(
            'INSERT INTO leads (
                batch_id, lead_id, name, email, phone, course, specialization, campus, college_name, city, lead_origin, campaign, lead_stage, lead_status, form_initiated, paid_apps, state, region, source_file
             ) VALUES (
                :batch_id, :lead_id, :name, :email, :phone, :course, :specialization, :campus, :college_name, :city, :lead_origin, :campaign, :lead_stage, :lead_status, :form_initiated, :paid_apps, :state, :region, :source_file
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
                    'lead_origin' => $this->stringOrNull($row['lead_origin'] ?? null),
                    'campaign' => $this->stringOrNull($row['campaign'] ?? null),
                    'lead_stage' => $this->stringOrNull($row['lead_stage'] ?? null),
                    'lead_status' => $this->stringOrNull($row['lead_status'] ?? null),
                    'form_initiated' => $this->stringOrNull($row['form_initiated'] ?? null),
                    'paid_apps' => $this->stringOrNull($row['paid_apps'] ?? null),
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

    private function buildFilterQuery(array $filters): array
    {
        $conditions = [];
        $bindings = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $searchConditions = [];
            foreach (self::SEARCH_COLUMNS as $index => $column) {
                $placeholder = ':search_' . $index;
                $searchConditions[] = $column . ' LIKE ' . $placeholder;
                $bindings[$placeholder] = '%' . $search . '%';
            }

            $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
        }

        foreach (self::FILTERABLE_COLUMNS as $column) {
            $values = $this->normalizeListFilter($filters[$column] ?? []);
            if ($values === []) {
                continue;
            }

            $placeholders = [];
            foreach ($values as $index => $value) {
                $placeholder = ':' . $column . '_' . $index;
                $placeholders[] = $placeholder;
                $bindings[$placeholder] = $value;
            }

            $conditions[] = $column . ' IN (' . implode(', ', $placeholders) . ')';
        }

        $dateFrom = $this->normalizeDate((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== null) {
            $conditions[] = 'DATE(created_at) >= :date_from';
            $bindings[':date_from'] = $dateFrom;
        }

        $dateTo = $this->normalizeDate((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== null) {
            $conditions[] = 'DATE(created_at) <= :date_to';
            $bindings[':date_to'] = $dateTo;
        }

        if ($conditions === []) {
            return ['', $bindings];
        }

        return [' WHERE ' . implode(' AND ', $conditions), $bindings];
    }

    private function bindFilterValues(\PDOStatement $statement, array $bindings): void
    {
        foreach ($bindings as $placeholder => $value) {
            $statement->bindValue($placeholder, $value, PDO::PARAM_STR);
        }
    }

    private function normalizeListFilter(mixed $value): array
    {
        if (is_array($value)) {
            $values = $value;
        } else {
            $raw = trim((string) $value);
            $values = $raw === '' ? [] : explode(',', $raw);
        }

        $normalized = array_values(array_unique(array_filter(array_map(
            static fn ($item): string => trim((string) $item),
            $values
        ), static fn (string $item): bool => $item !== '')));

        return $normalized;
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd-m-Y'];
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof \DateTimeImmutable && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        try {
            $connection = Database::connection();
            $columns = $connection->query('SHOW COLUMNS FROM leads')->fetchAll(PDO::FETCH_ASSOC);
            $indexes = $connection->query('SHOW INDEX FROM leads')->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return;
        }

        $existingColumns = [];
        foreach (is_array($columns) ? $columns : [] as $column) {
            $name = (string) ($column['Field'] ?? '');
            if ($name !== '') {
                $existingColumns[$name] = true;
            }
        }

        $columnDefinitions = [
            'lead_origin' => 'ALTER TABLE leads ADD COLUMN lead_origin VARCHAR(190) DEFAULT NULL AFTER city',
            'campaign' => 'ALTER TABLE leads ADD COLUMN campaign VARCHAR(190) DEFAULT NULL AFTER lead_origin',
            'lead_stage' => 'ALTER TABLE leads ADD COLUMN lead_stage VARCHAR(190) DEFAULT NULL AFTER campaign',
            'lead_status' => 'ALTER TABLE leads ADD COLUMN lead_status VARCHAR(190) DEFAULT NULL AFTER lead_stage',
            'form_initiated' => 'ALTER TABLE leads ADD COLUMN form_initiated VARCHAR(50) DEFAULT NULL AFTER lead_status',
            'paid_apps' => 'ALTER TABLE leads ADD COLUMN paid_apps VARCHAR(50) DEFAULT NULL AFTER form_initiated',
        ];

        foreach ($columnDefinitions as $column => $sql) {
            if (isset($existingColumns[$column])) {
                continue;
            }

            try {
                $connection->exec($sql);
            } catch (PDOException) {
                // Best effort so existing installations do not fatally fail.
            }
        }

        $existingIndexes = [];
        foreach (is_array($indexes) ? $indexes : [] as $index) {
            $name = (string) ($index['Key_name'] ?? '');
            if ($name !== '') {
                $existingIndexes[$name] = true;
            }
        }

        $indexDefinitions = [
            'leads_created_at_index' => 'ALTER TABLE leads ADD INDEX leads_created_at_index (created_at)',
            'leads_course_index' => 'ALTER TABLE leads ADD INDEX leads_course_index (course)',
            'leads_state_index' => 'ALTER TABLE leads ADD INDEX leads_state_index (state)',
            'leads_city_index' => 'ALTER TABLE leads ADD INDEX leads_city_index (city)',
            'leads_lead_origin_index' => 'ALTER TABLE leads ADD INDEX leads_lead_origin_index (lead_origin)',
            'leads_campaign_index' => 'ALTER TABLE leads ADD INDEX leads_campaign_index (campaign)',
            'leads_lead_stage_index' => 'ALTER TABLE leads ADD INDEX leads_lead_stage_index (lead_stage)',
            'leads_lead_status_index' => 'ALTER TABLE leads ADD INDEX leads_lead_status_index (lead_status)',
            'leads_form_initiated_index' => 'ALTER TABLE leads ADD INDEX leads_form_initiated_index (form_initiated)',
            'leads_paid_apps_index' => 'ALTER TABLE leads ADD INDEX leads_paid_apps_index (paid_apps)',
        ];

        foreach ($indexDefinitions as $index => $sql) {
            if (isset($existingIndexes[$index])) {
                continue;
            }

            try {
                $connection->exec($sql);
            } catch (PDOException) {
                // Best effort so existing installations do not fatally fail.
            }
        }

        self::$schemaEnsured = true;
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
