<?php declare(strict_types=1);

require_once __DIR__ . '/../../Backend/utilitaire.php';

requireRole(['directeur'], '../Auth/login.php');

$currentSessionId = ensureSession($anneeActive, $typeSessionActive, $conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlashMessage('danger', 'Jeton CSRF invalide.');
        redirectTo(lien('accueil_admin'));
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add_theme') {
        $titre = appCleanText((string) ($_POST['titre'] ?? ''), 100);
        $sessionId = filter_var($_POST['session_id'] ?? 0, FILTER_VALIDATE_INT);
        $result = Addtheme($titre, $sessionId === false ? 0 : (int) $sessionId);
        setFlashMessage(
            !empty($result['success']) ? 'success' : (string) ($result['alert_type'] ?? 'danger'),
            (string) ($result['message'] ?? '')
        );
    } elseif ($action === 'update_theme') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $titre = appCleanText((string) ($_POST['titre'] ?? ''), 100);
        $sessionId = filter_var($_POST['session_id'] ?? 0, FILTER_VALIDATE_INT);
        $result = updateTheme((int) $id, $titre, $sessionId === false ? 0 : (int) $sessionId);
        setFlashMessage(
            !empty($result['success']) ? 'success' : (string) ($result['alert_type'] ?? 'danger'),
            (string) ($result['message'] ?? '')
        );
    } elseif ($action === 'delete_theme') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $result = deleteTheme((int) $id);
        setFlashMessage(
            !empty($result['success']) ? 'success' : (string) ($result['alert_type'] ?? 'danger'),
            (string) ($result['message'] ?? '')
        );
    } elseif ($action === 'add_image') {
        $titre = appCleanText((string) ($_POST['titre'] ?? ''), 255);
        $ordre = filter_var($_POST['ordre'] ?? 0, FILTER_VALIDATE_INT);
        $ordre = $ordre === false ? 0 : max(0, min(5, (int) $ordre));
        $visible = isset($_POST['visible']) && $_POST['visible'] === '1';

        $imagesExistantes = array_filter(getAllActiviteImages(), fn($img) => (int) ($img['ordre'] ?? -1) >= 0 && (int) ($img['ordre'] ?? -1) <= 5);

        $ordreDejaPris = array_filter($imagesExistantes, fn($img) => (int) ($img['ordre'] ?? -1) === $ordre);

        if (count($imagesExistantes) >= 6) {
            setFlashMessage('warning', 'Maximum de 6 images atteint (ordre 0 à 5). Supprimez-en une avant d\'en ajouter une nouvelle.');
        } elseif ($ordreDejaPris !== []) {
            setFlashMessage('warning', 'L\'ordre ' . $ordre . ' est déjà utilisé par une autre image. Choisissez un ordre libre.');
        } else {
            $result = saveActiviteImageUpload($_FILES['image'] ?? [], $titre, $ordre, $visible);
            setFlashMessage($result['success'] ? 'success' : 'danger', (string) ($result['message'] ?? ''));
        }
    } elseif ($action === 'update_image') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $titre = appCleanText((string) ($_POST['titre'] ?? ''), 255);
        $ordre = filter_var($_POST['ordre'] ?? 0, FILTER_VALIDATE_INT);
        $ordre = $ordre === false ? 0 : max(0, min(5, (int) $ordre));
        $visible = isset($_POST['visible']) && $_POST['visible'] === '1';

        $autresImages = array_filter(getAllActiviteImages(), fn($img) => (int) $img['id'] !== (int) $id && (int) ($img['ordre'] ?? -1) >= 0 && (int) ($img['ordre'] ?? -1) <= 5);
        $ordreDejaPris = array_filter($autresImages, fn($img) => (int) ($img['ordre'] ?? -1) === $ordre);

        
        if ($ordreDejaPris !== []) {
            setFlashMessage('warning', 'L\'ordre ' . $ordre . ' est déjà utilisé par une autre image. Choisissez un ordre libre.');
        } else {
            $result = updateActiviteImageMeta((int) $id, $titre, $ordre, $visible);
            setFlashMessage($result['success'] ? 'success' : 'danger', (string) ($result['message'] ?? ''));
        }
    } elseif ($action === 'delete_image') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $result = deleteActiviteImage((int) $id);
        setFlashMessage($result['success'] ? 'success' : 'danger', (string) ($result['message'] ?? ''));
    }

    redirectTo(lien('accueil_admin'));
}

$images = getAllActiviteImages();
$imagesAccueil = array_filter($images, fn($img) => (int) ($img['ordre'] ?? -1) >= 0 && (int) ($img['ordre'] ?? -1) <= 5);
$themes = getAllThemes();
$sessions = getAllSessions();
$ordresUtilises = array_map(fn($img) => (int) ($img['ordre'] ?? 0), $imagesAccueil);
?>
<div class="container-fluid home-shell">
    <main class="home-main">
        <div class="page-header breadcrumb-controls stats-header">
            <div>
                <h1>Page d'accueil publique</h1>
                <p class="text-muted">Gerer les images affichees sur la vitrine (stockage securise hors webroot).</p>
            </div>
        </div>

        <?php displayFlashMessage(); ?>

        <div class="card mb-4">
            <div class="card-header">Ajouter un thème</div>
            <div class="card-body">
                <?php if ($sessions === []): ?>
                    <div class="alert alert-warning" role="alert">Aucune session disponible. Creez d'abord une session (annee + type) avant d'ajouter un thème.</div>
                <?php else: ?>
                    <form action="" method="post" enctype="multipart/form-data" class="row g-3" id="addThemeForm">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="add_theme">

                        <div class="col-md-7">
                            <label for="theme_titre">Thème</label>
                            <input type="text" class="form-control" name="titre" id="theme_titre" maxlength="100" required>
                        </div>

                        <div class="col-md-3">
                            <label for="id_session">Session</label>
                            <?php
                            // Récupérer la session par défaut pour l'année active
                            // On utilise la fonction ensureSession avec le type de session courant (par défaut)
                            $defaultSessionType = currentSessionType(); // ou une valeur fixe selon votre logique
                            $defaultSessionId = ensureSession($anneeActive, $defaultSessionType);

                            // On peut aussi rechercher dans $sessions pour avoir le libellé
                            $activeSession = null;
                            foreach ($sessions as $session) {
                                if ((int)$session['id_session'] === (int)$defaultSessionId) {
                                    $activeSession = $session;
                                    break;
                                }
                            }
                            if ($activeSession) :
                            ?>
                                <input type="hidden" name="session_id" value="<?= e((int)$activeSession['id_session']) ?>">
                                <input type="text" class="form-control" 
                                    value="<?= e(sessionTypeLabel((string)$activeSession['type_session'])) ?>" 
                                    disabled readonly>
                            <?php else : ?>
                                <p class="text-warning">Aucune session active trouvée.</p>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100" data-loading-text="...">Ajouter</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header">
                Ajouter une image
                <?php if (count($imagesAccueil) >= 6): ?>
                    <span class="badge bg-warning text-dark float-end">Limite atteinte (6/6)</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (count($imagesAccueil) >= 6): ?>
                    <div class="alert alert-warning" role="alert">Le maximum de 6 images (ordre 0 à 5) est atteint. Supprimez une image existante pour en ajouter une nouvelle.</div>
                <?php else: ?>
                    <form action="" method="post" enctype="multipart/form-data" class="row g-3" id="addImageForm">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="add_image">
                        <div class="col-md-4">
                            <label for="image">Image (JPG, PNG, WEBP)</label>
                            <input type="file" class="form-control" name="image" id="image" accept="image/jpeg,image/png,image/webp" required>
                        </div>
                        <div class="col-md-3">
                            <label for="titre">Titre</label>
                            <input type="text" class="form-control" name="titre" id="titre" maxlength="255">
                        </div>
                        <div class="col-md-2">
                            <label for="ordre">Ordre (0 à 5, unique)</label>
                            <input type="number" class="form-control" name="ordre" id="ordre" min="0" max="5" value="0">
                            <small class="text-muted">Déjà utilisés : <?= e(implode(', ', $ordresUtilises) ?: 'aucun') ?></small>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="visible" id="visible" value="1" checked>
                                <label class="form-check-label" for="visible">Visible</label>
                            </div>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100" data-loading-text="...">Ajouter</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Images existantes (<?= count($imagesAccueil) ?> / 6)</div>
            <div class="card-body">
                <?php
                    $imagesAffichees = array_filter($imagesAccueil, function ($image) {
                        return isset($image['ordre']) && (int) $image['ordre'] >= 0 && (int) $image['ordre'] <= 5;
                    });
                    usort($imagesAffichees, fn($a, $b) => (int) $a['ordre'] <=> (int) $b['ordre']);
                    $imagesAffichees = array_slice($imagesAffichees, 0, 6);
                ?>
                <?php if ($imagesAffichees === []): ?>
                    <div class="alert alert-info" role="status">Aucune image. Ajoutez la premiere ci-dessus.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Aperçu</th>
                                    <th>Titre</th>
                                    <th style="width: 100px;">Ordre</th>
                                    <th style="width: 90px;">Visible</th>
                                    <th style="width: 130px;"></th>
                                    <th style="width: 110px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($imagesAffichees as $image): ?>
                                    <tr>
                                        <form action="" method="post" style="display: contents;">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="update_image">
                                            <input type="hidden" name="id" value="<?= e((int) $image['id']) ?>">
                                            <td>
                                                <img src="<?= e(activiteImageUrl((int) $image['id'])) ?>" alt="<?= e((string) ($image['titre'] ?? 'Apercu')) ?>" style="width: 80px; height: 60px; object-fit: cover; border-radius: 4px;">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm" name="titre" maxlength="255" value="<?= e((string) ($image['titre'] ?? '')) ?>">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm" name="ordre" min="0" max="5" value="<?= e((int) ($image['ordre'] ?? 0)) ?>">
                                            </td>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input" name="visible" value="1"<?= (int) ($image['visible'] ?? 0) === 1 ? ' checked' : '' ?>>
                                            </td>
                                            <td>
                                                <button type="submit" class="btn btn-sm btn-primary w-100">Mettre a jour</button>
                                            </td>
                                        </form>
                                        <form action="" method="post" style="display: contents;" onsubmit="return confirm('Supprimer cette image ?');">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_image">
                                            <input type="hidden" name="id" value="<?= e((int) $image['id']) ?>">
                                            <td>
                                                <button type="submit" class="btn btn-sm btn-danger w-100">Supprimer</button>
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