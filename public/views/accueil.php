<?php
require_once __DIR__ . '/../../Backend/utilitaire.php';

$currentPage = 'accueil';
$assetBase = app_url('Backend/Assets');
$pageTitle = 'Accueil - Fun&Loisirs';
$theme = appContainer()->get(\Patro\Inscription\ThemeService::class)->getCurrentThemeTitle();

$activiteImages = [];
try {
    $activiteRepository = appContainer()->get(\Patro\Domain\Activite\Repository\ActiviteImageRepository::class);
    $activiteRepository->ensureSessionColumn();
    $activiteImages = $activiteRepository->findVisibleBySession(
        appContainer()->get(\Patro\Inscription\SessionService::class)->getActiveAdminSessionId()
    );
} catch (Throwable $e) {
    error_log('Accueil images error: ' . $e->getMessage());
}
if (!is_array($activiteImages)) {
    $activiteImages = [];
}

$heroImage = rtrim($assetBase, '/') . '/img/friend.png';
$placeImage = rtrim($assetBase, '/') . '/img/office-man.png';
foreach ($activiteImages as $image) {
    if ((int) ($image['ordre'] ?? -1) === 0 && !empty($image['id'])) {
        $heroImage = app_url('public/media/activite.php') . '?' . http_build_query(['id' => (int) $image['id']]);
    }
    if ((int) ($image['ordre'] ?? -1) === 1 && !empty($image['id'])) {
        $placeImage = app_url('public/media/activite.php') . '?' . http_build_query(['id' => (int) $image['id']]);
    }
}

// Filtrer les images pour les activités (ordre >= 1)
$activityImages = array_filter($activiteImages, function($img) {
    return isset($img['ordre']) && (int)$img['ordre'] >= 1;
});

// Trier par ordre (déjà fait, mais on assure)
usort($activityImages, function($a, $b) {
    return ($a['ordre'] ?? 0) - ($b['ordre'] ?? 0);
});

// Sélectionner uniquement les images d'ordre 1 à 5
$topActivities = array_filter($activityImages, function($img) {
    $ordre = (int) ($img['ordre'] ?? 0);
    return $ordre >= 1 && $ordre <= 5;
});

// Définir les tons et icônes pour alternance
$tones = ['purple', 'orange', 'green', 'blue', 'pink'];
$icons = ['bi-stars-fill', 'bi-lightning-fill', 'bi-shield-check', 'bi-trophy-fill', 'bi-balloon-heart'];
?>

<section class="fun-hero parallax-hero" aria-labelledby="hero-title" style="--hero-image: url('<?= e($heroImage) ?>');" data-parallax-strength="0.12">
    <div class="hero-doodles" aria-hidden="true">
        <span class="paper-plane"></span>
        <span class="sun-smile"></span>
    </div>
    <div class="fun-hero-copy">
        <h1 id="hero-title">THEME<span><?= e($theme) ?></span></h1>
        <div class="hero-actions">
            <a class="btn btn-sun" href="<?= e(lien('activites', [], true)) ?>">Découvrir nos activités <span aria-hidden="true">›</span></a>
            <a class="btn btn-ghost" href="<?= e(lien('apropos', [], true)) ?>">En savoir plus</a>
        </div>
    </div>
</section>

<section class="promise-strip reveal-on-scroll reveal-stagger" aria-label="Nos engagements" data-reveal-stagger="36">
    <article class="reveal-on-scroll reveal-item" data-reveal-index="1">
        <span class="promise-icon purple" aria-hidden="true"><i class="bi bi-people-fill"></i></span>
        <div><h2>Encadrement professionnel</h2><p>Une équipe diplômée, attentive et passionnée.</p></div>
    </article>
    <article class="reveal-on-scroll reveal-item" data-reveal-index="2">
        <span class="promise-icon orange" aria-hidden="true"><i class="bi bi-lightning-fill"></i></span>
        <div><h2>Activités variées</h2><p>Sport, culture, nature, créativité... il y en a pour tous les goûts !</p></div>
    </article>
    <article class="reveal-on-scroll reveal-item" data-reveal-index="3">
        <span class="promise-icon green" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
        <div><h2>Sécurité et bienveillance</h2><p>Le bien-être des enfants est notre priorité.</p></div>
    </article>
    <article class="reveal-on-scroll reveal-item" data-reveal-index="4">
        <span class="promise-icon pink" aria-hidden="true"><i class="bi bi-trophy-fill"></i></span>
        <div><h2>Épanouissement et autonomie</h2><p>Nous encourageons la confiance, l'entraide et l'autonomie.</p></div>
    </article>
</section>

<section class="activities-showcase" id="activites" aria-labelledby="activity-title">
    <div class="section-title">
        <h2 id="activity-title">Des activités pour tous !</h2>
    </div>
    <div class="activity-card-row reveal-on-scroll" data-reveal-children="true" data-reveal-stagger="48">
        <?php if (!empty($topActivities)): ?>
            <?php $index = 0; ?>
            <?php foreach ($topActivities as $image): ?>
                <?php
                    $tone = $tones[$index % count($tones)];
                    $icon = $icons[$index % count($icons)];
                    $title = !empty($image['titre']) ? $image['titre'] : 'Activité ' . ($index + 1);
                    $index++;
                ?>
                <article class="fun-activity <?= e('tone-' . $tone) ?> reveal-item" data-reveal-index="<?= e($index) ?>">
                    <img src="<?= e(app_url('public/media/activite.php') . '?' . http_build_query(['id' => (int) $image['id']])) ?>" alt="<?= e($title) ?>" width="260" height="210" loading="lazy" decoding="async">
                    <span class="activity-badge" aria-hidden="true"><i class="bi <?= e($icon) ?>"></i></span>
                    <h3><?= e($title) ?></h3>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted">Aucune activité disponible pour le moment.</p>
        <?php endif; ?>
    </div>
    <a class="btn btn-purple" href="<?= e(lien('activites', [], true)) ?>">Voir toutes nos activités <span aria-hidden="true">›</span></a>
</section>

<section class="cta-panel reveal-on-scroll" aria-labelledby="cta-title">
    <div class="cta-copy">
        <p class="eyebrow">Rejoignez l'aventure</p>
        <h2 id="cta-title">Inscrivez votre enfant aujourd'hui et profitez d'un programme complet.</h2>
        <p>Entre activités créatives, sportives et découvertes en plein air, chaque enfant trouve sa place dans notre club.</p>
        <div class="cta-actions">
            <a class="btn btn-sun" href="<?= e(app_url('public/inscription.php')) ?>">Je m'inscris</a>
            <a class="btn btn-ghost" href="<?= e(lien('contact', [], true)) ?>">Nous contacter</a>
        </div>
    </div>
</section>