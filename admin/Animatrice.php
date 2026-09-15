<?php
// Ce fichier est inclus dans le contenu principal (ex: via home.php).
// Les variables $context, $sectionConfig, $anneeActive, $typeSessionActive existent déjà[cite: 34].

$searchQuery = requestTextParam('search', 80);
$sectionParam = requestTextParam('section', 20);
// Récupération des inscrites depuis le contexte spécifique aux filles[cite: 33, 37]
$inscrits = $context['animateurs'] ?? $context['inscription'] ?? [];
$tableTitle = $context['title'] ?? 'Animatrices Filles';
$messageInscritsTable = $context['empty_message'] ?? 'Aucune animatrice trouvée.';
$showSectionColumn = $context['show_section_breakdown'] ?? false;

// Filtre de recherche par nom/prénom[cite: 33]
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
<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-0"><?= e($tableTitle) ?></h1>
    </div>
    
    <!-- Formulaire de recherche -->
    <form method="get" class="d-flex align-items-center gap-2">
        <input type="hidden" name="page" value="Animatrice">
        <?php 
        if ($sectionParam !== '' && ctype_digit($sectionParam)): ?>
            <input type="hidden" name="section" value="<?= e($sectionParam) ?>">
        <?php endif; ?>
        
        <div class="input-group">
            <input type="text" class="form-control" name="search" placeholder="Rechercher une animatrice..." value="<?= e($searchQuery) ?>" maxlength="80">
            <button class="btn btn-primary" type="submit" aria-label="Rechercher">
                <i class="bi bi-search" aria-hidden="true"></i>
            </button>
        </div>
        
        <?php if ($searchQuery !== ''): ?>
            <a href="?page=Animatrice<?= $sectionParam !== '' ? '&section='.e($sectionParam) : '' ?>" class="btn btn-outline-secondary" title="Réinitialiser la recherche">
                <i class="bi bi-x-circle" aria-hidden="true"></i>
            </a>
        <?php endif; ?>
    </form>
</div>

<?php displayFlashMessage(); ?>
<div id="ajax-flash-message-container"></div>

<!-- Inclusion du tableau d'affichage[cite: 33, 36] -->
<?php require __DIR__ . '/partial/animateurs_table.php'; ?>