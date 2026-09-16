<?php

declare(strict_types=1);

namespace Patro\Presentation\Formatter;

final class MoneyFormatter
{
    public static function fcfa(int $amount): string
    {
        return number_format(max(0, $amount), 0, ',', ' ') . ' FCFA';
    }
}
