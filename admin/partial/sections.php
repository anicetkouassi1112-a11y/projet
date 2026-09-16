<?php declare(strict_types=1);

require_once __DIR__ . '/utilitaire.php';

function sectionConfig(string $genderKey): array
{
    $genre = $genderKey === 'fille' ? 'Fille' : 'Garçon';
    $sections = [];
    foreach (appContainer()->get(\Patro\Inscription\SectionService::class)->getAllSections() as $section) {
        if (\Patro\Domain\Inscription\Genre::normalize((string) ($section['genre'] ?? '')) !== $genre) continue;

        $key = (string) (int) $section['id_section'];
        $sections[$key] = [
            'id_section' => (int) $section['id_section'],
            'label' => (string) $section['nom_section'],
            'age_min' => (int) $section['age_min'],
            'age_max' => (int) $section['age_max'],
        ];
    }

    return [
        'genre' => $genre,
        'label' => $genre === 'Fille' ? 'Filles' : 'Garçons',
        'title' => $genre === 'Fille' ? 'Inscrits Filles' : 'Inscrits Garçons',
        'route' => $genre === 'Fille' ? 'fille' : 'garcon', // Utilisé pour $_GET['page']
        'sections' => $sections,
    ];
}

function normalizeSectionPage(?string $page, array $config): ?string
{
    // On utilise standard $_GET['section'] pour la navigation
    $sectionParam = $_GET['section'] ?? null;
    if ($sectionParam === null || trim($sectionParam) === '') return null;

    $rawPage = trim($sectionParam);
    // Validation : le paramètre doit être un entier valide
    if (!ctype_digit($rawPage)) return null;
    
    if (isset($config['sections'][$rawPage])) return $rawPage;

    return null;
}

function genderPageContext(string $genderKey, int $anneeActive, ?string $typeSession = null): array
{
    $config = sectionConfig($genderKey);
    $pageKey = normalizeSectionPage($_GET['section'] ?? null, $config);
    $typeSession = appContainer()->get(\Patro\Inscription\SessionService::class)->normalizeSessionType($typeSession, appContainer()->get(\Patro\Inscription\SessionService::class)->getCurrentSessionType());
    
    $showSectionBreakdown = appContainer()->get(\Patro\Inscription\SessionService::class)->sectionBreakdownEnabled($typeSession);
    if (!$showSectionBreakdown) $pageKey = null;

    $sessionLabel = sessionTypeLabel($typeSession);
    $idSession = appContainer()->get(\Patro\Inscription\SessionService::class)
        ->ensureSession($anneeActive, $typeSession);
    $isScolaire = ($typeSession === 'scolaire');
    
    // Récupération de tous les inscrits du genre (avec ou sans section)
    $allGenreInscrits = fetchInscritsBySectionIds([], $idSession, $config['genre'], $isScolaire);

    // Si une section est sélectionnée, on filtre sur celle-ci
    if ($pageKey !== null) {
        $section = $config['sections'][$pageKey];
        $inscrits = fetchInscritsBySectionIds([(int) $section['id_section']], $idSession, $config['genre'], $isScolaire);
        $title = $section['label'] . ' (' . $anneeActive . ' - ' . $sessionLabel . ')';
        $emptyMessage = 'Aucun inscrit trouvé pour ' . $section['label'] . ' en ' . $anneeActive . '.';
    } else {
        $inscrits = $allGenreInscrits;
        $title = $config['title'] . ' (' . $anneeActive . ' - ' . $sessionLabel . ')';
        $emptyMessage = 'Aucun inscrit trouvé pour ' . $config['label'] . ' en ' . $anneeActive . '.';
    }

    return [
        'config' => $config,
        'page_key' => $pageKey,
        'title' => $title,
        'empty_message' => $emptyMessage,
        'inscription' => $inscrits,
        'counts' => $showSectionBreakdown
            ? sectionCountsForConfig($config['sections'], $allGenreInscrits)
            : [$config['label'] => count($allGenreInscrits)],
        'show_section_breakdown' => $showSectionBreakdown,
    ];
}

// ============================================================
// Fonctions dédiées aux Animateurs / Animatrices
// ============================================================

/**
 * Récupère les animateurs filtrés par sections, session et genre.
 */
function fetchAnimateursBySectionIds(array $sectionIds, int $idSession, ?string $genre, bool $isScolaire = false): array
{
    $normalizedGenre = $genre !== null ? \Patro\Domain\Animateur\AnimateurGenre::normalize($genre) : null;
    return appContainer()->get(\Patro\Domain\Animateur\Repository\AnimateurRepository::class)
        ->findBySectionIds($sectionIds, $idSession, $normalizedGenre !== '' ? $normalizedGenre : null, $isScolaire);
}

/**
 * Génère la configuration des sections pour les animateurs / animatrices.
 */
function animateurSectionConfig(string $genderKey): array
{
    $isFemale = in_array(strtolower($genderKey), ['fille', 'animatrice', 'f'], true);
    $genre = $isFemale ? 'Fille' : 'Garçon';

    $sections = [];
    foreach (appContainer()->get(\Patro\Inscription\SectionService::class)->getAllSections() as $section) {
        if (\Patro\Domain\Inscription\Genre::normalize((string) ($section['genre'] ?? '')) !== $genre) {
            continue;
        }

        $key = (string) (int) $section['id_section'];
        $sections[$key] = [
            'id_section' => (int) $section['id_section'],
            'label'      => (string) $section['nom_section'],
            'age_min'    => (int) $section['age_min'],
            'age_max'    => (int) $section['age_max'],
        ];
    }

    return [
        'genre'      => $genre,
        // Code court ('M'/'F') attendu par normalizeAnimateurGenre() dans
        // fetchAnimateursBySectionIds() — distinct de 'genre' (Garçon/Fille)
        // qui sert au libellé et au filtrage des sections.
        'genre_code' => $isFemale ? 'F' : 'M',
        'label'      => $isFemale ? 'Animatrices' : 'Animateurs',
        'title'      => $isFemale ? 'Liste des Animatrices' : 'Liste des Animateurs',
        'route'      => $isFemale ? 'Animatrice' : 'Animateur',
        'sections'   => $sections,
    ];
}

/**
 * Construit le contexte complet pour la page Animateur / Animatrice.
 */
function genderAnimateurPageContext(string $genderKey, int $anneeActive, ?string $typeSession = null): array
{
    $config = animateurSectionConfig($genderKey);
    $pageKey = normalizeSectionPage($_GET['section'] ?? null, $config);
    $typeSession = appContainer()->get(\Patro\Inscription\SessionService::class)->normalizeSessionType($typeSession, appContainer()->get(\Patro\Inscription\SessionService::class)->getCurrentSessionType());

    $showSectionBreakdown = appContainer()->get(\Patro\Inscription\SessionService::class)->sectionBreakdownEnabled($typeSession);
    if (!$showSectionBreakdown) {
        $pageKey = null;
    }

    $sessionLabel = sessionTypeLabel($typeSession);
    $idSession = appContainer()->get(\Patro\Inscription\SessionService::class)
        ->ensureSession($anneeActive, $typeSession);
    $isScolaire = ($typeSession === 'scolaire');

    // Récupère tous les animateurs du genre
    // NB: on passe genre_code ('M'/'F') et non genre ('Garçon'/'Fille'),
    // car normalizeAnimateurGenre() (utilisée dans fetchAnimateursBySectionIds)
    // n'accepte que 'masculin'/'m'/'feminin'/'f' — passer 'Garçon'/'Fille'
    // fait échouer la normalisation et désactive silencieusement le filtre.
    $allGenreAnimateurs = fetchAnimateursBySectionIds([], $idSession, $config['genre_code'], $isScolaire);

    $labelGenre = ($config['genre'] === 'Fille') ? 'Aucune animatrice trouvée' : 'Aucun animateur trouvé';

    // Filtrage si une section spécifique est sélectionnée
    if ($pageKey !== null) {
        $section = $config['sections'][$pageKey];
        $animateurs = fetchAnimateursBySectionIds([(int) $section['id_section']], $idSession, $config['genre_code'], $isScolaire);
        $title = $config['label'] . ' - ' . $section['label'] . ' (' . $anneeActive . ' - ' . $sessionLabel . ')';
        $emptyMessage = $labelGenre . ' pour ' . $section['label'] . ' en ' . $anneeActive . '.';
    } else {
        $animateurs = $allGenreAnimateurs;
        $title = $config['title'] . ' (' . $anneeActive . ' - ' . $sessionLabel . ')';
        $emptyMessage = $labelGenre . ' pour ' . $config['label'] . ' en ' . $anneeActive . '.';
    }

    return [
        'config'                 => $config,
        'page_key'               => $pageKey,
        'title'                  => $title,
        'empty_message'          => $emptyMessage,
        'inscription'            => $animateurs,
        'animateurs'             => $animateurs,
        'counts'                 => $showSectionBreakdown
            ? sectionCountsForConfig($config['sections'], $allGenreAnimateurs)
            : [$config['label'] => count($allGenreAnimateurs)],
        'show_section_breakdown' => $showSectionBreakdown,
    ];
}

function normalizeLookupKey(string $value): string
{
    $value = trim($value);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = strtr($value, [
        'à' => 'a', 'â' => 'a', 'ä' => 'a',
        'ç' => 'c',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i',
        'ô' => 'o', 'ö' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'Ã' => 'a', 'Ã¢' => 'a', 'Ã¤' => 'a',
        'Ã§' => 'c',
        'Ã©' => 'e', 'Ã¨' => 'e', 'Ãª' => 'e', 'Ã«' => 'e',
        'Ã®' => 'i', 'Ã¯' => 'i',
        'Ã´' => 'o', 'Ã¶' => 'o',
        'Ã¹' => 'u', 'Ã»' => 'u', 'Ã¼' => 'u',
    ]);

    return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
}

function sectionIdsByNames(array $names): array
{
    $wanted = array_map('normalizeLookupKey', $names);
    $ids = [];

    foreach (appContainer()->get(\Patro\Inscription\SectionService::class)->getAllSections() as $section) {
        if (in_array(normalizeLookupKey((string) $section['nom_section']), $wanted, true)) {
            $ids[] = (int) $section['id_section'];
        }
    }

    return $ids;
}

function fetchInscritsBySectionIds(array $sectionIds, int $idSession, ?string $genre, bool $isScolaire = false): array
{
    return appContainer()->get(\Patro\Domain\Inscription\Repository\InscriptionRepository::class)
        ->findBySectionIds($sectionIds, $idSession, $genre !== null ? \Patro\Domain\Inscription\Genre::normalize($genre) : null, $isScolaire);
}

function fetchInscritsByGenre(string $genre, int $idSession, string $etat = 'inscrit'): array
{
    return appContainer()->get(\Patro\Domain\Inscription\Repository\InscriptionRepository::class)
        ->findByGenre($genre, $idSession, $etat);
}

function fetchInscritsBySections(array $sectionNames, int $idSession, ?string $genre = null, string $etat = 'inscrit'): array
{
    $sectionIds = sectionIdsByNames($sectionNames);
    if (!$sectionIds) {
        return [];
    }

    return appContainer()->get(\Patro\Domain\Inscription\Repository\InscriptionRepository::class)
        ->findBySections($sectionIds, $idSession, $genre !== null ? \Patro\Domain\Inscription\Genre::normalize($genre) : null, $etat);
}

function sectionCounts(array $inscrits): array
{
    $counts = [];
    foreach ($inscrits as $inscrit) {
        $section = (string) ($inscrit['nom_section'] ?? $inscrit['section'] ?? 'Non specifie');
        $counts[$section] = ($counts[$section] ?? 0) + 1;
    }

    return $counts;
}

function sectionCountsForConfig(array $sections, array $inscrits): array
{
    $counts = [];
    foreach ($sections as $section) {
        $counts[(string) $section['label']] = 0;
    }

    foreach ($inscrits as $inscrit) {
        $section = (string) ($inscrit['nom_section'] ?? $inscrit['section'] ?? '');
        if ($section !== '') {
            $counts[$section] = ($counts[$section] ?? 0) + 1;
        }
    }

    return $counts;
}