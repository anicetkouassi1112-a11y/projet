<?php
require_once __DIR__ . '/../../Backend/utilitaire.php';
require_once __DIR__ . '/../../Backend/cinetpay.php';

$idInscrit = filter_var($_GET['inscrit_id'] ?? $_POST['inscrit_id'] ?? ($_SESSION['last_inscrit_id'] ?? null), FILTER_VALIDATE_INT);
if (!$idInscrit) {
    redirectTo(app_url('public/inscription.php'));
}

if (!canAccessPublicInscrit((int) $idInscrit)) {
    redirectTo(app_url('public/inscription.php'));
}

$inscrit = appContainer()->get(\Patro\Domain\Inscription\Repository\InscriptionRepository::class)->findById((int) $idInscrit);
if (!$inscrit || empty($inscrit['etat'])) {
    redirectTo(app_url('public/inscription.php'));
}

$anneeInscription = (int) ($inscrit['annee'] ?? date('Y'));
$age = '';
if (!empty($inscrit['date_naissance'])) {
    $computedAge = \Patro\Domain\Inscription\AgeCalculator::calculate((string) $inscrit['date_naissance'], $anneeInscription);
    $age = $computedAge === null ? 'N/A' : (string) $computedAge;
}

$fullName = trim((string) ($inscrit['nom'] ?? '') . ' ' . (string) ($inscrit['prenom'] ?? ''));
$montantInscription = (int) ($inscrit['montant_inscription'] ?? 0);
$prixTeeShirt = (int) ($inscrit['prix_tee_shirt'] ?? 0);
$montantTotal = $montantInscription + $prixTeeShirt;
$reference = !empty($inscrit['identifiant']) ? (string) $inscrit['identifiant'] : 'PATRO-' . str_pad((string) $idInscrit, 5, '0', STR_PAD_LEFT);
$validationSoumiseAt = !empty($inscrit['validation_soumise_at']) ? date('d/m/Y H:i', strtotime((string) $inscrit['validation_soumise_at'])) : '';
$submissionState = ((string) ($inscrit['etat'] ?? '') === 'inscrit') ? 'Inscription validee' : ($validationSoumiseAt !== '' ? 'Demande transmise' : 'En attente de validation');

$pageTitle = 'Confirmation d\'inscription';
$assetBase = app_url('Backend/Assets');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?></title>
    <link href="<?= e($assetBase) ?>/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($assetBase) ?>/css/inscriptin.css">
    <?php renderFaviconTags($assetBase); ?>
</head>
<body class="public-page">
    <main class="registration-card confirmation-card">
        <span class="status-pill status-pill-warning"><?= e($submissionState) ?></span>
        <h1>Inscription enregistrée</h1>

        <div class="alert alert-info confirmation-message" role="alert">
            Votre inscription est bien enregistrée. La validation finale se fera en présentiel à l'administration.
        </div>

        <dl class="confirmation-summary">
            <?php if (!empty($inscrit['identifiant'])): ?>
            <div>
                <dt>Identifiant</dt>
                <dd><?= e((string) $inscrit['identifiant']) ?></dd>
            </div>
            <?php endif; ?>
            <div>
                <dt>Nom et prénom</dt>
                <dd><?= e($fullName) ?></dd>
            </div>
            <div>
                <dt>Age</dt>
                <dd><?= e($age) ?></dd>
            </div>
            <div>
                <dt>Genre</dt>
                <dd><?= e((string) ($inscrit['genre'] ?? '')) ?></dd>
            </div>
            <div>
                <dt>Session</dt>
                <dd><?= e(sessionTypeLabel($inscrit['type_session'] ?? null)) ?></dd>
            </div>
            <div>
                <dt>Montant inscription</dt>
                <dd><?= e(formatFcfa($montantInscription)) ?></dd>
            </div>
            <?php if ($prixTeeShirt > 0): ?>
            <div>
                <dt>Tee-shirt</dt>
                <dd><?= e(formatFcfa($prixTeeShirt)) ?> - Taille <?= e((string) ($inscrit['taille_tee_shirt'] ?? '')) ?></dd>
            </div>
            <?php endif; ?>
            <div>
                <dt>Total</dt>
                <dd><?= e(formatFcfa($montantTotal)) ?></dd>
            </div>
            <div>
                <dt>Référence</dt>
                <dd><?= e($reference) ?></dd>
            </div>
            <div>
                <dt>Validation</dt>
                <dd><?= e($validationSoumiseAt !== '' ? 'Soumise le ' . $validationSoumiseAt : 'à transmettre à l\'administration') ?></dd>
            </div>
        </dl>

        <div class="invoice-actions">
            <?php if (cinetpayEnabled()): ?>
            <a class="btn btn-primary btn-block form-submit-spaced" href="<?= e(app_url('public/cinetpay.php') . '?' . http_build_query(['inscrit_id' => $idInscrit])) ?>">
                Payer avec CinetPay
            </a>
            <?php endif; ?>
            <a class="btn btn-primary btn-block form-submit-spaced" href="<?= e(app_url('public/generer_pdf.php') . '?' . http_build_query(['inscrit_id' => $idInscrit])) ?>">
                Generer le PDF
            </a>
        </div>
    </main>
</body>
</html>
