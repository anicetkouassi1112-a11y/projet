<?php

declare(strict_types=1);

namespace Patro\Domain\Activite\Repository;

use PDO;

final class ActiviteImageRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function ensureSessionColumn(): bool
    {
        try {
            $column = $this->connection->query("SHOW COLUMNS FROM activite_images LIKE 'session_id'")->fetch(PDO::FETCH_ASSOC);
            if (!$column) {
                $this->connection->exec('ALTER TABLE activite_images ADD COLUMN session_id INT NULL AFTER id');
                $this->connection->exec('CREATE INDEX idx_activite_session_visible_ordre ON activite_images (session_id, visible, ordre)');
            }

            return true;
        } catch (\Throwable $exception) {
            error_log('Activite images session column error: ' . $exception->getMessage());
            return false;
        }
    }

    /** @return list<array<string,mixed>> */
    public function findVisibleBySession(int $sessionId): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, titre, image_path, ordre, visible, created_at, description, session_id
             FROM activite_images
             WHERE visible = 1
               AND session_id = :session_id
             ORDER BY ordre ASC, id ASC'
        );
        $statement->execute([':session_id' => $sessionId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function findAllBySession(int $sessionId): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, titre, image_path, ordre, visible, created_at, description, session_id
             FROM activite_images
             WHERE session_id = :session_id
             ORDER BY ordre ASC, id ASC'
        );
        $statement->execute([':session_id' => $sessionId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(int $sessionId, string $title, string $imagePath, int $order, bool $visible, string $description): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO activite_images (session_id, titre, image_path, ordre, visible, description)
             VALUES (:session_id, :titre, :image_path, :ordre, :visible, :description)'
        );
        $statement->execute([
            ':session_id' => $sessionId,
            ':titre' => $title !== '' ? $title : null,
            ':image_path' => $imagePath,
            ':ordre' => max(0, min(9999, $order)),
            ':visible' => $visible ? 1 : 0,
            ':description' => $description !== '' ? $description : null,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function existsInSession(int $id, int $sessionId): bool
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM activite_images WHERE id = :id AND session_id = :session_id');
        $statement->execute([':id' => $id, ':session_id' => $sessionId]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function updateMeta(int $id, int $sessionId, ?string $title, int $order, bool $visible, ?string $description): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE activite_images
             SET titre = :titre,
                 ordre = :ordre,
                 visible = :visible,
                 description = :description
             WHERE id = :id AND session_id = :session_id'
        );

        $result = $statement->execute([
            ':titre' => $title !== '' ? $title : null,
            ':ordre' => max(0, min(9999, $order)),
            ':visible' => $visible ? 1 : 0,
            ':description' => $description !== '' ? $description : null,
            ':id' => $id,
            ':session_id' => $sessionId,
        ]);

        return $result && $statement->rowCount() > 0;
    }

    public function replaceImagePath(int $id, int $sessionId, string $path): bool
    {
        $statement = $this->connection->prepare('UPDATE activite_images SET image_path = :path WHERE id = :id AND session_id = :session_id');
        $statement->execute([':path' => $path, ':id' => $id, ':session_id' => $sessionId]);

        return $statement->rowCount() > 0;
    }

    public function pathByIdAndSession(int $id, int $sessionId): ?string
    {
        $statement = $this->connection->prepare('SELECT image_path FROM activite_images WHERE id = :id AND session_id = :session_id LIMIT 1');
        $statement->execute([':id' => $id, ':session_id' => $sessionId]);
        $value = $statement->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    public function deleteBySession(int $id, int $sessionId): bool
    {
        $statement = $this->connection->prepare('DELETE FROM activite_images WHERE id = :id AND session_id = :session_id');
        $statement->execute([':id' => $id, ':session_id' => $sessionId]);

        return $statement->rowCount() > 0;
    }

    /** @return array<string,mixed>|null */
    public function findByIdAndSession(int $id, int $sessionId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, titre, image_path, ordre, visible, created_at, description, session_id
             FROM activite_images
             WHERE id = :id AND session_id = :session_id
             LIMIT 1'
        );
        $statement->execute([':id' => $id, ':session_id' => $sessionId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
