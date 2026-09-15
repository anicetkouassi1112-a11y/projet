<?php

declare(strict_types=1);

namespace Patro\Domain\Paiement\Repository;

use PDO;

final class CinetPayTransactionRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /** @return array<string,mixed> */
    public function findByTransactionId(string $transactionId): array
    {
        $this->ensureTable();
        $statement = $this->connection->prepare(
            'SELECT t.*, t.id_inscription AS id_inscrit
             FROM cinetpay_transactions t
             WHERE t.transaction_id = :transaction_id
             LIMIT 1'
        );
        $statement->execute([':transaction_id' => $transactionId]);

        return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string,mixed> $transaction */
    public function create(array $transaction): void
    {
        $this->ensureTable();
        $statement = $this->connection->prepare(
            'INSERT INTO cinetpay_transactions
                (transaction_id, id_inscription, amount, currency, status, request_payload)
             VALUES
                (:transaction_id, :id_inscription, :amount, :currency, :status, :request_payload)'
        );
        $statement->execute([
            ':transaction_id' => $transaction['transaction_id'],
            ':id_inscription' => $transaction['id_inscrit'],
            ':amount' => $transaction['amount'],
            ':currency' => $transaction['currency'],
            ':status' => $transaction['status'],
            ':request_payload' => $transaction['request_payload'],
        ]);
    }

    /** @param array<string,mixed> $values */
    public function update(string $transactionId, array $values): void
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
        $parameters = [':transaction_id' => $transactionId];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $values)) {
                $sets[] = $field . ' = :' . $field;
                $parameters[':' . $field] = $values[$field];
            }
        }

        if ($sets === []) {
            return;
        }

        $statement = $this->connection->prepare(
            'UPDATE cinetpay_transactions SET ' . implode(', ', $sets) . ' WHERE transaction_id = :transaction_id'
        );
        $statement->execute($parameters);
    }

    private function ensureTable(): void
    {
        $this->connection->exec(
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
}
