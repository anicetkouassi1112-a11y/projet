<?php

declare(strict_types=1);

namespace Patro\Domain\Inscription\Repository;

use PDO;

final class InscriptionRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /** @return list<array<string,mixed>> */
    public function findBySectionIds(array $sectionIds, int $sessionId, ?string $genre, bool $isScolaire, string $state = 'inscrit'): array
    {
        $parameters = [':session_id' => $sessionId, ':state' => $state];
        $where = ['i.id_session = :session_id', 'i.etat = :state'];

        if (!$isScolaire && $sectionIds !== []) {
            $placeholders = [];
            foreach (array_values($sectionIds) as $index => $sectionId) {
                $key = ':section_' . $index;
                $placeholders[] = $key;
                $parameters[$key] = (int) $sectionId;
            }
            $where[] = 'i.id_section IN (' . implode(', ', $placeholders) . ')';
        }
        if ($genre !== null) {
            $where[] = 'u.genre = :genre';
            $parameters[':genre'] = $genre;
        }

        $statement = $this->connection->prepare(
            'SELECT i.*, u.nom, u.prenom, u.date_naissance, u.genre, u.tel, u.adresse, s.nom_section
             FROM inscription i
             INNER JOIN utilisateur u ON u.id_utilisateur = i.id_utilisateur
             LEFT JOIN section s ON s.id_section = i.id_section
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY i.id_inscription ASC'
        );
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function findByGenre(string $genre, int $sessionId, string $state = 'inscrit'): array
    {
        $statement = $this->connection->prepare(
            'SELECT u.*, i.id_inscription, i.id_inscription AS id_inscrit, i.id_section,
                    i.identifiant, i.etat, i.montant_inscription, i.prix_tee_shirt, i.taille_tee_shirt,
                    s.nom_section AS section, s.nom_section
             FROM inscription i
             INNER JOIN utilisateur u ON u.id_utilisateur = i.id_utilisateur
             LEFT JOIN section s ON s.id_section = i.id_section
             WHERE u.genre = :genre AND i.id_session = :session_id AND i.etat = :state
             ORDER BY i.id_inscription ASC'
        );
        $statement->execute([':genre' => $genre, ':session_id' => $sessionId, ':state' => $state]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function search(string $term, int $yearId, string $sessionType, string $state = 'inscrit'): array
    {
        $statement = $this->connection->prepare(
            'SELECT u.*, i.id_inscription AS id_inscrit, i.id_inscription,
                    i.id_section, i.identifiant, i.etat, i.montant_inscription,
                    i.prix_tee_shirt, i.taille_tee_shirt,
                    s.nom_section AS section
             FROM inscription i
             INNER JOIN utilisateur u ON u.id_utilisateur = i.id_utilisateur
             LEFT JOIN section s ON s.id_section = i.id_section
             INNER JOIN session ses ON ses.id_session = i.id_session
             WHERE ses.annee_id = :year_id
               AND ses.type_session = :session_type
               AND i.etat = :state
               AND (u.nom LIKE :term OR u.prenom LIKE :term
                    OR CONCAT(u.nom, " ", u.prenom) LIKE :term)
             ORDER BY i.id_inscription ASC'
        );
        $statement->execute([
            ':year_id' => $yearId,
            ':session_type' => $sessionType,
            ':state' => $state,
            ':term' => '%' . $term . '%',
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function findBySections(array $sectionIds, int $sessionId, ?string $genre, string $state = 'inscrit'): array
    {
        return $this->findBySectionIds($sectionIds, $sessionId, $genre, false, $state);
    }

    /** @return array<string,mixed> */
    public function findById(int $inscriptionId): array
    {
        $statement = $this->connection->prepare(
            'SELECT u.*, i.id_inscription AS id_inscrit, i.id_inscription, i.id_session,
                    i.id_section, i.identifiant, i.montant_inscription, i.prix_tee_shirt,
                    i.taille_tee_shirt, i.etat, i.created_at, i.updated_at,
                    s.nom_section AS section, s.nom_section, ses.type_session,
                    a.idannee AS annee_id, a.ans AS annee
             FROM inscription i
             INNER JOIN utilisateur u ON u.id_utilisateur = i.id_utilisateur
             LEFT JOIN section s ON s.id_section = i.id_section
             INNER JOIN session ses ON ses.id_session = i.id_session
             INNER JOIN annee a ON a.idannee = ses.annee_id
             WHERE i.id_inscription = :id
             LIMIT 1'
        );
        $statement->execute([':id' => $inscriptionId]);

        return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function findIdByIdentity(
        string $lastName,
        string $firstName,
        string $birthDate,
        int $yearId,
        string $sessionType
    ): ?int {
        $statement = $this->connection->prepare(
            'SELECT i.id_inscription
             FROM inscription i
             INNER JOIN utilisateur u ON u.id_utilisateur = i.id_utilisateur
             INNER JOIN session s ON s.id_session = i.id_session
             WHERE u.nom = :last_name AND u.prenom = :first_name
               AND u.date_naissance = :birth_date AND s.annee_id = :year_id
               AND s.type_session = :session_type
             LIMIT 1'
        );
        $statement->execute([
            ':last_name' => $lastName, ':first_name' => $firstName,
            ':birth_date' => $birthDate, ':year_id' => $yearId,
            ':session_type' => $sessionType,
        ]);
        $value = $statement->fetchColumn();

        return $value === false ? null : (int) $value;
    }

    public function updateState(int $inscriptionId, string $state): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE inscription SET etat = :state WHERE id_inscription = :id'
        );
        $statement->execute([':state' => $state, ':id' => $inscriptionId]);

        return $statement->rowCount() > 0;
    }

    public function updateParticipant(
        int $userId,
        string $lastName,
        string $firstName,
        string $birthDate,
        string $gender,
        string $phone,
        string $address
    ): void {
        $statement = $this->connection->prepare(
            'UPDATE utilisateur
             SET nom = :last_name, prenom = :first_name, date_naissance = :birth_date,
                 genre = :gender, tel = :phone, adresse = :address
             WHERE id_utilisateur = :id'
        );
        $statement->execute([
            ':last_name' => $lastName, ':first_name' => $firstName,
            ':birth_date' => $birthDate, ':gender' => $gender,
            ':phone' => $phone, ':address' => $address, ':id' => $userId,
        ]);
    }

    public function updateSection(int $inscriptionId, ?int $sectionId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE inscription SET id_section = :section_id WHERE id_inscription = :id'
        );
        $statement->execute([':section_id' => $sectionId, ':id' => $inscriptionId]);
    }

    /** @return list<array<string,mixed>> */
    public function findPending(int $yearId, string $sessionType, ?string $term = null): array
    {
        $conditions = [
            's.annee_id = :year_id',
            's.type_session = :session_type',
            'i.etat = :state',
        ];
        $parameters = [
            ':year_id' => $yearId,
            ':session_type' => $sessionType,
            ':state' => 'En attente',
        ];
        if ($term !== null && $term !== '') {
            $conditions[] = '(u.nom LIKE :term OR u.prenom LIKE :term OR CONCAT(u.nom, " ", u.prenom) LIKE :term)';
            $parameters[':term'] = '%' . $term . '%';
        }

        $statement = $this->connection->prepare(
            'SELECT i.id_inscription, u.nom, u.prenom, u.date_naissance, u.genre,
                    i.etat, i.montant_inscription, sec.nom_section
             FROM inscription i
             INNER JOIN utilisateur u ON u.id_utilisateur = i.id_utilisateur
             LEFT JOIN section sec ON sec.id_section = i.id_section
             INNER JOIN session s ON s.id_session = i.id_session
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY i.created_at DESC'
        );
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function findPaidTeeShirts(int $yearId, string $sessionType, ?string $genre = null, ?string $term = null): array
    {
        $conditions = [
            's.annee_id = :year_id',
            's.type_session = :session_type',
            'i.etat = :state',
            'i.prix_tee_shirt > 0',
        ];
        $parameters = [
            ':year_id' => $yearId,
            ':session_type' => $sessionType,
            ':state' => 'inscrit',
        ];
        if ($genre !== null && $genre !== '') {
            $conditions[] = 'u.genre = :genre';
            $parameters[':genre'] = $genre;
        }
        if ($term !== null && $term !== '') {
            $conditions[] = '(u.nom LIKE :term OR u.prenom LIKE :term OR CONCAT(u.nom, " ", u.prenom) LIKE :term)';
            $parameters[':term'] = '%' . $term . '%';
        }

        $orderBy = $sessionType === 'vacance'
            ? 'sec.nom_section ASC, u.nom ASC, u.prenom ASC'
            : 'u.genre ASC, u.nom ASC, u.prenom ASC';
        $statement = $this->connection->prepare(
            'SELECT i.id_inscription AS id_inscrit, i.identifiant,
                    i.prix_tee_shirt, i.taille_tee_shirt,
                    u.nom, u.prenom, u.genre, u.tel, sec.nom_section AS section
             FROM inscription i
             INNER JOIN utilisateur u ON u.id_utilisateur = i.id_utilisateur
             INNER JOIN session s ON s.id_session = i.id_session
             LEFT JOIN section sec ON sec.id_section = i.id_section
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY ' . $orderBy
        );
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function validatePending(int $inscriptionId): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE inscription
             SET etat = :new_state
             WHERE id_inscription = :id AND etat = :old_state'
        );
        $statement->execute([
            ':new_state' => 'inscrit',
            ':id' => $inscriptionId,
            ':old_state' => 'En attente',
        ]);

        return $statement->rowCount() > 0;
    }

    /** @return array{identifiant:string,ordre_inscription:int} */
    public function allocateIdentifier(?string $section, string $genre, int $digits = 3): array
    {
        $digits = max(1, $digits);
        $genreCode = $this->genreCode($genre);
        $section = trim((string) $section);
        $hasSection = $section !== '';
        $sectionCode = $hasSection ? $this->sectionCode($section) : null;
        $sequenceName = $hasSection
            ? 'inscrits:' . $sectionCode . ':' . $genreCode
            : 'inscrits:GEN:' . $genreCode;

        $sql = $hasSection
            ? 'INSERT INTO identifiant_sequences (sequence_name, last_number)
               SELECT :sequence_name, GREATEST(COUNT(*), COALESCE(MAX(CAST(SUBSTRING_INDEX(i.identifiant, "-", -1) AS UNSIGNED)), 0))
               FROM inscription i INNER JOIN section s ON s.id_section = i.id_section
               INNER JOIN utilisateur u ON u.id_utilisateur = i.id_utilisateur
               WHERE s.nom_section = :section AND u.genre = :genre
               ON DUPLICATE KEY UPDATE sequence_name = sequence_name'
            : 'INSERT INTO identifiant_sequences (sequence_name, last_number)
               SELECT :sequence_name, GREATEST(COUNT(*), COALESCE(MAX(CAST(SUBSTRING_INDEX(i.identifiant, "-", -1) AS UNSIGNED)), 0))
               FROM inscription i INNER JOIN utilisateur u ON u.id_utilisateur = i.id_utilisateur
               WHERE i.id_section IS NULL AND u.genre = :genre
               ON DUPLICATE KEY UPDATE sequence_name = sequence_name';
        $parameters = [':sequence_name' => $sequenceName, ':genre' => $genre];
        if ($hasSection) {
            $parameters[':section'] = $section;
        }
        $this->connection->prepare($sql)->execute($parameters);

        $statement = $this->connection->prepare(
            'SELECT last_number FROM identifiant_sequences WHERE sequence_name = :sequence_name FOR UPDATE'
        );
        $statement->execute([':sequence_name' => $sequenceName]);
        $last = $statement->fetchColumn();
        if ($last === false) {
            throw new \RuntimeException('Compteur d identifiants introuvable.');
        }
        $next = (int) $last + 1;
        if (strlen((string) $next) > $digits) {
            throw new \RuntimeException('La largeur configuree pour l ordre d inscription est depassee.');
        }
        $this->connection->prepare(
            'UPDATE identifiant_sequences SET last_number = :last_number WHERE sequence_name = :sequence_name'
        )->execute([':last_number' => $next, ':sequence_name' => $sequenceName]);

        return [
            'identifiant' => $hasSection
                ? sprintf('%s-%s-%s', $sectionCode, $genreCode, str_pad((string) $next, $digits, '0', STR_PAD_LEFT))
                : sprintf('%s-%s', $genreCode, str_pad((string) $next, $digits, '0', STR_PAD_LEFT)),
            'ordre_inscription' => $next,
        ];
    }

    public function createOrUpdateUser(string $lastName, string $firstName, string $birthDate, string $genre, string $phone, string $address): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO utilisateur (nom, prenom, date_naissance, genre, tel, adresse)
             VALUES (:last_name, :first_name, :birth_date, :genre, :phone, :address)
             ON DUPLICATE KEY UPDATE id_utilisateur = LAST_INSERT_ID(id_utilisateur),
                genre = VALUES(genre), tel = VALUES(tel), adresse = VALUES(adresse), updated_at = NOW()'
        );
        $statement->execute([
            ':last_name' => $lastName, ':first_name' => $firstName, ':birth_date' => $birthDate,
            ':genre' => $genre, ':phone' => $phone, ':address' => $address,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function create(
        string $identifier,
        int $userId,
        int $sessionId,
        ?int $sectionId,
        int $registrationAmount,
        int $shirtPrice,
        ?string $shirtSize,
        string $state
    ): int {
        $statement = $this->connection->prepare(
            'INSERT INTO inscription
             (identifiant, id_utilisateur, id_session, id_section, montant_inscription, prix_tee_shirt, taille_tee_shirt, etat)
             VALUES (:identifier, :user_id, :session_id, :section_id, :registration_amount, :shirt_price, :shirt_size, :state)'
        );
        $statement->execute([
            ':identifier' => $identifier, ':user_id' => $userId, ':session_id' => $sessionId,
            ':section_id' => $sectionId, ':registration_amount' => $registrationAmount,
            ':shirt_price' => $shirtPrice, ':shirt_size' => $shirtSize, ':state' => $state,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function genreCode(string $genre): string
    {
        return match ($this->lookup($genre)) {
            'garcon', 'masculin', 'm' => 'M',
            'fille', 'feminin', 'f' => 'F',
            default => throw new \InvalidArgumentException('Genre invalide pour la generation de l identifiant.'),
        };
    }

    private function sectionCode(string $section): string
    {
        $codes = [
            'stange' => 'AN', 'sttharcis' => 'TH', 'stkizito' => 'KI', 'stdominique' => 'DO',
            'stvincent' => 'VI', 'stjoseph' => 'JO', 'antoinettemeo' => 'AM',
            'mariagoretti' => 'MG', 'therese' => 'TR', 'bernadette' => 'BE',
        ];
        $key = $this->lookup($section);
        if (isset($codes[$key])) {
            return $codes[$key];
        }
        $fallback = strtoupper(preg_replace('/[^A-Z0-9]+/', '', strtoupper($section)) ?? '');
        return substr($fallback !== '' ? $fallback : 'XX', 0, 12);
    }

    private function lookup(string $value): string
    {
        $value = function_exists('mb_strtolower') ? mb_strtolower(trim($value), 'UTF-8') : strtolower(trim($value));
        return preg_replace('/[^a-z0-9]+/', '', strtr($value, ['ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e'])) ?? '';
    }
}
