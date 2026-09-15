<?php declare(strict_types=1);

/**
 * Liste des jeux d'un type donné — vue Directeur.
 * Reprend la structure de jeux_liste.php, avec les actions de gestion
 * (créer / modifier / supprimer) réservées au directeur.
 */
require_once __DIR__ . '/../../Backend/utilitaire.php';
requireRole(['directeur'], '../Auth/login.php');

// Note: pour la vue Directeur, on n'utilise pas currentAnimateur
// Les filtres sont gérés différemment dans cette vue

// 1. GESTION DES REQUÊTES POST (Création, Modification, Suppression)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlashMessage('danger', 'Jeton CSRF invalide.');
        redirectTo(lien('liste_type_jeux'));
    }

    $typeRetour = requestTextParam('type', 50);

    setFlashMessage('warning', 'Action non reconnue.');
    redirectTo(lien('liste_type_jeux'));
}

// 2. RÉCUPÉRATION DES JEUX DU TYPE SÉLECTIONNÉ (comme jeux_liste.php)
$typeSelectionne = requestTextParam('type', 50);
$isNonClasse = ($typeSelectionne === 'non_classe');

if ($isNonClasse) {
    $jeux = appContainer()->get(\Patro\Domain\Jeu\Repository\JeuRepository::class)->findByType(null);
    $titrePage = 'Jeux non classés';
} else {
    $jeux = appContainer()->get(\Patro\Domain\Jeu\Repository\JeuRepository::class)->findByType($typeSelectionne);
    $titrePage = 'Jeux de type : ' . $typeSelectionne;
}
?>
<div class="container-fluid home-shell">
    <main class="home-main py-4">

        <div class="mb-4">
            <a href="<?= e(lien('liste_type_jeux')) ?>" class="btn btn-outline-secondary btn-sm mb-3">
                <i class="bi bi-arrow-left"></i> Retour aux catégories
            </a>
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3">
                <div>
                    <h1 class="h3 mb-0 text-gray-800"><?= e($titrePage) ?></h1>
                    <p class="text-muted mb-0"><?= count($jeux) ?> jeu(x) trouvé(s) dans cette catégorie.</p>
                </div>
            </div>
        </div>

        <?php displayFlashMessage(); ?>

        <?php if (empty($jeux)): ?>
            <div class="alert alert-warning">Aucun jeu trouvé pour cette catégorie.</div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($jeux as $jeu):
                    $jeuJson = htmlspecialchars(json_encode($jeu), ENT_QUOTES, 'UTF-8');
                ?>
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
                            <div class="card-footer bg-white border-0 pb-3 d-flex gap-2">
                                <a href="<?= e(lien('liste_jeux_detail', ['id' => $jeu['id'], 'type' => $typeSelectionne])) ?>" class="btn btn-primary flex-grow-1">
                                    <i class="bi bi-eye"></i> Voir les détails
                                </a>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>
