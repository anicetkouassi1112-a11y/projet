<?php

declare(strict_types=1);

namespace Patro\Infrastructure\Database;

use PDO;
use Patro\Config\Environment;

/**
 * Fabrique PDO injectable pour les repositories et services modernes.
 */
final class PdoConnectionFactory
{
    public function create(): PDO
    {
        $host = (string) Environment::get('DB_HOST', 'localhost');
        $database = (string) Environment::get('DB_NAME', 'projet_db');
        $user = (string) Environment::get('DB_USER', 'root');
        $password = (string) Environment::get('DB_PASS', '');
        $charset = (string) Environment::get('DB_CHARSET', 'utf8mb4');

        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $database, $charset);

        return new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
