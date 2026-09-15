<?php declare(strict_types=1);

require_once __DIR__ . '/../utilitaire.php';
require_once __DIR__ . '/../../admin/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$idInscrit = filter_var($_GET['inscrit_id'] ?? ($_SESSION['last_inscrit_id'] ?? null), FILTER_VALIDATE_INT);
if (!$idInscrit) {
    exit('Inscription introuvable.');
}

if (!canAccessPublicInscrit((int) $idInscrit)) {
    http_response_code(403);
    exit('Acces non autorise.');
}

$inscrit = getInscritById((int) $idInscrit);
if (!$inscrit) {
    exit('Inscription introuvable.');
}

$etat = (string) ($inscrit['etat'] ?? 'En attente');
$isValidated = $etat === 'inscrit';
$dateDocument = date('d/m/Y H:i:s');
$fullName = trim((string) ($inscrit['nom'] ?? '') . ' ' . (string) ($inscrit['prenom'] ?? ''));

$logoPath = __DIR__ . '/../Assets/img/logo_patro.jpeg';
$logoBase64 = is_file($logoPath) ? base64_encode((string) file_get_contents($logoPath)) : '';
$documentTitle = 'Fiche d\'inscription';
$statusText = $isValidated ? 'Inscrit' : 'En attente de validation';
$statusClass = $isValidated ? 'status-ok' : 'status-waiting';
$documentReference = !empty($inscrit['identifiant'])
    ? (string) $inscrit['identifiant']
    : 'PATRO-' . str_pad((string) $idInscrit, 5, '0', STR_PAD_LEFT);
$montantInscription = (int) ($inscrit['montant_inscription'] ?? 0);
$prixTeeShirt = (int) ($inscrit['prix_tee_shirt'] ?? 0);
$montantTotal = $montantInscription + $prixTeeShirt;

$rows = [
    'Identifiant' => $documentReference,
    'Nom complet' => $fullName,
    'Date de naissance' => (string) ($inscrit['date_naissance'] ?? ''),
    'Genre' => (string) ($inscrit['genre'] ?? ''),
    'Section' => (string) ($inscrit['section'] ?? ''),
    'Telephone' => (string) ($inscrit['tel'] ?? ''),
    'Adresse' => (string) ($inscrit['adresse'] ?? ''),
    'Montant inscription' => formatFcfa($montantInscription),
    'Tee-shirt' => $prixTeeShirt > 0
        ? formatFcfa($prixTeeShirt) . ' - Taille ' . (string) ($inscrit['taille_tee_shirt'] ?? '')
        : 'Non',
    'Total a regler' => formatFcfa($montantTotal),
    'Annee' => (string) ($inscrit['annee'] ?? ''),
    'Session' => sessionTypeLabel($inscrit['type_session'] ?? null),
    'Statut' => $statusText,
];

$tableRows = '';
foreach ($rows as $label => $value) {
    $tableRows .= '<tr><td class="label">' . e($label) . '</td><td class="value">' . e($value !== '' ? $value : '-') . '</td></tr>';
}

$notice = $isValidated
    ? 'Cette fiche confirme l\'inscription definitive de la personne ci-dessus.'
    : 'Cette fiche confirme l\'enregistrement de la personne ci-dessus.';

$html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; size: A4 portrait; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #1f2933;
            background: #f4f7fb;
            font-size: 10.5px;
            line-height: 1.3;
        }
        .document {
            width: 93%;
            background: #ffffff;
            padding: 26px 32px 20px;
            height: 95.9%;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .topline {
            height: 5px;
            background: #0b5cad;
            margin: -26px -32px 18px;
        }
        .header {
            width: 100%;
            margin-bottom: 14px;
            border-collapse: collapse;
        }
        .header td {
            vertical-align: middle;
        }
        .logo-cell {
            width: 120px;
            text-align: left;
        }
        .title-cell {
            text-align: left;
        }
        .logo {
            width: 85px;
            height: 85px;
            display: block;
            margin: 0;
            object-fit: contain;
        }
        .eyebrow {
            margin: 0 0 4px;
            color: #627d98;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }
        h1 {
            margin: 0;
            color: #0b5cad;
            font-size: 22px;
            font-weight: bold;
            text-align: left;
        }
        .subtitle {
            margin: 5px 0 0;
            color: #52606d;
            font-size: 10.5px;
        }
        .meta {
            width: 100%;
            margin: 14px 0;
            border-collapse: separate;
            border-spacing: 0;
            background: #f8fbff;
        }
        .meta td {
            width: 33.33%;
            padding: 9px 14px;
            text-align: center;
            vertical-align: top;
        }
        .meta .meta-label {
            display: block;
            color: #718096;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .7px;
            margin-bottom: 3px;
        }
        .meta .meta-value {
            display: block;
            color: #243b53;
            font-size: 11px;
            font-weight: bold;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 16px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .status-ok {
            color: #0f5132;
            background: #dff6e8;
        }
        .status-waiting {
            color: #7a4f01;
            background: #fff1cc;
        }
        .notice {
            margin: 0 0 14px;
            padding: 10px 16px;
            color: #1e3a5f;
            background: #eef6ff;
            font-size: 10.5px;
            text-align: center;
        }
        .section-title {
            margin: 0 0 7px;
            color: #102a43;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .6px;
        }
        .info-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 4px;
            margin-top: 0;
        }
        .info-table td {
            padding: 7px 12px;
            background: #f8fafc;
            vertical-align: top;
        }
        .info-table .label {
            width: 35%;
            color: #52606d;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .4px;
        }
        .info-table .value {
            color: #102a43;
            font-size: 11px;
            font-weight: bold;
        }
        .footer {
            margin-top: 30%;
            padding-top: 8px;
            color: #7b8794;
            font-size: 9px;
            text-align: center;
            margin-left: -32px;
        }
    </style>
</head>
<body>
    <div class="document">
        <div class="topline"></div>
        <table class="header">
            <tr>
                <td class="logo-cell">' .
                    ($logoBase64 !== '' ? '<img src="data:image/jpeg;base64,' . $logoBase64 . '" class="logo" alt="Logo">' : '') .
                '</td>
                <td class="title-cell">
                    <p class="eyebrow">Patronage Saint Joseph</p>
                    <h1>' . e($documentTitle) . '</h1>
                    <p class="subtitle">Document officiel de suivi d\'inscription</p>
                </td>
            </tr>
        </table>
        <table class="meta">
            <tr>
                <td>
                    <span class="meta-label">Référence</span>
                    <span class="meta-value">' . e($documentReference) . '</span>
                </td>
                <td>
                    <span class="meta-label">Date d\'emission</span>
                    <span class="meta-value">' . e($dateDocument) . '</span>
                </td>
                <td>
                    <span class="meta-label">Statut</span>
                    <span class="status-badge ' . e($statusClass) . '">' . e($statusText) . '</span>
                </td>
            </tr>
        </table>
        <div class="notice">' . e($notice) . '</div>
        <p class="section-title">Informations de l\'inscrit</p>
        <table class="info-table">' . $tableRows . '</table>
        <div class="footer">Document genere automatiquement - Référence ' . e($documentReference) . '</div>
    </div>
</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $fullName) ?: 'inscription';
$dompdf->stream('Fiche_' . $safeName . '_' . time() . '.pdf', ['Attachment' => true]);