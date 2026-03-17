<?php

declare(strict_types=1);

namespace Services;

final class AuthService
{
    public function attempt(string $email, string $password): bool
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
