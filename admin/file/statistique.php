<?php declare(strict_types=1);

require_once __DIR__ . '/../../Backend/utilitaire.php';

requireRole(['directeur'], '../Auth/login.php');

$anneeActive = displayYearFromRequest((int) date('Y'));
$typeSessionActive = displaySessionTypeFromRequest(appContainer()->get(\Patro\Inscription\SessionService::class)->getCurrentSessionType());
$anneeId = appContainer()->get(\Patro\Inscription\SessionService::class)->ensureAnnee($anneeActive);
$searchQuery = trim((string) ($_GET['search'] ?? ''));
$sectionStats = [];
$showSectionBreakdown = sectionBreakdownEnabled($typeSessionActive);
$teeShirtStats = [];
$montantTotalInscription = [];
$pendingCount = 0;
$totaux = [];

try {
    $statisticsRepository = appContainer()->get(\Patro\Domain\Statistics\Repository\StatisticsRepository::class);
    $totaux = $statisticsRepository->totals(
        $anneeId,
        $typeSessionActive,
        'inscrit',
        \Patro\Domain\Inscription\Genre::GARCON->value,
        \Patro\Domain\Inscription\Genre::FILLE->value
    );
    $pendingCount = $statisticsRepository->pendingCount($anneeId, $typeSessionActive, 'En attente');

    if ($showSectionBreakdown) {
        $sectionStats = $statisticsRepository->sectionTotals($anneeId, $typeSessionActive, 'inscrit');
    }

    $teeShirtStats = $statisticsRepository->teeShirtTotals($anneeId, $typeSessionActive, 'inscrit');
    $montantTotalInscription = [
        'montant_inscription' => $statisticsRepository->registrationAmount($anneeId, $typeSessionActive, 'inscrit'),
    ];
} catch (PDOException $e) {
    error_log('Statistique load error: ' . $e->getMessage());
    setFlashMessage('danger', 'Erreur pendant le chargement des statistiques.');
}

$totalGarcon = (int) ($totaux['garcons'] ?? 0);
$totalFille = (int) ($totaux['filles'] ?? 0);
$nombreTotal = (int) ($totaux['total'] ?? 0);
$totalTeeShirts = (int) ($teeShirtStats['total_tee_shirts'] ?? 0);
$totalMontantInscription = (int) ($montantTotalInscription['montant_inscription'] ?? 0);
$totalVentesTeeShirts = (int) ($teeShirtStats['total_ventes'] ?? 0);
$totalDossiers = max(1, $nombreTotal + $pendingCount);
$genderTotal = max(1, $totalGarcon + $totalFille);
$garconPercent = (int) round(($totalGarcon / $genderTotal) * 100);
$fillePercent = (int) round(($totalFille / $genderTotal) * 100);
$pendingPercent = (int) round(($pendingCount / $totalDossiers) * 100);
$validatedPercent = (int) round(($nombreTotal / $totalDossiers) * 100);
$anneesDisponibles = appContainer()->get(\Patro\Inscription\SessionService::class)->getDistinctYears();
$sessionLabel = sessionTypeLabel($typeSessionActive);
$pageTitle = 'Statistiques - ' . $anneeActive . ' - ' . $sessionLabel;
$assetBase = rtrim($assetBase ?? '../Backend/Assets', '/');
?>
<div class="container-fluid home-shell">
    <main class="home-main">
        <div class="page-header breadcrumb-controls stats-header">
            <div>
                <span class="stats-eyebrow">Tableau de bord</span>
                <h1>Statistiques</h1>
                <p><?= e($anneeActive) ?> - Session <?= e($sessionLabel) ?></p>
            </div>
            <div class="breadcrumb-buttons">
                <?php
                    $yearRoute = 'home.php';
                    $yearQueryParams = ['page' => 'statistique'];
                ?>
                <?php require __DIR__ . '/../partial/year_navigation.php'; ?>
                <?php foreach (\Patro\Domain\Inscription\SessionType::values() as $typeSession): ?>
                    <a class="btn btn-secondary btn-sm <?= $typeSession === $typeSessionActive ? 'active' : '' ?>"
                    href="<?= e(lien('statistique', ['annee' => $anneeActive, 'type_session' => $typeSession, 'search' => $searchQuery])) ?>">
                        <?= e(sessionTypeLabel($typeSession)) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php displayFlashMessage(); ?>

        <section class="stats-kpi-grid" aria-label="Indicateurs principaux">
            <div class="stats-kpi stats-kpi-primary ">
                <span>Montant inscriptions</span>
                <strong><?= e(formatFcfa($totalMontantInscription)) ?></strong>
                <small><?= e($nombreTotal) ?> inscrits valides</small>
            </div>
            <div class="stats-kpi stats-kpi-link">
                <span>Tee-shirts payes</span>
                    <strong><?= e($totalTeeShirts) ?></strong>
                <small><?= e(formatFcfa($totalVentesTeeShirts)) ?></small>
            </div>
            <div class="stats-kpi stats-kpi-link">
                <span>Inscriptions validees</span>
                    <strong><?= e($nombreTotal) ?></strong>
                <small><?= e($validatedPercent) ?>% des dossiers</small>
            </div>
            <div class="stats-kpi stats-kpi-link">
                <span>En attente de validation</span>
                <strong><?= e($pendingCount) ?></strong>
                <small><?= e($pendingPercent) ?>% des dossiers</small>
            </div>
        </section>

        <section class="stats-panels" aria-label="Repartition des statistiques">
            <article class="stats-panel">
                <div class="stats-panel-heading">
                    <h2>Repartition par genre</h2>
                    <span><?= e($genderTotal === 1 && $nombreTotal === 0 ? 0 : $genderTotal) ?> inscrits</span>
                </div>
                <div class="stats-split">
                    <div>
                        <a href="<?= lien('garcon', ['annee' => $anneeActive, 'type_session' => $typeSessionActive]) ?>">
                            <strong><?= e($totalGarcon) ?></strong>
                            <span>Garcons</span>
                        </a>
                    </div>
                    <div>
                        <a href="<?= lien('fille', ['annee' => $anneeActive, 'type_session' => $typeSessionActive]) ?>">
                            <strong><?= e($totalFille) ?></strong>
                            <span>Filles</span>
                        </a>
                    </div>
                </div>
                <div class="stats-bar" aria-label="Garcons <?= e($garconPercent) ?>%, filles <?= e($fillePercent) ?>%">
                    <span class="stats-bar-primary" style="width: <?= e($garconPercent) ?>%"></span>
                    <span class="stats-bar-accent" style="width: <?= e($fillePercent) ?>%"></span>
                </div>
            </article>

            <article class="stats-panel">
                <div class="stats-panel-heading">
                    <h2>Tailles tee-shirt</h2>
                    <span><?= e($totalTeeShirts) ?> tee-shirts</span>
                </div>
                <div class="stats-size-grid">
                    <?php foreach (validTeeShirtSizes() as $size): ?>
                        <?php
                        $key = 'taille_' . strtolower($size);
                        $sizeTotal = (int) ($teeShirtStats[$key] ?? 0);
                        $sizePercent = $totalTeeShirts > 0 ? (int) round(($sizeTotal / $totalTeeShirts) * 100) : 0;
                        ?>
                        <div class="stats-size-row">
                            <span><?= e($size) ?></span>
                            <div class="stats-progress"><span style="width: <?= e($sizePercent) ?>%"></span></div>
                            <strong><?= e($sizeTotal) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>

        <?php if ($showSectionBreakdown): ?>
        <section class="stats-panel" aria-label="Statistiques par section">
            <div class="stats-panel-heading">
                <h2>Repartition par section</h2>
                <span><?= e(count($sectionStats)) ?> sections</span>
            </div>
            <div class="table-responsive stats-table-wrap">
                <table class="table table-sm table-striped table-hover align-middle mb-0 stats-table">
                    <thead>
                        <tr>
                            <th>Section</th>
                            <th>Total</th>
                            <th>Part</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sectionStats as $section): ?>
                            <?php
                            $sectionTotal = (int) ($section['total'] ?? 0);
                            $sectionPercent = $nombreTotal > 0 ? (int) round(($sectionTotal / $nombreTotal) * 100) : 0;
                            ?>
                            <tr>
                                <td><?= e(canonicalSectionName($section['section_nom'] ?? null)) ?></td>
                                <td><?= e($sectionTotal) ?></td>
                                <td>
                                    <div class="stats-progress stats-progress-table"><span style="width: <?= e($sectionPercent) ?>%"></span></div>
                                    <small><?= e($sectionPercent) ?>%</small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$sectionStats): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted">Aucune statistique disponible.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
    </main>
</div>