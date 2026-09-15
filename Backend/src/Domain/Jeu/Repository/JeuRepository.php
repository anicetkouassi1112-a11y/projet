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
    public function findAll(): array
    {
        $statement = $this->connection->query('SELECT * FROM jeux ORDER BY nom ASC');
        return $statement->fetchAll(PDO::FETCH_ASSOC);
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

    /** @return list<array<string,mixed>> */
    public function types(): array
    {
        $statement = $this->connection->query(
            'SELECT type_jeu, COUNT(id) AS total FROM jeux GROUP BY type_jeu ORDER BY type_jeu ASC'
        );
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $this->connection->beginTransaction();
        try {
            $sql = 'INSERT INTO jeux (
                nom, objectif, age_conseille, duree, nombre_joueurs,
                lieu, type_jeu, materiel, mise_en_place, deroulement,
                regles, fin_jeu, but_pedagogique
            ) VALUES (
                :nom, :objectif, :age_conseille, :duree, :nombre_joueurs,
                :lieu, :type_jeu, :materiel, :mise_en_place, :deroulement,
                :regles, :fin_jeu, :but_pedagogique
            )';
            $statement = $this->connection->prepare($sql);
            $statement->execute($this->normalizeData($data));
            $id = (int) $this->connection->lastInsertId();
            $this->connection->commit();
            return $id;
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    public function update(int $id, array $data): bool
    {
        $normalized = $this->normalizeData($data);
        $normalized[':id'] = $id;

        $fields = [
            'nom', 'objectif', 'age_conseille', 'duree', 'nombre_joueurs',
            'lieu', 'type_jeu', 'materiel', 'mise_en_place', 'deroulement',
            'regles', 'fin_jeu', 'but_pedagogique',
        ];
        $set = [];
        foreach ($fields as $field) {
            $key = ':' . $field;
            if (array_key_exists($key, $normalized)) {
                $set[] = $field . ' = ' . $key;
            }
        }

        if ($set === []) {
            return false;
        }

        $sql = 'UPDATE jeux SET ' . implode(', ', $set) . ' WHERE id = :id';
        $statement = $this->connection->prepare($sql);
        return $statement->execute($normalized);
    }

    public function delete(int $id): bool
    {
        $statement = $this->connection->prepare('DELETE FROM jeux WHERE id = :id');
        $statement->execute([':id' => $id]);
        return $statement->rowCount() > 0;
    }

    /** @param array<string,mixed> $data */
    private function normalizeData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            $field = ltrim((string) $key, ':');
            if ($field === '') {
                continue;
            }
            $normalized[':' . $field] = $value;
        }

        $fields = ['nom', 'objectif', 'age_conseille', 'duree', 'nombre_joueurs', 'lieu', 'type_jeu', 'materiel', 'mise_en_place', 'deroulement', 'regles', 'fin_jeu', 'but_pedagogique'];
        foreach ($fields as $field) {
            if (!array_key_exists(':' . $field, $normalized)) {
                $normalized[':' . $field] = '';
            }
        }

        return $normalized;
    }
}
