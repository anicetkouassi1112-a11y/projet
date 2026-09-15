<?php

require_once __DIR__ . '/../../Backend/utilitaire.php';

$currentPage = 'accueil';
$assetBase = app_url('Backend/Assets');
$pageTitle = 'Activités - Fun&Loisirs';
$theme = appContainer()->get(\Patro\Inscription\ThemeService::class)->getCurrentThemeTitle();

$activiteImages = [];
try {
    $activiteRepository = appContainer()->get(\Patro\Domain\Activite\Repository\ActiviteImageRepository::class);
    $activiteRepository->ensureSessionColumn();
    $activiteImages = $activiteRepository->findVisibleBySession(
        appContainer()->get(\Patro\Inscription\SessionService::class)->getActiveAdminSessionId()
    );
} catch (Throwable $e) {
    error_log('Activites images error: ' . $e->getMessage());
}
if (!is_array($activiteImages)) {
    $activiteImages = [];
}

$typeSessionActuel = activeSessionTypeFromRequest();

// Filtrer les images avec ordre >= 1 (activités)
$activityImages = array_filter($activiteImages, function($img) {
    return isset($img['ordre']) && (int)$img['ordre'] >= 1;
});

// Trier par ordre
usort($activityImages, function($a, $b) {
    return ($a['ordre'] ?? 0) - ($b['ordre'] ?? 0);
});

$tones = ['purple', 'orange', 'green', 'blue', 'pink'];
$icons = ['bi-stars-fill', 'bi-lightning-fill', 'bi-shield-check', 'bi-trophy-fill', 'bi-balloon-heart'];
$toneIndex = 0;
?>
<div class="page-header mb-4">
    <h1>Quelques activités menées au cours de <span>PATRO </span><?= e(sessionTypeLabel($typeSessionActuel)) ?></h1>
    <p class="lead text-muted">Chaque section correspond à une tranche d'âge et un programme adapté.</p>
</div>

<section class="activity" id="activites">
    <?php if (!empty($activityImages)): ?>
        <?php foreach ($activityImages as $image): ?>
            <?php
                $tone = $tones[$toneIndex % count($tones)];
                $icon = $icons[$toneIndex % count($icons)];
                $toneIndex++;
                $title = !empty($image['titre']) ? $image['titre'] : 'Activité';
            ?>
            <div class="activity-wrapper">
                <article class="fun-activitie <?= e('tone-' . $tone) ?>">
                    <img src="<?= e(app_url('public/media/activite.php') . '?' . http_build_query(['id' => (int) $image['id']])) ?>" alt="<?= e($title) ?>" loading="lazy" decoding="async">
                    <span class="activity-badge"><i class="bi <?= e($icon) ?>"></i></span>
                </article>
                <div class="activity-content">
                    <h3><?= e($title) ?></h3>
                    <p><?= e($image['description'] ?? 'Aucune description disponible.') ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted">Aucune activité disponible pour le moment.</p>
    <?php endif; ?>
</section>