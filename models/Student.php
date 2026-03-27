<?php

declare(strict_types=1);

namespace Models;

use Config\Database;

final class Student
{
    public function all(): array
    {
        $statement = Database::connection()->query(
            'SELECT id, name, mobile, email, city, state, course, region, source_file, created_at
             FROM students
             ORDER BY id DESC'
        );

        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function countAll(): int
    {
        $statement = Database::connection()->query('SELECT COUNT(*) FROM students');

        return (int) $statement->fetchColumn();
    }

    public function insertMany(array $rows, ?string $sourceFile = null): int
    {
        if ($rows === []) {
            return 0;
        }

        $connection = Database::connection();
        $statement = $connection->prepare(
            'INSERT INTO students (name, mobile, email, city, state, course, region, source_file)
             VALUES (:name, :mobile, :email, :city, :state, :course, :region, :source_file)'
        );

        $inserted = 0;
        $connection->beginTransaction();

        try {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $statement->execute([
                    'name' => $this->stringOrNull($row['name'] ?? null),
                    'mobile' => $this->stringOrNull($row['mobile'] ?? null),
                    'email' => $this->stringOrNull($row['email'] ?? null),
                    'city' => $this->stringOrNull($row['city'] ?? null),
                    'state' => $this->stringOrNull($row['state'] ?? null),
                    'course' => $this->stringOrNull($row['course'] ?? null),
                    'region' => $this->normalizeRegion((string) ($row['region'] ?? 'West')),
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
            default => 'West',
        };
    }
}
