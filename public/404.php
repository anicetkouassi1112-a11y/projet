<?php
require_once __DIR__ . '/../Backend/utilitaire.php';
// On vérifie si du code HTML a déjà été envoyé au navigateur
if (!headers_sent()) {
    http_response_code(404);
}
$pageTitle = 'Page introuvable';
$assetBase = app_url('Backend/Assets');
require_once __DIR__ . '/include/head.php';
require_once __DIR__ . '/include/topbar.php';
?>
<main class="container py-5 text-center">
    <h1>404 — Page introuvable</h1>
    <p class="text-muted">La page demandee n'existe pas ou a ete deplacee.</p>
    <a class="btn btn-primary" href="<?= e(lien('accueil', [], true)) ?>">Retour a l'accueil</a>
</main>
<?php require_once __DIR__ . '/include/foot.php'; ?>
