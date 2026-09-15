<?php

require_once __DIR__ . '/../cinetpay.php';

$idInscrit = filter_var($_GET['inscrit_id'] ?? $_POST['inscrit_id'] ?? ($_SESSION['last_inscrit_id'] ?? null), FILTER_VALIDATE_INT);
if (!$idInscrit || !canAccessPublicInscrit((int) $idInscrit)) {
    redirectTo(app_url('public/inscription.php'));
}

$inscrit = appContainer()->get(\Patro\Domain\Inscription\Repository\InscriptionRepository::class)->findById((int) $idInscrit);
if (!$inscrit) {
    redirectTo(app_url('public/inscription.php'));
}

$message = '';
$alertType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $message = 'Session expiree. Rechargez la page.';
        $alertType = 'danger';
    } else {
        $result = cinetpayInitiatePayment((int) $idInscrit);
        if (!empty($result['success']) && !empty($result['payment_url'])) {
            cinetpayRedirectToCheckout((string) $result['payment_url']);
        }
        $message = (string) ($result['message'] ?? 'Le paiement n a pas pu etre lance.');
        $alertType = 'warning';
    }
}

$montantInscription = (int) ($inscrit['montant_inscription'] ?? 0);
$prixTeeShirt = (int) ($inscrit['prix_tee_shirt'] ?? 0);
$montantTotal = $montantInscription + $prixTeeShirt;
$fullName = trim((string) ($inscrit['nom'] ?? '') . ' ' . (string) ($inscrit['prenom'] ?? ''));
$reference = !empty($inscrit['identifiant']) ? (string) $inscrit['identifiant'] : 'PATRO-' . (int) $idInscrit;
$pageTitle = 'Paiement CinetPay';
$assetBase = app_url('Backend/Assets');
$disabled = !cinetpayConfigured();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?></title>
    <link href="<?= e($assetBase) ?>/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($assetBase) ?>/css/inscriptin.css">
    <style>
        .payment-status-box {
            margin: 18px 0;
            padding: 16px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #d9e2ec;
        }

        .payment-summary {
            display: grid;
            gap: 10px;
            margin: 22px 0;
        }

        .payment-summary div {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 12px 14px;
            border: 1px solid #d9e2ec;
            border-radius: 6px;
            background: #fff;
        }

        .payment-summary dt {
            color: #607086;
            font-weight: 800;
        }

        .payment-summary dd {
            margin: 0;
            text-align: right;
            font-weight: 900;
        }
    </style>
</head>
<body class="public-page">
    <main class="registration-card confirmation-card">
        <span class="status-pill status-pill-warning">CinetPay</span>
        <h1>Paiement de l'inscription</h1>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= e($alertType) ?>" role="alert"><?= e($message) ?></div>
        <?php endif; ?>

        <?php if (!cinetpayEnabled()): ?>
            <div class="alert alert-info" role="alert">
                Le paiement en ligne est desactive pour le moment. La validation finale se fait en presentiel.
            </div>
        <?php elseif (!cinetpayConfigured()): ?>
            <div class="alert alert-warning" role="alert">
                CinetPay est active, mais les identifiants de production ne sont pas encore configures.
            </div>
        <?php endif; ?>

        <div class="payment-status-box">
            Vous serez redirige vers le guichet securise CinetPay. Le paiement sera disponible seulement quand le compte marchand sera configure.
        </div>

        <dl class="payment-summary">
            <div><dt>Inscrit</dt><dd><?= e($fullName !== '' ? $fullName : '-') ?></dd></div>
            <div><dt>Reference</dt><dd><?= e($reference) ?></dd></div>
            <div><dt>Inscription</dt><dd><?= e(formatFcfa($montantInscription)) ?></dd></div>
            <div><dt>Tee-shirt</dt><dd><?= e($prixTeeShirt > 0 ? formatFcfa($prixTeeShirt) : 'Non') ?></dd></div>
            <div><dt>Total</dt><dd><?= e(formatFcfa($montantTotal)) ?></dd></div>
        </dl>

        <form method="post" action="cinetpay.php?<?= e(http_build_query(['inscrit_id' => (int) $idInscrit])) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <button type="submit" class="btn btn-primary btn-block form-submit-spaced" <?= $disabled ? 'disabled' : '' ?>>
                Payer avec CinetPay
            </button>
            <a class="btn btn-outline-secondary btn-block form-submit-spaced" href="<?= e(app_url('public/auth/confirmation_enregistrement.php') . '?' . http_build_query(['inscrit_id' => (int) $idInscrit])) ?>">
                Retour
            </a>
        </form>
    </main>
</body>
</html>
