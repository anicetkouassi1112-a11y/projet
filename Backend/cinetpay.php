<?php

declare(strict_types=1);

require_once __DIR__ . '/utilitaire.php';

function cinetpayService(): \Patro\Paiement\CinetPayService
{
    return appContainer()->get(\Patro\Paiement\CinetPayService::class);
}

function cinetpayEnabled(): bool
{
    return cinetpayService()->isEnabled();
}

function cinetpayConfigured(): bool
{
    return cinetpayService()->isConfigured();
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

function cinetpayUpdateTransaction(string $transactionId, array $values): void
{
    cinetpayService()->updateTransaction($transactionId, $values);
}

function cinetpayFindTransaction(string $transactionId): array
{
    return cinetpayService()->findTransaction($transactionId);
}

function cinetpayInitiatePayment(int $idInscrit): array
{
    return cinetpayService()->initiatePayment($idInscrit);
}

function cinetpayVerifyTransaction(string $transactionId): array
{
    return cinetpayService()->verifyTransaction($transactionId);
}
