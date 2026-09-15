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
 * Établit et retourne une connexion PDO à la base de données
 * Utilise le pattern Singleton pour éviter les connexions multiples
 * 
 * @return PDO Instance de connexion à la base de données
 * @throws PDOException En cas d'erreur de connexion
 */
function getConnection(): PDO
{
    $container = $GLOBALS['patro_container'] ?? null;
    if ($container instanceof \Patro\Shared\Container && $container->has(PDO::class)) {
        return $container->get(PDO::class);
    }

    throw new RuntimeException('Connexion PDO non enregistrée dans le conteneur Patro.');
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
    if (class_exists('\Patro\Auth\AdminAuth')) {
        return \Patro\Auth\AdminAuth::login($username, $password);
    }
    
    $stmt = getConnection()->prepare('SELECT id_admin, username, password, role, created_at FROM admin WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin || empty($admin['password'])) {
        return [];
    }

    $storedPassword = (string) $admin['password'];
    $passwordHashValid = password_verify($password, $storedPassword);
    $legacyPasswordValid = !$passwordHashValid && hash_equals($storedPassword, md5($password));

    if (!$passwordHashValid && !$legacyPasswordValid) {
        return [];
    }

    if ($legacyPasswordValid) {
        try {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $update = getConnection()->prepare('UPDATE admin SET password = :password WHERE id_admin = :id_admin');
            $update->execute([
                ':password' => $newHash,
                ':id_admin' => (int) $admin['id_admin'],
            ]);
            $admin['password'] = $newHash;
        } catch (PDOException $e) {
            error_log('Password rehash error: ' . $e->getMessage());
        }
    }

    return $admin;
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
function getConfig(string $key, ?string $default = null): ?string
{
    try {
        $container = $GLOBALS['patro_container'] ?? null;
        if ($container instanceof \Patro\Shared\Container && $container->has(\Patro\Domain\Configuration\Repository\ConfigurationRepository::class)) {
            return $container->get(\Patro\Domain\Configuration\Repository\ConfigurationRepository::class)->find($key, $default);
        }

        $stmt = getConnection()->prepare('SELECT config_value FROM configurations WHERE config_key = :key LIMIT 1');
        $stmt->execute([':key' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string) $value;
    } catch (PDOException $e) {
        error_log('Get config error: ' . $e->getMessage());
        return $default;
    }
}

function getConfigAmount(string $key, int $default = 0): int
{
    $value = getConfig($key, (string) $default);
    $amount = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

    return $amount === false ? $default : (int) $amount;
}

function inscriptionBaseAmount(): int
{
    return getConfigAmount('inscription_montant', 500);
}

function teeShirtPrice(): int
{
    return getConfigAmount('tee_shirt_prix', 500);
}

function formatFcfa(int $amount): string
{
    return number_format(max(0, $amount), 0, ',', ' ') . ' FCFA';
}

function setConfig(string $key, ?string $value): bool
{
    try {
        $container = $GLOBALS['patro_container'] ?? null;
        if ($container instanceof \Patro\Shared\Container && $container->has(\Patro\Domain\Configuration\Repository\ConfigurationRepository::class)) {
            $container->get(\Patro\Domain\Configuration\Repository\ConfigurationRepository::class)->save($key, $value);
            return true;
        }

        $stmt = getConnection()->prepare(
            'INSERT INTO configurations (config_key, config_value) VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)'
        );
        $stmt->execute([':key' => $key, ':value' => $value ?? '']);
        return true;
    } catch (PDOException $e) {
        error_log('Set config error: ' . $e->getMessage());
        return false;
    }
}

// Cree ou retrouve la session SQL correspondant a l annee et au type actifs.
function ensureSession(int $anneeVal, string $typeSession, ?PDO $connect = null): int
{
    if (class_exists('\Patro\Inscription\SessionService')) {
        $service = appContainer()->get(\Patro\Inscription\SessionService::class);
        return $service->ensureSession($anneeVal, $typeSession);
    }
    
    $connect = $connect ?: getConnection();
    $typeSession = normalizeSessionType($typeSession);
    $anneeId = ensureAnnee($anneeVal, $connect);

    $stmt = $connect->prepare(
        'INSERT INTO session (annee_id, type_session) VALUES (:annee_id, :type_session)
         ON DUPLICATE KEY UPDATE type_session = VALUES(type_session)'
    );
    $stmt->execute([
        ':annee_id' => $anneeId,
        ':type_session' => $typeSession,
    ]);

    $select = $connect->prepare(
        'SELECT id_session
         FROM session
         WHERE annee_id = :annee_id
           AND type_session = :type_session
         LIMIT 1'
    );
    $select->execute([
        ':annee_id' => $anneeId,
        ':type_session' => $typeSession,
    ]);
    $id = $select->fetchColumn();

    if ($id === false) {
        throw new RuntimeException('Session introuvable.');
    }

    return (int) $id;
}

// Session animateur de reference pour les formulaires publics.
function currentAnimateurSessionId(?PDO $connect = null): int
{
    $annee = (int) ($_SESSION['annee_active'] ?? date('Y'));
    if ($annee < 2000 || $annee > 2100) {
        $annee = (int) date('Y');
    }

    return ensureSession($annee, currentSessionType(), $connect);
}

function sessionLabelById(int $idSession, ?PDO $connect = null): string
{
    if (class_exists('\Patro\Inscription\SessionService')) {
        $service = appContainer()->get(\Patro\Inscription\SessionService::class);
        return $service->sessionLabelById($idSession);
    }
    
    $connect = $connect ?: getConnection();
    $stmt = $connect->prepare(
        'SELECT a.ans, s.type_session
         FROM session s
         INNER JOIN annee a ON a.idannee = s.annee_id
         WHERE s.id_session = :id_session
         LIMIT 1'
    );
    $stmt->execute([':id_session' => $idSession]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        return 'Session inconnue';
    }

    return (string) $session['ans'] . ' - ' . sessionTypeLabel((string) $session['type_session']);
}

function getAllSessions(?PDO $connect = null): array
{
    if (class_exists('\Patro\Inscription\SessionService')) {
        $service = appContainer()->get(\Patro\Inscription\SessionService::class);
        return $service->getAllSessions();
    }
    
    $connect = $connect ?: getConnection();
    $stmt = $connect->query(
        'SELECT s.id_session, a.ans, s.type_session
         FROM session s
         INNER JOIN annee a ON a.idannee = s.annee_id
         ORDER BY a.ans DESC, FIELD(s.type_session, "scolaire", "vacance") ASC'
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllSections(?PDO $connect = null): array
{
    if (class_exists('\Patro\Inscription\SectionService')) {
        $service = appContainer()->get(\Patro\Inscription\SectionService::class);
        return $service->getAllSections();
    }
    
    $connect = $connect ?: getConnection();
    $stmt = $connect->query(
        'SELECT id_section, nom_section, description, genre, age_min, age_max
         FROM section
         ORDER BY genre ASC, age_min ASC, age_max ASC, nom_section ASC'
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function nomSectionExiste(string $nom, ?PDO $connect = null): bool
{
    if (class_exists('\Patro\Inscription\SectionService')) {
        $service = appContainer()->get(\Patro\Inscription\SectionService::class);
        return $service->nomSectionExiste($nom);
    }
    
    $connect = $connect ?: getConnection();
    $stmt = $connect->prepare('SELECT COUNT(*) FROM section WHERE nom_section = :nom');
    $stmt->execute([':nom' => $nom]);

    return (int) $stmt->fetchColumn() > 0;
}

function titreExiste(string $titre, ?PDO $connect = null, ?int $sessionId = null): bool
{
    $sessionId = $sessionId ?: getActiveAdminSessionId();
    $container = $GLOBALS['patro_container'] ?? null;
    if ($connect === null && $container instanceof \Patro\Shared\Container && $container->has(\Patro\Domain\Inscription\Repository\ThemeRepository::class)) {
        return $container->get(\Patro\Domain\Inscription\Repository\ThemeRepository::class)->existsByTitle($titre, $sessionId);
    }

    $connect = $connect ?: getConnection();
    $stmt = $connect->prepare('SELECT COUNT(*) FROM themes WHERE titre = :titre AND session_id = :session_id');
    $stmt->execute([
        ':titre' => $titre,
        ':session_id' => $sessionId,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}
function sectionIntervalOverlap(string $genre, int $ageMin, int $ageMax, ?PDO $connect = null): array
{
    if (class_exists('\Patro\Inscription\SectionService')) {
        $service = appContainer()->get(\Patro\Inscription\SectionService::class);
        return $service->sectionIntervalOverlap($genre, $ageMin, $ageMax);
    }
    
    $connect = $connect ?: getConnection();
    $stmt = $connect->prepare(
        'SELECT id_section, nom_section, age_min, age_max
         FROM section
         WHERE genre = :genre
           AND age_min <= :age_max
           AND age_max >= :age_min
         ORDER BY age_min ASC, age_max ASC
         LIMIT 1'
    );
    $stmt->execute([
        ':genre' => $genre,
        ':age_min' => $ageMin,
        ':age_max' => $ageMax,
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function creerSection(string $nomSection, string $description = '', string $genre = '', int|string|null $ageMin = null, int|string|null $ageMax = null): array
{
    if (class_exists('\Patro\Inscription\SectionService')) {
        $service = appContainer()->get(\Patro\Inscription\SectionService::class);
        $ageMin = filter_var($ageMin, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 120]]);
        $ageMax = filter_var($ageMax, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 120]]);
        $ageMin = $ageMin === false ? 0 : (int) $ageMin;
        $ageMax = $ageMax === false ? 0 : (int) $ageMax;
        return $service->creerSection($nomSection, $description, $genre, $ageMin, $ageMax);
    }
    
    requireCsrfToken();
    
    $nomSection = appCleanText($nomSection, 100);
    $description = appCleanText($description, 255);
    $genre = normalizeGenre($genre);
    $ageMin = filter_var($ageMin, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 120]]);
    $ageMax = filter_var($ageMax, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 120]]);

    if ($nomSection === '') {
        return ['success' => false, 'message' => 'Le nom de la section est obligatoire.', 'alert_type' => 'warning'];
    }

    if (!in_array($genre, validGenres(), true)) {
        return ['success' => false, 'message' => 'Le genre de la section est obligatoire.', 'alert_type' => 'warning'];
    }

    if ($ageMin === false || $ageMax === false) {
        return ['success' => false, 'message' => 'Les ages minimum et maximum sont obligatoires.', 'alert_type' => 'warning'];
    }

    $ageMin = (int) $ageMin;
    $ageMax = (int) $ageMax;

    if ($ageMin > $ageMax) {
        return ['success' => false, 'message' => 'L age minimum doit etre inferieur ou egal a l age maximum.', 'alert_type' => 'warning'];
    }

    $connect = getConnection();

    try {
        if (nomSectionExiste($nomSection, $connect)) {
            return ['success' => false, 'message' => 'Cette section existe deja.', 'alert_type' => 'warning'];
        }

        $overlap = sectionIntervalOverlap($genre, $ageMin, $ageMax, $connect);
        if ($overlap) {
            return [
                'success' => false,
                'message' => 'Chevauchement refuse: la section "' . (string) $overlap['nom_section'] . '" couvre deja les ages ' . (int) $overlap['age_min'] . '-' . (int) $overlap['age_max'] . ' pour ce genre.',
                'alert_type' => 'warning',
            ];
        }

        $stmt = $connect->prepare(
            'INSERT INTO section (nom_section, description, genre, age_min, age_max)
             VALUES (:nom_section, :description, :genre, :age_min, :age_max)'
        );
        $stmt->execute([
            ':nom_section' => $nomSection,
            ':description' => $description !== '' ? $description : null,
            ':genre' => $genre,
            ':age_min' => $ageMin,
            ':age_max' => $ageMax,
        ]);

        return [
            'success' => true,
            'message' => 'Section "' . $nomSection . '" ajoutee avec succes.',
            'alert_type' => 'success',
            'id_section' => (int) $connect->lastInsertId(),
        ];
    } catch (PDOException $e) {
        error_log('Create section error: ' . $e->getMessage());

        if ($e->getCode() === '23000') {
            return ['success' => false, 'message' => 'Cette section existe deja.', 'alert_type' => 'warning'];
        }

        return ['success' => false, 'message' => 'Erreur base de donnees pendant l ajout de la section.', 'alert_type' => 'danger'];
    }
}

function sessionExists(int $idSession, ?PDO $connect = null): bool
{
    if (class_exists('\Patro\Inscription\SessionService')) {
        $service = appContainer()->get(\Patro\Inscription\SessionService::class);
        return $service->sessionExists($idSession);
    }
    
    $connect = $connect ?: getConnection();
    $stmt = $connect->prepare('SELECT COUNT(*) FROM session WHERE id_session = :id_session');
    $stmt->execute([':id_session' => $idSession]);

    return (int) $stmt->fetchColumn() > 0;
}

function Addtheme(string $titre, int $sessionId): array
{
    if (class_exists('\Patro\Inscription\ThemeService')) {
        $service = appContainer()->get(\Patro\Inscription\ThemeService::class);
        return $service->addTheme($titre, $sessionId);
    }
    
    requireCsrfToken();
    
    if ($sessionId !== getActiveAdminSessionId()) {
        return ['success' => false, 'message' => 'Vous ne pouvez ajouter un thème que pour la session active.', 'alert_type' => 'danger'];
    }

    $titre = appCleanText($titre, 100);

    if ($titre === '') {
        return ['success' => false, 'message' => 'Le nom du thme est obligatoire.', 'alert_type' => 'warning'];
    }

    if ($sessionId <= 0) {
        return ['success' => false, 'message' => 'Veuillez choisir une session pour ce thme.', 'alert_type' => 'warning'];
    }

    $connect = getConnection();

    try {
        if (!sessionExists($sessionId, $connect)) {
            return ['success' => false, 'message' => 'Session invalide.', 'alert_type' => 'danger'];
        }

        if (titreExiste($titre, $connect, $sessionId)) {
            return ['success' => false, 'message' => 'Ce thme existe deja.', 'alert_type' => 'warning'];
        }

        $stmt = $connect->prepare(
            'INSERT INTO themes (titre, session_id)
             VALUES (:titre, :session_id)'
        );
        $stmt->execute([
            ':titre' => $titre,
            ':session_id' => $sessionId,
        ]);

        return [
            'success' => true,
            'message' => 'Le thme "' . $titre . '" a ete ajoute avec succes.',
            'alert_type' => 'success',
            'id_theme' => (int) $connect->lastInsertId(),
        ];
    } catch (PDOException $e) {
        error_log('Add theme error: ' . $e->getMessage());

        return ['success' => false, 'message' => 'Erreur base de donnees pendant l ajout de ce thme.', 'alert_type' => 'danger'];
    }
}

function getAllThemes(?PDO $connect = null): array
{
    if (class_exists('\Patro\Inscription\ThemeService')) {
        $service = appContainer()->get(\Patro\Inscription\ThemeService::class);
        return $service->getAllThemes();
    }
    
    $connect = $connect ?: getConnection();
    $stmt = $connect->prepare(
        'SELECT t.id, t.titre, t.session_id, a.ans, s.type_session
         FROM themes t
         INNER JOIN session s ON s.id_session = t.session_id
         INNER JOIN annee a ON a.idannee = s.annee_id
         WHERE t.session_id = :session_id
         ORDER BY t.titre ASC'
    );
    $stmt->execute([':session_id' => getActiveAdminSessionId()]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCurrentThemeTitle(?PDO $connect = null): string
{
    if (class_exists('\Patro\Inscription\ThemeService')) {
        $service = appContainer()->get(\Patro\Inscription\ThemeService::class);
        return $service->getCurrentThemeTitle();
    }
    
    $connect = $connect ?: getConnection();
    $stmt = $connect->prepare(
        'SELECT t.titre
         FROM themes t
         WHERE t.session_id = :session_id
         ORDER BY t.titre ASC
         LIMIT 1'
    );
    $stmt->execute([':session_id' => getActiveAdminSessionId()]);

    $titre = $stmt->fetchColumn();

    return $titre !== false ? (string) $titre : '';
}

function updateTheme(int $id, string $titre, int $sessionId): array
{
    if (class_exists('\Patro\Inscription\ThemeService')) {
        $service = appContainer()->get(\Patro\Inscription\ThemeService::class);
        return $service->updateTheme($id, $titre, $sessionId);
    }
    
    requireCsrfToken();
    
    if ($sessionId !== getActiveAdminSessionId()) {
        return ['success' => false, 'message' => 'Vous ne pouvez modifier un thème que pour la session active.', 'alert_type' => 'danger'];
    }

    $titre = appCleanText($titre, 100);

    if ($id <= 0) {
        return ['success' => false, 'message' => 'Thme invalide.', 'alert_type' => 'danger'];
    }

    if ($titre === '') {
        return ['success' => false, 'message' => 'Le nom du thme est obligatoire.', 'alert_type' => 'warning'];
    }

    if ($sessionId <= 0) {
        return ['success' => false, 'message' => 'Veuillez choisir une session pour ce thme.', 'alert_type' => 'warning'];
    }

    $connect = getConnection();

    try {
        if (!sessionExists($sessionId, $connect)) {
            return ['success' => false, 'message' => 'Session invalide.', 'alert_type' => 'danger'];
        }

        $existsStmt = $connect->prepare('SELECT COUNT(*) FROM themes WHERE id = :id AND session_id = :session_id');
        $existsStmt->execute([
            ':id' => $id,
            ':session_id' => getActiveAdminSessionId(),
        ]);
        if ((int) $existsStmt->fetchColumn() === 0) {
            return ['success' => false, 'message' => 'Thme introuvable.', 'alert_type' => 'danger'];
        }

        $dupStmt = $connect->prepare('SELECT COUNT(*) FROM themes WHERE titre = :titre AND session_id = :session_id AND id != :id');
        $dupStmt->execute([
            ':titre' => $titre,
            ':session_id' => $sessionId,
            ':id' => $id,
        ]);
        if ((int) $dupStmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Ce thme existe deja.', 'alert_type' => 'warning'];
        }

        $stmt = $connect->prepare(
            'UPDATE themes SET titre = :titre WHERE id = :id AND session_id = :session_id'
        );
        $stmt->execute([
            ':titre' => $titre,
            ':session_id' => $sessionId,
            ':id' => $id,
        ]);

        return ['success' => true, 'message' => 'Thme mis a jour avec succes.', 'alert_type' => 'success'];
    } catch (PDOException $e) {
        error_log('Update theme error: ' . $e->getMessage());

        return ['success' => false, 'message' => 'Erreur base de donnees pendant la mise a jour du thme.', 'alert_type' => 'danger'];
    }
}

function deleteTheme(int $id): array
{
    if (class_exists('\Patro\Inscription\ThemeService')) {
        $service = appContainer()->get(\Patro\Inscription\ThemeService::class);
        return $service->deleteTheme($id);
    }
    
    requireCsrfToken();
    
    if ($id <= 0) {
        return ['success' => false, 'message' => 'Thme invalide.', 'alert_type' => 'danger'];
    }

    $connect = getConnection();

    try {
        $stmt = $connect->prepare('DELETE FROM themes WHERE id = :id AND session_id = :session_id');
        $stmt->execute([
            ':id' => $id,
            ':session_id' => getActiveAdminSessionId(),
        ]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Thme introuvable.', 'alert_type' => 'warning'];
        }

        return ['success' => true, 'message' => 'Thme supprime avec succes.', 'alert_type' => 'success'];
    } catch (PDOException $e) {
        error_log('Delete theme error: ' . $e->getMessage());

        return ['success' => false, 'message' => 'Erreur base de donnees pendant la suppression du thme.', 'alert_type' => 'danger'];
    }
}

function generateAnimateurCodeValue(int $length = 10): string
{
    if (class_exists('\Patro\Animateur\AnimateurService')) {
        $service = appContainer()->get(\Patro\Animateur\AnimateurService::class);
        return $service->generateAnimateurCodeValue($length);
    }
    
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    $code = '';

    for ($i = 0; $i < $length; $i++) {
        $code .= $alphabet[random_int(0, $max)];
    }

    return $code;
}

function createUniqueAnimateurCode(PDO $connect, int $length = 10): string
{
    if (class_exists('\Patro\Animateur\AnimateurService')) {
        $service = appContainer()->get(\Patro\Animateur\AnimateurService::class);
        return $service->createUniqueAnimateurCode($connect, $length);
    }
    
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $code = generateAnimateurCodeValue($length);
        $stmt = $connect->prepare('SELECT COUNT(*) FROM code_inscription_animateur WHERE code = :code');
        $stmt->execute([':code' => $code]);

        if ((int) $stmt->fetchColumn() === 0) {
            return $code;
        }
    }

    throw new RuntimeException('Generation de code impossible.');
}

// Genere des codes a usage unique pour une session, sans attribution de section.
function createAnimateurCodes(int $idSession, int $idAdmin, int $quantite, ?string $dateExpiration = null): array
{
    if (appContainer()->has(\Patro\Application\Animateur\GenererCodesAnimateur::class)) {
        requireCsrfToken();
        $service = appContainer()->get(\Patro\Application\Animateur\GenererCodesAnimateur::class);
        return $service->execute(new \Patro\Application\Animateur\GenererCodesAnimateurCommand(
            $idSession,
            $idAdmin,
            $quantite,
            $dateExpiration,
            getActiveAdminSessionId(),
            app_int('ANIMATEUR_CODE_LENGTH', 10)
        ));
    }

    if (class_exists('\Patro\Animateur\AnimateurService')) {
        $service = appContainer()->get(\Patro\Animateur\AnimateurService::class);
        return $service->createAnimateurCodes($idSession, $idAdmin, $quantite, $dateExpiration);
    }
    
    requireCsrfToken();
    
    if ($idSession !== getActiveAdminSessionId()) {
        return ['success' => false, 'message' => 'Vous ne pouvez generer des codes que pour la session active.', 'codes' => []];
    }

    $quantite = max(1, min(100, $quantite));
    $connect = getConnection();
    $codes = [];

    try {
        $connect->beginTransaction();
        $insert = $connect->prepare(
            'INSERT INTO code_inscription_animateur (code, id_session, id_admin, date_expiration)
             VALUES (:code, :id_session, :id_admin, :date_expiration)'
        );

        for ($i = 0; $i < $quantite; $i++) {
            $code = createUniqueAnimateurCode($connect, app_int('ANIMATEUR_CODE_LENGTH', 10));
            $insert->execute([
                ':code' => $code,
                ':id_session' => $idSession,
                ':id_admin' => $idAdmin,
                ':date_expiration' => $dateExpiration ?: null,
            ]);
            $codes[] = $code;
        }

        $connect->commit();
        return ['success' => true, 'message' => count($codes) . ' code(s) genere(s).', 'codes' => $codes];
    } catch (Throwable $e) {
        if ($connect->inTransaction()) {
            $connect->rollBack();
        }
        error_log('Create animateur codes error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur pendant la generation des codes.', 'codes' => []];
    }
}

// Consomme un code et cree ou reinscrit l animateur atomiquement.
function registerAnimateurWithCode(string $code, string $nom, string $prenom, string $genre, string $tel, string $password, string $passwordConfirm): array
{
    if (appContainer()->has(\Patro\Application\Animateur\InscrireAnimateurParCode::class)) {
        requireCsrfToken();
        $attempts = $_SESSION['animateur_code_attempts'] ?? ['count' => 0, 'locked_until' => 0];
        if ((int) ($attempts['locked_until'] ?? 0) > time()) {
            return ['success' => false, 'message' => 'Trop de tentatives. Veuillez patienter avant de reessayer.', 'alert_type' => 'danger'];
        }
        $service = appContainer()->get(\Patro\Application\Animateur\InscrireAnimateurParCode::class);
        $result = $service->execute(new \Patro\Application\Animateur\InscrireAnimateurParCodeCommand(
            $code,
            $nom,
            $prenom,
            $genre,
            $tel,
            $password,
            $passwordConfirm,
            getActiveAdminSessionId()
        ));
        if (!$result['success'] && ($result['message'] ?? '') === 'Code invalide ou deja utilise.') {
            $count = (int) ($attempts['count'] ?? 0) + 1;
            $_SESSION['animateur_code_attempts'] = [
                'count' => $count,
                'locked_until' => $count >= 8 ? time() + 600 : 0,
            ];
        } elseif ($result['success']) {
            unset($_SESSION['animateur_code_attempts']);
        }
        return $result;
    }

    if (class_exists('\Patro\Animateur\AnimateurService')) {
        $service = appContainer()->get(\Patro\Animateur\AnimateurService::class);
        return $service->registerAnimateurWithCode($code, $nom, $prenom, $genre, $tel, $password, $passwordConfirm);
    }
    
    requireCsrfToken();
    
    $code = strtoupper(appCleanText($code, 20));
    $nom = appCleanText($nom, 120);
    $prenom = appCleanText($prenom, 120);
    $genre = normalizeAnimateurGenre($genre);
    $tel = normalizeIvorianPhone($tel);

    if ($code === '' || $nom === '' || $prenom === '' || $genre === ''|| $password === '' || $passwordConfirm === '') {
        return ['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.', 'alert_type' => 'warning'];
    }

    if (!isValidIvorianPhone($tel)) {
        return ['success' => false, 'message' => 'Numero de telephone ivoirien invalide.', 'alert_type' => 'warning'];
    }

    if ($password !== $passwordConfirm) {
        return ['success' => false, 'message' => 'Les mots de passe ne correspondent pas.', 'alert_type' => 'warning'];
    }

    if (strlen($password) < 8 || strlen($password) > 256) {
        return ['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caracteres.', 'alert_type' => 'warning'];
    }

    $attempts = $_SESSION['animateur_code_attempts'] ?? ['count' => 0, 'locked_until' => 0];
    $lockedUntil = (int) ($attempts['locked_until'] ?? 0);
    
    if ($lockedUntil > time()) {
        return ['success' => false, 'message' => 'Trop de tentatives. Veuillez patienter avant de reessayer.', 'alert_type' => 'danger'];
    }

    $connect = getConnection();

    try {
        $connect->beginTransaction();

        $currentSessionId = currentAnimateurSessionId($connect);
        
        $stmt = $connect->prepare(
            'SELECT c.*
             FROM code_inscription_animateur c
             WHERE c.code = :code
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([':code' => $code]);
        $codeRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$codeRow || (string) $codeRow['statut'] !== 'disponible') {
            $connect->rollBack();
            $_SESSION['animateur_code_attempts'] = [
                'count' => ((int) ($attempts['count'] ?? 0)) + 1,
                'locked_until' => ((int) ($attempts['count'] ?? 0)) + 1 >= 8 ? time() + 600 : 0,
            ];
            return ['success' => false, 'message' => 'Code invalide ou deja utilise.', 'alert_type' => 'danger'];
        }

        if (!empty($codeRow['date_expiration']) && strtotime((string) $codeRow['date_expiration']) < time()) {
            $connect->rollBack();
            return ['success' => false, 'message' => 'Ce code a expire. Veuillez demander un nouveau code.', 'alert_type' => 'warning'];
        }

        if ((int) $codeRow['id_session'] !== $currentSessionId) {
            $connect->rollBack();
            return ['success' => false, 'message' => 'Ce code ne correspond pas a la session en cours.', 'alert_type' => 'warning'];
        }

        // CORRECTION 1 : Ajout de la virgule entre genre_a et tel
        $find = $connect->prepare('SELECT id_animateur, nom_a, prenom_a, genre_a, tel, password, statut, created_at, updated_at FROM animateur WHERE tel = :tel LIMIT 1 FOR UPDATE');
        $find->execute([':tel' => $tel]);
        $animateur = $find->fetch(PDO::FETCH_ASSOC);
        $warning = '';
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($animateur) {
            $idAnimateur = (int) $animateur['id_animateur'];
            if (
                identifierLookupKey((string) $animateur['nom_a']) !== identifierLookupKey($nom)
                || identifierLookupKey((string) $animateur['prenom_a']) !== identifierLookupKey($prenom)
            ) {
                $warning = ' Le nom ou le prenom differe de la fiche existante.';
            }

            $update = $connect->prepare(
                'UPDATE animateur
                 SET nom_a = :nom,
                     prenom_a = :prenom,
                     genre_a = :genre,
                     password = :password,
                     statut = "actif"
                 WHERE id_animateur = :id_animateur'
            );
            $update->execute([
                ':nom' => $nom,
                ':prenom' => $prenom,
                ':genre' => $genre,
                ':password' => $passwordHash,
                ':id_animateur' => $idAnimateur,
            ]);
        } else {
            // CORRECTION 2 : Ajout de :genre dans les VALUES
            $insert = $connect->prepare(
                'INSERT INTO animateur (nom_a, prenom_a, genre_a, tel, password, statut)
                 VALUES (:nom, :prenom, :genre, :tel, :password, "actif")'
            );
            $insert->execute([
                ':nom' => $nom,
                ':prenom' => $prenom,
                ':genre' => $genre,
                ':tel' => $tel,
                ':password' => $passwordHash,
            ]);
            $idAnimateur = (int) $connect->lastInsertId();
        }

        $sessionInsert = $connect->prepare(
            'INSERT INTO animateur_session (id_animateur, id_session, id_code, id_section)
             VALUES (:id_animateur, :id_session, :id_code, NULL)'
        );
        $sessionInsert->execute([
            ':id_animateur' => $idAnimateur,
            ':id_session' => (int) $codeRow['id_session'],
            ':id_code' => (int) $codeRow['id_code'],
        ]);

        $consume = $connect->prepare(
            'UPDATE code_inscription_animateur
             SET statut = "utilise", id_animateur = :id_animateur, utilise_le = CURRENT_TIMESTAMP
             WHERE id_code = :id_code'
        );
        $consume->execute([
            ':id_animateur' => $idAnimateur,
            ':id_code' => (int) $codeRow['id_code'],
        ]);

        $connect->commit();
        unset($_SESSION['animateur_code_attempts']);

        return [
            'success' => true,
            'message' => 'Votre enregistrement animateur est confirme.' . $warning,
            'alert_type' => $warning === '' ? 'success' : 'warning',
            'id_animateur' => $idAnimateur,
        ];
    } catch (PDOException $e) {
        if ($connect->inTransaction()) {
            $connect->rollBack();
        }
        error_log('Register animateur error: ' . $e->getMessage());

        if ($e->getCode() === '23000') {
            return ['success' => false, 'message' => 'Cet animateur est deja enregistre pour cette session.', 'alert_type' => 'warning'];
        }

        return ['success' => false, 'message' => 'Erreur pendant l enregistrement. Veuillez reessayer.', 'alert_type' => 'danger'];
    } catch (Throwable $e) {
        if ($connect->inTransaction()) {
            $connect->rollBack();
        }
        error_log('Register animateur error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur pendant l enregistrement. Veuillez reessayer.', 'alert_type' => 'danger'];
    }
}

function loginAnimateur(string $nom_a, string $password): array
{
    if (appContainer()->has(\Patro\Application\Animateur\AuthentifierAnimateur::class)) {
        $service = appContainer()->get(\Patro\Application\Animateur\AuthentifierAnimateur::class);
        return $service->execute($nom_a, $password, getActiveAdminSessionId());
    }

    if (class_exists('\Patro\Animateur\AnimateurService')) {
        $service = appContainer()->get(\Patro\Animateur\AnimateurService::class);
        return $service->loginAnimateur($nom_a, $password);
    }
    
    $nom_a = appCleanText($nom_a, 120);
    if ($nom_a === '' || $password === '') {
        return ['success' => false, 'message' => 'Veuillez renseigner le nom et le mot de passe.'];
    }

    $connect = getConnection();
    $currentSessionId = currentAnimateurSessionId($connect);
    $stmt = $connect->prepare(
        'SELECT a.*, ans.id_animateur_session, ans.id_session, ans.id_section,
                sec.nom_section, sec.genre AS genre_section
         FROM animateur a
         INNER JOIN animateur_session ans
            ON ans.id_animateur = a.id_animateur
           AND ans.id_session = :id_session
         LEFT JOIN section sec ON sec.id_section = ans.id_section
         WHERE a.nom_a = :nom_a
         LIMIT 1'
    );
    $stmt->execute([
        ':nom_a' => $nom_a,
        ':id_session' => $currentSessionId,
    ]);
    $animateur = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$animateur || empty($animateur['password'])) {
        return ['success' => false, 'message' => 'Identifiants incorrects ou animateur non inscrit pour la session en cours.'];
    }

    $storedPassword = (string) $animateur['password'];
    $passwordHashValid = password_verify($password, $storedPassword);
    $legacyPasswordValid = !$passwordHashValid && hash_equals($storedPassword, md5($password));

    if (!$passwordHashValid && !$legacyPasswordValid) {
        return ['success' => false, 'message' => 'Identifiants incorrects.'];
    }

    if ($legacyPasswordValid) {
        try {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $update = $connect->prepare('UPDATE animateur SET password = :password WHERE id_animateur = :id_animateur');
            $update->execute([
                ':password' => $newHash,
                ':id_animateur' => (int) $animateur['id_animateur'],
            ]);
            $animateur['password'] = $newHash;
        } catch (PDOException $e) {
            error_log('Password rehash error (animateur): ' . $e->getMessage());
        }
    }

    if ((string) $animateur['statut'] === 'bloque') {
        return ['success' => false, 'message' => 'Votre compte animateur est bloque. Demandez un nouveau code a l administrateur pour la session en cours.'];
    }

    return ['success' => true, 'message' => 'Connexion reussie.', 'animateur' => $animateur];
}
// Bloque les animateurs actifs qui ne possedent pas de ligne animateur_session.
function blockAnimateursNotRegistered(int $idSession, ?PDO $connect = null): int
{
    if (class_exists('\Patro\Animateur\AnimateurService')) {
        $service = appContainer()->get(\Patro\Animateur\AnimateurService::class);
        return $service->blockAnimateursNotRegistered($idSession);
    }
    
    $connect = $connect ?: getConnection();
    $stmt = $connect->prepare(
        'UPDATE animateur a
         SET a.statut = "bloque"
         WHERE a.statut = "actif"
           AND a.id_animateur NOT IN (
             SELECT id_animateur FROM animateur_session WHERE id_session = :id_session
           )'
    );
    $stmt->execute([':id_session' => $idSession]);

    return $stmt->rowCount();
}

function validSessionTypes(): array
{
    return ['scolaire', 'vacance'];
}

function normalizeSessionType(?string $type, string $default = 'scolaire'): string
{
    $type = strtolower(trim((string) $type));
    return in_array($type, validSessionTypes(), true) ? $type : $default;
}

function sessionTypeLabel(?string $type): string
{
    return normalizeSessionType($type) === 'vacance' ? 'Vacance' : 'Scolaire';
}

function currentSessionType(): string
{
    return normalizeSessionType(getConfig('inscription_type_session', 'scolaire'));
}

function sectionBreakdownEnabled(?string $typeSession = null): bool
{
    return normalizeSessionType($typeSession, currentSessionType()) !== 'scolaire';
}

function getConfigDateDebut(): ?string
{
    return getConfig('inscription_date_debut');
}

function getConfigDateFin(): ?string
{
    return getConfig('inscription_date_fin');
}

function inscriptionForceFerme(): bool
{
    return getConfig('inscription_force_ferme', 'off') === 'on';
}

/**
 * Vérifie si les inscriptions sont ouvertes (selon la date ET le forçage)
 */
function inscriptionsOpen(): bool
{
    // Si forçage actif, c'est fermé
    if (inscriptionForceFerme()) {
        return false;
    }

    $debut = getConfigDateDebut();
    $fin = getConfigDateFin();

    // Sans période définie, c'est ouvert
    if (!$debut && !$fin) {
        return true;
    }

    $aujourdhui = date('Y-m-d');

    // Période complète
    if ($debut && $fin) {
        return $aujourdhui >= $debut && $aujourdhui <= $fin;
    }

    // Only debut
    if ($debut) {
        return $aujourdhui >= $debut;
    }

    // Only fin
    if ($fin) {
        return $aujourdhui <= $fin;
    }

    return true;
}

/**
 * Message si inscriptions fermées
 */
function inscriptionClosedMessage(): string
{
    $debut = getConfigDateDebut();
    $fin = getConfigDateFin();

    if (inscriptionForceFerme()) {
        return 'Les inscriptions sont actuellement fermees. Veuillez contacter l\'administrateur.';
    }

    if ($debut && $fin) {
        return sprintf(
            'Les inscriptions sont ouvertes du %s au %s.',
            date('d/m/Y', strtotime($debut)),
            date('d/m/Y', strtotime($fin))
        );
    }

    return 'Les inscriptions sont fermees.';
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

function findMatchingSection(string $genre, string $dateNaissance, ?int $referenceYear = null, ?PDO $connect = null, ?string $typeSession = null): ?array
{
    $genre = normalizeGenre($genre);
    if (!in_array($genre, validGenres(), true)) {
        return null;
    }

    $age = calculateAge($dateNaissance, $referenceYear);
    if ($age === null) {
        return null;
    }

    if (class_exists('\Patro\Inscription\SectionService')) {
        $service = appContainer()->get(\Patro\Inscription\SectionService::class);
        return $service->findMatchingSection($genre, $age, $typeSession);
    }

    // Session scolaire: pas de repartition par section, la section reste nulle (repartition par genre uniquement).
    if (!sectionBreakdownEnabled($typeSession)) {
        return null;
    }

    // Session vacance: repartition par section, croisee avec l'age.
    $connect = $connect ?: getConnection();
    $stmt = $connect->prepare(
        'SELECT id_section, nom_section, description, genre, age_min, age_max
         FROM section
         WHERE genre = :genre
           AND :age BETWEEN age_min AND age_max
         ORDER BY age_min ASC, age_max ASC, nom_section ASC
         LIMIT 1'
    );
    $stmt->execute([
        ':genre' => $genre,
        ':age' => $age,
    ]);

    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    return $section ?: null;
}

function determineSection(string $genre, string $dateNaissance, ?int $referenceYear = null, ?string $typeSession = null): ?string
{
    $matchedSection = findMatchingSection($genre, $dateNaissance, $referenceYear, null, $typeSession);
    return $matchedSection ? (string) $matchedSection['nom_section'] : null;
}
function getAnneeIdByValue(int $anneeVal, ?PDO $connect = null): ?int
{
    if (class_exists('\Patro\Inscription\SessionService')) {
        $service = appContainer()->get(\Patro\Inscription\SessionService::class);
        return $service->getAnneeIdByValue($anneeVal);
    }
    
    $connect = $connect ?: getConnection();
    $stmt = $connect->prepare('SELECT idannee FROM annee WHERE ans = :annee LIMIT 1');
    $stmt->execute([':annee' => $anneeVal]);
    $id = $stmt->fetchColumn();

    return $id === false ? null : (int) $id;
}

function getAnneeValueById(int $anneeId, ?PDO $connect = null): ?int
{
    if (class_exists('\Patro\Inscription\SessionService')) {
        $service = appContainer()->get(\Patro\Inscription\SessionService::class);
        return $service->getAnneeValueById($anneeId);
    }
    
    $connect = $connect ?: getConnection();
    $stmt = $connect->prepare('SELECT ans FROM annee WHERE idannee = :id LIMIT 1');
    $stmt->execute([':id' => $anneeId]);
    $value = $stmt->fetchColumn();

    return $value === false ? null : (int) $value;
}

function ensureAnnee(int $anneeVal, ?PDO $connect = null): int
{
    if (class_exists('\Patro\Inscription\SessionService')) {
        $service = appContainer()->get(\Patro\Inscription\SessionService::class);
        return $service->ensureAnnee($anneeVal);
    }
    
    $connect = $connect ?: getConnection();
    $stmt = $connect->prepare(
        'INSERT INTO annee (ans) VALUES (:annee)
         ON DUPLICATE KEY UPDATE ans = VALUES(ans)'
    );
    $stmt->execute([':annee' => $anneeVal]);

    return (int) getAnneeIdByValue($anneeVal, $connect);
}

function getDistinctYears(): array
{
    if (class_exists('\Patro\Inscription\SessionService')) {
        $service = appContainer()->get(\Patro\Inscription\SessionService::class);
        return $service->getDistinctYears();
    }
    
    try {
        $stmt = getConnection()->query('SELECT ans FROM annee ORDER BY ans DESC');
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (PDOException $e) {
        error_log('Distinct years error: ' . $e->getMessage());
        return [];
    }
}

function getInscritById(int $idInscrit, ?PDO $connect = null): array
{
    if ($connect === null && class_exists('\Patro\Domain\Inscription\Repository\InscriptionRepository')) {
        return (new \Patro\Domain\Inscription\Repository\InscriptionRepository(getConnection()))
            ->findById($idInscrit);
    }

    $connect = $connect ?: getConnection();
    $stmt = $connect->prepare(
        'SELECT u.*,
                i.id_inscription AS id_inscrit,
                i.id_inscription,
                i.id_session,
                i.id_section,
                i.identifiant,
                i.montant_inscription,
                i.prix_tee_shirt,
                i.taille_tee_shirt,
                i.etat,
                i.created_at,
                i.updated_at,
                s.nom_section AS section,
                s.nom_section,
                ses.type_session,
                a.idannee AS annee_id,
                a.ans AS annee
         FROM inscription i
         INNER JOIN utilisateur u ON u.id_utilisateur = i.id_utilisateur
         LEFT JOIN section s ON s.id_section = i.id_section
         INNER JOIN session ses ON ses.id_session = i.id_session
         INNER JOIN annee a ON a.idannee = ses.annee_id
         WHERE i.id_inscription = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $idInscrit]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function canAccessPublicInscrit(int $idInscrit): bool
{
    if (!empty($_SESSION['adpro'])) {
        return true;
    }

    return isset($_SESSION['last_inscrit_id']) && (int) $_SESSION['last_inscrit_id'] === $idInscrit;
}

function findInscritIdByIdentity(
    string $nom,
    string $prenom,
    string $dateNaissance,
    int $anneeId,
    string $typeSession,
    ?PDO $connect = null
): ?int
{
    $typeSession = normalizeSessionType($typeSession);
    if ($connect === null && class_exists('\Patro\Domain\Inscription\Repository\InscriptionRepository')) {
        return (new \Patro\Domain\Inscription\Repository\InscriptionRepository(getConnection()))
            ->findIdByIdentity($nom, $prenom, $dateNaissance, $anneeId, $typeSession);
    }

    $connect = $connect ?: getConnection();
    $stmt = $connect->prepare(
        'SELECT i.id_inscription
         FROM inscription i
         INNER JOIN utilisateur u ON u.id_utilisateur = i.id_utilisateur
         INNER JOIN session s ON s.id_session = i.id_session
         WHERE u.nom = :nom
           AND u.prenom = :prenom
           AND u.date_naissance = :date_naissance
           AND s.annee_id = :annee_id
           AND s.type_session = :type_session
         LIMIT 1'
    );
    $stmt->execute([
        ':nom' => $nom,
        ':prenom' => $prenom,
        ':date_naissance' => $dateNaissance,
        ':annee_id' => $anneeId,
        ':type_session' => $typeSession,
    ]);
    $id = $stmt->fetchColumn();

    return $id === false ? null : (int) $id;
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

/**
 * Genere l'identifiant metier SECTION-GENRE-ORDRE avec un compteur SQL verrouille.
 * Cette fonction doit etre appelee dans une transaction ouverte.
 */
function generateNextInscritIdentifiant(PDO $connect, ?string $section, string $genre, int $digits = 3): array
{
    $digits = max(1, $digits);
    $genreCode = genreCodeForIdentifier($genre);
    $section = trim((string) $section);
    $hasSection = $section !== '';
    $sectionCode = $hasSection ? sectionCodeForIdentifier($section) : null;
    $sequenceName = $hasSection
        ? sprintf('inscrits:%s:%s', $sectionCode, $genreCode)
        : sprintf('inscrits:GEN:%s', $genreCode);

    if ($hasSection) {
        $connect->prepare(
            'INSERT INTO identifiant_sequences (sequence_name, last_number)
             SELECT :sequence_name, GREATEST(
                 COUNT(*),
                 COALESCE(MAX(CAST(SUBSTRING_INDEX(identifiant, "-", -1) AS UNSIGNED)), 0)
             )
             FROM inscription i
             INNER JOIN section s ON s.id_section = i.id_section
             INNER JOIN utilisateur u ON u.id_utilisateur = i.id_utilisateur
             WHERE s.nom_section = :section
               AND u.genre = :genre
             ON DUPLICATE KEY UPDATE sequence_name = sequence_name'
        )->execute([
            ':sequence_name' => $sequenceName,
            ':section' => $section,
            ':genre' => $genre,
        ]);
    } else {
        // Session scolaire: pas de section, le compteur est partage par genre uniquement.
        $connect->prepare(
            'INSERT INTO identifiant_sequences (sequence_name, last_number)
             SELECT :sequence_name, GREATEST(
                 COUNT(*),
                 COALESCE(MAX(CAST(SUBSTRING_INDEX(identifiant, "-", -1) AS UNSIGNED)), 0)
             )
             FROM inscription i
             INNER JOIN utilisateur u ON u.id_utilisateur = i.id_utilisateur
             WHERE i.id_section IS NULL
               AND u.genre = :genre
             ON DUPLICATE KEY UPDATE sequence_name = sequence_name'
        )->execute([
            ':sequence_name' => $sequenceName,
            ':genre' => $genre,
        ]);
    }

    // FOR UPDATE verrouille la ligne du compteur jusqu'au COMMIT.
    $stmt = $connect->prepare(
        'SELECT last_number
         FROM identifiant_sequences
         WHERE sequence_name = :sequence_name
         FOR UPDATE'
    );
    $stmt->execute([':sequence_name' => $sequenceName]);
    $lastNumber = $stmt->fetchColumn();

    if ($lastNumber === false) {
        throw new RuntimeException('Compteur d identifiants introuvable.');
    }

    $nextNumber = (int) $lastNumber + 1;
    if (strlen((string) $nextNumber) > $digits) {
        throw new RuntimeException('La largeur configuree pour l ordre d inscription est depassee.');
    }

    $update = $connect->prepare(
        'UPDATE identifiant_sequences
         SET last_number = :last_number
         WHERE sequence_name = :sequence_name'
    );
    $update->execute([
        ':last_number' => $nextNumber,
        ':sequence_name' => $sequenceName,
    ]);

    $identifiant = $hasSection
        ? sprintf(
            '%s-%s-%s',
            $sectionCode,
            $genreCode,
            str_pad((string) $nextNumber, $digits, '0', STR_PAD_LEFT)
        )
        : sprintf(
            '%s-%s',
            $genreCode,
            str_pad((string) $nextNumber, $digits, '0', STR_PAD_LEFT)
        );

    return [
        'identifiant' => $identifiant,
        'ordre_inscription' => $nextNumber,
    ];
}

/**
 * Valide les donnees, genere l'identifiant metier et insere l'inscrit en une transaction PDO.
 */
function enregistrerInscrit(
    string $nom,
    string $prenom,
    string $dateNaissance,
    string $genre,
    string $tel,
    string $adresse,
    string $prixChoisi = '',
    string $tailleTeeShirt = '',
    ?int $annee = null
): array {
    requireCsrfToken();

    $container = $GLOBALS['patro_container'] ?? null;
    if ($container instanceof \Patro\Shared\Container
        && $container->has(\Patro\Application\Inscription\EnregistrerInscrit::class)) {
        $command = new \Patro\Application\Inscription\EnregistrerInscritCommand(
            $nom,
            $prenom,
            $dateNaissance,
            $genre,
            $tel,
            $adresse,
            $prixChoisi,
            $tailleTeeShirt,
            $annee ?: (int) date('Y'),
            currentSessionType(),
            inscriptionBaseAmount(),
            teeShirtPrice(),
            sectionBreakdownEnabled(currentSessionType()),
            app_int('IDENTIFIANT_ORDER_DIGITS', 3)
        );

        return $container->get(\Patro\Application\Inscription\EnregistrerInscrit::class)->execute($command);
    }
    
    $nom = appCleanText($nom, 120);
    $prenom = appCleanText($prenom, 120);
    $dateNaissance = trim($dateNaissance);
    $genre = normalizeGenre($genre);
    $tel = normalizeIvorianPhone($tel);
    $adresse = appCleanText($adresse, 180);
    $prixChoisi = trim($prixChoisi);
    $tailleTeeShirt = normalizeTeeShirtSize($tailleTeeShirt);
    $annee = $annee ?: (int) date('Y');
    $typeSession = currentSessionType();
    $montantBase = inscriptionBaseAmount();
    $prixTeeShirtConfigure = teeShirtPrice();
    $montantAvecTeeShirt = $montantBase + $prixTeeShirtConfigure;
    $montantInscription = 0;
    $prixTeeShirt = 0;

    if ($nom === '' || $prenom === '' || $dateNaissance === '' || $genre === '' || $tel === '' || $adresse === '') {
        return ['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.', 'alert_type' => 'danger'];
    }

    if (!in_array($genre, validGenres(), true)) {
        return ['success' => false, 'message' => 'Genre invalide.', 'alert_type' => 'danger'];
    }

    if (!isValidDateString($dateNaissance)) {
        return ['success' => false, 'message' => 'Date de naissance invalide.', 'alert_type' => 'danger'];
    }

    if (!isValidIvorianPhone($tel)) {
        return ['success' => false, 'message' => 'Numero de telephone ivoirien invalide.', 'alert_type' => 'danger'];
    }

    if ($prixChoisi === (string) $montantBase) {
        $montantInscription = $montantBase;
        $tailleTeeShirt = '';
    } elseif ($prixChoisi === (string) $montantAvecTeeShirt) {
        $montantInscription = $montantBase;
        $prixTeeShirt = $prixTeeShirtConfigure;
        if ($tailleTeeShirt === '') {
            return ['success' => false, 'message' => 'Veuillez selectionner la taille du tee-shirt.', 'alert_type' => 'warning'];
        }
    } else {
        return ['success' => false, 'message' => 'Montant d inscription invalide.', 'alert_type' => 'danger'];
    }

    $age = calculateAge($dateNaissance, $annee);
    if ($age === null) {
        return ['success' => false, 'message' => 'Date de naissance invalide.', 'alert_type' => 'danger'];
    }

    if ($age >= 25) {
        return ['success' => false, 'message' => 'Aucune inscription n\'est autorisé pour un age supérieur ou égale à 25 ans. Toutefois, vous pouvez vous inscrit en tant qu\'animateur. Pour plus information veuillez-vous rendre en présentiel.', 'alert_type' => 'warning'];
    }

    $section = findMatchingSection($genre, $dateNaissance, $annee, null, $typeSession);
    if ($section === null && sectionBreakdownEnabled($typeSession)) {
        return ['success' => false, 'message' => 'Aucune secion ne correspond a cet age et ce genre. Veuillez contacter l administrateur.', 'alert_type' => 'danger'];
    }

    $nomSection = $section['nom_section'] ?? null;
    $idSection = isset($section['id_section']) ? (int) $section['id_section'] : null;

    $connect = getConnection();

    try {
        $connect->beginTransaction();

        $idSession = ensureSession($annee, $typeSession, $connect);
        $anneeId = ensureAnnee($annee, $connect);
        $existingId = findInscritIdByIdentity($nom, $prenom, $dateNaissance, $anneeId, $typeSession, $connect);

        if ($existingId !== null) {
            $connect->commit();
            return [
                'success' => false,
                'message' => 'Cette personne est deja inscrite pour cette annee et ce type de session.',
                'alert_type' => 'warning',
                'id_inscrit' => $existingId,
            ];
        }

        $generatedIdentifier = generateNextInscritIdentifiant(
            $connect,
            $nomSection,
            $genre,
            app_int('IDENTIFIANT_ORDER_DIGITS', 3)
        );

        $userStmt = $connect->prepare(
            'INSERT INTO utilisateur (nom, prenom, date_naissance, genre, tel, adresse)
             VALUES (:nom, :prenom, :date_naissance, :genre, :tel, :adresse)
             ON DUPLICATE KEY UPDATE
                id_utilisateur = LAST_INSERT_ID(id_utilisateur),
                genre = VALUES(genre),
                tel = VALUES(tel),
                adresse = VALUES(adresse),
                updated_at = NOW()'
        );
        $userStmt->execute([
            ':nom' => $nom,
            ':prenom' => $prenom,
            ':date_naissance' => $dateNaissance,
            ':genre' => $genre,
            ':tel' => $tel,
            ':adresse' => $adresse,
        ]);
        $idUtilisateur = (int) $connect->lastInsertId();

        $stmt = $connect->prepare(
            'INSERT INTO inscription (identifiant, id_utilisateur, id_session, id_section, montant_inscription, prix_tee_shirt, taille_tee_shirt, etat)
             VALUES (:identifiant, :id_utilisateur, :id_session, :id_section, :montant_inscription, :prix_tee_shirt, :taille_tee_shirt, :etat)'
        );
        $stmt->execute([
            ':identifiant' => $generatedIdentifier['identifiant'],
            ':id_utilisateur' => $idUtilisateur,
            ':id_session' => $idSession,
            ':id_section' => $idSection,
            ':montant_inscription' => $montantInscription,
            ':prix_tee_shirt' => $prixTeeShirt,
            ':taille_tee_shirt' => $tailleTeeShirt !== '' ? $tailleTeeShirt : null,
            ':etat' => 'En attente',
        ]);

        $idInscrit = (int) $connect->lastInsertId();
        $connect->commit();

        return [
            'success' => true,
            'message' => 'Inscription enregistree avec succes.',
            'alert_type' => 'success',
            'id_inscrit' => $idInscrit,
            'identifiant' => $generatedIdentifier['identifiant'],
            'ordre_inscription' => $generatedIdentifier['ordre_inscription'],
            'section' => $nomSection,
            'id_section' => $idSection,
            'montant_inscription' => $montantInscription,
            'prix_tee_shirt' => $prixTeeShirt,
            'taille_tee_shirt' => $tailleTeeShirt,
            'annee_id' => $anneeId,
            'type_session' => $typeSession,
        ];
    } catch (PDOException $e) {
        if ($connect->inTransaction()) {
            $connect->rollBack();
        }
        error_log('Register inscrit error: ' . $e->getMessage());

        if ($e->getCode() === '23000') {
            $message = $e->getMessage();
            if (stripos($message, "key 'inscrits.tel'") !== false || stripos($message, "key 'tel'") !== false) {
                return [
                    'success' => false,
                    'message' => 'Ce numero de telephone est deja utilise. Si plusieurs enfants partagent ce numero, appliquez la migration de base de donnees pour retirer l ancienne contrainte unique sur tel.',
                    'alert_type' => 'warning',
                ];
            }

            return [
                'success' => false,
                'message' => 'Cette inscription existe deja ou viole une contrainte unique.',
                'alert_type' => 'warning',
            ];
        }

        return ['success' => false, 'message' => 'Erreur base de donnees pendant l inscription.', 'alert_type' => 'danger'];
    } catch (Throwable $e) {
        if ($connect->inTransaction()) {
            $connect->rollBack();
        }
        error_log('Register inscrit error: ' . $e->getMessage());

        return ['success' => false, 'message' => 'Erreur pendant la generation de l identifiant.', 'alert_type' => 'danger'];
    }
}

function nextRegistrationStepUrl(int $idInscrit): string
{
    return app_url('public/auth/confirmation_enregistrement.php') . '?' . http_build_query(['inscrit_id' => $idInscrit]);
}

function getAll(string $table, ?int $idSession = null, ?string $genderField = null, ?int $idSection = null): array
{
    try {
        $connect = getConnection();

        if ($table === 'inscription') {

            $sql = "
                SELECT
                    i.id_inscription,
                    i.identifiant,
                    u.nom,
                    u.prenom,
                    u.date_naissance,
                    u.genre,
                    u.tel,
                    u.adresse,
                    i.id_section,
                    sec.nom_section AS section,
                    i.montant_inscription,
                    i.prix_tee_shirt,
                    i.taille_tee_shirt,
                    i.etat
                FROM inscription i
                INNER JOIN utilisateur u
                    ON u.id_utilisateur = i.id_utilisateur
                LEFT JOIN section sec
                    ON sec.id_section = i.id_section
            ";

            $conditions = [];
            $params = [];

            if ($idSession !== null) {
                $conditions[] = "i.id_session = :id_session";
                $params[':id_session'] = $idSession;
            }

            if (!empty($genderField)) {
                $conditions[] = "u.genre = :genre";
                $params[':genre'] = $genderField;
            }

            // Afficher uniquement les inscrits validés
            $conditions[] = "i.etat = :etat";
            $params[':etat'] = 'inscrit';

            // Filtre par section
            if ($idSection !== null && $idSection > 0) {
                $conditions[] = "i.id_section = :id_section";
                $params[':id_section'] = $idSection;
            }

            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(" AND ", $conditions);
            }

            $sql .= " ORDER BY sec.nom_section, u.nom, u.prenom";

            $stmt = $connect->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $queries = [
            'annee' => 'SELECT idannee, ans, created_at FROM annee ORDER BY ans ASC',
            'admin' => 'SELECT id_admin, username, role, created_at FROM admin ORDER BY id_admin ASC',
        ];

        if (!isset($queries[$table])) {
            return [];
        }

        return $connect->query($queries[$table])->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log('Erreur getAll (' . $table . ') : ' . $e->getMessage());
        return [];
    }
}

function existe(string $table, string $field, mixed $value, string $type = 'one'): array
{
    $queries = [
        'inscription' => [
            'fields' => [
                'id_inscription' => 'i.id_inscription',
                'nom' => 'u.nom',
                'prenom' => 'u.prenom',
                'tel' => 'u.tel',
                'section' => 'sec.nom_section',
                'genre' => 'u.genre',
            ],
            'select' => 'SELECT i.id_inscription AS id_inscrit,
                                i.id_inscription,
                                i.identifiant,
                                u.nom,
                                u.prenom,
                                u.date_naissance,
                                u.genre,
                                u.tel,
                                u.adresse,
                                sec.nom_section AS section,
                                i.montant_inscription,
                                i.prix_tee_shirt,
                                i.taille_tee_shirt,
                                i.etat
                         FROM inscription i
                         INNER JOIN utilisateur u ON u.id_utilisateur = i.id_utilisateur
                         LEFT JOIN section sec ON sec.id_section = i.id_section',
        ],
        'annee' => [
            'fields' => ['idannee' => 'idannee', 'ans' => 'ans'],
            'select' => 'SELECT idannee, ans, created_at FROM annee',
        ],
        'admin' => [
            'fields' => ['id_admin' => 'id_admin', 'username' => 'username'],
            'select' => 'SELECT id_admin, username, role, created_at FROM admin',
        ],
    ];

    if (!isset($queries[$table], $queries[$table]['fields'][$field])) {
        return [];
    }

    $column = $queries[$table]['fields'][$field];
    $allowedColumns = array_values($queries[$table]['fields']);
    if (!in_array($column, $allowedColumns, true)) {
        return [];
    }

    $stmt = getConnection()->prepare($queries[$table]['select'] . " WHERE {$column} = :value");
    $stmt->execute([':value' => $value]);

    return $type === 'all'
        ? $stmt->fetchAll(PDO::FETCH_ASSOC)
        : ($stmt->fetch(PDO::FETCH_ASSOC) ?: []);
}

/**
 * Retourne l'ID de la session active pour l'administration.
 * La session active est définie par : année courante + type_session issu de la config.
 * Toute modification ne doit s'appliquer qu'à cette session.
 */
function getActiveAdminSessionId(): int
{
    if (class_exists('\Patro\Inscription\SessionService')) {
        $service = appContainer()->get(\Patro\Inscription\SessionService::class);
        return $service->getActiveAdminSessionId();
    }
    
    $annee = (int) date('Y');
    $type = currentSessionType();
    return ensureSession($annee, $type);
}


/**
 * Récupère l'URL d'une image d'activité en fonction de son ordre.
 * @param int $ordre  L'ordre recherché.
 * @param array $images  Le tableau d'images (déjà trié par ordre).
 * @param string $fallback  URL de secours si aucune image ne correspond.
 * @return string URL de l'image.
 */
function getActiviteImageByOrder(int $ordre, array $images, string $fallback = ''): string
{
    foreach ($images as $image) {
        if ((int) ($image['ordre'] ?? -1) === $ordre && !empty($image['id'])) {
            return activiteImageUrl((int) $image['id']);
        }
    }
    return $fallback;
}