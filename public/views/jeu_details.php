<?php
/**
 * Fiche détaillée d'un jeu spécifique.
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

$jeuId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
if ($jeuId === false || $jeuId <= 0) {
    setFlashMessage('danger', 'Jeu invalide.');
    redirectTo(app_url('public/views/animateur.php'));
}

$sections = appContainer()->get(\Patro\Domain\Inscription\Repository\SectionRepository::class);
$genreSection = '';
foreach ($sections->findAll() as $section) {
    if ((int) $section['id_section'] === $idSection) {
        $genreSection = strtolower(trim((string) $section['genre']));
        break;
    }
}
$jeu = appContainer()->get(\Patro\Domain\Jeu\Repository\JeuRepository::class)->findById($jeuId);

if (!$jeu) {
    setFlashMessage('danger', 'Jeu introuvable.');
    redirectTo(app_url('public/views/animateur.php'));
}

$typeSelectionne = empty($jeu['type_jeu']) ? 'non_classe' : $jeu['type_jeu'];
$pageTitle = 'Jeu : ' . $jeu['nom'];

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
    <a href="jeux_liste.php?type=<?= urlencode($typeSelectionne) ?>" class="btn btn-outline-secondary btn-sm mb-4">
        <i class="bi bi-arrow-left"></i> Retour à la liste
    </a>

    <div class="card shadow-lg border-0 mb-5">
        <div class="card-header bg-primary text-white p-4">
            <h1 class="display-6 fw-bold mb-0"><?= e($jeu['nom']) ?></h1>
            <p class="mb-0 mt-2 opacity-75">
                <i class="bi bi-tag-fill me-1"></i> Catégorie : <?= e($jeu['type_jeu'] ?: 'Non classé') ?>
            </p>
        </div>
        
        <div class="bg-light p-3 border-bottom d-flex flex-wrap gap-4 justify-content-center text-center">
            <div><span class="d-block text-muted small">Âge</span><strong class="text-dark"><?= e($jeu['age_conseille'] ?: '-') ?></strong></div>
            <div class="border-start ps-4"><span class="d-block text-muted small">Durée</span><strong class="text-dark"><?= e($jeu['duree'] ?: '-') ?></strong></div>
            <div class="border-start ps-4"><span class="d-block text-muted small">Joueurs</span><strong class="text-dark"><?= e($jeu['nombre_joueurs'] ?: '-') ?></strong></div>
            <div class="border-start ps-4"><span class="d-block text-muted small">Lieu</span><strong class="text-dark"><?= e($jeu['lieu'] ?: '-') ?></strong></div>
        </div>

        <div class="card-body p-4 p-md-5">
            <div class="row border-bottom pb-4 mb-4">
                <div class="col-md-12">
                    <h3 class="h5 fw-bold text-primary"><i class="bi bi-bullseye me-2"></i>Objectif / Imaginaire</h3>
                    <p class="fs-5 text-muted"><?= nl2br(e($jeu['objectif'] ?: 'Non renseigné')) ?></p>
                </div>
            </div>

            <div class="row g-5">
                <div class="col-lg-6 border-end-lg">
                    <h3 class="h5 fw-bold text-warning"><i class="bi bi-box-seam me-2"></i>Matériel & Préparation</h3>
                    <div class="mb-4">
                        <strong class="d-block text-secondary">Matériel requis :</strong>
                        <p><?= nl2br(e($jeu['materiel'] ?: 'Aucun matériel spécifique.')) ?></p>
                    </div>
                    <div>
                        <strong class="d-block text-secondary">Mise en place :</strong>
                        <p><?= nl2br(e($jeu['mise_en_place'] ?: 'Aucune mise en place spécifique.')) ?></p>
                    </div>
                </div>

                <div class="col-lg-6">
                    <h3 class="h5 fw-bold text-success"><i class="bi bi-play-circle me-2"></i>Action & Règles</h3>
                    <div class="mb-4">
                        <strong class="d-block text-secondary">Règles du jeu :</strong>
                        <p><?= nl2br(e($jeu['regles'] ?: 'Non renseignées.')) ?></p>
                    </div>
                    <div class="mb-4">
                        <strong class="d-block text-secondary">Déroulement :</strong>
                        <p><?= nl2br(e($jeu['deroulement'] ?: 'Non renseigné.')) ?></p>
                    </div>
                    <div>
                        <strong class="d-block text-secondary">Fin du jeu :</strong>
                        <p><?= nl2br(e($jeu['fin_jeu'] ?: 'Non renseignée.')) ?></p>
                    </div>
                </div>
            </div>

            <?php if (!empty($jeu['but_pedagogique'])): ?>
                <div class="mt-5 p-4 bg-light border-start border-4 border-info rounded">
                    <h3 class="h6 fw-bold text-info text-uppercase mb-2">But Pédagogique</h3>
                    <p class="mb-0 text-muted"><?= nl2br(e($jeu['but_pedagogique'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../include/foot.php'; ?>