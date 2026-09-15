<?php declare(strict_types=1);

/**
 * Sert les images d'activite stockees hors webroot (storage/activites).
 */

// Charger le bootstrap commun et les helpers de contenu public.
require_once __DIR__ . '/../../Backend/functions.php';
require_once __DIR__ . '/../../Backend/site.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    http_response_code(404);
    exit;
}

$row = getActiviteImageById((int) $id);
if (!$row) {
    http_response_code(404);
    exit;
}

// Images non visibles: accessibles uniquement aux admins connectes
if ((int) ($row['visible'] ?? 0) !== 1 && empty($_SESSION['adpro'])) {
    http_response_code(403);
    exit;
}

$filePath = resolveActiviteImagePath((string) ($row['image_path'] ?? ''));
if ($filePath === '') {
    http_response_code(404);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string) $finfo->file($filePath);
if (!str_starts_with($mime, 'image/')) {
    http_response_code(403);
    exit;
}

// Nettoyer tout buffer avant d'envoyer l'image
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . filesize($filePath));

readfile($filePath);
exit;
