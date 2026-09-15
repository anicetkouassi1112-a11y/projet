<?php

require_once __DIR__ . '/../../Backend/utilitaire.php';

// Vérifier si déjà connecté
if (!empty($_SESSION['animateur'])) {
    redirectTo(app_url('public/views/animateur.php'));
}

$message = '';
$nom_a = input('nom_a');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation CSRF
    if (class_exists('\Patro\Security\CsrfProtection')) {
        \Patro\Security\CsrfProtection::requireToken($_POST['csrf_token'] ?? null);
    } else {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            $message = 'Jeton CSRF invalide. Veuillez recharger la page.';
            if (class_exists('\Patro\Security\SecurityLogger')) {
                \Patro\Security\SecurityLogger::log('Invalid animateur login CSRF token', ['nom_a' => $nom_a]);
            } else {
                securityLog('Invalid animateur login CSRF token', ['nom_a' => $nom_a]);
            }
        }
    }
    
    if ($message === '') {
        $loginAttempts = (int) ($_SESSION['animateur_login_attempts'] ?? 0);
        $lockedUntil = (int) ($_SESSION['animateur_login_locked_until'] ?? 0);

        if ($lockedUntil > time()) {
            $message = 'Trop de tentatives. Veuillez patienter avant de reessayer.';
            if (class_exists('\Patro\Security\SecurityLogger')) {
                \Patro\Security\SecurityLogger::log('Animateur login blocked by rate limit', ['nom_a' => $nom_a]);
            } else {
                securityLog('Animateur login blocked by rate limit', ['nom_a' => $nom_a]);
            }
        } else {
            $password = (string) ($_POST['password'] ?? '');
            $result = loginAnimateur($nom_a, $password);

            if (empty($result['success'])) {
                $message = (string) ($result['message'] ?? 'Identifiants incorrects.');
                if (class_exists('\Patro\Security\SecurityLogger')) {
                    \Patro\Security\SecurityLogger::log('Failed animateur login', ['nom_a' => $nom_a]);
                } else {
                    securityLog('Failed animateur login', ['nom_a' => $nom_a]);
                }
                $_SESSION['animateur_login_attempts'] = $loginAttempts + 1;
                if ((int) $_SESSION['animateur_login_attempts'] >= 5) {
                    $_SESSION['animateur_login_locked_until'] = time() + 300;
                }
            } else {
                session_regenerate_id(true);
                $_SESSION['animateur'] = $result['animateur'];
                unset($_SESSION['animateur_login_attempts'], $_SESSION['animateur_login_locked_until']);
                actionLog('Successful animateur login', ['animateur_id' => (int) ($result['animateur']['id_animateur'] ?? 0)]);
                redirectTo(app_url('public/views/animateur.php'));
            }
        }
    }
}

$pageTitle = 'Connexion animateur';
$assetBase = '../../Backend/Assets';
?>
<!-- le reste du HTML est inchangé -->
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require __DIR__ . '/../include/head.php'; ?>
</head>
<body class="login-page">
    <header class="app-header">
    <nav class="navbar navbar-expand-lg border-bottom" aria-label="Navigation de connexion">
        <div class="container-fluid">
            <a class="navbar-brand" href="connexion.php">PATRO</a>
        </div>
        <div class="public-nav-actions">
            <a class="nav-link animateur-link" href="<?= e(app_url('public/home.php')) ?>">
                <i class="bi bi-person-circle me-2"></i>Portail public
            </a>
        </div>
    </nav>
    </header>
    <main class="login-card">
        <h1>Connexion animateur</h1>
        <p class="step-subtitle">Nom et mot de passe</p>

        <?php if ($message !== ''): ?>
            <div class="alert alert-danger"><?= e($message) ?></div>
        <?php endif; ?>
        <?php displayFlashMessage(); ?>

        <form action="connexion.php" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <div class="form-group">
                    <input type="text" class="form-control" name="nom_a" id="nom_a" placeholder=" " required value="<?= e($nom_a) ?>" maxlength="120">
                    <label for="nom_a" class="floating-label">Nom </label>
                </div>
                <div class="form-group">
                    <input type="password" class="form-control" name="password" id="password" placeholder=" " required>
                    <label for="password" class="floating-label">Mot de passe</label>
                </div>
            <button type="submit" class="btn btn-primary btn-block">Se connecter</button>
            <p class="text-center" style="margin-top: 15px;">
                <a href="animateur_inscription.php">S'enregistrer avec un code</a>
            </p>
        </form>
    </main>
    <?php require __DIR__ . '/../include/foot.php'; ?>
