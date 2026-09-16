<?php
/** @var array $inscrits Liste des animateurs/animatrices (colonnes: nom, prenom, genre, tel, statut, id_session, id_section, nom_section) */
/** @var int $anneeActive */
/** @var string $messageInscritsTable */
/** @var string $tableTitle */

require_once __DIR__ . '/../../Backend/utilitaire.php';

$tableTitle = $tableTitle ?? 'animateurs';
$inscrits = $inscrits ?? [];
$messageInscritsTable = $messageInscritsTable ?? 'Aucun animateur trouve.';
$canManageAnimateurs = adminHasRole(['directeur']);
$showSectionColumn = $showSectionColumn ?? appContainer()->get(\Patro\Inscription\SessionService::class)->sectionBreakdownEnabled($typeSessionActive ?? appContainer()->get(\Patro\Inscription\SessionService::class)->getCurrentSessionType());
$colspan = 6 + ($showSectionColumn ? 1 : 0) + ($canManageAnimateurs ? 1 : 0);

// --- Récupération des sections pour le filtrage dans animateur_row.php ---
$allSections = appContainer()->get(\Patro\Inscription\SectionService::class)->getAllSections(); // nécessaire pour le select des sections
// Attribution des sections animateurs
// --- Détermination de la page courante pour le genre cible ---
$currentPage = $currentPage ?? $GLOBALS['pageName'] ?? requestTextParam('page', 40);
?>
<div class="card mb-4 shadow-sm table-panel">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover align-middle mb-0 animateurs-table">
                <colgroup>
                    <col style="width: 10%;">
                    <col style="width: 20%;">
                    <col style="width: 20%;">
                    <col style="width: 15%;">
                    <col style="width: 10%;">
                    <?php if ($showSectionColumn): ?>
                    <col style="width: 15%;">
                    <?php endif; ?>
                    <col style="width: 10%;">
                    <?php if ($canManageAnimateurs): ?>
                    <col style="width: 20%;">
                    <?php endif; ?>
                </colgroup>
                <thead>
                    <tr>
                        <th class="text-center">N&deg; ordre</th>
                        <th class="text-center">Nom</th>
                        <th class="text-center">Prenom</th>
                        <th class="text-center">Telephone</th>
                        <th class="text-center">Genre</th>
                        <?php if ($showSectionColumn): ?>
                        <th class="text-center">Section</th>
                        <?php endif; ?>
                        <th class="text-center">Statut</th>
                        <?php if ($canManageAnimateurs): ?>
                            <th class="text-center">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="animateurs-table-body">
                    <?php if ($inscrits): ?>
                        <?php $filtered_animateurs_count = 0; ?>
                        <?php foreach ($inscrits as $inscrit): ?>
                            <?php $filtered_animateurs_count++; ?>
                            <?php require __DIR__ . '/animateur_row.php'; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= e($colspan) ?>" class="text-center text-muted py-4">
                                <?= e($messageInscritsTable) ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>