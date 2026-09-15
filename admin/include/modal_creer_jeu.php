<?php
/**
 * Partial d'affichage : modal de création/modification de jeu.
 *
 * IMPORTANT : ce fichier ne doit contenir AUCUNE logique de traitement du
 * formulaire (création/mise à jour en base). Cette logique est déjà gérée
 * intégralement par liste_type_jeux.php (action=create / action=update / action=delete),
 * qui fait un redirectTo() (donc un exit()) avant d'atteindre ce include.
 *
 * Ce fichier suppose qu'il est inclus depuis une page qui a déjà fait
 * require_once .../Backend/utilitaire.php et requireRole(...).
 */
?>
<div class="modal fade jeu-modal" id="jeuModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="jeuModalLabel">
    <div class="modal-dialog jeu-modal-dialog">
        <div class="modal-content jeu-modal-content">
            <div class="modal-header jeu-modal-header">
                <h5 class="jeu-modal-title" id="jeuModalLabel"><i class="bi bi-controller me-2"></i>Ajouter un jeu</h5>
                <button type="button" class="jeu-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="" method="post" id="jeuForm" class="jeu-modal-form needs-validation" novalidate>
                <div class="modal-body jeu-modal-body">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="jeuId" value="">

                    <section class="jeu-section jeu-section--info">
                        <h6 class="jeu-section-title"><i class="bi bi-info-circle me-2"></i>Informations générales</h6>
                        <div class="jeu-section-grid">
                            <div class="jeu-field jeu-field--half">
                                <label for="nom" class="jeu-field-label">Nom du jeu <span class="jeu-required">*</span></label>
                                <input type="text" class="form-control jeu-input" name="nom" id="nom" maxlength="150" required>
                            </div>
                            <div class="jeu-field jeu-field--half">
                                <label for="type_jeu" class="jeu-field-label">Type de jeu</label>
                                <input type="text" class="form-control jeu-input" name="type_jeu" id="type_jeu" placeholder="ex: Grand jeu, Intérieur..." maxlength="100">
                            </div>
                            <div class="jeu-field jeu-field--quarter">
                                <label for="age_conseille" class="jeu-field-label">Âge conseillé</label>
                                <input type="text" class="form-control jeu-input" name="age_conseille" id="age_conseille" placeholder="ex: 8-12 ans" maxlength="50">
                            </div>
                            <div class="jeu-field jeu-field--quarter">
                                <label for="duree" class="jeu-field-label">Durée</label>
                                <input type="text" class="form-control jeu-input" name="duree" id="duree" placeholder="ex: 45 min" maxlength="50">
                            </div>
                            <div class="jeu-field jeu-field--quarter">
                                <label for="nombre_joueurs" class="jeu-field-label">Nb joueurs</label>
                                <input type="text" class="form-control jeu-input" name="nombre_joueurs" id="nombre_joueurs" placeholder="ex: 10 à 30" maxlength="100">
                            </div>
                            <div class="jeu-field jeu-field--quarter">
                                <label for="lieu" class="jeu-field-label">Lieu idéal</label>
                                <input type="text" class="form-control jeu-input" name="lieu" id="lieu" placeholder="ex: Forêt..." maxlength="100">
                            </div>
                        </div>
                    </section>

                    <section class="jeu-section jeu-section--concept">
                        <h6 class="jeu-section-title"><i class="bi bi-box-seam me-2"></i>Concept &amp; Préparation</h6>
                        <div class="jeu-section-grid">
                            <div class="jeu-field jeu-field--full">
                                <label for="objectif" class="jeu-field-label">Objectif / Imaginaire <span class="jeu-required">*</span></label>
                                <textarea class="form-control jeu-input jeu-textarea" name="objectif" id="objectif" rows="2" required maxlength="5000"></textarea>
                                <div class="jeu-char-counter"><span id="objCounter">0 / 5000</span></div>
                            </div>
                            <div class="jeu-field jeu-field--half">
                                <label for="materiel" class="jeu-field-label">Matériel nécessaire</label>
                                <textarea class="form-control jeu-input jeu-textarea" name="materiel" id="materiel" rows="2" maxlength="5000"></textarea>
                            </div>
                            <div class="jeu-field jeu-field--half">
                                <label for="mise_en_place" class="jeu-field-label">Mise en place</label>
                                <textarea class="form-control jeu-input jeu-textarea" name="mise_en_place" id="mise_en_place" rows="2" maxlength="5000"></textarea>
                            </div>
                        </div>
                    </section>

                    <section class="jeu-section jeu-section--rules">
                        <h6 class="jeu-section-title"><i class="bi bi-play-circle me-2"></i>Déroulement &amp; Règles</h6>
                        <div class="jeu-section-grid">
                            <div class="jeu-field jeu-field--half">
                                <label for="regles" class="jeu-field-label">Règles principales <span class="jeu-required">*</span></label>
                                <textarea class="form-control jeu-input jeu-textarea" name="regles" id="regles" rows="3" required maxlength="5000"></textarea>
                                <div class="jeu-char-counter"><span id="reglesCounter">0 / 5000</span></div>
                            </div>
                            <div class="jeu-field jeu-field--half">
                                <label for="deroulement" class="jeu-field-label">Déroulement (Étapes)</label>
                                <textarea class="form-control jeu-input jeu-textarea" name="deroulement" id="deroulement" rows="3" maxlength="5000"></textarea>
                            </div>
                            <div class="jeu-field jeu-field--half">
                                <label for="fin_jeu" class="jeu-field-label">Fin du jeu (Victoire)</label>
                                <textarea class="form-control jeu-input jeu-textarea" name="fin_jeu" id="fin_jeu" rows="2" maxlength="5000"></textarea>
                            </div>
                            <div class="jeu-field jeu-field--half">
                                <label for="but_pedagogique" class="jeu-field-label">But pédagogique</label>
                                <textarea class="form-control jeu-input jeu-textarea" name="but_pedagogique" id="but_pedagogique" rows="2" maxlength="5000"></textarea>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="modal-footer jeu-modal-footer">
                    <button type="button" class="btn btn-outline-secondary jeu-btn-cancel" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary jeu-btn-submit" id="btnSubmitModal">
                        <i class="bi bi-save me-1"></i> Enregistrer le jeu
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Prévention de double soumission
document.getElementById('jeuForm')?.addEventListener('submit', function (e) {
    const btn = document.getElementById('btnSubmitModal');

    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    } else {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enregistrement...';
    }

    this.classList.add('was-validated');
});

// Compteurs de caractères interactifs (mis à jour en direct)
function updateCounter(element, counterId, max) {
    if (!element) return;
    const counter = document.getElementById(counterId);
    if (!counter) return;

    const length = element.value.length;
    counter.textContent = `${length} / ${max}`;

    counter.classList.remove('is-warning', 'is-danger');

    if (length >= max) {
        counter.classList.add('is-danger');
    } else if (length >= max * 0.85) {
        counter.classList.add('is-warning');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const objectif = document.getElementById('objectif');
    const regles = document.getElementById('regles');

    if (objectif) {
        updateCounter(objectif, 'objCounter', 5000);
        objectif.addEventListener('input', () => {
            updateCounter(objectif, 'objCounter', 5000);
        });
    }

    if (regles) {
        updateCounter(regles, 'reglesCounter', 5000);
        regles.addEventListener('input', () => {
            updateCounter(regles, 'reglesCounter', 5000);
        });
    }
});
</script>