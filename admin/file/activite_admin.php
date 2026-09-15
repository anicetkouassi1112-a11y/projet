<?php declare(strict_types=1);

require_once __DIR__ . '/../../Backend/utilitaire.php';
requireRole(['directeur'], '../Auth/login.php');

// Fonction utilitaire locale pour les retours d'actions
function handleActionResult(array $result): void
{
    $type = !empty($result['success']) ? 'success' : 'danger';
    setFlashMessage($type, (string) ($result['message'] ?? 'Une erreur est survenue.'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlashMessage('danger', 'Jeton CSRF invalide.');
        redirectTo(lien('accueil_admin'));
    }

    $action = $_POST['action'] ?? '';
    
    // Traitement des images
    switch ($action) {
        case 'add_image':
            $titre = appCleanText((string) ($_POST['titre'] ?? ''), 255);
            $ordre = max(6, (int)($_POST['ordre'] ?? 6));
            $visible = isset($_POST['visible']);
            $description = appCleanText((string) ($_POST['description'] ?? ''), 5000);

            $activitesExistantes = array_filter(getAllActiviteImages(), fn($img) => (int) ($img['ordre'] ?? 0) >= 6);
            $ordreDejaPris = array_filter($activitesExistantes, fn($img) => (int) ($img['ordre'] ?? -1) === $ordre);

            if ($ordreDejaPris !== []) {
                setFlashMessage('warning', 'L\'ordre ' . $ordre . ' est déjà utilisé par une autre activité. Choisissez un ordre libre.');
            } else {
                // Assurez-vous que votre fonction backend accepte le paramètre $description
                $result = saveActiviteImageUpload($_FILES['image'] ?? [], $titre, $ordre, $visible, $description);
                if (!empty($result['success'])) {
                    actionLog('Activite image added', ['id' => (int) ($result['id'] ?? 0), 'ordre' => $ordre]);
                }
                handleActionResult($result);
            }
            break;

        case 'update_image':
            $id = (int)($_POST['id'] ?? 0);
            $titre   = appCleanText((string) ($_POST['titre'] ?? ''), 255);
            $ordre   = max(6, (int)($_POST['ordre'] ?? 6));
            $visible = isset($_POST['visible']);
            $description = appCleanText((string) ($_POST['description'] ?? ''), 5000);

            // ---------- Vérification de l'ordre (inchangée) ----------
            $activitesExistantes = array_filter(
                getAllActiviteImages(),
                fn($img) => (int) ($img['ordre'] ?? 0) >= 6 && (int) $img['id'] !== $id
            );
            $ordreDejaPris = array_filter(
                $activitesExistantes,
                fn($img) => (int) ($img['ordre'] ?? -1) === $ordre
            );
            if ($ordreDejaPris !== []) {
                setFlashMessage('warning', 'L\'ordre ' . $ordre . ' est déjà utilisé par une autre activité. Choisissez un ordre libre.');
                break;
            }

            // ---------- Gestion du fichier uploadé ----------
            $fichier = $_FILES['nouvelle_image'] ?? null;
            $fichierValide = true;
            $cheminAncien = null;

            if ($fichier && $fichier['error'] === UPLOAD_ERR_OK) {
                // Valider le type MIME
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $fichier['tmp_name']);
                finfo_close($finfo);
                $typesAutorises = ['image/jpeg', 'image/png', 'image/webp'];
                if (!in_array($mime, $typesAutorises, true)) {
                    setFlashMessage('warning', 'Type de fichier non autorisé. Utilisez JPEG, PNG ou WebP.');
                    $fichierValide = false;
                }
                // Taille maximale configuree via ACTIVITE_MAX_SIZE_MB.
                $maxUploadBytes = max(1, app_int('ACTIVITE_MAX_SIZE_MB', 5)) * 1024 * 1024;
                if ($fichier['size'] > $maxUploadBytes) {
                    setFlashMessage('warning', 'Le fichier dépasse ' . app_int('ACTIVITE_MAX_SIZE_MB', 5) . ' Mo.');
                    $fichierValide = false;
                }
                // Récupérer l'ancien chemin pour suppression ultérieure
                $oldData = getActiviteImageById($id); // fonction existante
                if ($oldData && !empty($oldData['image_path'])) {
                    $cheminAncien = resolveActiviteImagePath((string) $oldData['image_path']);
                }
            } elseif ($fichier && $fichier['error'] !== UPLOAD_ERR_NO_FILE) {
                setFlashMessage('warning', 'Erreur lors de l\'upload du fichier (code ' . $fichier['error'] . ').');
                $fichierValide = false;
            }

            // Si tout est valide, on procède
            if ($fichierValide) {
                // 1. Mettre à jour les métadonnées (titre, ordre, visible, description)
                $resultMeta = updateActiviteImageMeta($id, $titre, $ordre, $visible, $description);
                if (!$resultMeta['success']) {
                    handleActionResult($resultMeta);
                    break;
                }

                // 2. Si un nouveau fichier a été uploadé, on remplace le fichier physique
                if ($fichier && $fichier['error'] === UPLOAD_ERR_OK) {
                    $resultFichier = replaceActiviteImageFile($id, $fichier['tmp_name'], $mime, $cheminAncien);
                    handleActionResult($resultFichier);
                } else {
                    // Pas de nouveau fichier : on retourne juste le succès des métadonnées
                    setFlashMessage('success', 'Image mise à jour (métadonnées).');
                    // Redirection si nécessaire (handleActionResult peut déjà le faire)
                }
            }

            // Si une redirection est gérée par handleActionResult, on peut l'appeler ici
            // Sinon, on redirige manuellement
            // header('Location: ' . $_SERVER['REQUEST_URI']);
            break;

        case 'delete_image':
            $deleteId = (int) ($_POST['id'] ?? 0);
            $result = deleteActiviteImage($deleteId);
            if (!empty($result['success'])) {
                actionLog('Activite image deleted', ['id' => $deleteId]);
            }
            handleActionResult($result);
            break;
    }
    redirectTo(lien('activite_admin'));
}

$images = getAllActiviteImages();

// N'affiche/gère ici que les images dont l'ordre est >= 6 (les ordres 0 à 5 sont réservés à la page d'accueil)
$images = array_filter($images, fn($image) => (int) ($image['ordre'] ?? 0) >= 6);
usort($images, fn($a, $b) => (int) $a['ordre'] <=> (int) $b['ordre']);

$ordresUtilises = array_map(fn($img) => (int) ($img['ordre'] ?? 0), $images);
?>
<div class="container-fluid home-shell">
    <main class="home-main">
        <div class="page-header breadcrumb-controls stats-header">
            <div>
                <h1>Page d'activité publique</h1>
                <p class="text-muted">Gérer les images et descriptions de vos activités.</p>
            </div>
        </div>

        <?php displayFlashMessage(); ?>

        <div class="card mb-4">
            <div class="card-header">Ajouter une activité</div>
            <div class="card-body">
                <form action="" method="post" enctype="multipart/form-data" class="row g-3" id="addImageForm">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="add_image">
                    
                    <div class="col-md-4">
                        <label for="image" class="form-label">Image (JPG, PNG, WEBP) <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="image" id="image" accept="image/jpeg,image/png,image/webp" required>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="titre" class="form-label">Titre</label>
                        <input type="text" class="form-control" name="titre" id="titre" maxlength="255" oninput="descUpdateCounter(this, 'titreCounter', 255)">
                        <div class="form-text text-end small" id="titreCounter">0 / 255</div>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="ordre" class="form-label">Ordre (à partir de 6, unique)</label>
                        <input type="number" class="form-control" name="ordre" id="ordre" min="6" max="9999" value="6">
                        <small class="text-muted">Déjà utilisés : <?= e($ordresUtilises !== [] ? implode(', ', $ordresUtilises) : 'aucun') ?></small>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-center mt-4">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="visible" id="visible" value="1" checked>
                            <label class="form-check-label" for="visible">Visible publiquement</label>
                        </div>
                    </div>
                    
                    <div class="col-12 mt-0">
                        <label for="description" class="form-label">Description détaillée <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" required maxlength="5000" rows="3" placeholder="Décrivez l'activité : programme, tranche d'âge, environnement, points forts..." oninput="descUpdateCounter(this, 'descCounter', 5000)"></textarea>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <span class="small text-muted"><i class="bi bi-info-circle"></i> Minimum 100 caractères recommandé.</span>
                            <span class="small fw-bold text-muted" id="descCounter">0 / 5000</span>
                        </div>
                    </div>
                    
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary px-4">Ajouter l'activité</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Activités existantes (<?= count($images) ?>)</div>
            <div class="card-body">
                <?php if ($images === []): ?>
                    <div class="alert alert-info" role="status">Aucune activité. Ajoutez la première ci-dessus.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Aperçu</th>
                                    <th>Titre</th>
                                    <th>Description</th>
                                    <th style="width: 110px;">Ordre</th>
                                    <th style="width: 90px;">Visible</th>
                                    <th></th>
                                    <th style="width: 110px;"></th>
                                    <th style="width: 110px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($images as $image): ?>
                                    <tr>
                                        <form action="" method="post" enctype="multipart/form-data" style="display: contents;">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="update_image">
                                            <input type="hidden" name="id" value="<?= e((int) $image['id']) ?>">
                                            <td>
                                                <img src="<?= e(activiteImageUrl((int) $image['id'])) ?>" alt="<?= e((string) ($image['titre'] ?? 'Aperçu')) ?>" style="width: 90px; height: 65px; object-fit: cover; border-radius: 4px;">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm" name="titre" maxlength="255" value="<?= e((string) ($image['titre'] ?? '')) ?>">
                                            </td>
                                            <td>
                                                <textarea class="form-control form-control-sm" name="description" rows="2" maxlength="5000" style="min-width: 220px;"><?= e((string) ($image['description'] ?? '')) ?></textarea>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm" name="ordre" min="6" max="9999" value="<?= e((int) ($image['ordre'] ?? 6)) ?>">
                                            </td>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input" name="visible" value="1"<?= (int) ($image['visible'] ?? 0) === 1 ? ' checked' : '' ?>>
                                            </td>
                                            <td>
                                                <input type="file" class="form-control form-control-sm" name="nouvelle_image" accept="image/jpeg,image/png,image/webp">
                                            </td>
                                            <td>
                                                <button type="submit" class="btn btn-sm btn-primary w-100">Mettre à jour</button>
                                            </td>
                                        </form>
                                        <form action="" method="post" style="display: contents;" onsubmit="return confirm('Supprimer définitivement cette activité et son image ?');">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_image">
                                            <input type="hidden" name="id" value="<?= e((int) $image['id']) ?>">
                                            <td>
                                                <button type="submit" class="btn btn-sm btn-outline-danger w-100">Supprimer</button>
                                            </td>
                                        </form>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
// Prévention de la double soumission
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', () => {
        const btn = form.querySelector('button[type="submit"]');
        if(btn) { 
            // On laisse un léger délai pour que le formulaire s'envoie avant de désactiver le bouton
            setTimeout(() => {
                btn.disabled = true; 
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Traitement...'; 
            }, 10);
        }
    });
});

// Gestion des compteurs de caractères
function descUpdateCounter(el, counterId, max) {
    var len = el.value.length;
    var counter = document.getElementById(counterId);
    if (!counter) return;
    
    counter.textContent = len + ' / ' + max;
    
    if (len >= max) {
        counter.classList.add('text-danger');
        counter.classList.remove('text-warning', 'text-muted');
    } else if (len >= max * 0.85) {
        counter.classList.add('text-warning');
        counter.classList.remove('text-danger', 'text-muted');
    } else {
        counter.classList.add('text-muted');
        counter.classList.remove('text-danger', 'text-warning');
    }
}

// Initialiser les compteurs au chargement pour les champs pré-remplis
document.addEventListener('DOMContentLoaded', function() {
    var titre = document.getElementById('titre');
    var desc  = document.getElementById('description');
    if (titre) descUpdateCounter(titre, 'titreCounter', 255);
    if (desc)  descUpdateCounter(desc,  'descCounter',  5000);
});
</script>