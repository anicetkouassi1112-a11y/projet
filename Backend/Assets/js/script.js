function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function updateInscritUrl() {
    const meta = document.querySelector('meta[name="update-inscrit-url"]');
    if (meta && meta.getAttribute('content')) {
        return meta.getAttribute('content');
    }

    const script = document.querySelector('script[src*="/Assets/js/script.js"], script[src*="Assets/js/script.js"]');
    if (script && script.src) {
        return new URL('../../partial/update_inscrit.php', script.src).toString();
    }

    return '/partial/update_inscrit.php';
}

function updateAnimateurSectionUrl() {
    const meta = document.querySelector('meta[name="update-animateur-section-url"]');
    if (meta && meta.getAttribute('content')) {
        return meta.getAttribute('content');
    }

    const script = document.querySelector('script[src*="/Assets/js/script.js"], script[src*="Assets/js/script.js"]');
    if (script && script.src) {
        return new URL('../../partial/update_animateur_section.php', script.src).toString();
    }

    return '/partial/update_animateur_section.php';
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.navbar-toggler[data-bs-target]').forEach((button) => {
        const targetId = button.getAttribute('data-bs-target');
        const panel = targetId ? document.querySelector(targetId) : null;
        if (!panel) return;

        button.addEventListener('click', () => {
            const expanded = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            panel.classList.toggle('show', !expanded);
        });
    });

    const yearButtons = Array.from(document.querySelectorAll('[data-year-button]'));
    if (yearButtons.length && typeof window.anime === 'function') {
        document.documentElement.classList.add('anime-ready');
        window.anime({
            targets: yearButtons,
            opacity: [0, 1],
            translateY: [-8, 0],
            scale: [0.94, 1],
            delay: (_, index) => index * 45,
            duration: 420,
            easing: 'easeOutBack',
        });

        yearButtons.forEach((button) => {
            button.addEventListener('mouseenter', () => {
                window.anime({
                    targets: button,
                    translateY: [0, -2],
                    scale: [1, 1.04],
                    duration: 180,
                    easing: 'easeOutCubic',
                });
            });

            button.addEventListener('mouseleave', () => {
                window.anime({
                    targets: button,
                    translateY: [-2, 0],
                    scale: [1.04, 1],
                    duration: 220,
                    easing: 'easeOutCubic',
                });
            });

            button.addEventListener('click', (event) => {
                if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || button.target === '_blank') {
                    return;
                }

                event.preventDefault();
                window.anime({
                    targets: button,
                    scale: [1, 0.94],
                    duration: 120,
                    easing: 'easeOutCubic',
                    complete: () => {
                        window.location.href = button.href;
                    },
                });
            });
        });
    } else if (yearButtons.length) {
        document.documentElement.classList.add('anime-fallback');
    }

    document.querySelectorAll('.navbar-search-form[data-autosubmit-delay]').forEach((form) => {
        const input = form.querySelector('input[name="search"]');
        if (!input) return;

        let timer = null;
        let lastSubmittedValue = input.value.trim();
        const delay = Number.parseInt(form.dataset.autosubmitDelay || '350', 10);

        input.addEventListener('input', () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => {
                const nextValue = input.value.trim();
                if (nextValue === lastSubmittedValue) {
                    return;
                }

                lastSubmittedValue = nextValue;
                form.requestSubmit();
            }, Number.isNaN(delay) ? 350 : delay);
        });
    });

    document.querySelectorAll('.alert.alert-dismissible.fade.show').forEach((alert) => {
        window.setTimeout(() => {
            if (!alert.classList.contains('show')) {
                return;
            }
            alert.classList.remove('show');
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 6000);
    });
});

function displayAjaxFlashMessage(type, message) {
    const container = document.getElementById('ajax-flash-message-container');
    if (!container) return;

    container.innerHTML = '';

    const messageDiv = document.createElement('div');
    const bootstrapType = type === 'error' ? 'danger' : type;
    messageDiv.className = `alert alert-${bootstrapType}`;
    messageDiv.textContent = message;
    container.appendChild(messageDiv);

    setTimeout(() => {
        messageDiv.remove();
    }, 5000);
}

function setRowEditState(row, editing) {
    const editButton = row.querySelector('.edit-button');
    const okButton = row.querySelector('.ok-button');
    const cancelButton = row.querySelector('.cancel-button');
    const deleteForm = row.querySelector('.delete-form');
    const editableFields = row.querySelectorAll('.editable-field');

    row.classList.toggle('editing', editing);
    if (editButton) editButton.classList.toggle('hidden', editing);
    if (okButton) okButton.classList.toggle('hidden', !editing);
    if (cancelButton) cancelButton.classList.toggle('hidden', !editing);
    if (deleteForm) deleteForm.classList.toggle('hidden', editing);

    editableFields.forEach((fieldDiv) => {
        fieldDiv.classList.toggle('editing', editing);
    });
}

function cancelEdit(buttonElement, rowId) {
    const row = document.getElementById(`inscrit-row-${rowId}`);
    if (!row) return;

    row.querySelectorAll('.editable-field').forEach((fieldDiv) => {
        const textDisplay = fieldDiv.querySelector('.text-display');
        const inputEdit = fieldDiv.querySelector('.input-edit');
        if (textDisplay && inputEdit) {
            inputEdit.value = textDisplay.textContent.trim();
        }
    });

    setRowEditState(row, false);
}

async function toggleEditMode(buttonElement, rowId) {
    const row = document.getElementById(`inscrit-row-${rowId}`);
    if (!row) return;

    const isEditing = row.classList.contains('editing');
    const editableFields = row.querySelectorAll('.editable-field');
    if (!editableFields.length || !row.querySelector('.input-edit')) {
        displayAjaxFlashMessage('error', 'Modification non autorisee sur cette page.');
        return;
    }

    if (!isEditing) {
        editableFields.forEach((fieldDiv) => {
            const textDisplay = fieldDiv.querySelector('.text-display');
            const inputEdit = fieldDiv.querySelector('.input-edit');
            if (textDisplay && inputEdit) {
                inputEdit.value = textDisplay.textContent.trim();
            }
        });
        setRowEditState(row, true);
        return;
    }

    const updatedData = { id_inscrit: rowId };
    editableFields.forEach((fieldDiv) => {
        const inputEdit = fieldDiv.querySelector('.input-edit');
        const fieldName = fieldDiv.dataset.field;
        if (inputEdit && fieldName) {
            updatedData[fieldName] = inputEdit.value;
        }
    });

    try {
        const response = await fetch(updateInscritUrl(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken(),
            },
            body: JSON.stringify(updatedData),
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            displayAjaxFlashMessage('error', result.message || 'Erreur lors de la mise a jour.');
            return;
        }

        editableFields.forEach((fieldDiv) => {
            const textDisplay = fieldDiv.querySelector('.text-display');
            const inputEdit = fieldDiv.querySelector('.input-edit');
            if (textDisplay && inputEdit) {
                textDisplay.textContent = fieldDiv.dataset.field === 'genre' && result.genre ? result.genre : inputEdit.value;
            }
        });

        const sectionDisplay = row.querySelector('.section-display');
        if (sectionDisplay && result.section) {
            sectionDisplay.textContent = result.section;
        }

        const fullName = `${updatedData.nom || ''} ${updatedData.prenom || ''}`.trim();
        const photoImage = row.querySelector('.inscrit-photo');
        const photoPlaceholder = row.querySelector('.inscrit-photo-placeholder');
        if (photoImage) {
            photoImage.alt = fullName ? `Photo de ${fullName}` : 'Photo de l inscrit';
        }
        if (photoPlaceholder) {
            photoPlaceholder.textContent = (fullName.charAt(0) || '?').toLocaleUpperCase();
        }

        setRowEditState(row, false);
        displayAjaxFlashMessage('success', result.message || 'Mise a jour reussie.');
    } catch (error) {
        displayAjaxFlashMessage('error', 'Erreur de connexion au serveur.');
        console.error(error);
    }
}

function confirmDelete(id) {
    return confirm(`Supprimer l'inscrit #${id} ? Cette action est irreversible.`);
}

// ------------------------------------------------------------------
// Attribution de section d'un animateur (partial/animateur_row.php)
// ------------------------------------------------------------------
// Ce champ est volontairement independant du mode edition general de la
// ligne (toggleEditMode/cancelEdit) : une fois une section attribuee, le
// select reste masque tant que l'utilisateur n'a pas clique sur le bouton
// "Modifier". La sauvegarde se fait immediatement au changement de valeur.

function toggleAnimateurSectionEdit(buttonElement) {
    const idAnimateur = buttonElement.dataset.idAnimateur;
    if (!idAnimateur) return;

    const wrapper = document.querySelector(`.section-field[data-id-animateur="${idAnimateur}"]`);
    if (!wrapper) return;

    const view = wrapper.querySelector('.section-view');
    const select = wrapper.querySelector('.section-select');
    if (!select) return;

    view.classList.add('hidden');
    select.classList.remove('hidden');
    select.focus();
}

function resetAnimateurSectionView(wrapper) {
    const view = wrapper.querySelector('.section-view');
    const select = wrapper.querySelector('.section-select');
    if (view) view.classList.remove('hidden');
    if (select && select.value !== '') select.classList.add('hidden');
}

async function submitAnimateurSectionChange(selectElement) {
    const wrapper = selectElement.closest('.section-field');
    if (!wrapper) return;

    const idAnimateur = wrapper.dataset.idAnimateur;
    const previousValue = selectElement.dataset.previousValue ?? '';
    const idSection = selectElement.value;

    selectElement.disabled = true;

    try {
        const response = await fetch(updateAnimateurSectionUrl(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken(),
            },
            body: JSON.stringify({
                id_animateur: idAnimateur,
                id_section: idSection,
            }),
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            displayAjaxFlashMessage('error', result.message || 'Erreur lors de la mise a jour de la section.');
            selectElement.value = previousValue;
            return;
        }

        const valueDisplay = wrapper.querySelector('.section-view-value');
        if (valueDisplay) {
            valueDisplay.textContent = result.section || 'Non assigne';
        }

        selectElement.dataset.previousValue = idSection;
        resetAnimateurSectionView(wrapper);
        displayAjaxFlashMessage('success', result.message || 'Section mise a jour.');
    } catch (error) {
        selectElement.value = previousValue;
        displayAjaxFlashMessage('error', 'Erreur de connexion au serveur.');
        console.error(error);
    } finally {
        selectElement.disabled = false;
    }
}