<?php

declare(strict_types=1);

namespace Patro\Domain\Inscription\Repository;

use PDO;

final class SectionRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /** @return list<array<string,mixed>> */
    public function findAll(): array
    {
        $statement = $this->connection->query(
            'SELECT id_section, nom_section, description, genre, age_min, age_max
             FROM section
             ORDER BY genre ASC, age_min ASC, age_max ASC, nom_section ASC'
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function existsByName(string $name): bool
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM section WHERE nom_section = :name');
        $statement->execute([':name' => $name]);

        return (int) $statement->fetchColumn() > 0;
    }

    /** @return array{id_section:int,nom_section:string,genre:string}|null */
    public function findById(int $sectionId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id_section, nom_section, genre
             FROM section
             WHERE id_section = :id
             LIMIT 1'
        );
        $statement->execute([':id' => $sectionId]);
        $section = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($section)) {
            return null;
        }

        return [
            'id_section' => (int) $section['id_section'],
            'nom_section' => (string) $section['nom_section'],
            'genre' => (string) $section['genre'],
        ];
    }

    /** @return array<string,mixed> */
    public function findAgeOverlap(string $genre, int $ageMin, int $ageMax): array
    {
        $statement = $this->connection->prepare(
            'SELECT id_section, nom_section, age_min, age_max
             FROM section
             WHERE genre = :genre AND age_min <= :age_max AND age_max >= :age_min
             ORDER BY age_min ASC, age_max ASC LIMIT 1'
        );
        $statement->execute([
            ':genre' => $genre,
            ':age_min' => $ageMin,
            ':age_max' => $ageMax,
        ]);

        return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(string $name, ?string $description, string $genre, int $ageMin, int $ageMax): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO section (nom_section, description, genre, age_min, age_max)
             VALUES (:name, :description, :genre, :age_min, :age_max)'
        );
        $statement->execute([
            ':name' => $name,
            ':description' => $description,
            ':genre' => $genre,
            ':age_min' => $ageMin,
            ':age_max' => $ageMax,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function findMatching(string $genre, int $age): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id_section, nom_section, description, genre, age_min, age_max
             FROM section
             WHERE genre = :genre AND :age BETWEEN age_min AND age_max
             ORDER BY age_min ASC, age_max ASC, nom_section ASC LIMIT 1'
        );
        $statement->execute([':genre' => $genre, ':age' => $age]);
        $section = $statement->fetch(PDO::FETCH_ASSOC);

        return $section ?: null;
    }
}
