<?php

declare(strict_types=1);

require_once __DIR__ . '/utilitaire.php';

use Patro\Paiement\CinetPayService;

function cinetpayService(): CinetPayService
{
    return appContainer()->get(CinetPayService::class);
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
    return cinetpayService()->getCurrency();
}

function cinetpayChannels(): string
{
    return cinetpayService()->getChannels();
}

function cinetpayEndpoint(string $path = ''): string
{
    return cinetpayService()->getEndpoint($path);
}

function cinetpayTransactionId(int $idInscrit): string
{
    return cinetpayService()->generateTransactionId($idInscrit);
}

function cinetpayCleanDescription(string $value): string
{
    return cinetpayService()->cleanDescription($value);
}

function cinetpayRedirectToCheckout(string $url): void
{
    cinetpayService()->redirectToCheckout($url);
}

function cinetpayInitiatePayment(int $idInscrit): array
{
    return cinetpayService()->initiatePayment($idInscrit);
}

function cinetpayFindTransaction(string $transactionId, ?PDO $conn = null): array
{
    return cinetpayService()->findTransaction($transactionId);
}

function cinetpayUpdateTransaction(PDO $conn, string $transactionId, array $values): void
{
    cinetpayService()->updateTransaction($transactionId, $values);
}

function cinetpayMarkInscriptionState(int $idInscrit, string $state): void
{
    cinetpayService()->markInscriptionState($idInscrit, $state);
}

function cinetpayVerifyTransaction(string $transactionId): array
{
    return cinetpayService()->verifyTransaction($transactionId);
}
