<?php

declare(strict_types=1);

namespace Patro\Application\Animateur;

final class InscrireAnimateurParCodeCommand
{
    public function __construct(
        public readonly string $code,
        public readonly string $nom,
        public readonly string $prenom,
        public readonly string $genre,
        public readonly string $tel,
        public readonly string $password,
        public readonly string $passwordConfirm,
        public readonly int $sessionId
    ) {
    }
}
