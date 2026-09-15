<?php

declare(strict_types=1);

namespace Patro\Application\Inscription;

final class ModifierInscritCommand
{
    public function __construct(
        public readonly int $inscriptionId,
        public readonly int $userId,
        public readonly string $lastName,
        public readonly string $firstName,
        public readonly string $birthDate,
        public readonly string $gender,
        public readonly string $phone,
        public readonly string $address,
        public readonly ?int $sectionId
    ) {
    }
}
