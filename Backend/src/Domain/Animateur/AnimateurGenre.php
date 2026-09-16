<?php

declare(strict_types=1);

namespace Patro\Domain\Animateur;

enum AnimateurGenre: string
{
    case MASCULIN = 'M';
    case FEMININ = 'F';

    public static function normalize(?string $value): string
    {
        $key = strtolower(trim((string) $value));

        return match ($key) {
            'masculin', 'm' => self::MASCULIN->value,
            'feminin', 'f' => self::FEMININ->value,
            default => '',
        };
    }
}
