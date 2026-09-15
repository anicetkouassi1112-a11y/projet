<?php

declare(strict_types=1);

namespace Patro\Application\Inscription;

final class EnregistrerInscritCommand
{
    public function __construct(
        public readonly string $nom,
        public readonly string $prenom,
        public readonly string $dateNaissance,
        public readonly string $genre,
        public readonly string $tel,
        public readonly string $adresse,
        public readonly string $prixChoisi,
        public readonly string $tailleTeeShirt,
        public readonly int $annee,
        public readonly string $typeSession,
        public readonly int $montantBase,
        public readonly int $prixTeeShirt,
        public readonly bool $sectionObligatoire,
        public readonly int $identifiantDigits = 3
    ) {
    }
}
