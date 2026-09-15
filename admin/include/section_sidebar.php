<?php
// Les variables proviennent de home.php ou du contexte de page
$pageName = $GLOBALS['pageName'] ?? $pageName ?? 'statistique';
$anneeActive = $anneeActive ?? date('Y');
$typeSessionActive = normalizeSessionType($typeSessionActive ?? appContainer()->get(\Patro\Inscription\SessionService::class)->getCurrentSessionType(), appContainer()->get(\Patro\Inscription\SessionService::class)->getCurrentSessionType());
$showSectionBreakdown = sectionBreakdownEnabled($typeSessionActive);

$sectionCounts = $sectionCounts ?? [];
$sectionTotal = array_sum($sectionCounts);

// Accepte $config['sections'] ou $sectionConfig['sections']
$sections = $config['sections'] ?? $sectionConfig['sections'] ?? [];

// Normalisation de la section courante avec validation
$rawSection = trim((string) ($_GET['section'] ?? ''));
$currentSection = ($rawSection !== '' && ctype_digit($rawSection)) ? $rawSection : null;

// Paramètres de base pour les liens
$baseParams = [
    'page' => $pageName,
    'annee' => $anneeActive,
    'type_session' => $typeSessionActive,
];

// Route de retour admin sécurisée
$adminRoute = function_exists('defaultAdminRoute') 
    ? defaultAdminRoute(currentadminRole()) 
    : defaultadminRoute(currentadminRole());
?>
<ul class="list-group shadow-sm">
    <li class="list-group-item bg-light">
        <a href="<?= e('../admin/' . $adminRoute) ?>" class="text-decoration-none text-dark fw-bold">
            <i class="bi bi-arrow-left me-2" aria-hidden="true"></i> Retour
        </a>
    </li>

    <li class="list-group-item <?= $currentSection === null ? 'active' : '' ?>">
        <a href="home.php?<?= http_build_query($baseParams) ?>" 
           class="d-flex justify-content-between align-items-center text-decoration-none <?= $currentSection === null ? 'text-white' : 'text-dark' ?>">
            <span><?= $showSectionBreakdown ? 'Toutes les sections' : 'Tous les inscrits' ?></span>
            <span class="badge <?= $currentSection === null ? 'bg-light text-primary' : 'bg-secondary' ?> rounded-pill">
                <?= e($sectionTotal) ?>
            </span>
        </a>
    </li>

    <?php if ($showSectionBreakdown && !empty($sections)): ?>
        <?php foreach ($sections as $sectionId => $section): ?>
            <?php
            $sectionKey = (string) $sectionId;
            $count = $sectionCounts[$section['label']] ?? 0;
            $isActive = ($currentSection === $sectionKey);
            $linkParams = array_merge($baseParams, ['section' => $sectionKey]);
            ?>
            <li class="list-group-item <?= $isActive ? 'active' : '' ?>">
                <a href="home.php?<?= http_build_query($linkParams) ?>" 
                   class="d-flex justify-content-between align-items-center text-decoration-none <?= $isActive ? 'text-white' : 'text-dark' ?>">
                    <span><?= e($section['label']) ?></span>
                    <span class="badge <?= $isActive ? 'bg-light text-primary' : 'bg-secondary' ?> rounded-pill">
                        <?= e($count) ?>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    <?php endif; ?>
</ul>