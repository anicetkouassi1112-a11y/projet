<?php

declare(strict_types=1);

namespace Patro\Application\Animateur;

use Patro\Domain\Animateur\Repository\AnimateurRepository;
use Patro\Infrastructure\Database\TransactionManager;
use Throwable;

final class GenererCodesAnimateur
{
    public function __construct(
        private AnimateurRepository $animateurs,
        private TransactionManager $transactions
    ) {
    }

    /** @return array<string,mixed> */
    public function execute(GenererCodesAnimateurCommand $command): array
    {
        if ($command->sessionId !== $command->sessionActiveId) {
            return ['success' => false, 'message' => 'Vous ne pouvez generer des codes que pour la session active.', 'codes' => []];
        }
        $quantity = max(1, min(100, $command->quantite));
        $codes = [];

        try {
            $this->transactions->begin();
            for ($index = 0; $index < $quantity; $index++) {
                $code = $this->generateCode($command->length);
                for ($attempt = 0; $attempt < 10 && $this->animateurs->codeExists($code); $attempt++) {
                    $code = $this->generateCode($command->length);
                }
                if ($this->animateurs->codeExists($code)) {
                    throw new \RuntimeException('Generation de code impossible.');
                }
                $this->animateurs->createCode($code, $command->sessionId, $command->adminId, $command->expiration);
                $codes[] = $code;
            }
            $this->transactions->commit();

            return ['success' => true, 'message' => count($codes) . ' code(s) genere(s).', 'codes' => $codes];
        } catch (Throwable $exception) {
            $this->transactions->rollback();
            error_log('Create animateur codes error: ' . $exception->getMessage());
            return ['success' => false, 'message' => 'Erreur pendant la generation des codes.', 'codes' => []];
        }
    }

    private function generateCode(int $length): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($index = 0, $max = strlen($alphabet) - 1; $index < max(1, $length); $index++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }
}
