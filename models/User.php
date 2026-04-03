<?php

declare(strict_types=1);

namespace Models;

use Config\Database;
use PDO;
use PDOException;

final class User
{
    private static bool $schemaEnsured = false;

    public function findByEmail(string $email): ?array
    {
        $this->ensureSchema();

        $statement = Database::connection()->prepare(
            'SELECT id, email, password_hash, created_at, updated_at
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $statement->execute([
            'email' => $this->normalizeEmail($email),
        ]);

        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($user) ? $user : null;
    }

    public function findById(int $id): ?array
    {
        $this->ensureSchema();

        $statement = Database::connection()->prepare(
            'SELECT id, email, password_hash, created_at, updated_at
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute([
            'id' => max(0, $id),
        ]);

        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($user) ? $user : null;
    }

    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $this->ensureSchema();

        $statement = Database::connection()->prepare(
            'UPDATE users
             SET password_hash = :password_hash,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute([
            'id' => max(0, $id),
            'password_hash' => $passwordHash,
        ]);
    }

    public function ensureSetup(): void
    {
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        $connection = Database::connection();
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                email VARCHAR(190) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY users_email_unique (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->ensureColumns($connection);
        $this->ensureIndexes($connection);
        $this->migrateLegacyAdmins($connection);
        $this->seedDefaultAdmin($connection);

        self::$schemaEnsured = true;
    }

    private function ensureColumns(PDO $connection): void
    {
        try {
            $columns = $connection->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException) {
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
            'password_hash' => 'ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NOT NULL AFTER email',
            'created_at' => 'ALTER TABLE users ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER password_hash',
            'updated_at' => 'ALTER TABLE users ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
        ];

        foreach ($definitions as $column => $sql) {
            if (isset($existing[$column])) {
                continue;
            }

            try {
                $connection->exec($sql);
            } catch (PDOException) {
                // Best effort for existing installations.
            }
        }
    }

    private function ensureIndexes(PDO $connection): void
    {
        try {
            $indexes = $connection->query('SHOW INDEX FROM users')->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return;
        }

        $hasEmailIndex = false;
        foreach (is_array($indexes) ? $indexes : [] as $index) {
            if ((string) ($index['Key_name'] ?? '') === 'users_email_unique') {
                $hasEmailIndex = true;
                break;
            }
        }

        if ($hasEmailIndex) {
            return;
        }

        try {
            $connection->exec('ALTER TABLE users ADD UNIQUE KEY users_email_unique (email)');
        } catch (PDOException) {
            // Best effort for existing installations.
        }
    }

    private function migrateLegacyAdmins(PDO $connection): void
    {
        try {
            $adminTable = $connection->query("SHOW TABLES LIKE 'admins'")->fetchColumn();
        } catch (PDOException) {
            return;
        }

        if ($adminTable === false) {
            return;
        }

        try {
            $connection->exec(
                'INSERT INTO users (email, password_hash, created_at, updated_at)
                 SELECT LOWER(TRIM(a.email)), a.password, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                 FROM admins a
                 LEFT JOIN users u ON u.email = LOWER(TRIM(a.email))
                 WHERE u.id IS NULL
                   AND a.email IS NOT NULL
                   AND TRIM(a.email) <> \'\'
                   AND a.password IS NOT NULL
                   AND TRIM(a.password) <> \'\''
            );
        } catch (PDOException) {
            // Best effort for existing installations.
        }
    }

    private function seedDefaultAdmin(PDO $connection): void
    {
        $email = $this->normalizeEmail(env('ADMIN_EMAIL', 'admin@gmail.com') ?? 'admin@gmail.com');
        $password = env('ADMIN_PASSWORD', 'admin@123') ?? 'admin@123';
        if ($email === '') {
            $email = 'admin@gmail.com';
        }

        if ($password === '') {
            $password = 'admin@123';
        }

        try {
            $statement = $connection->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $statement->execute([
                'email' => $email,
            ]);

            if ($statement->fetchColumn() !== false) {
                return;
            }

            $insert = $connection->prepare(
                'INSERT INTO users (email, password_hash, created_at, updated_at)
                 VALUES (:email, :password_hash, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
            );
            $insert->execute([
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            ]);
        } catch (PDOException) {
            // Best effort for installations that are still provisioning.
        }
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
