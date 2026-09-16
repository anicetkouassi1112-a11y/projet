<?php
/**
 * Liste des jeux filtrés par type.
 */
require_once __DIR__ . '/../../Backend/utilitaire.php';
$authorization = appContainer()->get(\Patro\Application\Animateur\AnimateurAuthorizationService::class);
if (!$authorization->isAuthenticated()) {
    redirectTo(app_url('public/auth/connexion.php'));
}
if ($authorization->isBlocked()) {
    $authorization->logout();
    appContainer()->get(\Patro\Http\SessionManager::class)->flash('danger', 'Votre compte animateur est bloqué.');
    redirectTo(app_url('public/auth/connexion.php'));
}
$animateur = $authorization->current();
$idSection = (int) ($animateur['id_section'] ?? 0);
$sectionName = (string) ($animateur['nom_section'] ?? 'Section');

$repository = appContainer()->get(\Patro\Domain\Jeu\Repository\JeuRepository::class);
$sections = appContainer()->get(\Patro\Domain\Inscription\Repository\SectionRepository::class);
$genreSection = '';
foreach ($sections->findAll() as $section) {
    if ((int) $section['id_section'] === $idSection) {
        $genreSection = strtolower(trim((string) $section['genre']));
        break;
    }
}

$typeSelectionne = requestTextParam('type', 50);
$isNonClasse = ($typeSelectionne === 'non_classe');

if ($isNonClasse) {
    $jeux = $repository->findByType(null);
    $titrePage = 'Jeux non classés';
} else {
    $jeux = $repository->findByType($typeSelectionne);
    $titrePage = 'Jeux de type : ' . $typeSelectionne;
}
$pageTitle = $titrePage;

require_once __DIR__ . '/../include/head.php';
?>
<nav class="navbar navbar-expand-lg public-navbar" aria-label="Navigation animateur">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= e(app_url('public/views/animateur.php')) ?>">
            PATRO — <?= ($genreSection === 'fille') ? 'Animatrice' : 'Animateur' ?>
        </a>
        <div class="navbar-nav ms-auto flex-row align-items-center gap-2">
            <span class="nav-link text-muted py-0"><?= e(trim(($animateur['prenom_a'] ?? '') . ' ' . ($animateur['nom_a'] ?? ''))) ?> (<?= e($sectionName) ?>)</span>
            <a class="nav-link animateur-link text-danger" href="<?= e(app_url('public/auth/logout_animateur.php')) ?>">Déconnexion</a>
        </div>
    </div>
</nav>

<main class="container py-4">
    <div class="mb-4">
        <a href="animateur.php" class="btn btn-outline-secondary btn-sm mb-3">
            <i class="bi bi-arrow-left"></i> Retour aux catégories
        </a>
        <h1 class="fw-bold text-primary"><?= e($titrePage) ?></h1>
        <p class="text-muted"><?= count($jeux) ?> jeu(x) trouvé(s) dans cette catégorie.</p>
    </div>

    <?php if (empty($jeux)): ?>
        <div class="alert alert-warning">Aucun jeu trouvé pour cette catégorie.</div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($jeux as $jeu): ?>
                <div class="col-md-6 col-lg-4">
                    <article class="card h-100 shadow-sm border-0">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h2 class="h5 fw-bold text-dark mb-0"><?= e($jeu['nom']) ?></h2>
                                <span class="badge bg-info text-dark"><?= e($jeu['age_conseille'] ?: 'Âge non défini') ?></span>
                            </div>
                            <p class="text-muted small mb-3">
                                <i class="bi bi-clock"></i> <?= e($jeu['duree'] ?: 'Durée non définie') ?>
                            </p>
                            <p class="card-text text-truncate" style="max-height: 4.5em; overflow: hidden;">
                                <?= e($jeu['objectif']) ?>
                            </p>
                        </div>
                        <div class="card-footer bg-white border-0 pb-3">
                            <a href="jeu_details.php?id=<?= e((int)$jeu['id']) ?>" class="btn btn-primary w-100">Voir les détails</a>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/../include/foot.php'; ?>