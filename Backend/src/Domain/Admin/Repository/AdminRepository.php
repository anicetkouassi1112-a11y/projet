<?php

declare(strict_types=1);

namespace Patro\Domain\Admin\Repository;

use PDO;

final class AdminRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByUsername(string $username): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id_admin, username, password, role, created_at
             FROM admin WHERE username = :username LIMIT 1'
        );
        $statement->execute([':username' => $username]);
        $admin = $statement->fetch(PDO::FETCH_ASSOC);

        return $admin ?: null;
    }

    public function updatePassword(int $adminId, string $passwordHash): void
    {
        $statement = $this->connection->prepare(
            'UPDATE admin SET password = :password WHERE id_admin = :id'
        );
        $statement->execute([':password' => $passwordHash, ':id' => $adminId]);
    }
}
