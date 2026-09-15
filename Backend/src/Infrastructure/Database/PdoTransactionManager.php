<?php

declare(strict_types=1);

namespace Patro\Infrastructure\Database;

use PDO;

final class PdoTransactionManager implements TransactionManager
{
    public function __construct(private PDO $connection)
    {
    }

    public function begin(): void
    {
        $this->connection->beginTransaction();
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollback(): void
    {
        if ($this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
    }

    public function isActive(): bool
    {
        return $this->connection->inTransaction();
    }
}
