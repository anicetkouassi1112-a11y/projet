<?php
/** @var array $anneesDisponibles */
/** @var int $anneeActive */
/** @var string $typeSessionActive */
/** @var string $searchQuery */
$yearRoute = $yearRoute ?? basename($_SERVER['PHP_SELF'] ?? 'home.php');
$searchQuery = $searchQuery ?? '';
$yearQueryParams = is_array($yearQueryParams ?? null) ? $yearQueryParams : [];
$typeSessionActive = normalizeSessionType($typeSessionActive ?? currentSessionType(), currentSessionType());
$anneesDisponibles = array_values(array_unique(array_map('intval', $anneesDisponibles ?? [])));
sort($anneesDisponibles);

$previousYear = null;
$nextYear = null;
foreach ($anneesDisponibles as $year) {
    if ($year < $anneeActive) {
        $previousYear = $year;
    }
    if ($year > $anneeActive && $nextYear === null) {
        $nextYear = $year;
    }
}

$yearUrl = function (int $year) use ($yearRoute, $typeSessionActive, $searchQuery, $yearQueryParams): string {
    $parts = parse_url($yearRoute);
    $path = $parts['path'] ?? $yearRoute;

    $existingParams = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $existingParams);
    }

    $params = array_merge($existingParams, [
        'annee' => $year,
        'type_session' => $typeSessionActive,
        'search' => $searchQuery,
    ], $yearQueryParams);

    return $path . '?' . http_build_query($params);
};
?>
<nav class="year-switcher" aria-label="Navigation des annees" data-animated-years>
    <?php if ($previousYear !== null): ?>
        <a class="year-step year-step-previous" href="<?= e($yearUrl($previousYear)) ?>" data-year-button>
            <span class="year-step-icon" aria-hidden="true">&lsaquo;</span>
            <span class="year-step-copy">
                <strong><?= e($previousYear) ?></strong>
            </span>
        </a>
    <?php else: ?>
        <span class="year-step year-step-previous is-disabled" aria-disabled="true">
            <span class="year-step-icon" aria-hidden="true">&lsaquo;</span>
            <strong>-</strong>
        </span>
    <?php endif; ?>

    <div class="year-current" aria-current="page">
        <?= e($anneeActive) ?>
    </div>

    <?php if ($nextYear !== null): ?>
        <a class="year-step year-step-next" href="<?= e($yearUrl($nextYear)) ?>" data-year-button>
            <span class="year-step-copy">
                <strong><?= e($nextYear) ?></strong>
            </span>
            <span class="year-step-icon" aria-hidden="true">&rsaquo;</span>
        </a>
    <?php else: ?>
        <span class="year-step year-step-next is-disabled" aria-disabled="true">
            <span class="year-step-copy"></span>
            <strong>-</strong>
            <span class="year-step-icon" aria-hidden="true">&rsaquo;</span>
        </span>
    <?php endif; ?>
</nav>
