<?php

declare(strict_types=1);

namespace Patro\Domain\Inscription\Repository;

use PDO;

final class ThemeRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function existsByTitle(string $title, int $sessionId, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM themes WHERE titre = :title AND session_id = :session_id';
        $parameters = [':title' => $title, ':session_id' => $sessionId];
        if ($exceptId !== null) {
            $sql .= ' AND id != :id';
            $parameters[':id'] = $exceptId;
        }

        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn() > 0;
    }

    public function create(string $title, int $sessionId): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO themes (titre, session_id) VALUES (:title, :session_id)'
        );
        $statement->execute([':title' => $title, ':session_id' => $sessionId]);

        return (int) $this->connection->lastInsertId();
    }

    public function belongsToSession(int $id, int $sessionId): bool
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM themes WHERE id = :id AND session_id = :session_id'
        );
        $statement->execute([':id' => $id, ':session_id' => $sessionId]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function update(int $id, string $title, int $sessionId): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE themes SET titre = :title WHERE id = :id AND session_id = :session_id'
        );
        $statement->execute([':title' => $title, ':id' => $id, ':session_id' => $sessionId]);

        return $statement->rowCount() > 0;
    }

    public function delete(int $id, int $sessionId): bool
    {
        $statement = $this->connection->prepare(
            'DELETE FROM themes WHERE id = :id AND session_id = :session_id'
        );
        $statement->execute([':id' => $id, ':session_id' => $sessionId]);

        return $statement->rowCount() > 0;
    }

    /** @return list<array<string,mixed>> */
    public function findAllForSession(int $sessionId): array
    {
        $statement = $this->connection->prepare(
            'SELECT t.id, t.titre, t.session_id, a.ans, s.type_session
             FROM themes t
             INNER JOIN session s ON s.id_session = t.session_id
             INNER JOIN annee a ON a.idannee = s.annee_id
             WHERE t.session_id = :session_id
             ORDER BY t.titre ASC'
        );
        $statement->execute([':session_id' => $sessionId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findFirstTitleForSession(int $sessionId): ?string
    {
        $statement = $this->connection->prepare(
            'SELECT titre FROM themes WHERE session_id = :session_id ORDER BY titre ASC LIMIT 1'
        );
        $statement->execute([':session_id' => $sessionId]);
        $title = $statement->fetchColumn();

        return $title === false ? null : (string) $title;
    }
}
