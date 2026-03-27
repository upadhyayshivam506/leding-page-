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

        $host = env('DB_HOST', '127.0.0.1');
        $port = env('DB_PORT', '3306');
        $database = env('DB_NAME', 'lead_management');
        $username = env('DB_USER', 'root');
        $password = env('DB_PASSWORD', '');
        $socket = env('DB_SOCKET');

        if ($socket === null && $host === 'localhost') {
            $defaultSocket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';
            if (is_file($defaultSocket)) {
                $socket = $defaultSocket;
            }
        }

        $dsn = $socket !== null && trim($socket) !== ''
            ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', trim($socket), $database)
            : sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

        try {
            self::$connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $exception) {
            throw new PDOException(
                'Database connection failed. Check your XAMPP MySQL settings and .env database values.',
                (int) $exception->getCode(),
                $exception
            );
        }

        return self::$connection;
    }
}
