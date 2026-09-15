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

    /** @return array<string,mixed>|null */
    public function findById(int $adminId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id_admin, username, password, role, created_at
             FROM admin WHERE id_admin = :id LIMIT 1'
        );
        $statement->execute([':id' => $adminId]);
        $admin = $statement->fetch(PDO::FETCH_ASSOC);

        return $admin ?: null;
    }

    public function usernameExistsForAnotherAdmin(string $username, int $adminId): bool
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM admin
             WHERE username = :username AND id_admin != :id'
        );
        $statement->execute([':username' => $username, ':id' => $adminId]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function updateUsername(int $adminId, string $username): void
    {
        $statement = $this->connection->prepare(
            'UPDATE admin SET username = :username WHERE id_admin = :id'
        );
        $statement->execute([':username' => $username, ':id' => $adminId]);
    }
}
