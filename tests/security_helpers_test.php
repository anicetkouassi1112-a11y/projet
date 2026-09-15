<?php

require_once dirname(__DIR__) . '/Backend/utilitaire.php';

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, 'Assertion failed: ' . $message . PHP_EOL);
        exit(1);
    }
}

assertTrue(e('<script>') === '&lt;script&gt;', 'HTML escaping must encode tags.');
assertTrue(publicRelativePath('activites/photo.jpg', 'activites') === 'activites/photo.jpg', 'Valid relative media path accepted.');
assertTrue(publicRelativePath('../Backend/.env') === '', 'Traversal path rejected.');
assertTrue(publicRelativePath('https://example.com/a.jpg') === '', 'Absolute URL rejected.');
assertTrue(isValidDateString('2026-07-24'), 'Valid ISO date accepted.');
assertTrue(!isValidDateString('2026-99-99'), 'Invalid ISO date rejected.');
assertTrue(normalizeIvorianPhone('+225 05 94 23 63 41') === '2250594236341', 'Phone normalization keeps digits.');
assertTrue(isValidIvorianPhone('0594236341'), 'Ivorian mobile number accepted.');
assertTrue(!isValidIvorianPhone('0294236341'), 'Invalid Ivorian prefix rejected.');

$schema = file_get_contents(dirname(__DIR__) . '/Database/database.sql');
assertTrue($schema !== false, 'Database schema must be readable.');
assertTrue(
    !preg_match('/^\s*DROP\s+DATABASE\b/im', $schema),
    'Production schema import must not drop the database.'
);
assertTrue(
    preg_match(
        "/\\('patro',\\s*'([^']+)'\\s*,\\s*'directeur'\\)/",
        $schema,
        $seedMatch
    ) === 1 && password_verify('1234', $seedMatch[1]),
    'Seed admin password must match the documented local bootstrap password.'
);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['csrf_token'] = 'known-token';
assertTrue(verifyCsrfToken('known-token'), 'Valid CSRF token accepted.');
assertTrue(!verifyCsrfToken('bad-token'), 'Invalid CSRF token rejected.');

echo 'Security helpers OK' . PHP_EOL;