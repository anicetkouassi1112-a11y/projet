<?php
/**
 * Partial : Ligne du tableau des animateurs / animatrices.
 *
 * Variables attendues :
 * - $inscrit / $animateur : array (Contient id_animateur, nom_a/nom, prenom_a/prenom, genre_a/genre, id_section, nom_section, statut, etc.)
 * - $allSections : array (Liste de toutes les sections récupérées avec getAllSections())
 * - $currentPage : string (Optionnel, ex: 'Animateur' ou 'Animatrice')
 * - $showSectionColumn : bool (Optionnel, détermine l'affichage de la colonne Section)
 * - $canManageAnimateurs : bool (Optionnel, détermine l'affichage de la colonne Action)
 * - $filtered_animateurs_count : int (Optionnel, numéro d'ordre dans la liste)
 *
 * IMPORTANT : la structure des <td> ci-dessous doit rester alignée avec les <th>
 * de animateurs_table.php (même ordre, mêmes conditions d'affichage), sous peine
 * de décalage de colonnes.
 */

// 1. Validation des données reçues
$animateur = $inscrit ?? $animateur ?? null;
if (!is_array($animateur)) {
    return;
}

// 2. Extractions et sécurisation des données
$idAnimateur = (int) ($animateur['id_animateur'] ?? $animateur['id_utilisateur'] ?? 0);
$nom = $animateur['nom_a'] ?? $animateur['nom'] ?? '';
$prenom = $animateur['prenom_a'] ?? $animateur['prenom'] ?? '';
$tel = $animateur['tel'] ?? 'N/A';
$genreRaw = $animateur['genre_a'] ?? $animateur['genre'] ?? '';
$statut = $animateur['statut'] ?? 'actif';

// Détermination du genre de la page / animateur
$currentPageName = $currentPage ?? ($_GET['page'] ?? '');
$genreLower = mb_strtolower((string) $genreRaw);
$isFemale = (stripos($currentPageName, 'Animatrice') !== false)
    || (stripos($currentPageName, 'fille') !== false)
    || $genreLower === 'f'
    || $genreLower === 'fille'
    || $genreLower === 'feminin'
    || $genreLower === 'féminin';

$targetGenre = $isFemale ? 'Fille' : 'Garçon';

// 3. Filtrage des sections selon le genre cible (utilisé uniquement si édition possible)
$filteredSections = array_filter($allSections ?? [], function ($sec) use ($targetGenre) {
    return isset($sec['genre']) && $sec['genre'] === $targetGenre;
});

// 4. Vérification de la section attribuée
$hasSection = !empty($animateur['id_section']);
$assignedSectionName = $animateur['nom_section'] ?? $animateur['section'] ?? 'Non assigné';

// 5. Gestion des droits et colonnes
// NB : ces valeurs doivent être calculées de façon identique dans animateurs_table.php,
// pour garantir que les colonnes de l'en-tête et des lignes correspondent toujours.
$canManageAnimateurs = $canManageAnimateurs ?? adminHasRole(['directeur']);
$showSectionColumn = $showSectionColumn ?? true;
?>

<tr id="animateur-row-<?= e($idAnimateur) ?>">
    <!-- 1. N° ordre -->
    <td class="text-center"><?= e($filtered_animateurs_count ?? $idAnimateur) ?></td>

    <!-- 2. Nom -->
    <td class="text-center"><?= e($nom) ?></td>

    <!-- 3. Prénom -->
    <td class="text-center"><?= e($prenom) ?></td>

    <!-- 4. Téléphone -->
    <td class="text-center"><?= e($tel) ?></td>

    <!-- 5. Genre -->
    <td class="text-center">
        <span class="badge <?= $isFemale ? 'bg-danger' : 'bg-primary' ?>">
            <?= $isFemale ? 'Animatrice' : 'Animateur' ?>
        </span>
    </td>

    <!-- 6. Section — présente uniquement si $showSectionColumn.
         .section-field regroupe l'affichage (.section-view) et le champ
         d'édition (.section-select) : c'est ce wrapper que script.js cible
         via data-id-animateur, indépendamment de l'emplacement du bouton
         "Modifier" (colonne Action). -->
    <?php if ($showSectionColumn): ?>
    <td class="section-cell text-center">
        <div class="section-field" data-id-animateur="<?= e($idAnimateur) ?>">
            <div class="section-view">
                <span class="section-view-value badge bg-info text-dark fs-6">
                    <?= e($hasSection ? $assignedSectionName : 'Non assigné') ?>
                </span>
            </div>

            <?php if ($canManageAnimateurs): ?>
            <select class="form-select form-select-sm section-select hidden"
                    data-previous-value="<?= e($animateur['id_section'] ?? '') ?>"
                    onchange="submitAnimateurSectionChange(this)">
                <option value="">-- Sélectionner une section (<?= e($targetGenre) ?>) --</option>
                <?php foreach ($filteredSections as $sec): ?>
                    <option value="<?= e($sec['id_section']) ?>"
                        <?= (isset($animateur['id_section']) && (int) $animateur['id_section'] === (int) $sec['id_section']) ? 'selected' : '' ?>>
                        <?= e($sec['nom_section']) ?> (<?= e($sec['age_min']) ?>-<?= e($sec['age_max']) ?> ans)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </div>
    </td>
    <?php endif; ?>

    <!-- 7. Statut -->
    <td class="text-center">
        <span class="badge <?= $statut === 'actif' ? 'bg-success' : ($statut === 'bloque' ? 'bg-danger' : 'bg-secondary') ?>">
            <?= e(ucfirst($statut)) ?>
        </span>
    </td>

    <!-- 8. Action — présente uniquement si $canManageAnimateurs.
         Bouton seul : cible la cellule Section de la même ligne via
         data-id-animateur (cf. toggleAnimateurSectionEdit dans script.js). -->
    <?php if ($canManageAnimateurs): ?>
    <td class="text-center">
        <button type="button"
                class="btn btn-sm btn-outline-secondary edit-section-btn"
                title="Modifier la section"
                data-id-animateur="<?= e($idAnimateur) ?>"
                onclick="toggleAnimateurSectionEdit(this)">
            <i class="bi bi-pencil"></i>
        </button>
    </td>
    <?php endif; ?>
</tr>