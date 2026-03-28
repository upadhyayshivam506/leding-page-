<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

use Config\Database;

function db(): PDO
{
    return Database::connection();
}

return db();
