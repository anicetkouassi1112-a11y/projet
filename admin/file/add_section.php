<?php declare(strict_types=1);

require_once __DIR__ . '/../../Backend/utilitaire.php';

requireRole(['directeur'], '../Auth/login.php');

$message   = '';
$alertType = 'danger';
$nomSection  = appCleanText((string) ($_POST['nom_section'] ?? ''), 100);
$genre       = appCleanText((string) ($_POST['genre'] ?? ''), 20);
$ageMin      = $_POST['age_min'] ?? null;
$ageMax      = $_POST['age_max'] ?? null;
$description = appCleanText((string) ($_POST['description'] ?? ''), 255);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $message = 'Jeton CSRF invalide. Veuillez recharger la page.';
        $alertType = 'danger';
    } else {
        $result = creerSection(
            $nomSection,
            $description,
            $genre,
            $ageMin,
            $ageMax
        );
        $message = (string) ($result['message'] ?? '');
        $alertType = (string) ($result['alert_type'] ?? 'danger');

        if (!empty($result['success'])) {
            setFlashMessage('success', $message);
            redirectTo(lien('add_section'));
        }
    }
}

// Rechargement de la liste après traitement POST (sections mises à jour)
$sections = appContainer()->get(\Patro\Inscription\SectionService::class)->getAllSections();
$sectionsByGenre = ['Garçon' => [], 'Fille' => []];
foreach ($sections as $section) {
    $sectionGenre = normalizeGenre((string) ($section['genre'] ?? ''));
    if (isset($sectionsByGenre[$sectionGenre])) {
        $sectionsByGenre[$sectionGenre][] = $section;
    }
}
?>
<div class="container-fluid home-shell">
    <main class="home-main">
        <div class="page-header breadcrumb-controls stats-header">
            <div>
                <h1>Ajouter une section</h1>
            </div>
        </div>

        <?php displayFlashMessage(); ?>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= e($alertType) ?> alert-dismissible fade show" role="alert">
                <?= e($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4 border-success">
            <div class="card-header bg-success text-white">Nouvelle section</div>
            <div class="card-body">
                <form action="" method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="creer_section">
                    <div class="col-md-2">
                        <label for="genre">Genre</label>
                        <select class="form-control" name="genre" id="genre" required>
                            <option value="">Choisir</option>
                            <option value="Garçon"<?= $genre === 'Garçon' ? ' selected' : '' ?>>Garcon</option>
                            <option value="Fille"<?= $genre === 'Fille' ? ' selected' : '' ?>>Fille</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="nom_section">Nom de la section</label>
                        <input type="text" class="form-control" name="nom_section" id="nom_section" maxlength="100" required>
                    </div>
                    <div class="col-md-2">
                        <label for="age_min">Age min</label>
                        <input type="number" class="form-control" name="age_min" id="age_min" min="0" max="120" required>
                    </div>
                    <div class="col-md-2">
                        <label for="age_max">Age max</label>
                        <input type="number" class="form-control" name="age_max" id="age_max" min="0" max="120" required>
                    </div>
                    <div class="col-md-3">
                        <label for="description">Description</label>
                        <input type="text" class="form-control" name="description" id="description" maxlength="255">
                    </div>
                    <div class="col-md-12" style="padding-top: 12px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i>
                            Ajouter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <?php foreach ($sectionsByGenre as $genreKey => $genreSections): ?>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Sections <?= e($genreKey === 'Garçon' ? 'Garcons' : 'Filles') ?></div>
                    <div class="card-body">
                        <?php if ($genreSections): ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Section</th>
                                            <th>Age</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($genreSections as $section): ?>
                                        <tr>
                                            <td><?= e((string) $section['nom_section']) ?></td>
                                            <td><?= e((int) $section['age_min']) ?>-<?= e((int) $section['age_max']) ?> ans</td>
                                            <td><?= e((string) ($section['description'] ?? '')) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">Aucune section definie pour ce genre.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>