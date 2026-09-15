<?php

declare(strict_types=1);

namespace Patro\Paiement;

use Patro\Config\Environment;
use Patro\Domain\Inscription\Repository\InscriptionRepository;
use Patro\Domain\Paiement\Repository\CinetPayTransactionRepository;
use PDOException;

/**
 * Service de gestion des paiements CinetPay
 */
class CinetPayService
{
    private const ALLOWED_HOSTS = ['checkout.cinetpay.com', 'secure.cinetpay.com'];
    private const ALLOWED_CHANNELS = ['ALL', 'MOBILE_MONEY', 'CREDIT_CARD', 'WALLET'];
    private const MAX_CALLBACK_URLS_AGE = 3600; // 1 heure

    public function __construct(
        private InscriptionRepository $inscriptionRepository,
        private CinetPayTransactionRepository $transactionRepository
    )
    {
    }

    /**
     * Vérifie si CinetPay est activé
     */
    public function isEnabled(): bool
    {
        return Environment::bool('CINETPAY_ENABLED', false);
    }

    /**
     * Vérifie si CinetPay est configuré
     */
    public function isConfigured(): bool
    {
        return $this->isEnabled()
            && trim((string) Environment::get('CINETPAY_APIKEY', '')) !== ''
            && trim((string) Environment::get('CINETPAY_SITE_ID', '')) !== '';
    }

    /**
     * Récupère la devise CinetPay
     */
    public function getCurrency(): string
    {
        $currency = strtoupper(trim((string) Environment::get('CINETPAY_CURRENCY', 'XOF')));
        return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'XOF';
    }

    /**
     * Récupère les canaux de paiement CinetPay
     */
    public function getChannels(): string
    {
        $channels = strtoupper(trim((string) Environment::get('CINETPAY_CHANNELS', 'MOBILE_MONEY')));
        return in_array($channels, self::ALLOWED_CHANNELS, true) ? $channels : 'MOBILE_MONEY';
    }

    /**
     * Récupère l'endpoint API CinetPay
     */
    public function getEndpoint(string $path = ''): string
    {
        $base = rtrim((string) Environment::get('CINETPAY_API_BASE_URL', 'https://api-checkout.cinetpay.com/v2'), '/');
        return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    /**
     * Génère un ID de transaction unique
     */
    public function generateTransactionId(int $idInscrit): string
    {
        return 'CP' . date('YmdHis') . $idInscrit . strtoupper(bin2hex(random_bytes(4)));
    }

    /**
     * Nettoie une description pour CinetPay
     */
    public function cleanDescription(string $value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9 ]+/', ' ', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';
        return substr($value !== '' ? $value : 'Inscription Patro', 0, 120);
    }

    /**
     * Redirection sécurisée vers le checkout CinetPay
     */
    public function redirectToCheckout(string $url): void
    {
        $parts = parse_url($url);
        if ($parts === false) {
            error_log('CinetPay checkout redirect blocked: invalid URL');
            $this->safeRedirect('/public/inscription.php');
        }
        
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https' || !in_array($host, self::ALLOWED_HOSTS, true)) {
            error_log('CinetPay checkout redirect blocked: ' . $url);
            $this->safeRedirect('/public/inscription.php');
        }

        if (!empty($parts['fragment'])) {
            error_log('CinetPay checkout redirect blocked: fragment not allowed');
            $this->safeRedirect('/public/inscription.php');
        }

        header('Location: ' . $url);
        exit;
    }

    /**
     * Requête HTTP POST JSON vers CinetPay
     */
    public function httpPostJson(string $url, array $payload): array
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
            return $this->curlRequest($url, $body, $headers);
        }

        return $this->streamRequest($url, $body, $headers);
    }

    /**
     * Initialise un paiement CinetPay
     */
    public function initiatePayment(int $idInscrit): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'Le paiement en ligne est desactive.'];
        }

        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'CinetPay n est pas encore configure.'];
        }

        $inscrit = $this->inscriptionRepository->findById($idInscrit);
        if (!$inscrit) {
            return ['success' => false, 'message' => 'Inscription introuvable.'];
        }

        $amount = (int) ($inscrit['montant_inscription'] ?? 0) + (int) ($inscrit['prix_tee_shirt'] ?? 0);
        $currency = $this->getCurrency();
        if ($amount <= 0 || ($currency !== 'USD' && $amount % 5 !== 0)) {
            return ['success' => false, 'message' => 'Montant invalide pour CinetPay.'];
        }

        $transactionId = $this->generateTransactionId($idInscrit);
        $payload = $this->buildPaymentPayload($idInscrit, $transactionId, $amount, $currency, $inscrit);

        try {
            $this->transactionRepository->create([
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

        $response = $this->httpPostJson($this->getEndpoint('payment'), $payload);
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
        $this->transactionRepository->update($transactionId, $update);

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

    /**
     * Vérifie une transaction CinetPay
     */
    public function verifyTransaction(string $transactionId): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'CinetPay non configure.'];
        }

        $payload = [
            'apikey' => (string) Environment::get('CINETPAY_APIKEY', ''),
            'site_id' => (string) Environment::get('CINETPAY_SITE_ID', ''),
            'transaction_id' => $transactionId,
        ];
        $response = $this->httpPostJson($this->getEndpoint('payment/check'), $payload);
        $body = is_array($response['body'] ?? null) ? $response['body'] : [];
        return [
            'success' => $response['ok'] && (string) ($body['code'] ?? '') === '00',
            'body' => $body,
        ];
    }

    /** @return array<string,mixed> */
    public function findTransaction(string $transactionId): array
    {
        return $this->transactionRepository->find($transactionId);
    }

    /** @param array<string,mixed> $values */
    public function updateTransaction(string $transactionId, array $values): void
    {
        $this->transactionRepository->update($transactionId, $values);
    }

    public function markInscriptionState(int $inscriptionId, string $state): void
    {
        $this->inscriptionRepository->updateState($inscriptionId, $state);
    }

    // Méthodes privées

    private function curlRequest(string $url, string $body, array $headers): array
    {
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

    private function streamRequest(string $url, string $body, array $headers): array
    {
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

    private function safeRedirect(string $path): void
    {
        $baseUrl = $this->getBaseUrl();
        header('Location: ' . $baseUrl . $path);
        exit;
    }

    private function getBaseUrl(): string
    {
        $configuredUrl = trim((string) Environment::get('APP_URL', ''));
        if ($configuredUrl !== '') {
            return rtrim($configuredUrl, '/');
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $basePath = trim(preg_replace('#/(Backend|admin|public)$#', '', $scriptDir), '/');

        return rtrim($scheme . '://' . $host . ($basePath ? '/' . $basePath : ''), '/');
    }

    private function buildPaymentPayload(int $idInscrit, string $transactionId, int $amount, string $currency, array $inscrit): array
    {
        $fullName = trim((string) ($inscrit['nom'] ?? '') . ' ' . (string) ($inscrit['prenom'] ?? ''));
        $nameParts = preg_split('/\s+/', $fullName, 2) ?: [];
        $customerName = $nameParts[0] ?? 'Client';
        $customerSurname = $nameParts[1] ?? 'Patro';
        $baseUrl = $this->getBaseUrl();

        return [
            'apikey' => (string) Environment::get('CINETPAY_APIKEY', ''),
            'site_id' => (string) Environment::get('CINETPAY_SITE_ID', ''),
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $this->cleanDescription('Inscription Patro ' . (!empty($inscrit['identifiant']) ? $inscrit['identifiant'] : $idInscrit)),
            'notify_url' => $baseUrl . '/public/cinetpay_notify.php',
            'return_url' => $baseUrl . '/public/cinetpay_return.php?' . http_build_query(['transaction_id' => $transactionId]),
            'channels' => $this->getChannels(),
            'metadata' => (string) $idInscrit,
            'lang' => 'FR',
            'customer_id' => (string) $idInscrit,
            'customer_name' => $this->cleanDescription($customerName),
            'customer_surname' => $this->cleanDescription($customerSurname),
            'customer_phone_number' => (string) ($inscrit['tel'] ?? ''),
            'invoice_data' => [
                'Reference' => !empty($inscrit['identifiant']) ? (string) $inscrit['identifiant'] : 'PATRO-' . $idInscrit,
                'Session' => $this->sessionTypeLabel($inscrit['type_session'] ?? null),
                'Total' => $this->formatFcfa($amount),
            ],
        ];
    }

    private function sessionTypeLabel(?string $type): string
    {
        $type = strtolower(trim((string) $type));
        return $type === 'vacance' ? 'Vacance' : 'Scolaire';
    }

    private function formatFcfa(int $amount): string
    {
        return number_format(max(0, $amount), 0, ',', ' ') . ' FCFA';
    }

}
