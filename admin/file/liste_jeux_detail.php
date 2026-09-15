<?php declare(strict_types=1);

/**
 * Fiche détaillée d'un jeu — vue Directeur.
 * Reprend la structure de jeu_details.php, avec les actions de gestion
 * (modifier / supprimer) réservées au directeur.
 */
require_once __DIR__ . '/../../Backend/utilitaire.php';
requireRole(['directeur'], '../Auth/login.php');

$jeuId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
if ($jeuId === false || $jeuId <= 0) {
    setFlashMessage('danger', 'Jeu introuvable.');
    redirectTo(lien('liste_type_jeux'));
}

// 1. GESTION DES REQUÊTES POST (Modification, Suppression)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlashMessage('danger', 'Jeton CSRF invalide.');
        redirectTo(lien('liste_type_jeux'));
    }

    $action = $_POST['action'] ?? '';
    $typeRetour = requestTextParam('type', 50);

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $repository = appContainer()->get(\Patro\Domain\Jeu\Repository\JeuRepository::class);
        $deleted = $repository->delete($id);
        $result = $deleted
            ? ['alert_type' => 'success', 'message' => 'Jeu supprimé avec succès.']
            : ['alert_type' => 'warning', 'message' => 'Jeu introuvable.'];
        setFlashMessage($result['alert_type'], $result['message']);
        redirectTo(lien('liste_jeu', ['type' => $typeRetour]));
    }

    if ($action === 'update') {
        $nom = appCleanText($_POST['nom'] ?? '', 150);
        $type_jeu = appCleanText($_POST['type_jeu'] ?? '', 100);
        $age_conseille = appCleanText($_POST['age_conseille'] ?? '', 50);
        $duree = appCleanText($_POST['duree'] ?? '', 50);
        $nombre_joueurs = appCleanText($_POST['nombre_joueurs'] ?? '', 100);
        $lieu = appCleanText($_POST['lieu'] ?? '', 100);
        $objectif = appCleanText($_POST['objectif'] ?? '', 5000);
        $materiel = appCleanText($_POST['materiel'] ?? '', 5000);
        $mise_en_place = appCleanText($_POST['mise_en_place'] ?? '', 5000);
        $deroulement = appCleanText($_POST['deroulement'] ?? '', 5000);
        $regles = appCleanText($_POST['regles'] ?? '', 5000);
        $fin_jeu = appCleanText($_POST['fin_jeu'] ?? '', 5000);
        $but_pedagogique = appCleanText($_POST['but_pedagogique'] ?? '', 5000);

        $id = (int)($_POST['id'] ?? 0);
        $repository = appContainer()->get(\Patro\Domain\Jeu\Repository\JeuRepository::class);
        $updated = $repository->update($id, [
            'nom' => $nom,
            'objectif' => $objectif,
            'age_conseille' => $age_conseille,
            'duree' => $duree,
            'nombre_joueurs' => $nombre_joueurs,
            'lieu' => $lieu,
            'type_jeu' => $type_jeu,
            'materiel' => $materiel,
            'mise_en_place' => $mise_en_place,
            'deroulement' => $deroulement,
            'regles' => $regles,
            'fin_jeu' => $fin_jeu,
            'but_pedagogique' => $but_pedagogique,
        ]);

        $result = $updated
            ? ['alert_type' => 'success', 'message' => 'Jeu mis à jour avec succès.']
            : ['alert_type' => 'warning', 'message' => 'Aucune donnée valide à mettre à jour.'];

        setFlashMessage($result['alert_type'] ?? 'danger', $result['message'] ?? 'Erreur inconnue.');
        redirectTo(lien('liste_jeux_detail', ['id' => $id, 'type' => $type_jeu !== '' ? $type_jeu : 'non_classe']));
    }

    setFlashMessage('warning', 'Action non reconnue.');
    redirectTo(lien('liste_type_jeux'));
}

if ($jeuId <= 0) {
    setFlashMessage('danger', 'Jeu invalide.');
    redirectTo(lien('liste_type_jeux'));
}

$jeu = appContainer()->get(\Patro\Domain\Jeu\Repository\JeuRepository::class)->findById($jeuId);

if (!$jeu) {
    setFlashMessage('danger', 'Jeu introuvable.');
    redirectTo(lien('liste_type_jeux'));
}

$typeSelectionne = empty($jeu['type_jeu']) ? 'non_classe' : $jeu['type_jeu'];
$jeuJson = htmlspecialchars(json_encode($jeu), ENT_QUOTES, 'UTF-8');
?>
<div class="container-fluid home-shell">
    <main class="home-main py-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="<?= e(lien('liste_jeu', ['type' => $typeSelectionne])) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Retour à la liste
            </a>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary" onclick='openJeuModal("update", <?= $jeuJson ?>)'>
                    <i class="bi bi-pencil"></i> Modifier
                </button>
                <form action="" method="post" onsubmit="return confirm('Voulez-vous vraiment supprimer ce jeu ?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$jeu['id'] ?>">
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-trash"></i> Supprimer
                    </button>
                </form>
            </div>
        </div>

        <?php displayFlashMessage(); ?>

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
</div>

<?php require __DIR__ . '/../include/modal_creer_jeu.php'; ?>

<script>
function openJeuModal(action, data = null) {
    const modalEl = document.getElementById('jeuModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    const form = document.getElementById('jeuForm');
    const btn = document.getElementById('btnSubmitModal');

    form.reset();
    form.classList.remove('was-validated');

    form.querySelectorAll('.is-valid, .is-invalid').forEach(el => {
        el.classList.remove('is-valid', 'is-invalid');
    });

    btn.disabled = false;

    document.getElementById('formAction').value = action;

    if (action === 'update' && data) {
        document.getElementById('jeuModalLabel').textContent = 'Modifier le jeu : ' + data.nom;
        btn.innerHTML = '<i class="bi bi-save me-1"></i> Mettre à jour';
        document.getElementById('jeuId').value = data.id || '';

        document.getElementById('nom').value = data.nom || '';
        document.getElementById('type_jeu').value = data.type_jeu || '';
        document.getElementById('age_conseille').value = data.age_conseille || '';
        document.getElementById('duree').value = data.duree || '';
        document.getElementById('nombre_joueurs').value = data.nombre_joueurs || '';
        document.getElementById('lieu').value = data.lieu || '';
        document.getElementById('objectif').value = data.objectif || '';
        document.getElementById('materiel').value = data.materiel || '';
        document.getElementById('mise_en_place').value = data.mise_en_place || '';
        document.getElementById('regles').value = data.regles || '';
        document.getElementById('deroulement').value = data.deroulement || '';
        document.getElementById('fin_jeu').value = data.fin_jeu || '';
        document.getElementById('but_pedagogique').value = data.but_pedagogique || '';
    }

    if (typeof updateCounter === 'function') {
        const objEl = document.getElementById('objectif');
        const reglesEl = document.getElementById('regles');
        if(objEl) updateCounter(objEl, 'objCounter', 5000);
        if(reglesEl) updateCounter(reglesEl, 'reglesCounter', 5000);
    }

    modal.show();
}
</script>