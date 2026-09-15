<?php

declare(strict_types=1);

require_once __DIR__ . '/utilitaire.php';

function cinetpayEnabled(): bool
{
    if (class_exists('\Patro\Paiement\CinetPayService')) {
        $service = new \Patro\Paiement\CinetPayService();
        return $service->isEnabled();
    }
    
    return app_bool('CINETPAY_ENABLED', false);
}

function cinetpayConfigured(): bool
{
    if (class_exists('\Patro\Paiement\CinetPayService')) {
        $service = new \Patro\Paiement\CinetPayService();
        return $service->isConfigured();
    }
    
    return cinetpayEnabled()
        && trim((string) app_env('CINETPAY_APIKEY', '')) !== ''
        && trim((string) app_env('CINETPAY_SITE_ID', '')) !== '';
}

function cinetpayCurrency(): string
{
    $currency = strtoupper(trim((string) app_env('CINETPAY_CURRENCY', 'XOF')));
    return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'XOF';
}

function cinetpayChannels(): string
{
    $channels = strtoupper(trim((string) app_env('CINETPAY_CHANNELS', 'MOBILE_MONEY')));
    return in_array($channels, ['ALL', 'MOBILE_MONEY', 'CREDIT_CARD', 'WALLET'], true) ? $channels : 'MOBILE_MONEY';
}

function cinetpayEndpoint(string $path = ''): string
{
    $base = rtrim((string) app_env('CINETPAY_API_BASE_URL', 'https://api-checkout.cinetpay.com/v2'), '/');
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function cinetpayTransactionId(int $idInscrit): string
{
    return 'CP' . date('YmdHis') . $idInscrit . strtoupper(bin2hex(random_bytes(4)));
}

function cinetpayCleanDescription(string $value): string
{
    $value = preg_replace('/[^a-zA-Z0-9 ]+/', ' ', $value) ?? '';
    $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';
    return substr($value !== '' ? $value : 'Inscription Patro', 0, 120);
}

function cinetpayRedirectToCheckout(string $url): void
{
    $parts = parse_url($url);
    if ($parts === false) {
        error_log('CinetPay checkout redirect blocked: invalid URL');
        redirectTo(app_url('public/inscription.php'));
    }
    
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));

    if ($scheme !== 'https' || !in_array($host, ['checkout.cinetpay.com', 'secure.cinetpay.com'], true)) {
        error_log('CinetPay checkout redirect blocked: ' . $url);
        redirectTo(app_url('public/inscription.php'));
    }

    // Validation supplémentaire : pas de fragments arbitraires
    if (!empty($parts['fragment'])) {
        error_log('CinetPay checkout redirect blocked: fragment not allowed');
        redirectTo(app_url('public/inscription.php'));
    }

    header('Location: ' . $url);
    exit;
}

function cinetpayHttpPostJson(string $url, array $payload): array
{
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        return ['ok' => false, 'http_code' => 0, 'body' => [], 'error' => 'Payload invalide.'];
    }

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: ProjetPatro/1.0',
    ];

    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $raw = curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);
        $raw = file_get_contents($url, false, $context);
        $error = $raw === false ? 'Requete HTTP impossible.' : '';
        $httpCode = 0;
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
                $httpCode = (int) $matches[1];
                break;
            }
        }
    }

    $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($decoded)) {
        $decoded = [];
    }

    return [
        'ok' => $error === '' && $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'body' => $decoded,
        'error' => $error,
    ];
}

function cinetpayEnsureTransactionsTable(PDO $conn): void
{
    $conn->exec(
        'CREATE TABLE IF NOT EXISTS cinetpay_transactions (
            id INT NOT NULL AUTO_INCREMENT,
            transaction_id VARCHAR(80) NOT NULL,
            id_inscription INT NOT NULL,
            amount INT UNSIGNED NOT NULL,
            currency CHAR(3) NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT "INITIATED",
            payment_token VARCHAR(255) DEFAULT NULL,
            payment_url TEXT DEFAULT NULL,
            request_payload LONGTEXT DEFAULT NULL,
            response_payload LONGTEXT DEFAULT NULL,
            notification_payload LONGTEXT DEFAULT NULL,
            verified_payload LONGTEXT DEFAULT NULL,
            failure_reason TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_cinetpay_transaction_id (transaction_id),
            KEY idx_cinetpay_inscription (id_inscription),
            KEY idx_cinetpay_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );
}

function cinetpayStoreTransaction(PDO $conn, array $transaction): void
{
    cinetpayEnsureTransactionsTable($conn);
    $stmt = $conn->prepare(
        'INSERT INTO cinetpay_transactions
            (transaction_id, id_inscription, amount, currency, status, request_payload)
         VALUES
            (:transaction_id, :id_inscription, :amount, :currency, :status, :request_payload)'
    );
    $stmt->execute([
        ':transaction_id' => $transaction['transaction_id'],
        ':id_inscription' => $transaction['id_inscrit'],
        ':amount' => $transaction['amount'],
        ':currency' => $transaction['currency'],
        ':status' => $transaction['status'],
        ':request_payload' => $transaction['request_payload'],
    ]);
}

function cinetpayUpdateTransaction(PDO $conn, string $transactionId, array $values): void
{
    $allowed = [
        'status',
        'payment_token',
        'payment_url',
        'response_payload',
        'notification_payload',
        'verified_payload',
        'failure_reason',
    ];
    $sets = [];
    $params = [':transaction_id' => $transactionId];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $values)) {
            $sets[] = $field . ' = :' . $field;
            $params[':' . $field] = $values[$field];
        }
    }

    if (!$sets) {
        return;
    }

    $stmt = $conn->prepare('UPDATE cinetpay_transactions SET ' . implode(', ', $sets) . ' WHERE transaction_id = :transaction_id');
    $stmt->execute($params);
}

function cinetpayFindTransaction(string $transactionId, ?PDO $conn = null): array
{
    $conn = $conn ?: getConnection();
    cinetpayEnsureTransactionsTable($conn);
    $stmt = $conn->prepare('SELECT t.*, t.id_inscription AS id_inscrit FROM cinetpay_transactions t WHERE t.transaction_id = :transaction_id LIMIT 1');
    $stmt->execute([':transaction_id' => $transactionId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function cinetpayInitiatePayment(int $idInscrit): array
{
    if (class_exists('\Patro\Paiement\CinetPayService')) {
        $service = new \Patro\Paiement\CinetPayService();
        return $service->initiatePayment($idInscrit);
    }
    
    if (!cinetpayEnabled()) {
        return ['success' => false, 'message' => 'Le paiement en ligne est desactive.'];
    }

    if (!cinetpayConfigured()) {
        return ['success' => false, 'message' => 'CinetPay n est pas encore configure.'];
    }

    $inscrit = getInscritById($idInscrit);
    if (!$inscrit) {
        return ['success' => false, 'message' => 'Inscription introuvable.'];
    }

    $amount = (int) ($inscrit['montant_inscription'] ?? 0) + (int) ($inscrit['prix_tee_shirt'] ?? 0);
    $currency = cinetpayCurrency();
    if ($amount <= 0 || ($currency !== 'USD' && $amount % 5 !== 0)) {
        return ['success' => false, 'message' => 'Montant invalide pour CinetPay.'];
    }

    $transactionId = cinetpayTransactionId($idInscrit);
    $fullName = trim((string) ($inscrit['nom'] ?? '') . ' ' . (string) ($inscrit['prenom'] ?? ''));
    $nameParts = preg_split('/\s+/', $fullName, 2) ?: [];
    $customerName = $nameParts[0] ?? 'Client';
    $customerSurname = $nameParts[1] ?? 'Patro';
    $baseUrl = app_base_url();
    $payload = [
        'apikey' => (string) app_env('CINETPAY_APIKEY', ''),
        'site_id' => (string) app_env('CINETPAY_SITE_ID', ''),
        'transaction_id' => $transactionId,
        'amount' => $amount,
        'currency' => $currency,
        'description' => cinetpayCleanDescription('Inscription Patro ' . (!empty($inscrit['identifiant']) ? $inscrit['identifiant'] : $idInscrit)),
        'notify_url' => $baseUrl . '/public/cinetpay_notify.php',
        'return_url' => $baseUrl . '/public/cinetpay_return.php?' . http_build_query(['transaction_id' => $transactionId]),
        'channels' => cinetpayChannels(),
        'metadata' => (string) $idInscrit,
        'lang' => 'FR',
        'customer_id' => (string) $idInscrit,
        'customer_name' => cinetpayCleanDescription($customerName),
        'customer_surname' => cinetpayCleanDescription($customerSurname),
        'customer_phone_number' => (string) ($inscrit['tel'] ?? ''),
        'invoice_data' => [
            'Reference' => !empty($inscrit['identifiant']) ? (string) $inscrit['identifiant'] : 'PATRO-' . $idInscrit,
            'Session' => sessionTypeLabel($inscrit['type_session'] ?? null),
            'Total' => formatFcfa($amount),
        ],
    ];

    $conn = getConnection();
    try {
        cinetpayStoreTransaction($conn, [
            'transaction_id' => $transactionId,
            'id_inscrit' => $idInscrit,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'INITIATED',
            'request_payload' => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ]);
    } catch (PDOException $e) {
        error_log('CinetPay store transaction error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Impossible de preparer le paiement.'];
    }

    $response = cinetpayHttpPostJson(cinetpayEndpoint('payment'), $payload);
    $body = is_array($response['body'] ?? null) ? $response['body'] : [];
    $data = is_array($body['data'] ?? null) ? $body['data'] : [];

    $update = [
        'status' => (string) ($body['message'] ?? 'INIT_ERROR'),
        'response_payload' => json_encode($body, JSON_UNESCAPED_SLASHES),
    ];
    if (!empty($data['payment_token'])) {
        $update['payment_token'] = (string) $data['payment_token'];
    }
    if (!empty($data['payment_url'])) {
        $update['payment_url'] = (string) $data['payment_url'];
    }
    if (!$response['ok'] || (string) ($body['code'] ?? '') !== '201') {
        $update['failure_reason'] = (string) ($body['description'] ?? $response['error'] ?? 'Erreur CinetPay');
    }
    cinetpayUpdateTransaction($conn, $transactionId, $update);

    if ($response['ok'] && (string) ($body['code'] ?? '') === '201' && !empty($data['payment_url'])) {
        return [
            'success' => true,
            'payment_url' => (string) $data['payment_url'],
            'transaction_id' => $transactionId,
        ];
    }

    error_log('CinetPay init failed: HTTP ' . (int) ($response['http_code'] ?? 0) . ' ' . json_encode($body));
    return ['success' => false, 'message' => 'Le paiement n a pas pu etre initialise.'];
}

function cinetpayVerifyTransaction(string $transactionId): array
{
    if (class_exists('\Patro\Paiement\CinetPayService')) {
        $service = new \Patro\Paiement\CinetPayService();
        return $service->verifyTransaction($transactionId);
    }
    
    if (!cinetpayConfigured()) {
        return ['success' => false, 'message' => 'CinetPay non configure.'];
    }

    $payload = [
        'apikey' => (string) app_env('CINETPAY_APIKEY', ''),
        'site_id' => (string) app_env('CINETPAY_SITE_ID', ''),
        'transaction_id' => $transactionId,
    ];
    $response = cinetpayHttpPostJson(cinetpayEndpoint('payment/check'), $payload);
    $body = is_array($response['body'] ?? null) ? $response['body'] : [];
    return [
        'success' => $response['ok'] && (string) ($body['code'] ?? '') === '00',
        'body' => $body,
    ];
}

