<?php

declare(strict_types=1);

namespace Patro\Domain\Inscription;

final class IvorianPhone
{
    public static function normalize(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    public static function isValid(string $phone): bool
    {
        return (bool) preg_match('/^(01|05|07)[0-9]{8}$/', self::normalize($phone));
    }
}
