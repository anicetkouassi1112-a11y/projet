<?php

declare(strict_types=1);

namespace Patro\Domain\Inscription;

enum SessionType: string
{
    case SCOLAIRE = 'scolaire';
    case VACANCE = 'vacance';

    public static function normalize(?string $value, self $default = self::SCOLAIRE): self
    {
        return self::tryFrom(strtolower(trim((string) $value))) ?? $default;
    }

    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }

    public function label(): string
    {
        return $this === self::VACANCE ? 'Vacance' : 'Scolaire';
    }
}
