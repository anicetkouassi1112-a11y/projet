<?php
// Fichier : partial/inscrit_row.php (ou ligne du tableau)

if (!isset($inscrit) || !is_array($inscrit)) {
    return;
}

// Sécurisation des données
$idInscrit = (int) ($inscrit['id_inscription'] ?? 0);
$nom = trim($inscrit['nom'] ?? '');
$prenom = trim($inscrit['prenom'] ?? '');
$dateNaissance = $inscrit['date_naissance'] ?? '';
$genreRaw = $inscrit['genre'] ?? '';
$genreNormalized = normalizeGenre($genreRaw);
$tel = trim($inscrit['tel'] ?? '');
$adresse = trim($inscrit['adresse'] ?? '');
$montant = (int) ($inscrit['montant_inscription'] ?? 0);
$prixTee = (int) ($inscrit['prix_tee_shirt'] ?? 0);
$tailleTee = trim($inscrit['taille_tee_shirt'] ?? '');
$section = $inscrit['section'] ?? $inscrit['nom_section'] ?? null;
$idSessionInscrit = (int) ($inscrit['id_session'] ?? 0);

// Calcul de l'âge
$age = '';
if (!empty($dateNaissance)) {
    $anneeActive = (int) ($anneeActive ?? date('Y'));
    $computedAge = calculateAge($dateNaissance, $anneeActive);
    $age = $computedAge === null ? 'N/A' : (string) $computedAge;
}

// Droits et affichage de la colonne section
$canManageInscrits = $canManageInscrits ?? adminHasRole(['directeur']);
$typeSessionActive = $typeSessionActive ?? currentSessionType();
$showSectionColumn = $showSectionColumn ?? sectionBreakdownEnabled($typeSessionActive);

// Numéro de ligne
static $rowNumber = 0;
$rowNumber++;
$currentRowNumber = $rowNumber;

// Vérification de la session active
$activeSessionId = getActiveAdminSessionId(); // fonction à ajouter dans functions.php
$isActiveSession = ($idSessionInscrit === $activeSessionId);

// Construction du chemin vers le PDF – utilisation de app_url() pour une URL fiable
$pdfUrl = app_url('public/generer_pdf.php') . '?inscrit_id=' . $idInscrit;

function safeDisplay($value) {
    return e((string) $value);
}
?>
<tr id="inscrit-row-<?= safeDisplay($idInscrit) ?>">
    <td class="text-center"><?= safeDisplay($currentRowNumber) ?></td>
    <td class="text-center">
        <div class="editable-field" data-field="nom">
            <span class="text-display"><?= safeDisplay($nom) ?></span>
            <?php if ($canManageInscrits && $isActiveSession): ?>
            <input type="text" class="input-edit form-control input-sm" value="<?= safeDisplay($nom) ?>">
            <?php endif; ?>
        </div>
    </td>
    <td class="text-center">
        <div class="editable-field" data-field="prenom">
            <span class="text-display"><?= safeDisplay($prenom) ?></span>
            <?php if ($canManageInscrits && $isActiveSession): ?>
            <input type="text" class="input-edit form-control input-sm" value="<?= safeDisplay($prenom) ?>">
            <?php endif; ?>
        </div>
    </td>
    <td class="text-center">
        <div class="editable-field" data-field="date_naissance">
            <span class="text-display"><?= safeDisplay($dateNaissance) ?></span>
            <?php if ($canManageInscrits && $isActiveSession): ?>
            <input type="date" class="input-edit form-control input-sm" value="<?= safeDisplay($dateNaissance) ?>">
            <?php endif; ?>
        </div>
    </td>
    <td class="text-center"><?= safeDisplay($age) ?></td>
    <td class="text-center">
        <div class="editable-field" data-field="genre">
            <span class="text-display"><?= safeDisplay($genreNormalized) ?></span>
            <?php if ($canManageInscrits && $isActiveSession): ?>
            <select class="input-edit form-control input-sm">
                <option value="Garçon" <?= ($genreNormalized === 'Garçon') ? 'selected' : '' ?>>Garçon</option>
                <option value="Fille" <?= ($genreNormalized === 'Fille') ? 'selected' : '' ?>>Fille</option>
            </select>
            <?php endif; ?>
        </div>
    </td>
    <?php if ($showSectionColumn): ?>
        <td class="text-center">
            <span class="section-display">
                <?= safeDisplay(canonicalSectionName($section) ?? 'Non spécifié') ?>
            </span>
        </td>
    <?php endif; ?>
    <td class="text-center"><?= safeDisplay(formatFcfa($montant)) ?></td>
    <td class="text-center">
        <?php if ($prixTee > 0): ?>
            <?= safeDisplay(formatFcfa($prixTee)) ?>
            <?php if (!empty($tailleTee)): ?>
                <small class="text-muted">(<?= safeDisplay($tailleTee) ?>)</small>
            <?php endif; ?>
        <?php else: ?>
            <span class="text-muted">Non</span>
        <?php endif; ?>
    </td>
    <td class="text-center">
        <div class="editable-field" data-field="tel">
            <span class="text-display"><?= safeDisplay($tel) ?></span>
            <?php if ($canManageInscrits && $isActiveSession): ?>
            <input type="tel" class="input-edit form-control input-sm" maxlength="10" value="<?= safeDisplay($tel) ?>">
            <?php endif; ?>
        </div>
    </td>
    <td class="text-center">
        <div class="editable-field" data-field="adresse">
            <span class="text-display"><?= safeDisplay($adresse) ?></span>
            <?php if ($canManageInscrits && $isActiveSession): ?>
            <input type="text" class="input-edit form-control input-sm" value="<?= safeDisplay($adresse) ?>">
            <?php endif; ?>
        </div>
    </td>
    <?php if ($canManageInscrits): ?>
    <td class="text-center">
        <button type="button" 
                class="edit-button btn btn-info btn-sm action-icon-button <?= !$isActiveSession ? 'disabled' : '' ?>" 
                title="Modifier" aria-label="Modifier" 
                onclick="<?= $isActiveSession ? "toggleEditMode(this, $idInscrit)" : '' ?>">
            <i class="bi bi-pencil-fill" aria-hidden="true"></i>
        </button>
        <button type="button" 
                class="ok-button hidden btn btn-success btn-sm action-icon-button <?= !$isActiveSession ? 'disabled' : '' ?>" 
                title="Confirmer" aria-label="Confirmer" 
                onclick="<?= $isActiveSession ? "toggleEditMode(this, $idInscrit)" : '' ?>">
            <i class="bi bi-check-lg" aria-hidden="true"></i>
        </button>
        <button type="button" 
                class="cancel-button hidden btn btn-warning btn-sm action-icon-button <?= !$isActiveSession ? 'disabled' : '' ?>" 
                title="Annuler" aria-label="Annuler" 
                onclick="<?= $isActiveSession ? "cancelEdit(this, $idInscrit)" : '' ?>">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
        <button type="button" class="pdf-button btn btn-secondary btn-sm action-icon-button" title="Générer le PDF" aria-label="Générer le PDF" onclick="window.open('<?= safeDisplay($pdfUrl) ?>', '_blank')">
            <i class="bi bi-file-earmark-pdf-fill" aria-hidden="true"></i>
        </button>
    </td>
    <?php endif; ?>
</tr>