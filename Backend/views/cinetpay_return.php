<?php

require_once __DIR__ . '/../cinetpay.php';

$transactionId = requestTextParam('transaction_id', 80);

// Validation du format du transaction ID
if ($transactionId !== '' && !preg_match('/^CP[0-9A-Za-z]{20,}$/', $transactionId)) {
    error_log('CinetPay return invalid transaction ID format: ' . $transactionId);
    $transactionId = '';
}

$transaction = $transactionId !== '' ? cinetpayFindTransaction($transactionId) : [];

if ($transaction) {
    $verification = cinetpayVerifyTransaction($transactionId);
    $body = is_array($verification['body'] ?? null) ? $verification['body'] : [];
    $data = is_array($body['data'] ?? null) ? $body['data'] : [];
    $status = (string) ($data['status'] ?? ($body['message'] ?? ($transaction['status'] ?? 'UNKNOWN')));
    cinetpayUpdateTransaction($transactionId, [
        'status' => $status,
        'verified_payload' => json_encode($body, JSON_UNESCAPED_SLASHES),
    ]);
}

$idInscrit = (int) ($transaction['id_inscrit'] ?? ($_SESSION['last_inscrit_id'] ?? 0));
if ($idInscrit > 0) {
    redirectTo(app_url('public/auth/confirmation_enregistrement.php') . '?' . http_build_query(['inscrit_id' => $idInscrit]));
}

redirectTo(app_url('public/inscription.php'));
