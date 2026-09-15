<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Backend/utilitaire.php';

// Vérifier si déjà connecté
if (class_exists('\Patro\Auth\AdminAuth')) {
    $currentAdmin = \Patro\Auth\AdminAuth::currentAdmin();
    if (!empty($currentAdmin)) {
        redirectTo('../' . defaultadminRoute());
    }
} else {
    if (!empty($_SESSION['adpro'])) {
        redirectTo('../' . defaultadminRoute());
    }
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation CSRF
    if (class_exists('\Patro\Security\CsrfProtection')) {
        \Patro\Security\CsrfProtection::requireToken($_POST['csrf_token'] ?? null);
    } else {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            $message = 'Jeton CSRF invalide.';
            securityLog('Invalid admin login CSRF token');
        }
    }
    
    if ($message === '') {
        $loginAttempts = (int) ($_SESSION['login_attempts'] ?? 0);
        $lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);

        if ($lockedUntil > time()) {
            $message = 'Trop de tentatives. Veuillez patienter avant de reessayer.';
            if (class_exists('\Patro\Security\SecurityLogger')) {
                \Patro\Security\SecurityLogger::log('Admin login blocked by rate limit', [
                    'username' => appCleanText((string) ($_POST['username'] ?? ''), 80),
                ]);
            } else {
                securityLog('Admin login blocked by rate limit', [
                    'username' => appCleanText((string) ($_POST['username'] ?? ''), 80),
                ]);
            }
        } else {
            $username = appCleanText((string) ($_POST['username'] ?? ''), 80);
            $password = (string) ($_POST['password'] ?? '');

            if ($username === '' || $password === '') {
                $message = 'Veuillez renseigner le nom utilisateur et le mot de passe.';
            } elseif (strlen($password) > 256) {
                $message = 'Mot de passe invalide.';
            } else {
                // Utiliser AdminAuth si disponible
                if (class_exists('\Patro\Auth\AdminAuth')) {
                    $admin = \Patro\Auth\AdminAuth::login($username, $password);
                } else {
                    $admin = loginadmin($username, $password);
                }
                
                if (!$admin) {
                    $message = 'Identifiants incorrects.';
                    if (class_exists('\Patro\Security\SecurityLogger')) {
                        \Patro\Security\SecurityLogger::log('Failed admin login', ['username' => $username]);
                    } else {
                        securityLog('Failed admin login', ['username' => $username]);
                    }
                    $_SESSION['login_attempts'] = $loginAttempts + 1;
                    if ((int) $_SESSION['login_attempts'] >= 5) {
                        $_SESSION['login_locked_until'] = time() + 300;
                    }
                } else {
                    appContainer()->get(\Patro\Application\Auth\AdminAuthenticationService::class)->establish($admin);
                    unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
                    actionLog('Successful admin login', [
                        'admin_id' => (int) ($admin['id_admin'] ?? 0),
                        'role' => (string) ($admin['role'] ?? ''),
                    ]);
                    redirectTo('../' . defaultadminRoute((string) ($admin['role'] ?? 'directeur')));
                }
            }
        }
    }
}

$pageTitle = 'Connexion';
$assetBase = function_exists('app_url') ? app_url('Backend/Assets') : rtrim($assetBase ?? '../../Backend/Assets', '/');

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require __DIR__ . '/../include/head.php'; ?>
</head>
<body class="login-page">
    <header class="app-header">
    <nav class="navbar navbar-expand-lg border-bottom" aria-label="Navigation de connexion">
        <div class="container-fluid">
            <a class="navbar-brand" href="login.php">admin-PATRO</a>
        </div>
    </nav>
    </header>

    <main class="login-card">
        <h1>Connexion administrateur</h1>
        <?php if ($message !== ''): ?>
            <div class="alert alert-danger"><?= e($message) ?></div>
        <?php endif; ?>
        <form action="login.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="form-group">
                <label for="username">Nom utilisateur</label>
                <input type="text" class="form-control" name="username" id="username" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" class="form-control" name="password" id="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block login-submit">
                <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                Se connecter
            </button>
        </form>
    </main>
    <?php require __DIR__ . '/../include/foot.php'; ?>