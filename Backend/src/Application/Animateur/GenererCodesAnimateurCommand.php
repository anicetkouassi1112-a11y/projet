<?php

declare(strict_types=1);

namespace Patro\Application\Animateur;

final class GenererCodesAnimateurCommand
{
    public function __construct(
        public readonly int $sessionId,
        public readonly int $adminId,
        public readonly int $quantite,
        public readonly ?string $expiration,
        public readonly int $sessionActiveId,
        public readonly int $length = 10
    ) {
    }
}
