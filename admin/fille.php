<?php
// Ce fichier est inclus dans home.php.
// Les variables $context, $sectionConfig, $anneeActive, $typeSessionActive existent déjà.

$searchQuery = requestTextParam('search', 80);
$inscrits = $context['inscription'] ?? [];
$tableTitle = $context['title'] ?? 'Inscrits';
$messageInscritsTable = $context['empty_message'] ?? 'Aucun inscrit.';
$showSectionColumn = $context['show_section_breakdown'] ?? false;

// Filtre de recherche par nom/prénom
if ($searchQuery !== '') {
    $needle = normalizeLookupKey($searchQuery);
    $inscrits = array_values(array_filter($inscrits, static function (array $inscrit) use ($needle): bool {
        $nomComplet = trim((string) ($inscrit['nom'] ?? '') . ' ' . (string) ($inscrit['prenom'] ?? ''));
        return str_contains(normalizeLookupKey($nomComplet), $needle);
    }));
    $tableTitle = 'Résultat de recherche - ' . $context['title'];
    $messageInscritsTable = 'Aucun résultat pour "' . e($searchQuery) . '".';
}
?>
<div class="page-header breadcrumb-controls">
    <h1><?= e($tableTitle) ?></h1>
</div>

<?php displayFlashMessage(); ?>
<div id="ajax-flash-message-container"></div>

<?php require __DIR__ . '/partial/inscrits_table.php'; ?>