<?php

declare(strict_types=1);

namespace Services;

use Models\User;
use PDOException;

final class AuthService
{
    public function __construct(private readonly User $users = new User())
    {
    }

    public function initialize(): void
    {
        try {
            $this->users->ensureSetup();
        } catch (PDOException) {
            // Best effort only. The login page can still render even if the database is unavailable.
        }
    }

    public function authenticate(string $email, string $password): ?array
    {
        try {
            $user = $this->users->findByEmail($email);
        } catch (PDOException) {
            return null;
        }

        if ($user === null) {
            return null;
        }

        $storedPasswordHash = (string) ($user['password_hash'] ?? '');
        if ($storedPasswordHash === '' || !password_verify($password, $storedPasswordHash)) {
            return null;
        }

        if (password_needs_rehash($storedPasswordHash, PASSWORD_BCRYPT)) {
            try {
                $rehash = password_hash($password, PASSWORD_BCRYPT);
                $this->users->updatePasswordHash((int) ($user['id'] ?? 0), $rehash);
                $user['password_hash'] = $rehash;
            } catch (PDOException) {
                // Best effort only. Existing hash remains valid for this request.
            }
        }

        return $user;
    }

    public function login(array $user): void
    {
        session_regenerate_id(true);

        $_SESSION['admin'] = [
            'id' => (int) ($user['id'] ?? 0),
            'email' => strtolower(trim((string) ($user['email'] ?? ''))),
            'logged_in_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public function check(): bool
    {
        return isset($_SESSION['admin']['id'], $_SESSION['admin']['email']);
    }

    public function user(): ?array
    {
        if (!$this->check()) {
            return null;
        }

        return $_SESSION['admin'];
    }

    public function updatePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        $user = $this->users->findById($userId);
        if ($user === null) {
            return false;
        }

        $storedPasswordHash = (string) ($user['password_hash'] ?? '');
        if ($storedPasswordHash === '' || !password_verify($currentPassword, $storedPasswordHash)) {
            return false;
        }

        $this->users->updatePasswordHash($userId, password_hash($newPassword, PASSWORD_BCRYPT));

        return true;
    }
}
