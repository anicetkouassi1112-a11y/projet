<?php
require_once __DIR__ . '/../Backend/utilitaire.php';


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$allowedPages = ['accueil', 'apropos', 'activites', 'contact', 'politique_confidentialite', 'mentions_legales'];
$currentPage = requestTextParam('page', 40);
if (!in_array($currentPage, $allowedPages, true)) {
    $currentPage = 'accueil';
}

$pageTitles = [
    'accueil' => 'Accueil - Patro',
    'apropos' => 'A propos - Patro',
    'activites' => 'Activites - Patro',
    'contact' => 'Contact - Patro',
    'politique_confidentialite' => 'Politique de Confidentialité - Patro',
    'mentions_legales' => 'Mentions Légales - Patro',
];
$pageTitle = $pageTitles[$currentPage];
$assetBase = app_url('Backend/Assets');

require_once __DIR__ . '/include/head.php';
require_once __DIR__ . '/include/topbar.php';
?>
<main class="container py-4 public-main">
    <?php displayFlashMessage(); ?>
    <?php require __DIR__ . '/views/main.php'; ?>
</main>
<?php require_once __DIR__ . '/include/foot.php'; ?>
