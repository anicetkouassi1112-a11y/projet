<?php declare(strict_types=1);

require_once __DIR__ . '/../../Backend/utilitaire.php';

$message = '';
$alertType = 'info';
$code = input('code');
$nom = input('nom');
$prenom = input('prenom');
$genre = input('genre');
$tel = input('tel');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $message = 'Jeton CSRF invalide. Veuillez recharger la page.';
        $alertType = 'danger';
    } else {
        $attempts = $_SESSION['animateur_code_attempts'] ?? ['count' => 0, 'locked_until' => 0];
        if ((int) ($attempts['locked_until'] ?? 0) > time()) {
            $result = [
                'success' => false,
                'message' => 'Trop de tentatives. Veuillez patienter avant de reessayer.',
                'alert_type' => 'danger',
            ];
        } else {
            $result = appContainer()->get(\Patro\Application\Animateur\InscrireAnimateurParCode::class)
                ->execute(new \Patro\Application\Animateur\InscrireAnimateurParCodeCommand(
                    $code,
                    $nom,
                    $prenom,
                    $genre,
                    $tel,
                    (string) ($_POST['password'] ?? ''),
                    (string) ($_POST['password_confirm'] ?? ''),
                    appContainer()->get(\Patro\Inscription\SessionService::class)->getActiveAdminSessionId()
                ));
            if (!$result['success'] && ($result['message'] ?? '') === 'Code invalide ou deja utilise.') {
                $count = (int) ($attempts['count'] ?? 0) + 1;
                $_SESSION['animateur_code_attempts'] = [
                    'count' => $count,
                    'locked_until' => $count >= 8 ? time() + 600 : 0,
                ];
            } elseif ($result['success']) {
                unset($_SESSION['animateur_code_attempts']);
            }
        }
        $message = (string) ($result['message'] ?? '');
        $alertType = (string) ($result['alert_type'] ?? 'danger');

        if (!empty($result['success'])) {
            setFlashMessage('success', $message);
            redirectTo(app_url('public/auth/connexion.php'));
        }
    }
}

$pageTitle = 'Inscription animateur';
$assetBase = '../../Backend/Assets';
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
            <a class="navbar-brand" href="connexion.php">PATRO</a>
        </div>
        <div class="public-nav-actions">
            <a class="nav-link animateur-link" href="<?= e(app_url('public/home.php?page=accueil')) ?>">
                <i class="bi bi-person-circle me-2"></i>Portail public
            </a>
        </div>
    </nav>
    </header>
    <main class="login-card">
        <h1>Inscription animateur</h1>
        <p class="step-subtitle">Code fourni par l'administrateur</p>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= e($alertType === 'error' ? 'danger' : $alertType) ?>">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <form action="animateur_inscription.php" method="post" class="registration-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="user-details">
                <div class="form-group">
                    <input type="text" class="form-control" name="code" id="code" placeholder=" " required value="<?= e($code) ?>" maxlength="20" autocomplete="one-time-code">
                    <label for="code" class="floating-label">Code d'inscription</label>
                </div>
                <div class="form-group">
                    <input type="text" class="form-control" name="nom" id="nom" placeholder=" " required value="<?= e($nom) ?>">
                    <label for="nom" class="floating-label">Nom</label>
                </div>
                <div class="form-group">
                    <input type="text" class="form-control" name="prenom" id="prenom" placeholder=" " required value="<?= e($prenom) ?>">
                    <label for="prenom" class="floating-label">Prenom</label>
                </div>
                <div class="form-group">
                    <select class="form-control" name="genre" id="genre" required>
                        <option value="">Sélectionner</option>
                        <option value="M" <?= $genre === 'M' ? 'selected' : '' ?>>M</option>
                        <option value="F" <?= $genre === 'F' ? 'selected' : '' ?>>F</option>
                    </select>
                    <label for="genre" class="floating-label"><i class="bi bi-gender-ambiguous"></i> Genre</label>
                </div>
                <div class="form-group">
                    <input type="tel" class="form-control" name="tel" id="tel" placeholder=" " required value="<?= e($tel) ?>" maxlength="10" pattern="(01|05|07)[0-9]{8}">
                    <label for="tel" class="floating-label">Telephone</label>
                </div>
                <div class="form-group">
                    <input type="password" class="form-control" name="password" id="password" placeholder=" " required minlength="8">
                    <label for="password" class="floating-label">Mot de passe</label>
                </div>
                <div class="form-group">
                    <input type="password" class="form-control" name="password_confirm" id="password_confirm" placeholder=" " required minlength="8">
                    <label for="password_confirm" class="floating-label">Confirmation</label>
                </div>
            </div>
            <button type="submit" class="btn btn-success btn-block">Valider l'inscription animateur</button>
            <p class="text-center" style="margin-top: 15px;">
                <a href="connexion.php">J ai deja un compte animateur</a>
            </p>
        </form>
    </main>
    <?php require __DIR__ . '/../include/foot.php'; ?>