<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Backend/utilitaire.php';

$configuration = appContainer()->get(\Patro\Application\Configuration\ConfigurationService::class);
$inscriptionsOuvertes = $configuration->registrationsOpen();
$message = '';
$alertType = '';
$nom = input('nom');
$prenom = input('prenom');
$dateNaissance = input('date_naissance');
$tel = input('tel');
$adresse = input('adresse');
$genre = input('genre');
$prix = input('prix');
$tailleTeeShirt = input('taille_tee_shirt');
$typeSessionActuel = currentSessionType();
$montantBase = $configuration->registrationAmount();
$prixTeeShirt = $configuration->teeShirtPrice();
$montantAvecTeeShirt = $montantBase + $prixTeeShirt;

// Rediriger si inscriptions fermees
if (!$inscriptionsOuvertes) {
    $closedMessage = $configuration->registrationClosedMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Empêcher l'inscription si inscriptions fermées
    if (!$inscriptionsOuvertes) {
        $message = $configuration->registrationClosedMessage();
        $alertType = 'warning';
    } else {
        // Validation CSRF
        if (class_exists('\Patro\Security\CsrfProtection')) {
            \Patro\Security\CsrfProtection::requireToken($_POST['csrf_token'] ?? null);
        } else {
            if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
                $message = 'Jeton CSRF invalide. Veuillez recharger la page.';
                $alertType = 'danger';
            }
        }
        
        if ($message === '') {
            $command = new \Patro\Application\Inscription\EnregistrerInscritCommand(
                $nom,
                $prenom,
                $dateNaissance,
                $genre,
                $tel,
                $adresse,
                $prix,
                $tailleTeeShirt,
                (int) date('Y'),
                $typeSessionActuel,
                $montantBase,
                $prixTeeShirt,
                sectionBreakdownEnabled($typeSessionActuel),
                app_int('IDENTIFIANT_ORDER_DIGITS', 3)
            );
            $result = appContainer()->get(\Patro\Application\Inscription\EnregistrerInscrit::class)->execute($command);
            $message = (string) ($result['message'] ?? '');
            $alertType = (string) ($result['alert_type'] ?? 'danger');

            if (!empty($result['success']) && !empty($result['id_inscrit'])) {
                $_SESSION['last_inscrit_id'] = (int) $result['id_inscrit'];
                redirectTo(nextRegistrationStepUrl((int) $result['id_inscrit']));
            }
        }
    }
}

$pageTitle = 'Inscription';
$assetBase = app_url('Backend/Assets');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require __DIR__ . '/../include/head.php'; ?>
</head>
<body class="login-page">
    <header class="app-header">
    <nav class="navbar navbar-expand-lg public-navbar" aria-label="Navigation de connexion">
        <div class="container-fluid public-nav-inner">
            <a class="navbar-brand public-brand" href="connexion.php">PATRO</a>
        </div>
        <div class="public-nav-actions">
            <a class="nav-link animateur-link" href="<?= e(app_url('public/home.php?page=accueil')) ?>">
                <i class="bi bi-person-circle me-2"></i>Portail public
            </a>
        </div>
    </nav>
    </header>
    <main class="login-card">
        <div class="registration-header">
            <h1>Inscription</h1>
            <p class="step-subtitle">Session <?= e(sessionTypeLabel($typeSessionActuel)) ?></p>
        </div>

        <?php if (!$inscriptionsOuvertes): ?>
            <div class="alert alert-warning">
                <strong>Inscriptions fermees</strong><br>
                <?= e($configuration->registrationClosedMessage()) ?>
            </div>
        <?php elseif ($message !== ''): ?>
            <div class="alert alert-<?= e($alertType === 'error' ? 'danger' : $alertType) ?>">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <form action="inscription.php" method="post" id="registrationForm" class="registration-form <?= !$inscriptionsOuvertes ? 'is-disabled' : '' ?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="user-details">
                <div class="input-box">
                    <input type="text" class="form-control" name="nom" id="nom" placeholder=" " required value="<?= e($nom) ?>">
                    <label for="nom" class="floating-label"><i class="bi bi-person"></i> Nom</label>
                </div>
                <div class="input-box">
                    <input type="text" class="form-control" name="prenom" id="prenom" placeholder=" " required value="<?= e($prenom) ?>">
                    <label for="prenom" class="floating-label"><i class="bi bi-person"></i> Prénom</label>
                </div>
                <div class="input-box">
                    <input type="date" class="form-control" name="date_naissance" id="date_naissance" placeholder=" " required value="<?= e($dateNaissance) ?>">
                    <label for="date_naissance" class="floating-label"><i class="bi bi-calendar3"></i> Date de naissance</label>
                </div>
                <div class="input-box">
                    <input type="tel" class="form-control" name="tel" id="tel" placeholder=" " required value="<?= e($tel) ?>" maxlength="10" pattern="(01|05|07)[0-9]{8}">
                    <label for="tel" class="floating-label"><i class="bi bi-telephone"></i> Téléphone</label>
                </div>
                <div class="input-box">
                    <input type="text" class="form-control" name="adresse" id="adresse" placeholder=" " required value="<?= e($adresse) ?>">
                    <label for="adresse" class="floating-label"><i class="bi bi-geo-alt"></i> Lieu de résidence</label>
                </div>
                <div class="input-box">
                    <select class="form-control" name="genre" id="genre" required>
                        <option value="">Sélectionner</option>
                        <option value="Garçon" <?= $genre === 'Garçon' ? 'selected' : '' ?>>Garçon</option>
                        <option value="Fille" <?= $genre === 'Fille' ? 'selected' : '' ?>>Fille</option>
                    </select>
                    <label for="genre" class="floating-label"><i class="bi bi-gender-ambiguous"></i> Genre</label>
                </div>
            </div>
            <button type="button" class="btn btn-primary btn-block" id="showOptionsButton">
                <i class="bi bi-arrow-right me-2"></i>S'inscrire
            </button>

            <section class="registration-options" id="registrationOptions" aria-hidden="true">
                <div class="options-header">
                    <h2><i class="bi bi-check-circle me-2"></i>Finaliser l'inscription</h2>
                    <p>Choisissez le montant et la taille du tee-shirt si besoin.</p>
                </div>

                <fieldset class="amount-section">
                    <legend><i class="bi bi-cash-coin me-2"></i>Montant de l'inscription</legend>
                    <div class="amount-options">
                        <label class="amount-option" for="prix0">
                            <input type="radio" id="prix0" name="prix" value="<?= e($montantBase) ?>" <?= $prix === (string) $montantBase ? 'checked' : '' ?> required>
                            <span>
                                <strong><?= e(formatFcfa($montantBase)) ?></strong>
                                <small>Inscription seule</small>
                            </span>
                        </label>
                        <label class="amount-option" for="prix1">
                            <input type="radio" id="prix1" name="prix" value="<?= e($montantAvecTeeShirt) ?>" <?= $prix === (string) $montantAvecTeeShirt ? 'checked' : '' ?> required>
                            <span>
                                <strong><?= e(formatFcfa($montantAvecTeeShirt)) ?></strong>
                                <small>Inscription + tee-shirt (<?= e(formatFcfa($prixTeeShirt)) ?>)</small>
                            </span>
                        </label>
                    </div>
                </fieldset>

                <fieldset class="amount-section tshirt-section" id="tshirtSection" aria-hidden="true">
                    <legend><i class="bi bi-t-shirt me-2"></i>Taille du tee-shirt</legend>
                    <div class="amount-options tshirt-options">
                        <?php foreach (validTeeShirtSizes() as $size): ?>
                        <label class="amount-option" for="taille<?= e($size) ?>">
                            <input type="radio" id="taille<?= e($size) ?>" name="taille_tee_shirt" value="<?= e($size) ?>" <?= $tailleTeeShirt === $size ? 'checked' : '' ?>>
                            <span><?= e($size) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <div class="loading-bar" id="registrationLoading" role="progressbar" aria-label="Inscription en cours" aria-hidden="true">
                    <span></span>
                </div>
                <button type="submit" name="btninscription" class="btn btn-success btn-block" data-loading-text="Inscription en cours...">
                    <i class="bi bi-check me-2"></i>Valider l'inscription
                </button>
            </section>
        </form>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('registrationForm');
            if (!form) return;

            const showOptionsButton = document.getElementById('showOptionsButton');
            const optionsPanel = document.getElementById('registrationOptions');
            const button = form.querySelector('button[type="submit"]');
            const loadingBar = document.getElementById('registrationLoading');
            const teeShirtPrice = document.getElementById('prix1');
            const teeShirtSection = document.getElementById('tshirtSection');
            const sizeInputs = form.querySelectorAll('input[name="taille_tee_shirt"]');

            const toggleTeeShirtSizes = () => {
                const isRequired = Boolean(teeShirtPrice && teeShirtPrice.checked);
                if (teeShirtSection) {
                    teeShirtSection.classList.toggle('is-visible', isRequired);
                    teeShirtSection.setAttribute('aria-hidden', isRequired ? 'false' : 'true');
                }
                sizeInputs.forEach((input) => {
                    input.required = isRequired;
                    if (!isRequired) {
                        input.checked = false;
                    }
                });
            };

            if (showOptionsButton && optionsPanel) {
                showOptionsButton.addEventListener('click', () => {
                    optionsPanel.classList.add('is-visible');
                    optionsPanel.setAttribute('aria-hidden', 'false');
                    showOptionsButton.hidden = true;
                    optionsPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                });
            }

            form.querySelectorAll('input[name="prix"]').forEach((input) => {
                input.addEventListener('change', toggleTeeShirtSizes);
            });

            toggleTeeShirtSizes();

            form.addEventListener('submit', () => {
                form.classList.add('is-submitting');
                if (loadingBar) {
                    loadingBar.setAttribute('aria-hidden', 'false');
                }
                if (button) {
                    button.textContent = button.dataset.loadingText || 'Inscription en cours...';
                    button.disabled = true;
                }
            });
        });
    </script>
</body>
</html>
