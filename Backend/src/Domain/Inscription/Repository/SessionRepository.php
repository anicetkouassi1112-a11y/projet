<?php

declare(strict_types=1);

namespace Patro\Domain\Inscription\Repository;

use PDO;
use Patro\Domain\Inscription\SessionType;
use RuntimeException;

final class SessionRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function ensureYear(int $year): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO annee (ans) VALUES (:year)
             ON DUPLICATE KEY UPDATE ans = VALUES(ans)'
        );
        $statement->execute([':year' => $year]);

        return $this->findYearId($year) ?? throw new RuntimeException('Année introuvable après création.');
    }

    public function findYearId(int $year): ?int
    {
        $statement = $this->connection->prepare(
            'SELECT idannee FROM annee WHERE ans = :year LIMIT 1'
        );
        $statement->execute([':year' => $year]);
        $value = $statement->fetchColumn();

        return $value === false ? null : (int) $value;
    }

    public function findYearValue(int $yearId): ?int
    {
        $statement = $this->connection->prepare(
            'SELECT ans FROM annee WHERE idannee = :id LIMIT 1'
        );
        $statement->execute([':id' => $yearId]);
        $value = $statement->fetchColumn();

        return $value === false ? null : (int) $value;
    }

    /** @return list<int> */
    public function findAllYears(): array
    {
        $statement = $this->connection->query('SELECT ans FROM annee ORDER BY ans DESC');

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function ensureSession(int $year, string $type): int
    {
        $sessionType = SessionType::normalize($type)->value;
        $yearId = $this->ensureYear($year);
        $statement = $this->connection->prepare(
            'INSERT INTO session (annee_id, type_session) VALUES (:year_id, :type)
             ON DUPLICATE KEY UPDATE type_session = VALUES(type_session)'
        );
        $statement->execute([':year_id' => $yearId, ':type' => $sessionType]);

        $select = $this->connection->prepare(
            'SELECT id_session FROM session
             WHERE annee_id = :year_id AND type_session = :type LIMIT 1'
        );
        $select->execute([':year_id' => $yearId, ':type' => $sessionType]);
        $id = $select->fetchColumn();

        return $id === false ? throw new RuntimeException('Session introuvable.') : (int) $id;
    }

    /** @return array{year:int,type:string}|null */
    public function findLabelData(int $sessionId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT a.ans AS year, s.type_session AS type
             FROM session s
             INNER JOIN annee a ON a.idannee = s.annee_id
             WHERE s.id_session = :id LIMIT 1'
        );
        $statement->execute([':id' => $sessionId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function findAll(): array
    {
        $statement = $this->connection->query(
            'SELECT s.id_session, a.ans, s.type_session
             FROM session s
             INNER JOIN annee a ON a.idannee = s.annee_id
             ORDER BY a.ans DESC, FIELD(s.type_session, "scolaire", "vacance") ASC'
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function exists(int $sessionId): bool
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM session WHERE id_session = :id');
        $statement->execute([':id' => $sessionId]);

        return (int) $statement->fetchColumn() > 0;
    }
}
