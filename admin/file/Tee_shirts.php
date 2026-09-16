<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Backend/utilitaire.php';

requireRole(['directeur', 'suppleant_1', 'suppleant_2'], '../Auth/login.php');

$anneeActive = activeYearFromRequest();
$typeSessionActive = activeSessionTypeFromRequest();
$searchQuery = requestTextParam('search', 80);
$genreFilterKey = strtolower(trim((string) ($_GET['genre'] ?? $_POST['genre'] ?? '')));
$genreFilter = match ($genreFilterKey) {
    'garcon', 'garcons', 'garçon', 'garçons' => normalizeGenre('Garcon'),
    'fille', 'filles' => normalizeGenre('Fille'),
    default => '',
};

$teeShirtRegistrations = [];
$message = '';
$genderLabel = $genreFilter === '' ? 'Tous les genres' : ($genreFilter === normalizeGenre('Fille') ? 'Filles' : 'Garçons');
$showSectionColumn = sectionBreakdownEnabled($typeSessionActive);

$baseRedirectParams = [
    'annee' => $anneeActive,
    'type_session' => $typeSessionActive,
    'search' => $searchQuery,
];
if ($genreFilterKey !== '') {
    $baseRedirectParams['genre'] = $genreFilterKey;
}

try {
    $anneeId = appContainer()->get(\Patro\Inscription\SessionService::class)->ensureAnnee($anneeActive);
    $teeShirtRegistrations = appContainer()
        ->get(\Patro\Domain\Inscription\Repository\InscriptionRepository::class)
        ->findPaidTeeShirts($anneeId, $typeSessionActive, $genreFilter, $searchQuery);

    if ($searchQuery !== '' && !$teeShirtRegistrations) {
        $message = 'Aucun resultat pour "' . e($searchQuery) . '".';
    }
} catch (PDOException $e) {
    error_log('Tee-shirt list error: ' . $e->getMessage());
    $message = 'Erreur pendant le chargement.';
}

// Calcul des statistiques globales pour affichage optionnel
$totalTeeShirts = count($teeShirtRegistrations);
$totalAmount = array_reduce(
    $teeShirtRegistrations,
    static fn (int $total, array $inscrit): int => $total + (int) ($inscrit['prix_tee_shirt'] ?? 0),
    0
);

// Structuration/Regroupement des données
$groupedRegistrations = [];
if ($showSectionColumn) {
    foreach ($teeShirtRegistrations as $inscrit) {
        $key = canonicalSectionName($inscrit['section'] ?? null) ?: 'Sans Section';
        $groupedRegistrations[$key][] = $inscrit;
    }
} else {
    foreach ($teeShirtRegistrations as $inscrit) {
        $key = ($inscrit['genre'] === normalizeGenre('Fille')) ? 'Filles' : 'Garçons';
        $groupedRegistrations[$key][] = $inscrit;
    }
}

$pageTitle = 'Tee-shirts Payés - ' . $genderLabel . ' - ' . sessionTypeLabel($typeSessionActive);

// Fonction de nettoyage pour l'affichage
function safeDisplay($value) {
    return e((string) $value);
}

?>

<div class="container-fluid home-shell">
    <main class="home-main">
        <div class="page-header breadcrumb-controls">
            <h1><?= e($pageTitle) ?></h1>
        </div>

        <?php displayFlashMessage(); ?>

        <?php if ($message !== ''): ?>
            <div class="alert alert-info"><?= e($message) ?></div>
        <?php endif; ?>

        <div class="alert alert-success d-flex justify-content-between align-items-center">
            <span><strong>Total des tee-shirts payés :</strong> <?= $totalTeeShirts ?></span>
            <span><strong>Montant total collecté :</strong> <?= formatFcfa($totalAmount) ?></span>
        </div>

        <div class="table-responsive pending-table-wrap">
            <table class="table table-sm table-striped table-hover align-middle mb-0 pending-table table-fixed-columns">
                <colgroup>
                    <col style="width: 8%;">
                    <col style="width: <?= $showSectionColumn ? '20%' : '20%' ?>;">
                    <col style="width: <?= $showSectionColumn ? '20%' : '20%' ?>;">
                    <col style="width: 20%;">
                    <?php if ($showSectionColumn): ?>
                        <col style="width: 20%;">
                    <?php endif; ?>
                    <col style="width: 20%;">
                    <col style="width: 20%;">
                    <col style="width: <?= $showSectionColumn ? '20%' : '20%' ?>;">
                </colgroup>
                <thead>
                    <tr>
                        <th class="text-center" >N&deg; ordre</th>
                        <th>Nom</th>
                        <th>Prenom</th>
                        <th>Genre</th>
                        <?php if ($showSectionColumn): ?>
                            <th>Section</th>
                        <?php endif; ?>
                        <th>Taille</th>
                        <th>Montant</th>
                        <th>Telephone</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($teeShirtRegistrations)): ?>
                        <tr>
                            <td colspan="<?= e($showSectionColumn ? 8 : 7) ?>" class="text-center text-muted">Aucun tee-shirt payé pour ce filtre.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($groupedRegistrations as $groupName => $inscrits): ?>
                            <tr class="table-secondary fw-bold">
                                <td colspan="<?= e($showSectionColumn ? 8 : 7) ?>" class="ps-3 text-uppercase small">
                                    <?= e($groupName) ?> (<?= count($inscrits) ?>)
                                </td>
                            </tr>
                            
                            <?php foreach ($inscrits as $inscrit): ?>
                                <tr id="inscrit-row-<?= safeDisplay($idInscrit) ?>">
                                    <td class="text-center"><?= safeDisplay($currentRowNumber) ?></td>
                                    <td><?= e(trim((string) ($inscrit['nom'] ?? ''))) ?></td>
                                    <td><?= e((string) ($inscrit['prenom'] ?? '')) ?></td>
                                    <td><?= e((string) ($inscrit['genre'] ?? '')) ?></td>
                                    <?php if ($showSectionColumn): ?>
                                        <td><?= e(canonicalSectionName($inscrit['section'] ?? null)) ?></td>
                                    <?php endif; ?>
                                    <td><strong><?= e((string) ($inscrit['taille_tee_shirt'] ?? '')) ?></strong></td>
                                    <td><?= e(formatFcfa((int) ($inscrit['prix_tee_shirt'] ?? 0))) ?></td>
                                    <td><?= e((string) ($inscrit['tel'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>