<?php

declare(strict_types=1);

namespace Patro\Domain\Animateur\Repository;

use PDO;

final class AnimateurRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function updateSessionSection(int $animateurSessionId, ?int $sectionId, int $sessionId): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE animateur_session
             SET id_section = :section_id
             WHERE id_animateur_session = :id AND id_session = :session_id'
        );
        $statement->execute([
            ':section_id' => $sectionId,
            ':id' => $animateurSessionId,
            ':session_id' => $sessionId,
        ]);

        return true;
    }

    /** @return array{id_animateur_session:int,genre_a:string}|null */
    public function findSessionAssignment(int $animateurId, int $sessionId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT ans.id_animateur_session, a.genre_a
             FROM animateur_session ans
             INNER JOIN animateur a ON a.id_animateur = ans.id_animateur
             WHERE ans.id_animateur = :animateur_id
               AND ans.id_session = :session_id
             LIMIT 1'
        );
        $statement->execute([
            ':animateur_id' => $animateurId,
            ':session_id' => $sessionId,
        ]);
        $assignment = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($assignment)) {
            return null;
        }

        return [
            'id_animateur_session' => (int) $assignment['id_animateur_session'],
            'genre_a' => (string) $assignment['genre_a'],
        ];
    }

    public function sectionExists(int $sectionId): bool
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM section WHERE id_section = :id');
        $statement->execute([':id' => $sectionId]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function deleteAvailableCode(int $codeId, int $sessionId): bool
    {
        $statement = $this->connection->prepare(
            'DELETE FROM code_inscription_animateur
             WHERE id_code = :id AND id_session = :session_id AND statut = "disponible"'
        );
        $statement->execute([':id' => $codeId, ':session_id' => $sessionId]);

        return $statement->rowCount() > 0;
    }

    public function activate(int $animateurId): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE animateur SET statut = "actif" WHERE id_animateur = :id'
        );
        $statement->execute([':id' => $animateurId]);

        return $statement->rowCount() > 0;
    }

    /** @return array<string,mixed>|null */
    public function findCodeForUpdate(string $code): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT c.* FROM code_inscription_animateur c
             WHERE c.code = :code LIMIT 1 FOR UPDATE'
        );
        $statement->execute([':code' => $code]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    public function findByPhoneForUpdate(string $phone): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id_animateur, nom_a, prenom_a, genre_a, tel, password, statut, created_at, updated_at
             FROM animateur WHERE tel = :phone LIMIT 1 FOR UPDATE'
        );
        $statement->execute([':phone' => $phone]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(string $lastName, string $firstName, string $genre, string $phone, string $passwordHash): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO animateur (nom_a, prenom_a, genre_a, tel, password, statut)
             VALUES (:last_name, :first_name, :genre, :phone, :password, "actif")'
        );
        $statement->execute([
            ':last_name' => $lastName, ':first_name' => $firstName,
            ':genre' => $genre, ':phone' => $phone, ':password' => $passwordHash,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function updateFromRegistration(int $animateurId, string $lastName, string $firstName, string $genre, string $passwordHash): void
    {
        $statement = $this->connection->prepare(
            'UPDATE animateur SET nom_a = :last_name, prenom_a = :first_name,
             genre_a = :genre, password = :password, statut = "actif"
             WHERE id_animateur = :id'
        );
        $statement->execute([
            ':last_name' => $lastName, ':first_name' => $firstName,
            ':genre' => $genre, ':password' => $passwordHash, ':id' => $animateurId,
        ]);
    }

    public function attachToSession(int $animateurId, int $sessionId, int $codeId): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO animateur_session (id_animateur, id_session, id_code, id_section)
             VALUES (:animateur_id, :session_id, :code_id, NULL)'
        );
        $statement->execute([
            ':animateur_id' => $animateurId, ':session_id' => $sessionId, ':code_id' => $codeId,
        ]);
    }

    public function consumeCode(int $codeId, int $animateurId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE code_inscription_animateur
             SET statut = "utilise", id_animateur = :animateur_id, utilise_le = CURRENT_TIMESTAMP
             WHERE id_code = :code_id'
        );
        $statement->execute([':animateur_id' => $animateurId, ':code_id' => $codeId]);
    }

    public function codeExists(string $code): bool
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM code_inscription_animateur WHERE code = :code'
        );
        $statement->execute([':code' => $code]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function createCode(string $code, int $sessionId, int $adminId, ?string $expiration): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO code_inscription_animateur (code, id_session, id_admin, date_expiration)
             VALUES (:code, :session_id, :admin_id, :expiration)'
        );
        $statement->execute([
            ':code' => $code, ':session_id' => $sessionId,
            ':admin_id' => $adminId, ':expiration' => $expiration ?: null,
        ]);
    }

    /** @return array<string,mixed>|null */
    public function findForLogin(string $name, int $sessionId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT a.*, ans.id_animateur_session, ans.id_session, ans.id_section,
                    sec.nom_section, sec.genre AS genre_section
             FROM animateur a
             INNER JOIN animateur_session ans ON ans.id_animateur = a.id_animateur
                AND ans.id_session = :session_id
             LEFT JOIN section sec ON sec.id_section = ans.id_section
             WHERE a.nom_a = :name LIMIT 1'
        );
        $statement->execute([':name' => $name, ':session_id' => $sessionId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function updatePassword(int $animateurId, string $passwordHash): void
    {
        $statement = $this->connection->prepare(
            'UPDATE animateur SET password = :password WHERE id_animateur = :id'
        );
        $statement->execute([':password' => $passwordHash, ':id' => $animateurId]);
    }

    public function blockNotRegistered(int $sessionId): int
    {
        $statement = $this->connection->prepare(
            'UPDATE animateur a SET a.statut = "bloque"
             WHERE a.statut = "actif"
               AND a.id_animateur NOT IN (
                   SELECT id_animateur FROM animateur_session WHERE id_session = :session_id
               )'
        );
        $statement->execute([':session_id' => $sessionId]);

        return $statement->rowCount();
    }

    /** @return list<array<string,mixed>> */
    public function findForSession(int $sessionId, ?int $sectionId = null, ?string $status = null): array
    {
        $where = ['ans.id_session = :session_id'];
        $parameters = [':session_id' => $sessionId];
        if ($sectionId !== null && $sectionId > 0) {
            $where[] = 'ans.id_section = :section_id';
            $parameters[':section_id'] = $sectionId;
        }
        if ($status !== null && $status !== '') {
            $where[] = 'a.statut = :status';
            $parameters[':status'] = $status;
        }

        $statement = $this->connection->prepare(
            'SELECT a.id_animateur, a.nom_a, a.prenom_a, a.tel, a.statut,
                    ans.id_animateur_session, ans.id_section, ans.date_inscription,
                    sec.nom_section
             FROM animateur_session ans
             INNER JOIN animateur a ON a.id_animateur = ans.id_animateur
             LEFT JOIN section sec ON sec.id_section = ans.id_section
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY sec.nom_section ASC, a.nom_a ASC, a.prenom_a ASC'
        );
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function findCodesForSession(int $sessionId): array
    {
        $statement = $this->connection->prepare(
            'SELECT c.*, a.nom_a, a.prenom_a, ad.username
             FROM code_inscription_animateur c
             INNER JOIN admin ad ON ad.id_admin = c.id_admin
             LEFT JOIN animateur a ON a.id_animateur = c.id_animateur
             WHERE c.id_session = :session_id
             ORDER BY c.created_at DESC, c.id_code DESC
             LIMIT 200'
        );
        $statement->execute([':session_id' => $sessionId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function bulkUnblockBySession(array $animateurIds, int $sessionId): int
    {
        if ($animateurIds === []) {
            return 0;
        }

        $placeholders = [];
        $parameters = [':session_id' => $sessionId];
        foreach (array_values($animateurIds) as $index => $animateurId) {
            $key = ':animateur_' . $index;
            $placeholders[] = $key;
            $parameters[$key] = (int) $animateurId;
        }

        $statement = $this->connection->prepare(
            'UPDATE animateur a
             INNER JOIN animateur_session ans ON ans.id_animateur = a.id_animateur
             SET a.statut = "actif"
             WHERE a.id_animateur IN (' . implode(', ', $placeholders) . ')
               AND ans.id_session = :session_id'
        );
        $statement->execute($parameters);

        return $statement->rowCount();
    }

    /** @return array{total:int,actifs:int,bloques:int,sans_section:int} */
    public function statsForSession(int $sessionId): array
    {
        $statement = $this->connection->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(a.statut = 'actif') AS actifs,
                SUM(a.statut = 'bloque') AS bloques,
                SUM(ans.id_section IS NULL) AS sans_section
             FROM animateur_session ans
             INNER JOIN animateur a ON a.id_animateur = ans.id_animateur
             WHERE ans.id_session = :session_id"
        );
        $statement->execute([':session_id' => $sessionId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'actifs' => (int) ($row['actifs'] ?? 0),
            'bloques' => (int) ($row['bloques'] ?? 0),
            'sans_section' => (int) ($row['sans_section'] ?? 0),
        ];
    }

    /** @return array{garcons:int,filles:int} */
    public function countGenderForSession(int $sessionId): array
    {
        $statement = $this->connection->prepare(
            'SELECT a.genre_a
             FROM animateur_session ans
             INNER JOIN animateur a ON a.id_animateur = ans.id_animateur
             WHERE ans.id_session = :session_id'
        );
        $statement->execute([':session_id' => $sessionId]);

        $totals = ['garcons' => 0, 'filles' => 0];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if (($row['genre_a'] ?? '') === 'M') {
                $totals['garcons']++;
            } elseif (($row['genre_a'] ?? '') === 'F') {
                $totals['filles']++;
            }
        }

        return $totals;
    }

    /** @return list<array<string,mixed>> */
    public function findSessionRows(int $sessionId, int $sectionId = 0, string $status = '', string $searchTerm = '', string $sortKey = 'section', bool $isScolaire = false): array
    {
        $sortOptions = [
            'section' => 'sec.nom_section ASC, a.nom_a ASC, a.prenom_a ASC',
            'nom' => 'a.nom_a ASC, a.prenom_a ASC',
            'nom_desc' => 'a.nom_a DESC, a.prenom_a DESC',
            'statut' => 'a.statut ASC, a.nom_a ASC',
            'date' => 'ans.date_inscription DESC',
            'date_asc' => 'ans.date_inscription ASC',
        ];
        $orderBy = $sortOptions[$sortKey] ?? $sortOptions['section'];

        $where = ['ans.id_session = :id_session'];
        $parameters = [':id_session' => $sessionId];

        if (!$isScolaire && $sectionId > 0) {
            $where[] = 'ans.id_section = :id_section';
            $parameters[':id_section'] = $sectionId;
        }
        if ($status !== '') {
            $where[] = 'a.statut = :statut';
            $parameters[':statut'] = $status;
        }
        if ($searchTerm !== '') {
            $where[] = '(a.nom_a LIKE :q1 OR a.prenom_a LIKE :q2 OR a.tel LIKE :q3)';
            $like = '%' . $searchTerm . '%';
            $parameters[':q1'] = $like;
            $parameters[':q2'] = $like;
            $parameters[':q3'] = $like;
        }

        $statement = $this->connection->prepare(
            'SELECT a.id_animateur, a.nom_a, a.prenom_a, a.tel, a.statut, a.genre_a,
                    ans.id_animateur_session, ans.id_section, ans.date_inscription,
                    sec.nom_section
             FROM animateur_session ans
             INNER JOIN animateur a ON a.id_animateur = ans.id_animateur
             LEFT JOIN section sec ON sec.id_section = ans.id_section
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY ' . $orderBy
        );
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function findBySectionIds(array $sectionIds, int $sessionId, ?string $genre, bool $isScolaire = false): array
    {
        $parameters = [':session_id' => $sessionId];
        $where = ['ans.id_session = :session_id'];
        if (!$isScolaire && $sectionIds !== []) {
            $placeholders = [];
            foreach (array_values($sectionIds) as $index => $sectionId) {
                $key = ':section_' . $index;
                $placeholders[] = $key;
                $parameters[$key] = (int) $sectionId;
            }
            $where[] = 'ans.id_section IN (' . implode(', ', $placeholders) . ')';
        }
        if ($genre !== null && $genre !== '') {
            $where[] = 'a.genre_a = :genre';
            $parameters[':genre'] = $genre;
        }

        $statement = $this->connection->prepare(
            'SELECT a.*, a.id_animateur, a.nom_a AS nom, a.prenom_a AS prenom,
                    a.nom_a, a.prenom_a, a.genre_a AS genre, a.genre_a, a.tel,
                    ans.id_session, ans.id_section, s.nom_section, s.nom_section AS section
             FROM animateur a
             INNER JOIN animateur_session ans ON ans.id_animateur = a.id_animateur
             LEFT JOIN section s ON s.id_section = ans.id_section
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY a.id_animateur ASC'
        );
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
