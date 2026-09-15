<?php

declare(strict_types=1);

namespace Patro\Infrastructure\Database;

interface TransactionManager
{
    public function begin(): void;

    public function commit(): void;

    public function rollback(): void;

    public function isActive(): bool;
}
