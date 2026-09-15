<?php declare(strict_types=1);

require_once __DIR__ . '/../../Backend/utilitaire.php';

requireRole(['directeur', 'suppleant_1', 'suppleant_2'], '../Auth/login.php');

$anneeActive = activeYearFromRequest();
$typeSessionActive = activeSessionTypeFromRequest();
$searchQuery = requestTextParam('search', 80);
$canValidate = adminHasRole(['directeur', 'suppleant_1', 'suppleant_2']);

$baseRedirectParams = [
    'annee' => $anneeActive,
    'type_session' => $typeSessionActive,
    'search' => $searchQuery,
];

// --- Traitement de la validation (Support AJAX + Fallback standard) ---
$action = (string) ($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '') {
    // Détection automatique si la requête vient de JavaScript (AJAX)
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_POST['ajax']);

    if (!$canValidate) {
        if ($isAjax) { 
            while (ob_get_level()) { ob_end_clean(); } // <--- NETTOIE LE TAMPON HTML DE HOME.PHP
            header('Content-Type: application/json'); 
            echo json_encode(['success' => false, 'message' => 'Accès interdit.']); 
            exit; 
        }
        setFlashMessage('danger', 'Acces interdit.');
        redirectTo(lien('attente') . '?' . http_build_query($baseRedirectParams));
    }

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        if ($isAjax) { 
            while (ob_get_level()) { ob_end_clean(); } // <--- NETTOIE LE TAMPON HTML DE HOME.PHP
            header('Content-Type: application/json'); 
            echo json_encode(['success' => false, 'message' => 'Jeton CSRF invalide.']); 
            exit; 
        }
        setFlashMessage('danger', 'Jeton CSRF invalide.');
        redirectTo(lien('attente') . '?' . http_build_query($baseRedirectParams));
    }

    if ($action !== 'validate') {
        if ($isAjax) { 
            while (ob_get_level()) { ob_end_clean(); } // <--- NETTOIE LE TAMPON HTML DE HOME.PHP
            header('Content-Type: application/json'); 
            echo json_encode(['success' => false, 'message' => 'Action invalide.']); 
            exit; 
        }
        setFlashMessage('warning', 'Action invalide.');
        redirectTo(lien('attente') . '?' . http_build_query($baseRedirectParams));
    }

    $idInscription = filter_var($_POST['id_inscription'] ?? null, FILTER_VALIDATE_INT);
    if (!$idInscription || $idInscription <= 0) {
        if ($isAjax) { 
            while (ob_get_level()) { ob_end_clean(); } // <--- NETTOIE LE TAMPON HTML DE HOME.PHP
            header('Content-Type: application/json'); 
            echo json_encode(['success' => false, 'message' => 'Inscription invalide.']); 
            exit; 
        }
        setFlashMessage('warning', 'Veuillez selectionner une inscription valide.');
        redirectTo(lien('attente') . '?' . http_build_query($baseRedirectParams));
    }

    try {
        $success = appContainer()
            ->get(\Patro\Domain\Inscription\Repository\InscriptionRepository::class)
            ->validatePending($idInscription);

        if ($isAjax) {
            while (ob_get_level()) { ob_end_clean(); } // <--- TRÈS IMPORTANT : Supprime le HTML pour ne laisser QUE le JSON pur
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Inscription validée avec succès.' : 'Inscription introuvable ou déjà validée.'
            ]);
            exit;
        }

        setFlashMessage(
            $success ? 'success' : 'warning',
            $success ? 'Inscription validee avec succes.' : 'Inscription introuvable ou deja validee.'
        );
    } catch (PDOException $e) {
        error_log('Validate inscription error: ' . $e->getMessage());
        if ($isAjax) {
            while (ob_get_level()) { ob_end_clean(); } // <--- NETTOIE LE TAMPON HTML DE HOME.PHP
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => "Erreur pendant la validation de l'inscription."]);
            exit;
        }
        setFlashMessage('danger', "Erreur pendant la validation de l'inscription.");
    }

    // Fallback standard
    redirectTo(lien('attente') . '?' . http_build_query($baseRedirectParams) . '#inscription-row-' . $idInscription);
}

// --- Chargement de la liste ---
$inscriptions = [];
try {
    $anneeId = selectedYearId($anneeActive);
    $inscriptions = appContainer()
        ->get(\Patro\Domain\Inscription\Repository\InscriptionRepository::class)
        ->findPending($anneeId, $typeSessionActive, $searchQuery);
} catch (PDOException $e) {
    error_log('Pending list error: ' . $e->getMessage());
    setFlashMessage('danger', 'Erreur pendant le chargement des inscriptions en attente.');
}

$anneesDisponibles = appContainer()->get(\Patro\Inscription\SessionService::class)->getDistinctYears();
$sessionLabel = sessionTypeLabel($typeSessionActive);
$pageTitle = 'Inscriptions en attente - ' . $anneeActive . ' - ' . $sessionLabel;
$assetBase = rtrim($assetBase ?? '../Backend/Assets', '/');
?>
<div class="container-fluid home-shell">
    <main class="home-main">
        <div class="page-header breadcrumb-controls stats-header">
            <div>
                <h1><i class="bi bi-clock me-2"></i>Inscriptions en attente de validation</h1>
                <p class="text-muted"><?= e($anneeActive) ?> - Session <?= e($sessionLabel) ?></p>
            </div>
            <div class="breadcrumb-buttons">
                <?php $yearRoute = lien('attente'); ?>
                <?php require __DIR__ . '/../partial/year_navigation.php'; ?>
                <?php foreach (validSessionTypes() as $typeSession): ?>
                    <a class="btn btn-secondary btn-sm <?= $typeSession === $typeSessionActive ? 'active' : '' ?>" href="<?= lien('attente') ?>?<?= e(http_build_query(['annee' => $anneeActive, 'type_session' => $typeSession, 'search' => $searchQuery])) ?>">
                        <?= e(sessionTypeLabel($typeSession)) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php displayFlashMessage(); ?>
        <div class="table-responsive pending-table-wrap">
            <table class="table table-sm table-striped table-hover align-middle mb-0 pending-table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Prenom</th>
                        <th>Date de naissance</th>
                        <th>Genre</th>
                        <th>Section</th>
                        <th>Montant</th>
                        <th>Etat</th>
                        <?php if ($canValidate): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inscriptions as $inscription): ?>
                        <tr id="inscription-row-<?= e((int) ($inscription['id_inscription'] ?? 0)) ?>">
                            <td><?= e((string) ($inscription['nom'] ?? '')) ?></td>
                            <td><?= e((string) ($inscription['prenom'] ?? '')) ?></td>
                            <td><?= e((string) ($inscription['date_naissance'] ?? '')) ?></td>
                            <td><?= e((string) ($inscription['genre'] ?? '')) ?></td>
                            <td><?= e(canonicalSectionName($inscription['nom_section'] ?? null)) ?></td>
                            <td><?= e(formatFcfa((int) ($inscription['montant_inscription'] ?? 0))) ?></td>
                            <td><span class="badge bg-warning"><?= e((string) ($inscription['etat'] ?? '')) ?></span></td>
                            <?php if ($canValidate): ?>
                                <td>
                                    <form method="post" action="<?= lien('attente') ?>" class="ajax-validate-form" data-row-id="inscription-row-<?= e((int) ($inscription['id_inscription'] ?? 0)) ?>" style="display:inline;">
                                        <input type="hidden" name="action" value="validate">
                                        <input type="hidden" name="id_inscription" value="<?= e((int) ($inscription['id_inscription'] ?? 0)) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="annee" value="<?= e($anneeActive) ?>">
                                        <input type="hidden" name="type_session" value="<?= e($typeSessionActive) ?>">
                                        <input type="hidden" name="search" value="<?= e($searchQuery) ?>">
                                        <button type="submit" class="btn btn-success btn-sm" title="Valider">
                                            <i class="bi bi-check"></i>
                                        </button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$inscriptions): ?>
                        <tr class="empty-row">
                            <td colspan="<?= e($canValidate ? 8 : 7) ?>" class="text-center text-muted">Aucune inscription en attente de validation.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($inscriptions): ?>
            <p class="text-muted small mt-2 (total-counter-wrap)">
                <i class="bi bi-info-circle me-1"></i><span id="total-counter"><?= e(count($inscriptions)) ?></span> inscription(s) en attente de validation.
            </p>
        <?php endif; ?>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.ajax-validate-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault(); // Bloque le rechargement natif de la page
            
            if (!confirm('Valider cette inscription ?')) {
                return;
            }

            const rowId = this.dataset.rowId;
            const row = document.getElementById(rowId);
            const button = this.querySelector('button[type="submit"]');
            const originalIcon = button.innerHTML;

            // Désactivation du bouton + Spinner de chargement
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

            const formData = new FormData(this);
            formData.append('ajax', '1'); // Force le flag ajax côté PHP

            // CORRECTION ICI : getAttribute('action') évite le conflit avec l'input name="action"
            fetch(this.getAttribute('action'), {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) throw new Error('Erreur réseau');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Animation visuelle : la ligne devient verte puis disparaît
                    row.style.transition = 'all 0.4s ease';
                    row.style.backgroundColor = 'rgba(20, 128, 74, 0.15)'; 
                    
                    setTimeout(() => {
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(30px)';
                        
                        setTimeout(() => {
                            row.remove();
                            
                            // Mise à jour dynamique du compteur en bas de page
                            const counterEl = document.getElementById('total-counter');
                            if (counterEl) {
                                let count = parseInt(counterEl.textContent, 10) - 1;
                                counterEl.textContent = count;
                                if (count <= 0) {
                                    window.location.reload(); 
                                }
                            }
                        }, 400);
                    }, 300);
                } else {
                    alert(data.message || 'Une erreur est survenue.');
                    button.disabled = false;
                    button.innerHTML = originalIcon;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Impossible de joindre le serveur ou session expirée.');
                button.disabled = false;
                button.innerHTML = originalIcon;
            });
        });
    });
});
</script>