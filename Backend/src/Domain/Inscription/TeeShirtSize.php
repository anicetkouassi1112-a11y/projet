<?php

declare(strict_types=1);

namespace Patro\Domain\Inscription;

enum TeeShirtSize: string
{
    case S = 'S';
    case M = 'M';
    case L = 'L';
    case XL = 'XL';
    case XXL = 'XXL';

    public static function values(): array
    {
        return array_map(static fn (self $size): string => $size->value, self::cases());
    }

    public static function normalize(?string $value): string
    {
        $value = strtoupper(trim((string) $value));
        return in_array($value, self::values(), true) ? $value : '';
    }
}
