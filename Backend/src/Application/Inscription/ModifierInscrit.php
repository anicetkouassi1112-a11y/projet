<?php

declare(strict_types=1);

namespace Patro\Application\Inscription;

use Patro\Domain\Inscription\Repository\InscriptionRepository;
use Patro\Infrastructure\Database\TransactionManager;
use Throwable;

final class ModifierInscrit
{
    public function __construct(
        private InscriptionRepository $inscriptions,
        private TransactionManager $transactions
    ) {
    }

    /** @return array<string,mixed> */
    public function execute(ModifierInscritCommand $command): array
    {
        try {
            $this->transactions->begin();
            $this->inscriptions->updateParticipant(
                $command->userId, $command->lastName, $command->firstName,
                $command->birthDate, $command->gender, $command->phone, $command->address
            );
            $this->inscriptions->updateSection($command->inscriptionId, $command->sectionId);
            $this->transactions->commit();

            return ['success' => true, 'message' => 'Inscrit mis a jour avec succes.'];
        } catch (Throwable $exception) {
            $this->transactions->rollback();
            error_log('Update inscrit error: ' . $exception->getMessage());

            return ['success' => false, 'message' => 'Erreur base de donnees pendant la mise a jour.'];
        }
    }
}
