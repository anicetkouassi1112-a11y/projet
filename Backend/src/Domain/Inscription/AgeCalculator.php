<?php

declare(strict_types=1);

namespace Patro\Domain\Inscription;

use DateTimeImmutable;
use Throwable;

final class AgeCalculator
{
    public static function calculate(string $birthDate, ?int $referenceYear = null): ?int
    {
        try {
            $birth = new DateTimeImmutable($birthDate);
            $reference = $referenceYear
                ? new DateTimeImmutable($referenceYear . '-12-31')
                : new DateTimeImmutable('today');

            return $reference->diff($birth)->y;
        } catch (Throwable) {
            return null;
        }
    }
}
