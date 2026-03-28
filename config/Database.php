<?php

declare(strict_types=1);

namespace Config;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = self::stringValue('DB_HOST', '127.0.0.1');
        $port = self::stringValue('DB_PORT', '3306');
        $database = self::stringValue('DB_NAME', 'lead_management');
        $username = self::stringValue('DB_USER', 'root');
        $password = self::readEnv('DB_PASSWORD') ?? '';
        $charset = self::stringValue('DB_CHARSET', 'utf8mb4');
        $socket = self::resolveSocket($host);

        $dsn = $socket !== null
            ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $socket, $database, $charset)
            : sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $database, $charset);

        try {
            self::$connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            $target = $socket !== null
                ? 'socket ' . $socket
                : 'host ' . $host . ':' . $port;

            throw new PDOException(
                'Database connection failed for ' . $target . '. Verify the .env database values for this environment.',
                (int) $exception->getCode(),
                $exception
            );
        }

        return self::$connection;
    }

    private static function stringValue(string $key, string $default): string
    {
        $value = self::readEnv($key);

        if ($value === null || trim($value) === '') {
            return $default;
        }

        return trim($value);
    }

    private static function readEnv(string $key): ?string
    {
        if (array_key_exists($key, $_ENV)) {
            return is_string($_ENV[$key]) ? $_ENV[$key] : (string) $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return is_string($_SERVER[$key]) ? $_SERVER[$key] : (string) $_SERVER[$key];
        }

        $value = getenv($key);

        if ($value === false) {
            return null;
        }

        return is_string($value) ? $value : (string) $value;
    }

    private static function resolveSocket(string $host): ?string
    {
        $socket = self::readEnv('DB_SOCKET');
        if ($socket !== null && trim($socket) !== '') {
            return trim($socket);
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            return null;
        }

        if (!in_array(strtolower(trim($host)), ['localhost', '127.0.0.1'], true)) {
            return null;
        }

        foreach ([
            '/var/run/mysqld/mysqld.sock',
            '/tmp/mysql.sock',
            '/var/lib/mysql/mysql.sock',
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
