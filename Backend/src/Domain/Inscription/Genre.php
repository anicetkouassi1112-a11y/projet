<?php

declare(strict_types=1);

namespace Patro\Domain\Inscription;

enum Genre: string
{
    case GARCON = 'Garçon';
    case FILLE = 'Fille';

    public static function normalize(?string $value): string
    {
        $key = self::lookupKey((string) $value);

        return match ($key) {
            'garcon', 'masculin', 'm' => self::GARCON->value,
            'fille', 'feminin', 'f' => self::FILLE->value,
            default => '',
        };
    }

    public static function values(): array
    {
        return array_map(static fn (self $genre): string => $genre->value, self::cases());
    }

    private static function lookupKey(string $value): string
    {
        $value = function_exists('mb_strtolower')
            ? mb_strtolower(trim($value), 'UTF-8')
            : strtolower(trim($value));
        $value = strtr($value, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ç' => 'c',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        ]);

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }
}
