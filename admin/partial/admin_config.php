<?php
declare(strict_types=1);

require_once __DIR__ . '/utilitaire.php';
require_once __DIR__ . '/backups.php';

requireRole(['directeur'], '../Auth/login.php');
$configuration = appContainer()->get(\Patro\Application\Configuration\ConfigurationService::class);

$configPages = ['general', 'sauvegarde'];
$configPage = (string) ($_GET['config_page'] ?? 'general');
if (!in_array($configPage, $configPages, true)) {
    $configPage = 'general';
}

$message = '';
$alertType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $message = 'Jeton CSRF invalide. Veuillez recharger la page.';
        $alertType = 'danger';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'update_dates') {
            $dateDebut = trim((string) ($_POST['date_debut'] ?? ''));
            $dateFin = trim((string) ($_POST['date_fin'] ?? ''));

            if ($dateDebut && !isValidDateString($dateDebut)) {
                $message = 'La date de debut n est pas valide (format: YYYY-MM-DD).';
                $alertType = 'danger';
            } elseif ($dateFin && !isValidDateString($dateFin)) {
                $message = 'La date de fin n est pas valide (format: YYYY-MM-DD).';
                $alertType = 'danger';
            } elseif ($dateDebut && $dateFin && $dateDebut > $dateFin) {
                $message = 'La date de debut doit etre avant la date de fin.';
                $alertType = 'danger';
            } else {
                $configuration->set('inscription_date_debut', $dateDebut ?: null);
                $configuration->set('inscription_date_fin', $dateFin ?: null);
                $message = 'Periodes d inscriptions mises a jour avec succes.';
            }
        } elseif ($action === 'toggle_fermeture') {
            $forceFerme = isset($_POST['force_ferme']) && $_POST['force_ferme'] === '1';
            $configuration->set('inscription_force_ferme', $forceFerme ? 'on' : 'off');
            $message = $forceFerme ? 'Inscriptions fermees manuellement.' : 'Inscriptions rouvertes manuellement.';
        } elseif ($action === 'update_session_type') {
            $typeSession = appContainer()->get(\Patro\Inscription\SessionService::class)->normalizeSessionType($_POST['type_session'] ?? null, '');
            if ($typeSession === '') {
                $message = 'Type de session invalide.';
                $alertType = 'danger';
            } else {
                $configuration->set('inscription_type_session', $typeSession);
                $_SESSION['type_session_active'] = $typeSession;
                $message = 'Type de session actif mis a jour: ' . sessionTypeLabel($typeSession) . '.';
            }
        } elseif ($action === 'update_registration_prices') {
            $montantInscription = filter_var($_POST['montant_inscription'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $prixTeeShirt = filter_var($_POST['prix_tee_shirt'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

            if ($montantInscription === false || $prixTeeShirt === false) {
                $message = 'Les montants doivent etre des nombres entiers valides.';
                $alertType = 'danger';
            } else {
                $configuration->set('inscription_montant', (string) $montantInscription);
                $configuration->set('tee_shirt_prix', (string) $prixTeeShirt);
                $message = 'Montants d inscription mis a jour avec succes.';
            }
        } elseif ($action === 'create_backup') {
            $annee = filter_var($_POST['annee'] ?? null, FILTER_VALIDATE_INT);
            $typeSession = appContainer()->get(\Patro\Inscription\SessionService::class)->normalizeSessionType($_POST['type_session'] ?? null, '');

            if (!$annee || $annee < 2000 || $annee > 2100 || $typeSession === '') {
                $configPage = 'sauvegarde';
                $message = 'Parametres de sauvegarde invalides.';
                $alertType = 'danger';
            } else {
                try {
                    $backup = createDatabaseBackup((int) $annee, $typeSession);
                    $path = (string) $backup['path'];
                    $filename = basename((string) $backup['filename']);
                    if (!is_file($path) || !is_readable($path)) {
                        throw new RuntimeException('Fichier de sauvegarde introuvable.');
                    }

                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }

                    header('Content-Type: application/json; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    header('Content-Length: ' . filesize($path));
                    header('Cache-Control: private, no-store, no-cache, must-revalidate');
                    header('Pragma: no-cache');
                    readfile($path);
                    exit;
                } catch (Throwable $e) {
                    error_log('Backup error: ' . $e->getMessage());
                    $configPage = 'sauvegarde';
                    $message = 'Erreur pendant la sauvegarde des donnees.';
                    $alertType = 'danger';
                }
            }
        }
    }
}

$dateDebutActuel = $configuration->registrationDateStart() ?? '';
$dateFinActuel = $configuration->registrationDateEnd() ?? '';
$forceFermeActuel = $configuration->forceRegistrationClosed();
$typeSessionActuel = appContainer()->get(\Patro\Inscription\SessionService::class)->getCurrentSessionType();
$montantInscriptionActuel = $configuration->registrationAmount();
$prixTeeShirtActuel = $configuration->teeShirtPrice();
$anneeActive = activeYearFromRequest();
$pageTitle = 'Configuration - administration';
$assetBase = '../../Backend/Assets';
?>

<div class="container-fluid">
    <main class="admin-config-main">
        <div class="page-header">
            <h1><?= e($configPage === 'general' ? 'Configuration' : 'Sauvegarde des donnees') ?></h1>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= e($alertType) ?> alert-dismissible fade show" role="alert">
                <?= e($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
        <?php endif; ?>

        <?php if ($configPage === 'general'): ?>
            <div class="card mb-4 border-info">
                <div class="card-header bg-info text-white">Type de session actif</div>
                <div class="card-body">
                    <form method="post" class="form-inline">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="update_session_type">
                        <div class="form-group mr-3">
                            <label for="type_session" class="mr-2">Session</label>
                            <select class="form-control" name="type_session" id="type_session">
                                <?php foreach (\Patro\Domain\Inscription\SessionType::values() as $typeSession): ?>
                                    <option value="<?= e($typeSession) ?>" <?= $typeSession === $typeSessionActuel ? 'selected' : '' ?>>
                                        <?= e(sessionTypeLabel($typeSession)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-top: 12px;">Changer</button>
                    </form>
                </div>
            </div>

            <div class="card mb-4 border-primary">
                <div class="card-header bg-primary text-white">Periode d'inscription</div>
                <div class="card-body">
                    <form method="post" class="form-inline">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="update_dates">
                        <div class="form-group mr-3">
                            <label for="date_debut" class="mr-2">Date d'ouverture</label>
                            <input type="date" class="form-control" name="date_debut" id="date_debut" value="<?= e($dateDebutActuel) ?>">
                        </div>
                        <div class="form-group mr-3">
                            <label for="date_fin" class="mr-2">Date de fermeture</label>
                            <input type="date" class="form-control" name="date_fin" id="date_fin" value="<?= e($dateFinActuel) ?>">
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-top: 12px;">Enregistrer</button>
                    </form>
                </div>
            </div>

            <div class="card mb-4 border-info">
                <div class="card-header bg-info text-white">Montants de l'inscription</div>
                <div class="card-body">
                    <form method="post" class="form-inline">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="update_registration_prices">
                        <div class="form-group mr-3">
                            <label for="montant_inscription" class="mr-2">Inscription seule</label>
                            <input type="number" class="form-control" name="montant_inscription" id="montant_inscription" min="1" step="1" value="<?= e($montantInscriptionActuel) ?>" required>
                        </div>
                        <div class="form-group mr-3">
                            <label for="prix_tee_shirt" class="mr-2">Prix tee-shirt</label>
                            <input type="number" class="form-control" name="prix_tee_shirt" id="prix_tee_shirt" min="0" step="1" value="<?= e($prixTeeShirtActuel) ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-top: 12px;">Enregistrer</button>
                    </form>
                    <p class="text-muted" style="margin-top: 10px;">
                        Montant avec tee-shirt: <?= e(formatFcfa($montantInscriptionActuel + $prixTeeShirtActuel)) ?>
                    </p>
                </div>
            </div>
            
            <div class="card mb-4 border-<?= $forceFermeActuel ? 'danger' : 'secondary' ?>">
                <div class="card-header <?= $forceFermeActuel ? 'bg-danger text-white' : 'bg-secondary text-white' ?>">Fermeture manuelle</div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="toggle_fermeture">
                        <label>
                            <input type="checkbox" name="force_ferme" value="1" <?= $forceFermeActuel ? 'checked' : '' ?> onchange="this.form.submit()">
                            &nbsp;Fermer les inscriptions maintenant
                        </label>
                    </form>
                </div>
            </div>
        <?php elseif ($configPage === 'sauvegarde'): ?>
            <div class="card mb-4 border-info">
                <div class="card-header bg-info text-white">Exporter une sauvegarde</div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Telecharge une sauvegarde JSON des inscriptions et photos disponibles pour une annee et une session.
                    </p>
                    <form method="post" class="form-inline">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="create_backup">
                        <div class="form-group mr-3">
                            <label for="backup_annee" class="mr-2">Annee</label>
                            <input type="number" class="form-control" name="annee" id="backup_annee" min="2000" max="2100" value="<?= e($anneeActive) ?>" required>
                        </div>
                        <div class="form-group mr-3">
                            <label for="backup_type_session" class="mr-2">Session</label>
                            <select class="form-control" name="type_session" id="backup_type_session" required>
                                <?php foreach (\Patro\Domain\Inscription\SessionType::values() as $typeSession): ?>
                                    <option value="<?= e($typeSession) ?>" <?= $typeSession === $typeSessionActuel ? 'selected' : '' ?>>
                                        <?= e(sessionTypeLabel($typeSession)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-download" aria-hidden="true"></i>
                            Sauvegarder les donnees
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>
