<?php declare(strict_types=1);

/** @var array $inscrits */
/** @var int $anneeActive */
/** @var string $messageInscritsTable */
/** @var string $tableTitle */

require_once __DIR__ . '/../../Backend/utilitaire.php';
$tableTitle = $tableTitle ?? 'inscription';
$inscrits = $inscrits ?? [];
$messageInscritsTable = $messageInscritsTable ?? 'Aucun inscrit trouve.';
$canManageInscrits = adminHasRole(['directeur']);
$showSectionColumn = $showSectionColumn ?? sectionBreakdownEnabled($typeSessionActive ?? currentSessionType());
$colspan = 10 + ($showSectionColumn ? 1 : 0) + ($canManageInscrits ? 1 : 0);
?>
<div class="card mb-4 shadow-sm table-panel">
    <div class="card-header">
        <?= e($tableTitle) ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover align-middle mb-0 inscrits-table">
                <colgroup>
                    <col style="width: 20%;">
                    <col style="width: 20%;">
                    <col style="width: 20%;">
                    <col style="width: 20%;">
                    <col style="width: 20%;">
                    <col style="width: 20%;">
                    <?php if ($showSectionColumn): ?>
                    <col style="width: 20%;">
                    <?php endif; ?>
                    <col style="width: 20%;">
                    <col style="width: 20%;">
                    <col style="width: 20%;">
                    <col style="width: 20%;">
                    <col style="width: 30%;">
                </colgroup>
                <thead>
                    <tr>
                        <th class="text-center">N&deg; ordre</th>
                        <th class="text-center">Nom</th>
                        <th class="text-center">Prenom</th>
                        <th class="text-center">Date de Naissance</th>
                        <th class="text-center">Age</th>
                        <th class="text-center">Genre</th>
                        <?php if ($showSectionColumn): ?>
                        <th class="text-center">Section</th>
                        <?php endif; ?>
                        <th class="text-center">Montant</th>
                        <th class="text-center">Tee-shirt</th>
                        <th class="text-center">Telephone</th>
                        <th class="text-center">Adresse</th>
                        <?php if ($canManageInscrits): ?>
                            <th class="text-center">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="inscrits-table-body">
                    <?php if ($inscrits): ?>
                        <?php $filtered_inscrits_count = 0; ?>
                        <?php foreach ($inscrits as $inscrit): ?>
                            <?php if (($inscrit['etat'] ?? '') === 'inscrit'): ?>
                                <?php $filtered_inscrits_count++; ?>
                                <?php require __DIR__ . '/inscrit_row.php'; ?>
                            <?php endif; ?>
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
