<?php
// Ce fichier est inclus dans le contenu principal (ex: via home.php).
// Les variables $context, $sectionConfig, $anneeActive, $typeSessionActive existent déjà[cite: 34].

$searchQuery = requestTextParam('search', 80);
$sectionParam = requestTextParam('section', 20);
// Récupération des inscrits depuis le contexte spécifique aux garçons[cite: 32, 37]
$inscrits = $context['animateurs'] ?? $context['inscription'] ?? [];
$tableTitle = $context['title'] ?? 'Animateurs Garçons';
$messageInscritsTable = $context['empty_message'] ?? 'Aucun animateur trouvé.';
$showSectionColumn = $context['show_section_breakdown'] ?? false;

// Filtre de recherche par nom/prénom[cite: 32]
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
        <input type="hidden" name="page" value="Animateur">
        <?php 
        $sectionParam = requestTextParam('section', 20);
        if ($sectionParam !== '' && ctype_digit($sectionParam)): ?>
            <input type="hidden" name="section" value="<?= e($sectionParam) ?>">
        <?php endif; ?>
        
        <div class="input-group">
            <input type="text" class="form-control" name="search" placeholder="Rechercher un animateur..." value="<?= e($searchQuery) ?>" maxlength="80">
            <button class="btn btn-primary" type="submit" aria-label="Rechercher">
                <i class="bi bi-search" aria-hidden="true"></i>
            </button>
        </div>
        
        <?php if ($searchQuery !== ''): ?>
            <a href="?page=Animateur<?= $sectionParam !== '' ? '&section='.e($sectionParam) : '' ?>" class="btn btn-outline-secondary" title="Réinitialiser la recherche">
                <i class="bi bi-x-circle" aria-hidden="true"></i>
            </a>
        <?php endif; ?>
    </form>
</div>

<?php displayFlashMessage(); ?>
<div id="ajax-flash-message-container"></div>

<!-- Inclusion du tableau d'affichage[cite: 32, 36] -->
<?php require __DIR__ . '/partial/animateurs_table.php'; ?>