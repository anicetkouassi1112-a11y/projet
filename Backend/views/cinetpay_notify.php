<?php

require_once __DIR__ . '/../cinetpay.php';

http_response_code(200);

// Health check pour CinetPay
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo 'OK';
    exit;
}

// Validation de la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'OK';
    exit;
}

// Validation et nettoyage du transaction ID
$transactionId = trim((string) ($_POST['cpm_trans_id'] ?? ''));
if ($transactionId === '' || !preg_match('/^CP[0-9A-Za-z]{20,}$/', $transactionId)) {
    error_log('CinetPay notify invalid transaction ID format');
    echo 'OK';
    exit;
}

// Validation de la taille du payload POST
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 10240) { // 10KB max pour notification
    error_log('CinetPay notify payload too large');
    echo 'OK';
    exit;
}

try {
    $transaction = cinetpayFindTransaction($transactionId);
    if (!$transaction) {
        error_log('CinetPay notify unknown transaction: ' . $transactionId);
        echo 'OK';
        exit;
    }

    // Filtrer et nettoyer les données POST avant stockage
    $safePost = [];
    $allowedFields = [
        'cpm_trans_id',
        'cpm_amount',
        'cpm_currency',
        'cpm_custom',
        'cpm_designation',
        'cpm_prefix',
        'cpm_language',
        'cpm_version',
        'cpm_payment_method',
        'cpm_payment_date',
        'cpm_result',
        'cpm_error_message',
        'cpm_phone_number',
        'signature',
        'status',
    ];
    
    foreach ($allowedFields as $field) {
        if (isset($_POST[$field])) {
            $safePost[$field] = is_string($_POST[$field]) ? trim($_POST[$field]) : $_POST[$field];
        }
    }

    cinetpayUpdateTransaction($transactionId, [
        'notification_payload' => json_encode($safePost, JSON_UNESCAPED_SLASHES),
    ]);

    $verification = cinetpayVerifyTransaction($transactionId);
    $body = is_array($verification['body'] ?? null) ? $verification['body'] : [];
    $data = is_array($body['data'] ?? null) ? $body['data'] : [];
    $status = (string) ($data['status'] ?? ($body['message'] ?? 'UNKNOWN'));

    cinetpayUpdateTransaction($transactionId, [
        'status' => $status,
        'verified_payload' => json_encode($body, JSON_UNESCAPED_SLASHES),
    ]);

    if ($status === 'ACCEPTED') {
        cinetpayMarkInscriptionState((int) ($transaction['id_inscrit'] ?? 0), 'inscrit');
    }

    echo 'OK';
} catch (PDOException $e) {
    error_log('CinetPay notify database error: ' . $e->getMessage());
    echo 'OK';
} catch (Throwable $e) {
    error_log('CinetPay notify error: ' . $e->getMessage());
    echo 'OK';
}
