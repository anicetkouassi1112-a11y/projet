        <?php
        if (!empty($GLOBALS['patroFooterRendered'])) {
            return;
        }

        $GLOBALS['patroFooterRendered'] = true;
        $assetBase = rtrim($assetBase ?? '../Backend/Assets', '/');
        $assetVersion = '20260602';
        ?>
        <footer class="footer">
            <div class="container text-center">
                <span class="text-muted">&copy; <?= date('Y') ?> Patro - Tous droits reserves.</span>
            </div>
            <script>
// Prévention de double soumission
document.getElementById('jeuForm')?.addEventListener('submit', function (e) {
    const btn = document.getElementById('jeuSubmit');
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    } else if (btn) { 
        setTimeout(() => {
            btn.disabled = true; 
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enregistrement...';
        }, 10);
    }
    this.classList.add('was-validated');
});

// Compteurs de caractères interactifs
function updateCounter(el, counterId, max) {
    const len = el.value.length;
    const counter = document.getElementById(counterId);
    if (!counter) return;
    
    counter.textContent = len + ' / ' + max;
    
    if (len >= max) {
        counter.classList.add('text-danger');
        counter.classList.remove('text-warning', 'text-muted');
    } else if (len >= max * 0.85) {
        counter.classList.add('text-warning');
        counter.classList.remove('text-danger', 'text-muted');
    } else {
        counter.classList.add('text-muted');
        counter.classList.remove('text-danger', 'text-warning');
    }
}

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialisation des compteurs de caractères
    const obj = document.getElementById('objectif');
    const regles = document.getElementById('regles');
    
    if(obj) updateCounter(obj, 'objCounter', 5000);
    if(regles) updateCounter(regles, 'reglesCounter', 5000);

    // 2. Auto-ouverture du modal si un message est présent
    <?php if ($message !== ''): ?>
        // ✅ Correct : vérifier que l'élément existe avant initialisation
        const modalEl = document.getElementById('monModal');
        if (modalEl) {
            const myModal = new bootstrap.Modal(modalEl);
            myModal.show();
        }
    <?php endif; ?>
});
</script>
        </footer>
        
        <script src="<?= e($assetBase) ?>/js/bootstrap.bundle.min.js?v=<?= e($assetVersion) ?>"></script>
        <script src="<?= e($assetBase) ?>/js/anime.min.js?v=<?= e($assetVersion) ?>"></script>
        <script src="<?= e($assetBase) ?>/js/script.js?v=<?= e($assetVersion) ?>"></script>
    </body>
</html>