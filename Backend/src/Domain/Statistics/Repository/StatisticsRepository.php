<?php

declare(strict_types=1);

namespace Patro\Domain\Statistics\Repository;

use PDO;

final class StatisticsRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /** @return array<string,mixed> */
    public function totals(int $yearId, string $sessionType, string $validatedState, string $male, string $female): array
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(i.id_inscription) AS total,
                    SUM(CASE WHEN u.genre = :male THEN 1 ELSE 0 END) AS garcons,
                    SUM(CASE WHEN u.genre = :female THEN 1 ELSE 0 END) AS filles
             FROM inscription i
             JOIN utilisateur u ON i.id_utilisateur = u.id_utilisateur
             JOIN session s ON i.id_session = s.id_session
             WHERE s.annee_id = :year_id AND s.type_session = :session_type AND i.etat = :state'
        );
        $statement->execute([
            ':male' => $male, ':female' => $female,
            ':year_id' => $yearId, ':session_type' => $sessionType, ':state' => $validatedState,
        ]);

        return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function pendingCount(int $yearId, string $sessionType, string $state): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(i.id_inscription)
             FROM inscription i JOIN session s ON i.id_session = s.id_session
             WHERE s.annee_id = :year_id AND s.type_session = :session_type AND i.etat = :state'
        );
        $statement->execute([':year_id' => $yearId, ':session_type' => $sessionType, ':state' => $state]);

        return (int) $statement->fetchColumn();
    }

    /** @return list<array<string,mixed>> */
    public function sectionTotals(int $yearId, string $sessionType, string $state): array
    {
        $statement = $this->connection->prepare(
            'SELECT sec.nom_section AS section_nom, COUNT(i.id_inscription) AS total
             FROM inscription i JOIN section sec ON i.id_section = sec.id_section
             JOIN session s ON i.id_session = s.id_session
             WHERE s.annee_id = :year_id AND s.type_session = :session_type AND i.etat = :state
             GROUP BY sec.id_section, sec.nom_section
             ORDER BY total DESC, sec.nom_section ASC'
        );
        $statement->execute([':year_id' => $yearId, ':session_type' => $sessionType, ':state' => $state]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed> */
    public function teeShirtTotals(int $yearId, string $sessionType, string $state): array
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(i.id_inscription) AS total_tee_shirts,
                    COALESCE(SUM(i.prix_tee_shirt), 0) AS total_ventes,
                    SUM(CASE WHEN i.taille_tee_shirt = "S" THEN 1 ELSE 0 END) AS taille_s,
                    SUM(CASE WHEN i.taille_tee_shirt = "M" THEN 1 ELSE 0 END) AS taille_m,
                    SUM(CASE WHEN i.taille_tee_shirt = "L" THEN 1 ELSE 0 END) AS taille_l,
                    SUM(CASE WHEN i.taille_tee_shirt = "XL" THEN 1 ELSE 0 END) AS taille_xl,
                    SUM(CASE WHEN i.taille_tee_shirt = "XXL" THEN 1 ELSE 0 END) AS taille_xxl
             FROM inscription i JOIN session s ON i.id_session = s.id_session
             WHERE s.annee_id = :year_id AND s.type_session = :session_type
               AND i.etat = :state AND i.prix_tee_shirt > 0'
        );
        $statement->execute([':year_id' => $yearId, ':session_type' => $sessionType, ':state' => $state]);

        return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function paidTeeShirts(
        int $yearId,
        string $sessionType,
        string $state,
        ?string $genre = null,
        string $search = '',
        bool $groupBySection = false
    ): array {
        $where = [
            's.annee_id = :year_id',
            's.type_session = :session_type',
            'i.etat = :state',
            'i.prix_tee_shirt > 0',
        ];
        $parameters = [
            ':year_id' => $yearId,
            ':session_type' => $sessionType,
            ':state' => $state,
        ];
        if ($genre !== null && $genre !== '') {
            $where[] = 'u.genre = :genre';
            $parameters[':genre'] = $genre;
        }
        if ($search !== '') {
            $where[] = '(u.nom LIKE :search_nom OR u.prenom LIKE :search_prenom OR CONCAT(u.nom, " ", u.prenom) LIKE :search_fullname)';
            $term = '%' . $search . '%';
            $parameters[':search_nom'] = $term;
            $parameters[':search_prenom'] = $term;
            $parameters[':search_fullname'] = $term;
        }
        $orderBy = $groupBySection
            ? 'sec.nom_section ASC, u.nom ASC, u.prenom ASC'
            : 'u.genre ASC, u.nom ASC, u.prenom ASC';
        $statement = $this->connection->prepare(
            'SELECT i.id_inscription AS id_inscrit, i.identifiant, i.prix_tee_shirt,
                    i.taille_tee_shirt, u.nom, u.prenom, u.genre, u.tel,
                    sec.nom_section AS section
             FROM inscription i
             INNER JOIN utilisateur u ON u.id_utilisateur = i.id_utilisateur
             INNER JOIN session s ON s.id_session = i.id_session
             LEFT JOIN section sec ON sec.id_section = i.id_section
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY ' . $orderBy
        );
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function registrationAmount(int $yearId, string $sessionType, string $state): int
    {
        $statement = $this->connection->prepare(
            'SELECT COALESCE(SUM(i.montant_inscription), 0)
             FROM inscription i JOIN session s ON i.id_session = s.id_session
             WHERE s.annee_id = :year_id AND s.type_session = :session_type AND i.etat = :state'
        );
        $statement->execute([':year_id' => $yearId, ':session_type' => $sessionType, ':state' => $state]);

        return (int) $statement->fetchColumn();
    }
}
