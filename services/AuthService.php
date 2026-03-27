<?php

declare(strict_types=1);

namespace Services;

use Models\AdminUser;
use PDOException;

final class AuthService
{
    public function __construct(private readonly AdminUser $adminUser = new AdminUser())
    {
    }

    public function attempt(string $email, string $password): bool
    {
        try {
            $admin = $this->adminUser->findByEmail($email);
        } catch (PDOException) {
            return $this->attemptEnvFallback($email, $password);
        }

        if ($admin !== null) {
            $storedPassword = (string) ($admin['password'] ?? '');

            if ($storedPassword !== '' && password_verify($password, $storedPassword)) {
                return true;
            }
        }

        return $this->attemptEnvFallback($email, $password);
    }

    private function attemptEnvFallback(string $email, string $password): bool
    {
        $configuredEmail = env('ADMIN_EMAIL');
        $configuredPassword = env('ADMIN_PASSWORD');

        if ($configuredEmail === null || $configuredPassword === null) {
            return false;
        }

        return hash_equals(strtolower($configuredEmail), strtolower(trim($email)))
            && hash_equals($configuredPassword, $password);
    }

    public function login(string $email): void
    {
        $_SESSION['admin'] = [
            'email' => $email,
            'logged_in_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function logout(): void
    {
        unset($_SESSION['admin']);
        session_regenerate_id(true);
    }

    public function check(): bool
    {
        return isset($_SESSION['admin']['email']);
    }

    public function user(): ?array
    {
        return $_SESSION['admin'] ?? null;
    }
}
