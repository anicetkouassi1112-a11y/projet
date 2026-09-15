<?php
$teeShirtCandidates = is_array($teeShirtCandidates ?? null) ? $teeShirtCandidates : [];
$baseRedirectParams = is_array($baseRedirectParams ?? null) ? $baseRedirectParams : [];
$teeShirtPrice = (int) ($teeShirtPrice ?? appContainer()
    ->get(\Patro\Application\Configuration\ConfigurationService::class)
    ->teeShirtPrice());
$showSectionColumn = $showSectionColumn ?? sectionBreakdownEnabled($typeSessionActive ?? currentSessionType());
$modalAction = 'Tee_shirts.php';
if ($baseRedirectParams) {
    $modalAction .= '?' . http_build_query($baseRedirectParams);
}
$modalCandidates = array_map(static function (array $candidate) use ($showSectionColumn): array {
    $candidateName = trim((string) ($candidate['nom'] ?? '') . ' ' . (string) ($candidate['prenom'] ?? ''));
    $candidateMeta = trim((string) ($candidate['genre'] ?? '') . ($showSectionColumn ? ' - ' . canonicalSectionName($candidate['section'] ?? null) : ''), ' -');

    return [
        'id' => (int) ($candidate['id_inscrit'] ?? 0),
        'label' => $candidateName . ($candidateMeta !== '' ? ' - ' . $candidateMeta : ''),
        'search' => trim($candidateName . ' ' . $candidateMeta),
    ];
}, $teeShirtCandidates);
?>
<div class="modal fade jeu-modal" data-bs-backdrop="static" id="AddPersonModal" tabindex="-1" aria-labelledby="AddPersonModalLabel">
    <div class="modal-dialog jeu-modal-dialog">
        <div class="modal-content jeu-modal-content">
            <div class="modal-header jeu-modal-header">
                <h5 class="jeu-modal-title" id="AddPersonModalLabel"><i class="bi bi-tags me-2"></i>Ajouter un tee-shirt</h5>
                <button type="button" class="jeu-modal-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>

            <form method="post" action="<?= e($modalAction) ?>" id="addPersonForm" class="jeu-modal-form needs-validation" novalidate>
                <div class="modal-body jeu-modal-body">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="add_tshirt">
                    <input type="hidden" name="genre" value="<?= e((string) ($baseRedirectParams['genre'] ?? '')) ?>">

                    <?php if (!$teeShirtCandidates): ?>
                        <div class="alert alert-info mb-0">
                            Aucun inscrit sans tee-shirt n'est disponible pour le filtre courant.
                        </div>
                    <?php else: ?>
                        <section class="jeu-section jeu-section--info">
                            <h6 class="jeu-section-title"><i class="bi bi-person-lines-fill me-2"></i>Inscrit concerné</h6>
                            <div class="jeu-section-grid">
                                <div class="jeu-field jeu-field--3quarter">
                                    <label for="modal_person_search" class="jeu-field-label">Nom et prenom <span class="jeu-required">*</span></label>
                                    <input type="search" class="form-control jeu-input" id="modal_person_search" placeholder="Rechercher un nom ou prenom" autocomplete="off" required>
                                    <input type="hidden" name="id_inscrit" id="modal_id_inscrit" required>
                                    <div class="person-search-results" id="modal_person_results" role="listbox" aria-label="Resultats de recherche"></div>
                                    <p class="text-muted selected-person-label" id="modal_selected_person">Aucune personne selectionnee.</p>
                                </div>
                                <div class="jeu-field jeu-field--quarter">
                                    <label for="modal_taille_tee_shirt" class="jeu-field-label">Taille <span class="jeu-required">*</span></label>
                                    <select class="form-control jeu-input" name="taille_tee_shirt" id="modal_taille_tee_shirt" required>
                                        <option value="">Choisir</option>
                                        <?php foreach (validTeeShirtSizes() as $size): ?>
                                            <option value="<?= e($size) ?>"><?= e($size) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </section>
                        <div class="alert alert-warning">
                            Montant ajoute a l'inscription : <strong><?= e(formatFcfa($teeShirtPrice)) ?></strong>.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer jeu-modal-footer">
                    <button type="button" class="btn btn-outline-secondary jeu-btn-cancel" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary jeu-btn-submit" id="btnSubmitAddPerson" disabled>
                        <i class="bi bi-check-lg me-1" aria-hidden="true"></i>
                        Enregistrer le tee-shirt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const candidates = <?= json_encode($modalCandidates, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const modal = document.getElementById('AddPersonModal');
    const searchInput = document.getElementById('modal_person_search');
    const hiddenInput = document.getElementById('modal_id_inscrit');
    const resultsBox = document.getElementById('modal_person_results');
    const selectedLabel = document.getElementById('modal_selected_person');
    const submitButton = document.getElementById('btnSubmitAddPerson');

    if (!modal || !searchInput || !hiddenInput || !resultsBox || !selectedLabel || !submitButton) {
        return;
    }

    const normalize = (value) => value
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    const selectCandidate = (candidate) => {
        hiddenInput.value = candidate.id;
        searchInput.value = candidate.label;
        selectedLabel.textContent = candidate.label;
        submitButton.disabled = false;
        resultsBox.innerHTML = '';
    };

    const renderResults = () => {
        hiddenInput.value = '';
        selectedLabel.textContent = 'Aucune personne selectionnee.';
        submitButton.disabled = true;

        const query = normalize(searchInput.value);
        const matches = candidates
            .filter((candidate) => query === '' || normalize(candidate.search).includes(query))
            .slice(0, 8);

        resultsBox.innerHTML = '';
        if (!matches.length) {
            resultsBox.innerHTML = '<div class="person-search-empty">Aucun resultat.</div>';
            return;
        }

        matches.forEach((candidate) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'person-search-item';
            button.textContent = candidate.label;
            button.setAttribute('role', 'option');
            button.addEventListener('click', () => selectCandidate(candidate));
            resultsBox.appendChild(button);
        });
    };

    searchInput.addEventListener('input', renderResults);
    searchInput.addEventListener('focus', renderResults);
    modal.addEventListener('shown.bs.modal', () => {
        searchInput.focus();
        renderResults();
    });

    // Prévention de double soumission (comme modal_creer_jeu.php)
    document.getElementById('addPersonForm')?.addEventListener('submit', function (e) {
        if (!this.checkValidity() || submitButton.disabled) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.add('was-validated');
            return;
        }

        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enregistrement...';
        this.classList.add('was-validated');
    });
});
</script>