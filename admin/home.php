<?php declare(strict_types=1);

require_once __DIR__ . '/../Backend/utilitaire.php';
require_once __DIR__ . '/partial/sections.php'; 

requireRole(['directeur'], 'Auth/login.php');

$allowedPages = [
    'statistique', 'Tee_shirts', 'attente', 'add_section',
    'animateurs', 'animateur_session', 'liste_type_jeux', 'liste_jeu', 'liste_jeux_detail',
    'accueil_admin', 'activite_admin', 'admin_config', 'modifier_mot_de_passe',
    'garcon', 'fille', 'Animateur', 'Animatrice'
];

$currentPage = requestTextParam('page', 40);
if (!in_array($currentPage, $allowedPages, true)) {
    $currentPage = 'statistique';
}

$pageTitles = [
    'statistique'           => 'Tableau de bord - Patro',
    'Tee_shirts'            => 'Tee-shirts payes - Patro',
    'attente'               => 'Inscriptions en attente - Patro',
    'add_section'           => 'Ajouter une Section - Patro',
    'animateurs'            => 'Animateurs - Patro',
    'animateur_session'     => 'Attribution de section - Patro',
    'liste_type_jeux'       => 'Liste des jeux par type - Patro',
    'liste_jeu'             => 'Liste des jeux - Patro',
    'liste_jeux_detail'     => 'Détail du jeu - Patro',
    'accueil_admin'         => 'Gestion de la page public - Patro',
    'activite_admin'        => 'Gestion des activites publiques - Patro',
    'admin_config'          => 'Configuration - Patro',
    'modifier_mot_de_passe' => 'Profil - Patro',
    'garcon'                => 'Garcons - Patro',
    'fille'                 => 'Filles - Patro',
    'Animateur'             => 'Animateurs Garçons - Patro',
    'Animatrice'            => 'Animatrices Filles - Patro',
];

$pageTitle = $pageTitles[$currentPage] ?? 'Patro';
$assetBase = app_url('Backend/Assets');

// =====================================================================
// PRÉPARATION DU CONTEXTE (Pour la sidebar et les vues enfants)
// =====================================================================
if ($currentPage === 'statistique') {
    $anneeActive = displayYearFromRequest();
    $typeSessionActive = displaySessionTypeFromRequest();
} else {
    $anneeActive = activeYearFromRequest();
    $typeSessionActive = activeSessionTypeFromRequest();
}

$sectionConfig = [];
$sectionCounts = [];
$context = [];

if ($currentPage === 'garcon') {
    $context = genderPageContext('garcon', $anneeActive, $typeSessionActive);
    $sectionConfig = $context['config'] ?? [];
    $sectionCounts = $context['counts'] ?? [];
} elseif ($currentPage === 'fille') {
    $context = genderPageContext('fille', $anneeActive, $typeSessionActive);
    $sectionConfig = $context['config'] ?? [];
    $sectionCounts = $context['counts'] ?? [];
} elseif ($currentPage === 'Animateur') {
    $context = genderAnimateurPageContext('M', $anneeActive, $typeSessionActive);
    $sectionConfig = $context['config'] ?? [];
    $sectionCounts = $context['counts'] ?? [];
} elseif ($currentPage === 'Animatrice') {
    $context = genderAnimateurPageContext('F', $anneeActive, $typeSessionActive);
    $sectionConfig = $context['config'] ?? [];
    $sectionCounts = $context['counts'] ?? [];
}
// =====================================================================

$GLOBALS['pageName'] = $currentPage;

require __DIR__ . '/include/head.php';
require __DIR__ . '/include/topbar.php';
?>

<div class="container-fluid home-shell">
    <div class="row home-layout">
        
        <aside class="col-md-2 home-sidebar">
            <?php if (in_array($currentPage, ['garcon', 'fille'], true)) : ?>
                <?php require __DIR__ . '/include/section_sidebar.php'; ?>
            <?php elseif (in_array($currentPage, ['Animateur', 'Animatrice'], true)) : ?>
                <?php require __DIR__ . '/include/animateur_sidebar.php'; ?>
            <?php else: ?>
                <?php require __DIR__ . '/include/sidebar.php'; ?>
            <?php endif; ?>
        </aside>

        <main class="col-md-10">
            <?php require __DIR__ . '/file/main.php'; ?>
        </main>
        
    </div>
</div>

<?php require __DIR__ . '/include/foot.php'; ?>