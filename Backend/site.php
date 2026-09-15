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

/**
 * Récupère tous les jeux de la base de données.
 */
function getAllJeux(?PDO $connect = null): array
{
    if (class_exists('Patro\\Domain\\Jeu\\Repository\\JeuRepository')) {
        $repository = new \Patro\Domain\Jeu\Repository\JeuRepository($connect ?: getConnection());
        return $repository->findAll();
    }

    $connect = $connect ?: getConnection();
    $stmt = $connect->query('SELECT * FROM jeux ORDER BY nom ASC');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Crée un nouveau jeu dans la base de données globale.
 */
function creerJeu(
    string $nom,
    string $objectif = '',
    string $regles = '',
    string $deroulement = '',
    string $materiel = '',
    string $age_conseille = '',
    string $duree = '',
    string $nombre_joueurs = '',
    string $lieu = '',
    string $type_jeu = '',
    string $mise_en_place = '',
    string $fin_jeu = '',
    string $but_pedagogique = ''
): array {
    $nom = appCleanText($nom, 150);
    $objectif = appCleanText($objectif, 5000);
    $regles = appCleanText($regles, 5000);
    $age_conseille = appCleanText($age_conseille, 50);
    $duree = appCleanText($duree, 50);
    $nombre_joueurs = appCleanText($nombre_joueurs, 100);
    $lieu = appCleanText($lieu, 100);
    $type_jeu = appCleanText($type_jeu, 100);
    $materiel = appCleanText($materiel, 5000);
    $mise_en_place = appCleanText($mise_en_place, 5000);
    $deroulement = appCleanText($deroulement, 5000);
    $fin_jeu = appCleanText($fin_jeu, 5000);
    $but_pedagogique = appCleanText($but_pedagogique, 5000);

    if ($nom === '') {
        return ['success' => false, 'message' => 'Le nom du jeu est obligatoire.', 'alert_type' => 'warning'];
    }

    try {
        if (class_exists('Patro\\Domain\\Jeu\\Repository\\JeuRepository')) {
            $id = (new \Patro\Domain\Jeu\Repository\JeuRepository(getConnection()))->create([
                'nom' => $nom, 'objectif' => $objectif, 'regles' => $regles,
                'deroulement' => $deroulement, 'materiel' => $materiel,
                'age_conseille' => $age_conseille, 'duree' => $duree,
                'nombre_joueurs' => $nombre_joueurs, 'lieu' => $lieu,
                'type_jeu' => $type_jeu, 'mise_en_place' => $mise_en_place,
                'fin_jeu' => $fin_jeu, 'but_pedagogique' => $but_pedagogique,
            ]);

            return ['success' => true, 'message' => 'Jeu "' . $nom . '" créé avec succès.', 'alert_type' => 'success', 'id' => $id];
        }

        $connect = getConnection();
        $stmt = $connect->prepare(
            'INSERT INTO jeux (
                nom, objectif, age_conseille, duree, nombre_joueurs,
                lieu, type_jeu, materiel, mise_en_place, deroulement,
                regles, fin_jeu, but_pedagogique
            ) VALUES (
                :nom, :objectif, :age_conseille, :duree, :nombre_joueurs,
                :lieu, :type_jeu, :materiel, :mise_en_place, :deroulement,
                :regles, :fin_jeu, :but_pedagogique
            )'
        );
        $stmt->execute([
            ':nom' => $nom, ':objectif' => $objectif, ':age_conseille' => $age_conseille,
            ':duree' => $duree, ':nombre_joueurs' => $nombre_joueurs, ':lieu' => $lieu,
            ':type_jeu' => $type_jeu, ':materiel' => $materiel, ':mise_en_place' => $mise_en_place,
            ':deroulement' => $deroulement, ':regles' => $regles, ':fin_jeu' => $fin_jeu,
            ':but_pedagogique' => $but_pedagogique,
        ]);

        return ['success' => true, 'message' => 'Jeu "' . $nom . '" créé avec succès.', 'alert_type' => 'success', 'id' => (int) $connect->lastInsertId()];
    } catch (PDOException $e) {
        error_log('Création jeu erreur : ' . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur lors de l\'enregistrement dans la base de données.', 'alert_type' => 'danger'];
    }
}

/**
 * Met à jour un jeu existant.
 */
function updateJeu(int $id, array $data): array
{
    if ($id <= 0) {
        return ['success' => false, 'message' => 'Données invalides.', 'alert_type' => 'warning'];
    }

    $normalized = [];
    foreach ($data as $key => $value) {
        $field = ltrim((string) $key, ':');
        if ($field !== '') {
            $normalized[$field] = $value;
        }
    }

    if (empty($normalized['nom'])) {
        return ['success' => false, 'message' => 'Données invalides.', 'alert_type' => 'warning'];
    }

    try {
        if (class_exists('Patro\\Domain\\Jeu\\Repository\\JeuRepository')) {
            $updated = (new \Patro\Domain\Jeu\Repository\JeuRepository(getConnection()))->update($id, $normalized);
            return $updated
                ? ['success' => true, 'message' => 'Jeu mis à jour avec succès.', 'alert_type' => 'success']
                : ['success' => false, 'message' => 'Aucune donnée valide à mettre à jour.', 'alert_type' => 'warning'];
        }

        $connect = getConnection();
        $data[':id'] = $id;
        $stmt = $connect->prepare(
            'UPDATE jeux SET
                nom = :nom, objectif = :objectif, age_conseille = :age_conseille,
                duree = :duree, nombre_joueurs = :nombre_joueurs, lieu = :lieu,
                type_jeu = :type_jeu, materiel = :materiel, mise_en_place = :mise_en_place,
                deroulement = :deroulement, regles = :regles, fin_jeu = :fin_jeu,
                but_pedagogique = :but_pedagogique
             WHERE id = :id'
        );
        $stmt->execute($data);
        return ['success' => true, 'message' => 'Jeu mis à jour avec succès.', 'alert_type' => 'success'];
    } catch (PDOException $e) {
        error_log('Update jeu erreur : ' . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur lors de la modification.', 'alert_type' => 'danger'];
    }
}

/**
 * Supprime un jeu.
 */
function deleteJeu(int $id): array
{
    if ($id <= 0) {
        return ['success' => false, 'message' => 'ID invalide.', 'alert_type' => 'warning'];
    }

    try {
        if (class_exists('Patro\\Domain\\Jeu\\Repository\\JeuRepository')) {
            $deleted = (new \Patro\Domain\Jeu\Repository\JeuRepository(getConnection()))->delete($id);
            return $deleted
                ? ['success' => true, 'message' => 'Jeu supprimé avec succès.', 'alert_type' => 'success']
                : ['success' => false, 'message' => 'Jeu introuvable.', 'alert_type' => 'warning'];
        }

        $stmt = getConnection()->prepare('DELETE FROM jeux WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return ['success' => true, 'message' => 'Jeu supprimé avec succès.', 'alert_type' => 'success'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur lors de la suppression.', 'alert_type' => 'danger'];
    }
}

function getVisibleActiviteImages(?PDO $connect = null): array
{
    try {
        $connect = $connect ?: getConnection();
        $repository = new \Patro\Domain\Activite\Repository\ActiviteImageRepository($connect);
        if (!$repository->ensureSessionColumn()) {
            return [];
        }

        return $repository->findVisibleBySession(getActiveAdminSessionId());
    } catch (PDOException $e) {
        error_log('Visible activite images error: ' . $e->getMessage());
        return [];
    }
}

function getAllActiviteImages(?PDO $connect = null): array
{
    try {
        $connect = $connect ?: getConnection();
        $repository = new \Patro\Domain\Activite\Repository\ActiviteImageRepository($connect);
        if (!$repository->ensureSessionColumn()) {
            return [];
        }

        return $repository->findAllBySession(getActiveAdminSessionId());
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
        $connect = getConnection();
        $repository = new \Patro\Domain\Activite\Repository\ActiviteImageRepository($connect);
        $repository->ensureSessionColumn();

        $id = $repository->create(getActiveAdminSessionId(), $titre, $storedPath, $ordre, $visible, $description);

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

    $connect = getConnection();
    $repository = new \Patro\Domain\Activite\Repository\ActiviteImageRepository($connect);
    if (!$repository->ensureSessionColumn() || !$repository->existsInSession($id, getActiveAdminSessionId())) {
        return ['success' => false, 'message' => 'Image introuvable.'];
    }

    $updated = $repository->updateMeta($id, getActiveAdminSessionId(), $titre, $ordre, $visible, $description);

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
        $connect = getConnection();
        $repository = new \Patro\Domain\Activite\Repository\ActiviteImageRepository($connect);
        $repository->ensureSessionColumn();
        if (!$repository->replaceImagePath($id, getActiveAdminSessionId(), $storedPath)) {
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

    $connect = getConnection();
    $repository = new \Patro\Domain\Activite\Repository\ActiviteImageRepository($connect);
    if (!$repository->ensureSessionColumn()) {
        return ['success' => false, 'message' => 'Image introuvable.'];
    }

    $image = $repository->findByIdAndSession($id, getActiveAdminSessionId());
    if (!$image) {
        return ['success' => false, 'message' => 'Image introuvable.'];
    }

    if (!$repository->deleteBySession($id, getActiveAdminSessionId())) {
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
    $connect = $connect ?: getConnection();
    $repository = new \Patro\Domain\Activite\Repository\ActiviteImageRepository($connect);
    $repository->ensureSessionColumn();

    return $repository->findByIdAndSession($id, getActiveAdminSessionId()) ?? [];
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