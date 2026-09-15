<?php

declare(strict_types=1);

namespace Patro\Domain\Jeu\Repository;

use PDO;

final class JeuRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /** @return list<array<string,mixed>> */
    public function findByType(?string $type): array
    {
        if ($type === null || $type === '') {
            $statement = $this->connection->query(
                "SELECT * FROM jeux WHERE type_jeu = '' OR type_jeu IS NULL ORDER BY nom ASC"
            );
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        }
        $statement = $this->connection->prepare(
            'SELECT * FROM jeux WHERE type_jeu = :type ORDER BY nom ASC'
        );
        $statement->execute([':type' => $type]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->connection->prepare('SELECT * FROM jeux WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
