<?php declare(strict_types=1);

require_once __DIR__ . '/../../Backend/utilitaire.php';

requireadmin('../Auth/login.php');

$message = '';
$alertType = 'success';
$admin = currentadmin();
$adminId = (int) ($admin['id_admin'] ?? 0);
$adminRepository = appContainer()->get(\Patro\Domain\Admin\Repository\AdminRepository::class);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $message = 'Jeton CSRF invalide. Veuillez recharger la page.';
        $alertType = 'danger';
    } elseif ($adminId <= 0) {
        $message = 'Session administrateur invalide.';
        $alertType = 'danger';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'update_username') {
            $newUsername = trim((string) ($_POST['username'] ?? ''));

            if ($newUsername === '') {
                $message = "Le nom d'utilisateur ne peut pas etre vide.";
                $alertType = 'danger';
            } elseif (strlen($newUsername) < 3 || strlen($newUsername) > 50) {
                $message = "Le nom d'utilisateur doit contenir entre 3 et 50 caracteres.";
                $alertType = 'danger';
            } elseif (!preg_match('/^[A-Za-z0-9_.-]+$/', $newUsername)) {
                $message = "Le nom d'utilisateur ne peut contenir que des lettres, chiffres, points, tirets et underscores.";
                $alertType = 'danger';
            } else {
                try {
                    // Verifie que le nom d'utilisateur n'est pas deja pris par un autre admin
                    if ($adminRepository->usernameExistsForOther($adminId, $newUsername)) {
                        $message = "Ce nom d'utilisateur est deja utilise.";
                        $alertType = 'danger';
                    } else {
                        $adminRepository->updateUsername($adminId, $newUsername);

                        // Met a jour les infos de session/admin courant si necessaire
                        if (is_array($_SESSION['adpro'] ?? null)) {
                            $_SESSION['adpro']['username'] = $newUsername;
                        }
                        $admin['username'] = $newUsername;

                        $message = "Nom d'utilisateur mis a jour avec succes.";
                    }
                } catch (PDOException $e) {
                    error_log('Username update error: ' . $e->getMessage());
                    $message = "Erreur pendant la mise a jour du nom d'utilisateur.";
                    $alertType = 'danger';
                }
            }
        } elseif ($action === 'update_password') {
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                $message = 'Veuillez renseigner tous les champs.';
                $alertType = 'danger';
            } elseif (strlen($newPassword) < 8) {
                $message = 'Le nouveau mot de passe doit contenir au moins 8 caracteres.';
                $alertType = 'danger';
            } elseif (!preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
                $message = 'Le nouveau mot de passe doit contenir au moins une lettre et un chiffre.';
                $alertType = 'danger';
            } elseif ($newPassword !== $confirmPassword) {
                $message = 'La confirmation ne correspond pas au nouveau mot de passe.';
                $alertType = 'danger';
            } else {
                try {
                    $storedPassword = (string) ($adminRepository->findPasswordHash($adminId) ?? '');

                    // Fallback pour les anciens comptes stockes en MD5 non sale.
                    // Le mot de passe sera automatiquement migre vers PASSWORD_DEFAULT ci-dessous.
                    $validCurrentPassword = $storedPassword !== ''
                        && (password_verify($currentPassword, $storedPassword) || hash_equals($storedPassword, md5($currentPassword)));

                    if (!$validCurrentPassword) {
                        $message = 'Mot de passe actuel incorrect.';
                        $alertType = 'danger';
                    } else {
                        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                        $adminRepository->updatePassword($adminId, $newHash);
                        session_regenerate_id(true);
                        $message = 'Mot de passe mis a jour avec succes.';
                    }
                } catch (PDOException $e) {
                    error_log('Password update error: ' . $e->getMessage());
                    $message = 'Erreur pendant la mise a jour du mot de passe.';
                    $alertType = 'danger';
                }
            }
        } else {
            $message = 'Action inconnue.';
            $alertType = 'danger';
        }
    }
}

$pageTitle = 'Profil - Mot de passe';
$assetBase = '../../Backend/Assets';
?>

<div class="container-fluid home-shell">
    <main class="admin-config-main">
        <div class="page-header">
            <h1>Profil</h1>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= e($alertType) ?> alert-dismissible fade show" role="alert">
                <?= e($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
        <?php endif; ?>

        <!-- Formulaire de modification du nom d'utilisateur -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white">Username</div>
            <div class="card-body">
                <form method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="update_username">
                    <div class="form-group">
                        <label for="username">Nom d'utilisateur</label>
                        <input type="text" class="form-control" name="username" id="username" value="<?= e($admin['username'] ?? '') ?>" required minlength="3" maxlength="50" pattern="[A-Za-z0-9_.\-]+">
                        <small class="form-text text-muted">Lettres, chiffres, points, tirets et underscores uniquement (3 a 50 caracteres).</small>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-person-fill" aria-hidden="true"></i>
                        Mettre a jour
                    </button>
                </form>
            </div>
        </div>

        <!-- Formulaire de modification du mot de passe -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white">Modifier le mot de passe</div>
            <div class="card-body">
                <form method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="update_password">
                    <div class="form-group">
                        <label for="current_password">Mot de passe actuel</label>
                        <input type="password" class="form-control" name="current_password" id="current_password" required autocomplete="current-password">
                    </div>
                    <div class="form-group">
                        <label for="new_password">Nouveau mot de passe</label>
                        <input type="password" class="form-control" name="new_password" id="new_password" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirmer le nouveau mot de passe</label>
                        <input type="password" class="form-control" name="confirm_password" id="confirm_password" required minlength="8" autocomplete="new-password">
                    </div>
                    <p class="text-muted small">Le mot de passe doit contenir au moins 8 caracteres, incluant au moins une lettre et un chiffre.</p>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-lock-fill" aria-hidden="true"></i>
                        Mettre a jour
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>