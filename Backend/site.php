<?php

/**
 * Contenu public et animateur: jeux, images d'activite, contact.
 */

function activiteStorageDirectory(): string
{
    $configured = trim((string) app_env('ACTIVITE_STORAGE_DIR', ''));
    if ($configured !== '' && !str_contains($configured, '..')) {
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configured);
        if (is_dir($path) || mkdir($path, 0770, true)) {
            return $path;
        }
    }

    $default = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'activites';
    if (!is_dir($default)) {
        mkdir($default, 0770, true);
    }

    return $default;
}

function getVisibleActiviteImages(?PDO $connect = null): array
{
    try {
        $repository = appContainer()->get(\Patro\Domain\Activite\Repository\ActiviteImageRepository::class);
        if (!$repository->ensureSessionColumn()) {
            return [];
        }

        return $repository->findVisibleBySession(appContainer()->get(\Patro\Inscription\SessionService::class)->getActiveAdminSessionId());
    } catch (PDOException $e) {
        error_log('Visible activite images error: ' . $e->getMessage());
        return [];
    }
}

function getAllActiviteImages(?PDO $connect = null): array
{
    try {
        $repository = appContainer()->get(\Patro\Domain\Activite\Repository\ActiviteImageRepository::class);
        if (!$repository->ensureSessionColumn()) {
            return [];
        }

        return $repository->findAllBySession(appContainer()->get(\Patro\Inscription\SessionService::class)->getActiveAdminSessionId());
    } catch (PDOException $e) {
        error_log('All activite images error: ' . $e->getMessage());
        return [];
    }
}

function activiteImageUrl(int $id): string
{
    return app_url('public/media/activite.php') . '?' . http_build_query(['id' => $id]);
}


function saveActiviteImageUpload(array $file, string $titre = '', int $ordre = 0, bool $visible = true, string $description = ''): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Veuillez choisir une image valide.'];
    }

    $maxBytes = max(1, app_int('ACTIVITE_MAX_SIZE_MB', 5)) * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxBytes) {
        return ['success' => false, 'message' => 'Image trop volumineuse.'];
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['success' => false, 'message' => 'Upload invalide.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmpName);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mime])) {
        return ['success' => false, 'message' => 'Format accepte: JPG, PNG ou WEBP.'];
    }

    $imageInfo = @getimagesize($tmpName);
    if (!is_array($imageInfo)) {
        return ['success' => false, 'message' => 'Fichier image invalide.'];
    }

    $directory = activiteStorageDirectory();
    $filename = 'activite_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extensions[$mime];
    $destination = $directory . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmpName, $destination)) {
        return ['success' => false, 'message' => 'Impossible d enregistrer l image.'];
    }
    @chmod($destination, 0640);

    $storedPath = 'activites/' . $filename;
    $titre = appCleanText($titre, 255);
    $ordre = max(0, min(9999, $ordre));
    $description = appCleanText($description, 5000);
    try {
        $repository = appContainer()->get(\Patro\Domain\Activite\Repository\ActiviteImageRepository::class);
        $repository->ensureSessionColumn();

        $id = $repository->create(appContainer()->get(\Patro\Inscription\SessionService::class)->getActiveAdminSessionId(), $titre, $storedPath, $ordre, $visible, $description);

        return ['success' => true, 'message' => 'Image ajoutee avec succes.', 'id' => $id];
    } catch (PDOException $e) {
        @unlink($destination);
        error_log('Save activite image error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur base de donnees.'];
    }
}

function updateActiviteImageMeta(int $id, string $titre, int $ordre, bool $visible, string $description = ''): array
{
    if ($id <= 0) {
        return ['success' => false, 'message' => 'Image introuvable.'];
    }

    $titre = appCleanText($titre, 255);
    $ordre = max(0, min(9999, $ordre));
    $description = appCleanText($description, 5000);

    $repository = appContainer()->get(\Patro\Domain\Activite\Repository\ActiviteImageRepository::class);
    if (!$repository->ensureSessionColumn() || !$repository->existsInSession($id, appContainer()->get(\Patro\Inscription\SessionService::class)->getActiveAdminSessionId())) {
        return ['success' => false, 'message' => 'Image introuvable.'];
    }

    $updated = $repository->updateMeta($id, appContainer()->get(\Patro\Inscription\SessionService::class)->getActiveAdminSessionId(), $titre, $ordre, $visible, $description);

    return $updated
        ? ['success' => true, 'message' => 'Image mise a jour.']
        : ['success' => false, 'message' => 'Image introuvable.'];
}

/**
 * Remplace le fichier image d'une activité par un nouveau fichier.
 *
 * @param int    $id            ID de l'activité
 * @param string $tmpPath       Chemin temporaire du fichier uploadé
 * @param string $mimeType      Type MIME validé
 * @param string|null $oldPath  Chemin absolu de l'ancien fichier (pour suppression)
 * @return array ['success' => bool, 'message' => string]
 */
function replaceActiviteImageFile(int $id, string $tmpPath, string $mimeType, ?string $oldPath = null): array
{
    if ($id <= 0) {
        return ['success' => false, 'message' => 'Image introuvable.'];
    }

    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return ['success' => false, 'message' => 'Upload invalide.'];
    }

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mimeType])) {
        return ['success' => false, 'message' => 'Format accepte: JPG, PNG ou WEBP.'];
    }

    $maxBytes = max(1, app_int('ACTIVITE_MAX_SIZE_MB', 5)) * 1024 * 1024;
    if (filesize($tmpPath) > $maxBytes) {
        return ['success' => false, 'message' => 'Image trop volumineuse.'];
    }

    if (!is_array(@getimagesize($tmpPath))) {
        return ['success' => false, 'message' => 'Fichier image invalide.'];
    }

    $storageDir = activiteStorageDirectory();
    $newFilename = 'activite_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extensions[$mimeType];
    $newPath = $storageDir . DIRECTORY_SEPARATOR . $newFilename;

    if (!move_uploaded_file($tmpPath, $newPath)) {
        return ['success' => false, 'message' => 'Impossible de deplacer le fichier uploade.'];
    }
    @chmod($newPath, 0640);

    $storedPath = 'activites/' . $newFilename;

    try {
        $repository = appContainer()->get(\Patro\Domain\Activite\Repository\ActiviteImageRepository::class);
        $repository->ensureSessionColumn();
        if (!$repository->replaceImagePath($id, appContainer()->get(\Patro\Inscription\SessionService::class)->getActiveAdminSessionId(), $storedPath)) {
            @unlink($newPath);
            return ['success' => false, 'message' => 'Image introuvable.'];
        }
    } catch (PDOException $e) {
        @unlink($newPath);
        error_log('Replace activite image error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur base de donnees.'];
    }

    if ($oldPath && is_file($oldPath) && realpath($oldPath) !== realpath($newPath)) {
        @unlink($oldPath);
    }

    actionLog('Activite image replaced', ['id' => $id, 'path' => $storedPath]);

    return ['success' => true, 'message' => 'Image remplacee avec succes.'];
}

function deleteActiviteImage(int $id): array
{
    if ($id <= 0) {
        return ['success' => false, 'message' => 'Image introuvable.'];
    }

    $repository = appContainer()->get(\Patro\Domain\Activite\Repository\ActiviteImageRepository::class);
    if (!$repository->ensureSessionColumn()) {
        return ['success' => false, 'message' => 'Image introuvable.'];
    }

    $image = $repository->findByIdAndSession($id, appContainer()->get(\Patro\Inscription\SessionService::class)->getActiveAdminSessionId());
    if (!$image) {
        return ['success' => false, 'message' => 'Image introuvable.'];
    }

    if (!$repository->deleteBySession($id, appContainer()->get(\Patro\Inscription\SessionService::class)->getActiveAdminSessionId())) {
        return ['success' => false, 'message' => 'Image introuvable.'];
    }

    $relative = basename(str_replace('\\', '/', (string) ($image['image_path'] ?? '')));
    $filePath = activiteStorageDirectory() . DIRECTORY_SEPARATOR . $relative;
    if (is_file($filePath)) {
        @unlink($filePath);
    }

    return ['success' => true, 'message' => 'Image supprimee.'];
}

function resolveActiviteImagePath(string $storedPath): string
{
    $relative = basename(str_replace('\\', '/', $storedPath));
    $path = activiteStorageDirectory() . DIRECTORY_SEPARATOR . $relative;

    return is_file($path) ? $path : '';
}

function getActiviteImageById(int $id, ?PDO $connect = null): array
{
    $repository = appContainer()->get(\Patro\Domain\Activite\Repository\ActiviteImageRepository::class);
    $repository->ensureSessionColumn();

    return $repository->findByIdAndSession($id, appContainer()->get(\Patro\Inscription\SessionService::class)->getActiveAdminSessionId()) ?? [];
}

/**
 * Gère l'accès et l'identité de l'utilisateur (Animateur).
 */
function currentAnimateur(): array
{
    if (appContainer()->has(\Patro\Application\Animateur\AnimateurAuthorizationService::class)) {
        return appContainer()->get(\Patro\Application\Animateur\AnimateurAuthorizationService::class)->current();
    }
    
    if (is_array($_SESSION['animateur'] ?? null)) {
        return $_SESSION['animateur'];
    }
    return [];
}

function requireAnimateur(string $loginUrl = 'auth/connexion.php'): void
{
    // 1. Strictement réservé aux animateurs. Si la session animateur est vide, on redirige.
    // L'administrateur n'aura donc pas accès via sa session "adpro".
    $authorization = appContainer()->get(\Patro\Application\Animateur\AnimateurAuthorizationService::class);
    if (!$authorization->isAuthenticated()) {
        redirectTo(app_url('public/' . ltrim($loginUrl, '/')));
    }

    // 2. Vérification du statut bloqué pour l'animateur
    if ($authorization->isBlocked()) {
        $authorization->logout();
        appContainer()->get(\Patro\Http\SessionManager::class)->flash('danger', 'Votre compte animateur est bloqué.');
        redirectTo(app_url('public/' . ltrim($loginUrl, '/')));
    }
}