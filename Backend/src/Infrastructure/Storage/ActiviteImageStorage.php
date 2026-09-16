<?php

declare(strict_types=1);

namespace Patro\Infrastructure\Storage;

use Patro\Config\Environment;
use RuntimeException;

final class ActiviteImageStorage
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(private string $baseDirectory)
    {
    }

    public function maxBytes(): int
    {
        return max(1, Environment::int('ACTIVITE_MAX_SIZE_MB', 5)) * 1024 * 1024;
    }

    /** @return array{path:string,mime:string} */
    public function storeUpload(array $file): array
    {
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $this->validateUpload($file, $tmpName);
        $mime = $this->detectMime($tmpName);
        $this->validateImage($tmpName, $mime);

        $extension = self::MIME_EXTENSIONS[$mime];
        $destination = $this->newPath($extension);
        if (!move_uploaded_file($tmpName, $destination)) {
            throw new RuntimeException('Impossible d enregistrer l image.');
        }
        @chmod($destination, 0640);

        return ['path' => 'activites/' . basename($destination), 'mime' => $mime];
    }

    public function resolve(string $storedPath): string
    {
        $relative = basename(str_replace('\\', '/', $storedPath));
        $path = $this->directory() . DIRECTORY_SEPARATOR . $relative;

        return is_file($path) ? $path : '';
    }

    public function remove(string $storedPath): void
    {
        $path = $this->resolve($storedPath);
        if ($path !== '') {
            @unlink($path);
        }
    }

    public function validateReplacement(string $tmpPath): string
    {
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException('Upload invalide.');
        }
        if ((int) filesize($tmpPath) > $this->maxBytes()) {
            throw new RuntimeException('Image trop volumineuse.');
        }

        $mime = $this->detectMime($tmpPath);
        $this->validateImage($tmpPath, $mime);

        return $mime;
    }

    /** @return array{path:string,mime:string} */
    public function replaceUpload(string $tmpPath, string $mime): array
    {
        $validatedMime = $this->validateReplacement($tmpPath);
        if ($validatedMime !== $mime) {
            throw new RuntimeException('Format accepte: JPG, PNG ou WEBP.');
        }

        $destination = $this->newPath(self::MIME_EXTENSIONS[$mime]);
        if (!move_uploaded_file($tmpPath, $destination)) {
            throw new RuntimeException('Impossible de deplacer le fichier uploade.');
        }
        @chmod($destination, 0640);

        return ['path' => 'activites/' . basename($destination), 'mime' => $mime];
    }

    private function validateUpload(array $file, string $tmpName): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Veuillez choisir une image valide.');
        }
        if ((int) ($file['size'] ?? 0) > $this->maxBytes()) {
            throw new RuntimeException('Image trop volumineuse.');
        }
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Upload invalide.');
        }
    }

    private function detectMime(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($path);
        if (!isset(self::MIME_EXTENSIONS[$mime])) {
            throw new RuntimeException('Format accepte: JPG, PNG ou WEBP.');
        }

        return $mime;
    }

    private function validateImage(string $path, string $mime): void
    {
        if (!is_array(@getimagesize($path)) || !isset(self::MIME_EXTENSIONS[$mime])) {
            throw new RuntimeException('Fichier image invalide.');
        }
    }

    private function directory(): string
    {
        $configured = trim((string) Environment::get('ACTIVITE_STORAGE_DIR', ''));
        $path = $configured !== '' && !str_contains($configured, '..')
            ? $this->baseDirectory . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configured)
            : $this->baseDirectory . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'activites';

        if (!is_dir($path) && !mkdir($path, 0770, true) && !is_dir($path)) {
            throw new RuntimeException('Impossible de creer le stockage des images.');
        }

        return $path;
    }

    private function newPath(string $extension): string
    {
        return $this->directory() . DIRECTORY_SEPARATOR
            . 'activite_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
    }
}
