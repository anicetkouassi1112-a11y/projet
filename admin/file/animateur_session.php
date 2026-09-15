<?php declare(strict_types=1);

require_once __DIR__ . '/../../Backend/utilitaire.php';

requireRole(['directeur'], '../Auth/login.php');

$animateurRepository = appContainer()->get(\Patro\Domain\Animateur\Repository\AnimateurRepository::class);
$anneeActive = activeYearFromRequest();
$typeSessionActive = activeSessionTypeFromRequest();
$currentSessionId = ensureSession($anneeActive, $typeSessionActive);
$isScolaire = ($typeSessionActive === 'scolaire');

$message = '';
$alertType = 'success';

$sections = appContainer()->get(\Patro\Inscription\SectionService::class)->getAllSections();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $message = 'Jeton CSRF invalide. Veuillez recharger la page.';
        $alertType = 'danger';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        // Seul le déblocage en masse est conservé
        if ($action === 'bulk_unblock') {
            $idsBruts = $_POST['unblock_ids'] ?? [];
            $ids = [];
            foreach ($idsBruts as $idBrut) {
                $id = filter_var($idBrut, FILTER_VALIDATE_INT);
                if ($id) {
                    $ids[] = $id;
                }
            }
            $ids = array_unique($ids);

            if (!$ids) {
                $message = 'Veuillez sélectionner au moins un animateur bloqué.';
                $alertType = 'danger';
            } else {
                $nb = $animateurRepository->bulkUnblockBySession($ids, $currentSessionId);
                $message = $nb > 0 ? "$nb animateur(s) débloqué(s)." : 'Aucune modification effectuée.';
                $alertType = $nb > 0 ? 'success' : 'warning';
            }
        }
    }
}

// ---- Filtres, recherche et tri ----
$filterSection = filter_var($_GET['id_section'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$filterStatut = (string) ($_GET['statut'] ?? '');
if (!in_array($filterStatut, ['', 'actif', 'bloque'], true)) {
    $filterStatut = '';
}
$searchTerm = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($searchTerm) > 100) {
    $searchTerm = mb_substr($searchTerm, 0, 100);
}

$sortOptions = [
    'section' => 'sec.nom_section ASC, a.nom_a ASC, a.prenom_a ASC',
    'nom'     => 'a.nom_a ASC, a.prenom_a ASC',
    'nom_desc' => 'a.nom_a DESC, a.prenom_a DESC',
    'statut'  => 'a.statut ASC, a.nom_a ASC',
    'date'    => 'ans.date_inscription DESC',
    'date_asc' => 'ans.date_inscription ASC',
];
$sortKey = (string) ($_GET['tri'] ?? 'section');
if (!array_key_exists($sortKey, $sortOptions)) {
    $sortKey = 'section';
}

$params = [':id_session' => $currentSessionId];
$where = ['ans.id_session = :id_session'];

if (!$isScolaire && $filterSection > 0) {
    $where[] = 'ans.id_section = :id_section';
    $params[':id_section'] = $filterSection;
}
if ($filterStatut !== '') {
    $where[] = 'a.statut = :statut';
    $params[':statut'] = $filterStatut;
}
if ($searchTerm !== '') {
    $where[] = '(a.nom_a LIKE :q1 OR a.prenom_a LIKE :q2 OR a.tel LIKE :q3)';
    $like = '%' . $searchTerm . '%';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
}

$animateurs = $animateurRepository->findSessionRows(
    $currentSessionId,
    $filterSection,
    $filterStatut,
    $searchTerm,
    $sortKey,
    $isScolaire
);

// ---- Statistiques globales de la session (non filtrées) ----
$stats = $animateurRepository->statsForSession($currentSessionId);

// ---- Totaux par genre (sur tous les animateurs de la session, non filtrés) ----
$totaux = $animateurRepository->countGenderForSession($currentSessionId);
$totalAnimateur = (int) ($totaux['garcons'] ?? 0);
$totalAnimatrice = (int) ($totaux['filles'] ?? 0);

$hasActiveFilters = ($filterSection > 0 || $filterStatut !== '' || $searchTerm !== '');

$pageTitle = 'Attribution des sections animateurs';
$assetBase = '../../Backend/Assets';

/** Construit un lien de tri en conservant les filtres actifs. */
function lienTri(string $cle, string $label, string $sortKeyActuel, int $filterSection, string $filterStatut, string $searchTerm): string
{
    $qs = http_build_query([
        'page' => 'animateur_session',
        'id_section' => $filterSection ?: null,
        'statut' => $filterStatut ?: null,
        'q' => $searchTerm ?: null,
        'tri' => $cle,
    ]);
    $actif = $sortKeyActuel === $cle;
    return '<a href="?' . $qs . '" class="text-decoration-none ' . ($actif ? 'fw-bold text-primary' : 'text-dark') . '">'
        . e($label) . ($actif ? ' <i class="bi bi-caret-down-fill" style="font-size:.65em;"></i>' : '') . '</a>';
}
?>
<div class="container-fluid home-shell">
    <main class="home-main">
        <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h1>Attribution des sections animateurs</h1>
                <p class="mb-0"><?= e($anneeActive) ?> - Session <?= e(sessionTypeLabel($typeSessionActive)) ?></p>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= e($alertType) ?> alert-dismissible fade show" role="alert">
                <?= e($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
        <?php endif; ?>

        <?php displayFlashMessage(); ?>

        <?php if ($isScolaire): ?>
            <div class="alert alert-info">Session scolaire : les animateurs inscrits sont affichés, mais aucune section n'est requise.</div>
        <?php endif; ?>

        <!-- Cartes de statistiques -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="fs-3 fw-bold"><?= e((int) $stats['total']) ?></div>
                        <div class="text-muted small">Inscrits</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card shadow-sm h-100 border-success">
                    <div class="card-body text-center">
                        <div class="fs-3 fw-bold text-success"><?= e((int) $stats['actifs']) ?></div>
                        <div class="text-muted small">Actifs</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card shadow-sm h-100 border-danger">
                    <div class="card-body text-center">
                        <div class="fs-3 fw-bold text-danger"><?= e((int) $stats['bloques']) ?></div>
                        <div class="text-muted small">Bloqués</div>
                    </div>
                </div>
            </div>
            <?php if (!$isScolaire): ?>
                <div class="col-6 col-md-3">
                    <div class="card shadow-sm h-100 border-warning">
                        <div class="card-body text-center">
                            <div class="fs-3 fw-bold text-warning"><?= e((int) $stats['sans_section']) ?></div>
                            <div class="text-muted small">Sans section</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Panneau Répartition par genre -->
        <section class="stats-panels mb-4" aria-label="Répartition des statistiques">
            <article class="stats-panel">
                <div class="stats-panel-heading">
                    <?php if(!$isScolaire): ?>
                        <h2>Attribution des sections</h2>
                    <?php else: ?>
                        <h2>Répartition par genre</h2>
                    <?php endif; ?>
                    <span><?= e((int) $stats['total']) ?> inscrits</span>
                </div>
                <div class="stats-split">
                    <div>
                        <a href="<?= lien('Animateur', ['annee' => $anneeActive, 'type_session' => $typeSessionActive]) ?>">
                            <strong><?= e($totalAnimateur) ?></strong>
                            <span>Animateur</span>
                        </a>
                    </div>
                    <div>
                        <a href="<?= lien('Animatrice', ['annee' => $anneeActive, 'type_session' => $typeSessionActive]) ?>">
                            <strong><?= e($totalAnimatrice) ?></strong>
                            <span>Animatrice</span>
                        </a>
                    </div>
                </div>
                <?php
                $garconPercent = $stats['total'] > 0 ? round(($totalAnimateur / $stats['total']) * 100, 1) : 0;
                $fillePercent = $stats['total'] > 0 ? round(($totalAnimatrice / $stats['total']) * 100, 1) : 0;
                ?>
                <div class="stats-bar" aria-label="Garçons <?= e($garconPercent) ?>%, filles <?= e($fillePercent) ?>%">
                    <span class="stats-bar-primary" style="width: <?= e($garconPercent) ?>%"></span>
                    <span class="stats-bar-accent" style="width: <?= e($fillePercent) ?>%"></span>
                </div>
            </article>
        </section>

        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-info text-dark d-flex justify-content-between align-items-center">
                <span>Animateurs inscrits à la session</span>
                <span class="badge bg-secondary"><?= e(count($animateurs)) ?> résultat(s)</span>
            </div>
            <div class="card-body border-bottom">
                <form method="get" class="row g-3 align-items-end">
                    <input type="hidden" name="page" value="animateur_session">
                    <input type="hidden" name="tri" value="<?= e($sortKey) ?>">

                    <div class="col-md-3">
                        <label for="q" class="form-label">Recherche</label>
                        <input type="text" class="form-control" id="q" name="q" placeholder="Nom, prénom ou téléphone" value="<?= e($searchTerm) ?>" maxlength="100">
                    </div>

                    <div class="col-md-3">
                        <label for="statut" class="form-label">Statut</label>
                        <select class="form-select" name="statut" id="statut">
                            <option value=""<?= $filterStatut === '' ? ' selected' : '' ?>>Tous</option>
                            <option value="actif"<?= $filterStatut === 'actif' ? ' selected' : '' ?>>Actif</option>
                            <option value="bloque"<?= $filterStatut === 'bloque' ? ' selected' : '' ?>>Bloqué</option>
                        </select>
                    </div>

                    <?php if (!$isScolaire): ?>
                        <div class="col-md-3">
                            <label for="id_section" class="form-label">Section</label>
                            <select class="form-select" name="id_section" id="id_section">
                                <option value="0">Toutes</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?= e((int) $section['id_section']) ?>"<?= $filterSection === (int) $section['id_section'] ? ' selected' : '' ?>>
                                        <?= e($section['nom_section']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Filtrer</button>
                        <?php if ($hasActiveFilters): ?>
                            <a href="?page=animateur_session" class="btn btn-outline-secondary">Réinitialiser</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if (!$animateurs): ?>
                <div class="p-4 text-center text-muted">
                    Aucun animateur ne correspond à ces critères pour cette session.
                </div>
            <?php else: ?>
                <form method="post" id="formAnimateurs">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <?php if (!$isScolaire): ?>
                                        <th style="width:2.5rem;"></th>
                                    <?php endif; ?>
                                    <th><?= lienTri('nom', 'Nom / Prénom', $sortKey, $filterSection, $filterStatut, $searchTerm) ?></th>
                                    <th>Téléphone</th>
                                    <?php if (!$isScolaire): ?>
                                        <th><?= lienTri('section', 'Section', $sortKey, $filterSection, $filterStatut, $searchTerm) ?></th>
                                    <?php endif; ?>
                                    <th><?= lienTri('statut', 'Statut', $sortKey, $filterSection, $filterStatut, $searchTerm) ?></th>
                                    <th><?= lienTri('date', 'Inscrit le', $sortKey, $filterSection, $filterStatut, $searchTerm) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($animateurs as $animateur): ?>
                                    <tr>
                                        <?php if (!$isScolaire): ?>
                                            <td>
                                                <?php if ($animateur['statut'] === 'bloque'): ?>
                                                    <input type="checkbox" class="form-check-input unblock-check" name="unblock_ids[]" value="<?= e((int) $animateur['id_animateur']) ?>" title="Sélectionner pour débloquer">
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                        <td><?= e($animateur['nom_a']) ?> <?= e($animateur['prenom_a']) ?></td>
                                        <td><?= e($animateur['tel']) ?></td>
                                        <?php if (!$isScolaire): ?>
                                            <td>
                                                <?php if (!empty($animateur['nom_section'])): ?>
                                                    <?= e($animateur['nom_section']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Non attribuée</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <span class="badge <?= $animateur['statut'] === 'actif' ? 'bg-success' : 'bg-danger' ?>">
                                                <?= e($animateur['statut']) ?>
                                            </span>
                                        </td>
                                        <td><?= e(date('d/m/Y H:i', strtotime((string) $animateur['date_inscription']))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="card-body d-flex flex-wrap gap-2 justify-content-end border-top">
                        <button type="submit" name="action" value="bulk_unblock" class="btn btn-success"
                                onclick="return confirm('Débloquer les animateurs sélectionnés ?');">
                            Débloquer la sélection
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </main>
</div>