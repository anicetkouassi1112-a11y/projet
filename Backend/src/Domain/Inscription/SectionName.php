<?php

declare(strict_types=1);

namespace Patro\Domain\Inscription;

final class SectionName
{
    public static function canonical(?string $section): string
    {
        $section = trim((string) $section);
        if ($section === '') {
            return 'Non specifie';
        }

        return match (self::lookupKey($section)) {
            'stange' => 'St Ange',
            'sttharcis' => 'St Tharcis',
            'stkizito' => 'St Kizito',
            'stdominique' => 'St Dominique',
            'stvincent' => 'St Vincent',
            'stjoseph' => 'St Joseph',
            'antoinettemeo' => 'Antoinette Méo',
            'mariagoretti' => 'Maria Goretti',
            'therese' => 'Thérèse',
            'bernadette' => 'Bernadette',
            default => $section,
        };
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
