<?php
// Les variables proviennent de home.php ou du contexte de page
$pageName = $GLOBALS['pageName'] ?? $currentPage ?? 'Animateur';
$anneeActive = $anneeActive ?? date('Y');
$typeSessionActive = appContainer()->get(\Patro\Inscription\SessionService::class)->normalizeSessionType($typeSessionActive ?? appContainer()->get(\Patro\Inscription\SessionService::class)->getCurrentSessionType(), appContainer()->get(\Patro\Inscription\SessionService::class)->getCurrentSessionType());
$showSectionBreakdown = appContainer()->get(\Patro\Inscription\SessionService::class)->sectionBreakdownEnabled($typeSessionActive);

$sectionCounts = $sectionCounts ?? [];
$sectionTotal = array_sum($sectionCounts);

// Accepte $config['sections'] ou $sectionConfig['sections']
$sections = $config['sections'] ?? $sectionConfig['sections'] ?? [];

// Normalisation de la section courante avec validation
$rawSection = trim((string) ($_GET['section'] ?? ''));
$currentSection = ($rawSection !== '' && ctype_digit($rawSection)) ? $rawSection : null;

// Libell� global adapt� au genre
$allLabel = ($pageName === 'Animatrice') ? 'Toutes les Animatrices' : 'Tous les Animateurs';

// Param�tres de base pour les liens
$baseParams = [
    'page' => $pageName,
    'annee' => $anneeActive,
    'type_session' => $typeSessionActive,
];
?>

<ul class="list-group shadow-sm">
    <li class="list-group-item bg-light">
        <a href="home.php?page=animateur_session" class="text-decoration-none text-dark fw-bold">
            <i class="bi bi-arrow-left me-2" aria-hidden="true"></i> Retour
        </a>
    </li>

    <li class="list-group-item <?= $currentSection === null ? 'active' : '' ?>">
        <a href="home.php?<?= http_build_query($baseParams) ?>" 
           class="d-flex justify-content-between align-items-center text-decoration-none <?= $currentSection === null ? 'text-white' : 'text-dark' ?>">
            <span><?= e($allLabel) ?></span>
            <span class="badge <?= $currentSection === null ? 'bg-light text-primary' : 'bg-secondary' ?> rounded-pill">
                <?= e($sectionTotal) ?>
            </span>
        </a>
    </li>
</ul>