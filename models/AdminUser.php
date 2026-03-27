<?php

declare(strict_types=1);

namespace Models;

use Config\Database;

final class AdminUser
{
    public function findByEmail(string $email): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, email, password FROM admins WHERE email = :email LIMIT 1'
        );
        $statement->execute([
            'email' => strtolower(trim($email)),
        ]);

        $admin = $statement->fetch();

        return $admin === false ? null : $admin;
    }
}
