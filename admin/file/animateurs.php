<?php declare(strict_types=1);

require_once __DIR__ . '/../../Backend/utilitaire.php';

requireRole(['directeur'], '../Auth/login.php');

$anneeActive = activeYearFromRequest();
$typeSessionActive = activeSessionTypeFromRequest();
$sessionService = appContainer()->get(\Patro\Inscription\SessionService::class);
$currentSessionId = $sessionService->ensureSession($anneeActive, $typeSessionActive);
$animateurRepository = appContainer()->get(\Patro\Domain\Animateur\Repository\AnimateurRepository::class);
$isScolaire = ($typeSessionActive === 'scolaire');

$message = '';
$alertType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $message = 'Jeton CSRF invalide. Veuillez recharger la page.';
        $alertType = 'danger';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'generate_codes') {
            $idSession = filter_var($_POST['id_session'] ?? null, FILTER_VALIDATE_INT);
            $quantite = filter_var($_POST['quantite'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
            $dateExpiration = trim((string) ($_POST['date_expiration'] ?? ''));

            if (!$idSession || !$quantite) {
                $message = 'Veuillez choisir une session et une quantite valide.';
                $alertType = 'danger';
            } elseif ((int) $idSession !== $currentSessionId) {
                $message = 'La generation de codes est limitee a la session active.';
                $alertType = 'danger';
            } elseif ($dateExpiration !== '' && !isValidDateString($dateExpiration)) {
                $message = 'Date d expiration invalide.';
                $alertType = 'danger';
            } else {
                $admin = currentadmin();
                $expiration = $dateExpiration !== '' ? $dateExpiration . ' 23:59:59' : null;
                $result = appContainer()->get(\Patro\Application\Animateur\GenererCodesAnimateur::class)->execute(
                    new \Patro\Application\Animateur\GenererCodesAnimateurCommand(
                        (int) $idSession,
                        (int) ($admin['id_admin'] ?? 0),
                        (int) $quantite,
                        $expiration,
                        $currentSessionId,
                        app_int('ANIMATEUR_CODE_LENGTH', 10)
                    )
                );
                $message = (string) ($result['message'] ?? '');
                $alertType = !empty($result['success']) ? 'success' : 'danger';
            }
        } elseif ($action === 'update_session_section') {
            if ($isScolaire) {
                $message = 'Aucune section ne doit etre attribuee en session scolaire.';
                $alertType = 'warning';
            } else {
                $idAnimateurSession = filter_var($_POST['id_animateur_session'] ?? null, FILTER_VALIDATE_INT);
                $idSection = filter_var($_POST['id_section'] ?? null, FILTER_VALIDATE_INT);

                if (!$idAnimateurSession || !$idSection) {
                    $message = 'Veuillez choisir une section valide.';
                    $alertType = 'danger';
                } else {
                    if (!$animateurRepository->sectionExists($idSection)) {
                        $message = 'Section introuvable.';
                        $alertType = 'danger';
                    } else {
                        $message = $animateurRepository->updateSessionSection($idAnimateurSession, $idSection, $currentSessionId)
                            ? 'Section mise a jour.'
                            : 'Aucune modification effectuee.';
                        $alertType = 'success';
                    }
                }
            }
        } elseif ($action === 'delete_code') {
            $idCode = filter_var($_POST['id_code'] ?? null, FILTER_VALIDATE_INT);
            if (!$idCode) {
                $message = 'Code invalide.';
                $alertType = 'danger';
            } else {
                $deleted = $animateurRepository->deleteAvailableCode($idCode, $currentSessionId);
                $message = $deleted ? 'Code non utilise supprime.' : 'Impossible de supprimer ce code.';
                $alertType = $deleted ? 'success' : 'warning';
            }
        } elseif ($action === 'block_unregistered') {
            $blocked = $animateurRepository->blockNotRegistered($currentSessionId);
            $message = $blocked . ' animateur(s) bloque(s) pour ' . $sessionService->sessionLabelById($currentSessionId) . '.';
        } elseif ($action === 'unblock_animateur') {
            $idAnimateur = filter_var($_POST['id_animateur'] ?? null, FILTER_VALIDATE_INT);
            if (!$idAnimateur) {
                $message = 'Animateur invalide.';
                $alertType = 'danger';
            } else {
                $message = $animateurRepository->activate($idAnimateur)
                    ? 'Animateur debloque manuellement.'
                    : 'Animateur introuvable.';
            }
        }
    }
}

$sections = appContainer()->get(\Patro\Inscription\SectionService::class)->getAllSections();
$filterSection = filter_var($_GET['id_section'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$filterStatut = (string) ($_GET['statut'] ?? '');
if (!in_array($filterStatut, ['', 'actif', 'bloque'], true)) {
    $filterStatut = '';
}

$animateurs = $animateurRepository->findForSession(
    $currentSessionId,
    !$isScolaire && $filterSection > 0 ? $filterSection : null,
    $filterStatut !== '' ? $filterStatut : null
);
$codes = $animateurRepository->findCodesForSession($currentSessionId);

$pageTitle = 'Gestion des animateurs';
$assetBase = '../../Backend/Assets';
?>
<div class="container-fluid home-shell">
    <main class="home-main">
        <div class="page-header">
            <h1>Gestion des animateurs</h1>
            <p><?= e($anneeActive) ?> - Session <?= e(\Patro\Domain\Inscription\SessionType::normalize($typeSessionActive)->label()) ?></p>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= e($alertType) ?>"><?= e($message) ?></div>
        <?php endif; ?>

        <?php displayFlashMessage(); ?>

        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white">Generer des codes d'inscription</div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="generate_codes">
                    <div class="col-md-4">
                        <label for="id_session">Session</label>
                        <input type="hidden" name="id_session" value="<?= e($currentSessionId) ?>">
                        <input type="text" class="form-control"
                               value="Session <?= e(\Patro\Domain\Inscription\SessionType::normalize($typeSessionActive)->label()) ?> <?= e($anneeActive) ?>"
                               disabled readonly>
                    </div>
                    <div class="col-md-3">
                        <label for="quantite">Quantite</label>
                        <input type="number" class="form-control" name="quantite" id="quantite" min="1" max="100" value="1" required>
                    </div>
                    <div class="col-md-3">
                        <label for="date_expiration">Expiration</label>
                        <input type="date" class="form-control" name="date_expiration" id="date_expiration">
                    </div>
                    <div class="col-md-2" style="padding-top: 24px;">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i>
                            Generer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4 shadow-sm border-warning">
            <div class="card-header bg-warning text-dark">Cloture des inscriptions animateurs</div>
            <div class="card-body">
                <form method="post" onsubmit="return confirm('Bloquer les animateurs non reinscrits pour cette session ?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="block_unregistered">
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-lock-fill" aria-hidden="true"></i>
                        Cloturer les inscriptions animateurs pour cette session
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card mb-4 shadow-sm">
            <div class="card-header">Codes generes</div>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Statut</th>
                            <th>Consomme par</th>
                            <th>Utilise le</th>
                            <th>Expiration</th>
                            <th>Admin</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($codes as $code): ?>
                            <tr>
                                <td><strong><?= e($code['code']) ?></strong></td>
                                <td><?= e($code['statut']) ?></td>
                                <td><?= $code['id_animateur'] ? e(trim((string) $code['nom_a'] . ' ' . (string) $code['prenom_a'])) : '-' ?></td>
                                <td><?= $code['utilise_le'] ? e(date('d/m/Y H:i', strtotime((string) $code['utilise_le']))) : '-' ?></td>
                                <td><?= $code['date_expiration'] ? e(date('d/m/Y', strtotime((string) $code['date_expiration']))) : '-' ?></td>
                                <td><?= e($code['username']) ?></td>
                                <td>
                                    <?php if ($code['statut'] === 'disponible'): ?>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer ce code non utilise ?');">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_code">
                                            <input type="hidden" name="id_code" value="<?= e((int) $code['id_code']) ?>">
                                            <button type="submit" class="btn btn-xs btn-danger">Supprimer</button>
                                        </form>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$codes): ?>
                            <tr><td colspan="7" class="text-center">Aucun code trouve pour cette session.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>