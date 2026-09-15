<?php declare(strict_types=1);

/**
 * Bibliothèque de jeux — vue Directeur.
 * Reprend la structure "catégories" de animateur.php (cartes par type_jeu),
 * tout en conservant la gestion CRUD (création / modification / suppression).
 */
require_once __DIR__ . '/../../Backend/utilitaire.php';
requireRole(['directeur'], '../Auth/login.php');

// 1. GESTION DES REQUÊTES POST (Création, Modification, Suppression)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlashMessage('danger', 'Jeton CSRF invalide.');
        redirectTo(lien('liste_type_jeux'));
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $repository = appContainer()->get(\Patro\Domain\Jeu\Repository\JeuRepository::class);
        $deleted = $repository->delete($id);
        $result = $deleted
            ? ['alert_type' => 'success', 'message' => 'Jeu supprimé avec succès.']
            : ['alert_type' => 'warning', 'message' => 'Jeu introuvable.'];
        setFlashMessage($result['alert_type'], $result['message']);
        redirectTo(lien('liste_type_jeux'));
    }

    if ($action === 'create' || $action === 'update') {
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

        $repository = appContainer()->get(\Patro\Domain\Jeu\Repository\JeuRepository::class);

        if ($action === 'create') {
            $id = $repository->create([
                'nom' => $nom,
                'objectif' => $objectif,
                'regles' => $regles,
                'deroulement' => $deroulement,
                'materiel' => $materiel,
                'age_conseille' => $age_conseille,
                'duree' => $duree,
                'nombre_joueurs' => $nombre_joueurs,
                'lieu' => $lieu,
                'type_jeu' => $type_jeu,
                'mise_en_place' => $mise_en_place,
                'fin_jeu' => $fin_jeu,
                'but_pedagogique' => $but_pedagogique,
            ]);
            $result = ['alert_type' => 'success', 'message' => $id > 0 ? 'Jeu créé avec succès.' : 'Échec de la création.'];
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $updated = $repository->update($id, [
                'nom' => $nom,
                'objectif' => $objectif,
                'regles' => $regles,
                'deroulement' => $deroulement,
                'materiel' => $materiel,
                'age_conseille' => $age_conseille,
                'duree' => $duree,
                'nombre_joueurs' => $nombre_joueurs,
                'lieu' => $lieu,
                'type_jeu' => $type_jeu,
                'mise_en_place' => $mise_en_place,
                'fin_jeu' => $fin_jeu,
                'but_pedagogique' => $but_pedagogique,
            ]);
            $result = $updated
                ? ['alert_type' => 'success', 'message' => 'Jeu mis à jour avec succès.']
                : ['alert_type' => 'warning', 'message' => 'Aucune donnée valide à mettre à jour.'];
        }

        setFlashMessage($result['alert_type'] ?? 'danger', $result['message'] ?? 'Erreur inconnue.');
        redirectTo(lien('liste_type_jeux'));
    }

    // Action POST non reconnue
    setFlashMessage('warning', 'Action non reconnue.');
    redirectTo(lien('liste_type_jeux'));
}

// 2. RÉCUPÉRATION DES TYPES DE JEUX (comme animateur.php)
$repository = appContainer()->get(\Patro\Domain\Jeu\Repository\JeuRepository::class);
$typesJeux = $repository->types();
?>
<div class="container-fluid home-shell">
    <main class="home-main py-4">

        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
            <div>
                <h1 class="h3 mb-0 text-gray-800">Bibliothèque de jeux</h1>
                <p class="text-muted mb-0">Gérez l'ensemble des jeux disponibles pour les animateurs, classés par catégorie.</p>
            </div>
            <button type="button" class="btn btn-primary shadow-sm" onclick="openJeuModal('create')">
                <i class="bi bi-plus-lg me-1"></i> Ajouter un jeu
            </button>
        </div>

        <?php displayFlashMessage(); ?>

        <?php if (empty($typesJeux)): ?>
            <div class="alert alert-info text-center shadow-sm" role="status">
                <i class="bi bi-info-circle fs-4 d-block mb-2"></i>
                <strong>Aucun jeu disponible.</strong><br>
                La bibliothèque est actuellement vide.
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($typesJeux as $type):
                    $nomType = empty($type['type_jeu']) ? 'Non classé / Autres' : $type['type_jeu'];
                    $urlType = empty($type['type_jeu']) ? 'non_classe' : $type['type_jeu'];
                ?>
                    <div class="col-md-6 col-lg-4">
                        <a href="<?= e(lien('liste_jeu', ['type' => $urlType])) ?>" class="text-decoration-none">
                            <article class="card h-100 shadow-sm jeu-card border-0 bg-white transition-hover">
                                <div class="card-body text-center p-4">
                                    <div class="activity-badge tone-purple mx-auto mb-3" style="width: 60px; height: 60px; display: grid; place-items: center; border-radius: 50%; background: var(--fun-purple); color: white; margin-top: -30px;">
                                        <i class="bi bi-controller fs-3"></i>
                                    </div>
                                    <h2 class="h4 card-title text-dark fw-bold mb-2"><?= e($nomType) ?></h2>
                                    <span class="badge bg-light text-primary border rounded-pill px-3 py-2">
                                        <?= (int)$type['total'] ?> jeu(x) disponible(s)
                                    </span>
                                </div>
                            </article>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<style>
.transition-hover { transition: transform 0.3s ease, box-shadow 0.3s ease; }
.transition-hover:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important; }
</style>

<?php require __DIR__ . '/../include/modal_creer_jeu.php'; ?>

<script>
/**
 * Ouvre le modal et pré-remplit les données selon l'action (Création ou Modification)
 */
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
    } else {
        document.getElementById('jeuModalLabel').textContent = 'Ajouter un nouveau jeu';
        btn.innerHTML = '<i class="bi bi-plus-lg me-1"></i> Créer le jeu';
        document.getElementById('jeuId').value = '';
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