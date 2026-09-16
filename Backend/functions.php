<?php

// Charger l'autoload Composer si disponible
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (is_file($autoloadPath)) {
    require_once $autoloadPath;
}

// Utiliser les nouvelles classes si disponibles, sinon utiliser les fonctions de compatibilité
if (class_exists('\Patro\Config\Environment')) {
    \Patro\Config\Environment::load(__DIR__ . '/.env');
} else {
    function loadEnvFile(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) < 2) {
                continue;
            }

            [$key, $value] = array_map('trim', $parts);
            if ($key === '' || getenv($key) !== false || isset($_ENV[$key], $_SERVER[$key])) {
                continue;
            }

            $value = trim($value, "\"'");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    loadEnvFile(__DIR__ . '/.env');
}

// Bootstrap POO centralisé. Les fonctions ci-dessous restent une façade
// temporaire pour les scripts historiques qui les utilisent encore.
require_once __DIR__ . '/bootstrap/app.php';

function appContainer(): \Patro\Shared\Container
{
    $container = $GLOBALS['patro_container'] ?? null;
    if (!$container instanceof \Patro\Shared\Container) {
        throw new RuntimeException('Conteneur Patro indisponible.');
    }

    return $container;
}

// Wrappers de compatibilité pour les fonctions d'environnement
function app_env(string $key, $default = null)
{
    if (class_exists('\Patro\Config\Environment')) {
        return \Patro\Config\Environment::get($key, $default);
    }
    
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return ($value === false || $value === null) ? $default : $value;
}

function app_bool(string $key, bool $default = false): bool
{
    if (class_exists('\Patro\Config\Environment')) {
        return \Patro\Config\Environment::bool($key, $default);
    }
    
    return filter_var(app_env($key, $default ? '1' : '0'), FILTER_VALIDATE_BOOLEAN);
}

function app_int(string $key, int $default): int
{
    if (class_exists('\Patro\Config\Environment')) {
        return \Patro\Config\Environment::int($key, $default);
    }
    
    $value = filter_var(app_env($key, $default), FILTER_VALIDATE_INT);
    return $value === false ? $default : (int) $value;
}

/**
 * Génère les balises favicon HTML avec versionnement pour éviter le cache
 * Cette fonction centralise la gestion des favicons pour toute l'application
 * 
 * @param string $assetBase Chemin de base des assets (ex: '../Backend/Assets')
 * @return array Tableau de balises HTML favicon
 */
function getFaviconTags(string $assetBase): array
{
    $assetBase = rtrim($assetBase, '/');
    $fsBase = __DIR__ . '/Assets/img';
    $manifestPath = __DIR__ . '/site.webmanifest';
    $versionSource = is_file($fsBase . '/favicon.ico') ? $fsBase . '/favicon.ico' : $fsBase;
    $version = is_file($versionSource) ? (string) filemtime($versionSource) : date('Ymd');

    $asset = static function (string $file) use ($assetBase, $version): string {
        return $assetBase . '/img/' . $file . '?v=' . $version;
    };

    $tags = [];
    if (is_file($fsBase . '/favicon.ico')) {
        $tags[] = '<link rel="icon" href="' . e($asset('favicon.ico')) . '" sizes="any">';
    }
    if (is_file($fsBase . '/favicon.svg')) {
        $tags[] = '<link rel="icon" type="image/svg+xml" href="' . e($asset('favicon.svg')) . '">';
    }
    foreach ([16, 32, 48] as $size) {
        $file = 'favicon-' . $size . 'x' . $size . '.png';
        if (is_file($fsBase . '/' . $file)) {
            $tags[] = '<link rel="icon" type="image/png" sizes="' . $size . 'x' . $size . '" href="' . e($asset($file)) . '">';
        }
    }
    if (is_file($fsBase . '/apple-touch-icon.png')) {
        $tags[] = '<link rel="apple-touch-icon" sizes="180x180" href="' . e($asset('apple-touch-icon.png')) . '">';
    }
    if (is_file($manifestPath)) {
        $manifestVersion = (string) filemtime($manifestPath);
        $tags[] = '<link rel="manifest" href="' . e(app_url('Backend/site.webmanifest') . '?v=' . $manifestVersion) . '">';
    }

    return $tags;
}
/**
 * Affiche les balises favicon HTML
 * 
 * @param string $assetBase Chemin de base des assets
 */
function renderFaviconTags(string $assetBase): void
{
    foreach (getFaviconTags($assetBase) as $tag) {
        echo $tag . "\n";
    }
}

function app_base_url(): string
{
    $configuredUrl = trim((string) app_env('APP_URL', ''));
    if ($configuredUrl !== '') {
        return rtrim($configuredUrl, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = trim(preg_replace('#/(Auth|file|partial|public)$#', '', $scriptDir), '/');

    return rtrim($scheme . '://' . $host . ($basePath ? '/' . $basePath : ''), '/');
}

function app_url(string $path = ''): string
{
    return app_base_url() . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function redirectTo(string $url): void
{
    $url = trim(str_replace(["\r", "\n"], '', $url));
    if ($url === '' || str_starts_with($url, '//')) {
        header('Location: index.php');
        exit();
    }

    // Autorise les URLs absolues same-origin (app_url) ; bloque les redirections externes ouvertes.
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
        $target = parse_url($url);
        $base = parse_url(app_base_url());
        $normalizePort = static function (array $parts): string {
            $host = strtolower((string) ($parts['host'] ?? ''));
            $scheme = strtolower((string) ($parts['scheme'] ?? 'http'));
            $port = $parts['port'] ?? null;
            if ($port === null) {
                $port = $scheme === 'https' ? 443 : 80;
            }
            return $host . ':' . (int) $port;
        };
        if ($normalizePort($target) !== $normalizePort($base)) {
            header('Location: index.php');
            exit();
        }
    }

    header('Location: ' . $url);
    exit();
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function publicRelativePath(?string $path, ?string $requiredBase = null): string
{
    $path = preg_replace('#/+#', '/', str_replace('\\', '/', trim((string) $path))) ?? '';
    if (
        $path === ''
        || str_contains($path, "\0")
        || str_starts_with($path, '/')
        || str_starts_with($path, '../')
        || str_contains($path, '/../')
        || preg_match('#^[a-z][a-z0-9+.-]*:#i', $path)
    ) {
        return '';
    }

    $base = trim(str_replace('\\', '/', (string) $requiredBase), '/');
    if ($base !== '' && $path !== $base && !str_starts_with($path, $base . '/')) {
        return '';
    }

    return $path;
}

function isValidDateString(string $date): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    [$year, $month, $day] = array_map('intval', explode('-', $date));
    return checkdate($month, $day, $year);
}

$appDebug = filter_var(app_env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('display_startup_errors', $appDebug ? '1' : '0');
ini_set('log_errors', '1');
$logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0770, true);
}
ini_set('error_log', $logDir . DIRECTORY_SEPARATOR . 'php-errors.log');

if (!defined('APP_LOG_DIR')) {
    define('APP_LOG_DIR', $logDir);
}

function appLog(string $channel, string $message, array $context = []): void
{
    $channel = preg_replace('/[^a-z0-9_-]/i', '', strtolower($channel)) ?: 'app';
    $path = APP_LOG_DIR . DIRECTORY_SEPARATOR . $channel . '.log';

    if (is_file($path) && filesize($path) > app_int('LOG_MAX_BYTES', 5242880)) {
        @rename($path, APP_LOG_DIR . DIRECTORY_SEPARATOR . $channel . '-' . date('Ymd-His') . '.log');
    }

    $entry = [
        'timestamp' => date(DATE_ATOM),
        'level' => $channel === 'security' ? 'warning' : 'info',
        'message' => $message,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'context' => $context,
    ];

    @file_put_contents(
        $path,
        json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function securityLog(string $message, array $context = []): void
{
    if (class_exists('\Patro\Security\SecurityLogger')) {
        \Patro\Security\SecurityLogger::log($message, $context);
        return;
    }
    
    appLog('security', $message, $context);
}

function actionLog(string $message, array $context = []): void
{
    appLog('actions', $message, $context);
}

if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
}
error_reporting(E_ALL);

/** Force HTTPS when APP_FORCE_HTTPS=true (production). */
function enforceHttps(): void
{
    if (PHP_SAPI === 'cli' || !app_bool('APP_FORCE_HTTPS', false)) {
        return;
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if ($isHttps) {
        return;
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: https://' . $host . $uri, true, 301);
    exit;
}

function sendSecurityHeaders(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && empty($_SESSION['adpro'])) {
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $publicPostPages = [
        '/Auth/login.php',
        '/public/inscription.php',
        '/public/auth/inscription.php',
        '/public/auth/connexion.php',
        '/public/auth/animateur_inscription.php',
        '/public/home.php',
        '/public/cinetpay.php',
        '/public/cinetpay_notify.php',
    ];
    $isAllowedPublicPost = false;

    foreach ($publicPostPages as $page) {
        if (str_ends_with($scriptName, $page)) {
            $isAllowedPublicPost = true;
            break;
        }
    }

    if (!$isAllowedPublicPost) {
        http_response_code(405);
        exit('Methode non autorisee.');
    }
}

/**
 * Vérifie que l'utilisateur admin est connecté, sinon redirige vers la page de login
 * 
 * @param string $loginUrl URL de redirection si non connecté
 */
function requireadmin(string $loginUrl = 'Auth/login.php'): void
{
    if (class_exists('\Patro\Auth\AdminAuth')) {
        \Patro\Auth\AdminAuth::requireAuth($loginUrl);
        return;
    }
    
    if (empty($_SESSION['adpro'])) {
        redirectTo($loginUrl);
    }

    $_SESSION['admin_last_activity'] = time();
}

function currentadmin(): array
{
    if (class_exists('\Patro\Auth\AdminAuth')) {
        return \Patro\Auth\AdminAuth::currentAdmin();
    }
    
    return is_array($_SESSION['adpro'] ?? null) ? $_SESSION['adpro'] : [];
}

function validadminRoles(): array
{
    return ['directeur', 'suppleant_1', 'suppleant_2'];
}

function currentadminRole(): string
{
    if (class_exists('\Patro\Auth\AdminAuth')) {
        return \Patro\Auth\AdminAuth::currentRole();
    }
    
    $admin = currentadmin();
    $role = (string) ($admin['role'] ?? 'directeur');

    return in_array($role, validadminRoles(), true) ? $role : 'directeur';
}

function adminHasRole(array $roles): bool
{
    if (class_exists('\Patro\Auth\AdminAuth')) {
        return \Patro\Auth\AdminAuth::hasRole($roles);
    }
    
    return in_array(currentadminRole(), $roles, true);
}

function abortForbidden(string $message = 'Acces interdit.'): void
{
    http_response_code(403);
    $safeMessage = e($message);
    $currentDir = basename(dirname($_SERVER['PHP_SELF'] ?? ''));
    $base = in_array($currentDir, ['Auth', 'file', 'partial'], true) ? '../' : '';
    $assetBase = app_url('Backend/Assets');
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>403 - Acces interdit</title><link rel="stylesheet" href="' . e($assetBase . '/css/bootstrap.min.css') . '"><link rel="stylesheet" href="' . e($assetBase . '/css/b.css') . '"></head><body><main class="container py-5"><div class="alert alert-danger"><h1>403</h1><p>' . $safeMessage . '</p><a class="btn btn-primary" href="' . e($base . defaultadminRoute()) . '">Retour</a></div></main></body></html>';
    exit();
}

function requireRole(array $roles, string $loginUrl = 'Auth/login.php'): void
{
    if (class_exists('\Patro\Auth\AdminAuth')) {
        \Patro\Auth\AdminAuth::requireRole($roles, $loginUrl);
        return;
    }
    
    requireadmin($loginUrl);

    if (!adminHasRole($roles)) {
        abortForbidden();
    }
}

function defaultadminRoute(?string $role = null): string
{
    $role = $role !== null && in_array($role, validadminRoles(), true) ? $role : currentadminRole();

    return match ($role) {
        default => 'home.php',
    };
}

function loginadmin(string $username, string $password): array
{
    return \Patro\Auth\AdminAuth::login($username, $password);
}

/**
 * Génère ou retourne le token CSRF pour la session actuelle
 * Protège contre les attaques Cross-Site Request Forgery
 * 
 * @return string Token CSRF
 */
function csrfToken(): string
{
    if (class_exists('\Patro\Security\CsrfProtection')) {
        return \Patro\Security\CsrfProtection::generateToken();
    }
    
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function appCleanText(string $value, int $maxLength = 255): string
{
    $value = trim(strip_tags($value));
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    return substr($value, 0, $maxLength);
}

function requestTextParam(string $name, int $maxLength = 120): string
{
    return appCleanText((string) ($_GET[$name] ?? ''), $maxLength);
}

/**
 * Valide et sanitize un paramètre POST de manière sécurisée
 * 
 * @param string $name Nom du paramètre
 * @param int $maxLength Longueur maximale
 * @return string Valeur nettoyée
 */
function requestPostParam(string $name, int $maxLength = 255): string
{
    return appCleanText((string) ($_POST[$name] ?? ''), $maxLength);
}

/**
 * Valide un entier depuis la requête (GET ou POST)
 * 
 * @param string $name Nom du paramètre
 * @param int $default Valeur par défaut
 * @param int $min Minimum autorisé
 * @param int $max Maximum autorisé
 * @return int Valeur validée
 */
function requestIntParam(string $name, int $default = 0, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
{
    $value = filter_var($_GET[$name] ?? $_POST[$name] ?? $default, FILTER_VALIDATE_INT);
    if ($value === false) {
        return $default;
    }
    return max($min, min($max, (int) $value));
}

/**
 * Vérifie la validité d'un token CSRF
 * 
 * @param string|null $token Token à vérifier
 * @return bool True si le token est valide, false sinon
 */
function verifyCsrfToken(?string $token): bool
{
    if (class_exists('\Patro\Security\CsrfProtection')) {
        return \Patro\Security\CsrfProtection::verifyToken($token);
    }
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!$token || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrfToken(?string $token = null): void
{
    if (class_exists('\Patro\Security\CsrfProtection')) {
        \Patro\Security\CsrfProtection::requireToken($token);
        return;
    }
    
    $token = $token ?? ($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null);
    if (!verifyCsrfToken($token)) {
        http_response_code(403);
        securityLog('CSRF token validation failed', ['ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
        exit('Erreur de validation CSRF. Veuillez recharger la page et reessayer.');
    }
}

function setFlashMessage(string $type, string $message): void
{
    if (!isset($_SESSION['flash_messages']) || !is_array($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }

    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getFlashMessages(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);

    return is_array($messages) ? $messages : [];
}

function normalizeIvorianPhone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function isValidIvorianPhone(string $phone): bool
{
    $phone = normalizeIvorianPhone($phone);
    return (bool) preg_match('/^(01|05|07)[0-9]{8}$/', $phone);
}

function validGenres(): array
{
    return ['Garçon', 'Fille'];
}

function normalizeGenre(?string $genre): string
{
    return match (identifierLookupKey((string) $genre)) {
        'garcon', 'garçon', 'masculin', 'm' => 'Garçon',
        'fille', 'feminin', 'f' => 'Fille',
        default => '',
    };
}

/**
 * Normalise le genre pour la table `animateur`, dont la colonne genre_a
 * est un ENUM('M','F') — à ne pas confondre avec normalizeGenre() qui
 * cible les ENUM('Garçon','Fille') de `section`/`utilisateur`.
 */
function normalizeAnimateurGenre(?string $genre): string
{
    return match (identifierLookupKey((string) $genre)) {
        'masculin', 'm' => 'M',
        'feminin', 'f' => 'F',
        default => '',
    };
}
function photoModuleEnabled(): bool
{
    return false;
}

function photoRequired(): bool
{
    return false;
}

function validTeeShirtSizes(): array
{
    return ['S', 'M', 'L', 'XL', 'XXL'];
}

function normalizeTeeShirtSize(?string $size): string
{
    $size = strtoupper(trim((string) $size));
    return in_array($size, validTeeShirtSizes(), true) ? $size : '';
}

// ===== CONFIGURATIONS GLOBALES =====
function formatFcfa(int $amount): string
{
    return number_format(max(0, $amount), 0, ',', ' ') . ' FCFA';
}

function validSessionTypes(): array
{
    return ['scolaire', 'vacance'];
}

function sessionTypeLabel(?string $type): string
{
    return appContainer()->get(\Patro\Inscription\SessionService::class)->normalizeSessionType($type) === 'vacance' ? 'Vacance' : 'Scolaire';
}

function sectionBreakdownEnabled(?string $typeSession = null): bool
{
    return appContainer()->get(\Patro\Inscription\SessionService::class)->normalizeSessionType(
        $typeSession,
        appContainer()->get(\Patro\Inscription\SessionService::class)->getCurrentSessionType()
    ) !== 'scolaire';
}


function calculateAge(string $dateNaissance, ?int $referenceYear = null): ?int
{
    try {
        $birthDate = new DateTimeImmutable($dateNaissance);
        $reference = $referenceYear
            ? new DateTimeImmutable($referenceYear . '-12-31')
            : new DateTimeImmutable('today');

        return $reference->diff($birthDate)->y;
    } catch (Throwable $e) {
        return null;
    }
}

function canAccessPublicInscrit(int $idInscrit): bool
{
    if (!empty($_SESSION['adpro'])) {
        return true;
    }

    return isset($_SESSION['last_inscrit_id']) && (int) $_SESSION['last_inscrit_id'] === $idInscrit;
}

function identifierLookupKey(string $value): string
{
    $value = trim($value);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);

    // Vrais caractères UTF-8 (encodage correct)
    $value = strtr($value, [
        'à' => 'a', 'â' => 'a', 'ä' => 'a',
        'ç' => 'c',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i',
        'ô' => 'o', 'ö' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u',
    ]);

    // Caractères mal encodés (double-encodage UTF-8 corrompu)
    $value = strtr($value, [
        'Ã ' => 'a', 'Ã¢' => 'a', 'Ã¤' => 'a',
        'Ã§' => 'c',
        'Ã©' => 'e', 'Ã¨' => 'e', 'Ãª' => 'e', 'Ã«' => 'e',
        'Ã®' => 'i', 'Ã¯' => 'i',
        'Ã´' => 'o', 'Ã¶' => 'o',
        'Ã¹' => 'u', 'Ã»' => 'u', 'Ã¼' => 'u',
    ]);

    return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
}

function canonicalSectionName(?string $section): string
{
    $section = trim((string) $section);
    if ($section === '') {
        return 'Non specifie';
    }

    return match (identifierLookupKey($section)) {
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

function sectionCodeForIdentifier(string $section): string
{
    $codes = [
        'stange' => 'AN',
        'sttharcis' => 'TH',
        'stkizito' => 'KI',
        'stdominique' => 'DO',
        'stvincent' => 'VI',
        'stjoseph' => 'JO',
        'antoinettemeo' => 'AM',
        'mariagoretti' => 'MG',
        'therese' => 'TR',
        'bernadette' => 'BE',
    ];

    $key = identifierLookupKey($section);
    if (isset($codes[$key])) {
        return $codes[$key];
    }

    $fallback = strtoupper(preg_replace('/[^A-Z0-9]+/', '', strtoupper($section)) ?? '');
    return substr($fallback !== '' ? $fallback : 'XX', 0, 12);
}

function genreCodeForIdentifier(string $genre): string
{
    $key = identifierLookupKey($genre);
    if (in_array($key, ['garcon', 'm', 'masculin'], true)) {
        return 'M';
    }

    if (in_array($key, ['fille', 'f', 'feminin'], true)) {
        return 'F';
    }

    throw new InvalidArgumentException('Genre invalide pour la generation de l identifiant.');
}

function nextRegistrationStepUrl(int $idInscrit): string
{
    return app_url('public/auth/confirmation_enregistrement.php') . '?' . http_build_query(['inscrit_id' => $idInscrit]);
}
