<?php

declare(strict_types=1);

namespace Patro\Domain\Configuration\Repository;

use PDO;

final class ConfigurationRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function find(string $key, ?string $default = null): ?string
    {
        $statement = $this->connection->prepare(
            'SELECT config_value FROM configurations WHERE config_key = :key LIMIT 1'
        );
        $statement->execute([':key' => $key]);
        $value = $statement->fetchColumn();

        return $value === false ? $default : (string) $value;
    }

    public function save(string $key, ?string $value): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO configurations (config_key, config_value)
             VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)'
        );
        $statement->execute([':key' => $key, ':value' => $value]);
    }
}
