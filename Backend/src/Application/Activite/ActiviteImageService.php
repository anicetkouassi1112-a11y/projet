<?php

declare(strict_types=1);

namespace Patro\Application\Activite;

use Patro\Domain\Activite\Repository\ActiviteImageRepository;
use Patro\Infrastructure\Storage\ActiviteImageStorage;
use Patro\Inscription\SessionService;
use PDOException;
use Throwable;

final class ActiviteImageService
{
    public function __construct(
        private ActiviteImageRepository $repository,
        private SessionService $sessions,
        private ActiviteImageStorage $storage
    ) {
    }

    public function add(array $file, string $title, int $order, bool $visible, string $description = ''): array
    {
        try {
            $stored = $this->storage->storeUpload($file);
            $this->repository->ensureSessionColumn();
            $id = $this->repository->create(
                $this->sessions->getActiveAdminSessionId(),
                $this->clean($title, 255),
                $stored['path'],
                $order,
                $visible,
                $this->clean($description, 5000)
            );

            return ['success' => true, 'message' => 'Image ajoutee avec succes.', 'id' => $id];
        } catch (Throwable $exception) {
            if (isset($stored['path'])) {
                $this->storage->remove($stored['path']);
            }
            return $this->failure($exception, 'Save activite image error');
        }
    }

    public function updateMeta(int $id, string $title, int $order, bool $visible, string $description = ''): array
    {
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Image introuvable.'];
        }

        try {
            $sessionId = $this->sessions->getActiveAdminSessionId();
            $this->repository->ensureSessionColumn();
            if (!$this->repository->existsInSession($id, $sessionId)) {
                return ['success' => false, 'message' => 'Image introuvable.'];
            }

            $updated = $this->repository->updateMeta(
                $id,
                $sessionId,
                $this->clean($title, 255),
                $order,
                $visible,
                $this->clean($description, 5000)
            );

            return $updated
                ? ['success' => true, 'message' => 'Image mise a jour.']
                : ['success' => false, 'message' => 'Image introuvable.'];
        } catch (Throwable $exception) {
            return $this->failure($exception, 'Update activite image error');
        }
    }

    public function replace(int $id, string $tmpPath): array
    {
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Image introuvable.'];
        }

        $newPath = null;
        try {
            $sessionId = $this->sessions->getActiveAdminSessionId();
            $this->repository->ensureSessionColumn();
            $oldPath = $this->repository->pathByIdAndSession($id, $sessionId);
            if ($oldPath === null) {
                return ['success' => false, 'message' => 'Image introuvable.'];
            }

            $stored = $this->storage->replaceUpload($tmpPath, $this->storage->validateReplacement($tmpPath));
            $newPath = $stored['path'];
            if (!$this->repository->replaceImagePath($id, $sessionId, $newPath)) {
                $this->storage->remove($newPath);
                return ['success' => false, 'message' => 'Image introuvable.'];
            }

            $this->storage->remove($oldPath);
            return ['success' => true, 'message' => 'Image remplacee avec succes.'];
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                $this->storage->remove($newPath);
            }
            return $this->failure($exception, 'Replace activite image error');
        }
    }

    public function delete(int $id): array
    {
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Image introuvable.'];
        }

        try {
            $sessionId = $this->sessions->getActiveAdminSessionId();
            $this->repository->ensureSessionColumn();
            $image = $this->repository->findByIdAndSession($id, $sessionId);
            if (!$image || !$this->repository->deleteBySession($id, $sessionId)) {
                return ['success' => false, 'message' => 'Image introuvable.'];
            }

            $this->storage->remove((string) ($image['image_path'] ?? ''));
            return ['success' => true, 'message' => 'Image supprimee.'];
        } catch (Throwable $exception) {
            return $this->failure($exception, 'Delete activite image error');
        }
    }

    public function resolvePath(string $storedPath): string
    {
        return $this->storage->resolve($storedPath);
    }

    private function clean(string $value, int $length): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
    }

    private function failure(Throwable $exception, string $context): array
    {
        error_log($context . ': ' . $exception->getMessage());
        return ['success' => false, 'message' => $exception instanceof PDOException ? 'Erreur base de donnees.' : $exception->getMessage()];
    }
}
