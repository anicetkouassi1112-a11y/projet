<?php
if (!isset($assetBase)) {
    $assetBase = app_url('Backend/Assets');
}
$activePage = $currentPage ?? requestTextParam('page', 40);
if ($activePage === '') {
    $activePage = 'accueil';
}
$navItems = [
    'accueil' => ['label' => 'Accueil', 'url' => lien('accueil', [], true), 'icon' => 'bi-house-door'],
    'activites' => ['label' => 'Activités', 'url' => lien('activites', [], true), 'icon' => 'bi-bicycle'],
    'apropos' => ['label' => 'À propos', 'url' => lien('apropos', [], true), 'icon' => 'bi-chat-left-text'],
];
$isInteriorPage = $activePage !== 'accueil';
?>
<nav class="navbar navbar-expand-lg public-navbar<?= $isInteriorPage ? ' public-navbar-solid' : '' ?>" aria-label="Navigation principale">
    <div class="container-fluid public-nav-inner">
        <a class="navbar-brand" href="<?= e(lien('accueil', [], true)) ?>" aria-label="Centre de Loisirs - Accueil">
            <span>PATRO</span>
        </a>
        <button type="button" class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#publicNavbar" aria-controls="publicNavbar" aria-expanded="false" aria-label="Navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="publicNavbar" aria-label="Contenu de la navigation principale">
            <ul class="navbar-nav public-nav-links mx-auto align-items-lg-center">
                <?php foreach ($navItems as $key => $item): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $activePage === $key ? ' active' : '' ?>" href="<?= e($item['url']) ?>"<?= $activePage === $key ? ' aria-current="page"' : '' ?>>
                            <i class="bi <?= e($item['icon']) ?> me-1 d-lg-none"></i>
                            <?= e($item['label']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="public-nav-actions">
                <?php if (!empty($_SESSION['animateur'])): ?>
                    <a class="nav-link animateur-link" href="<?= e(app_url('public/views/animateur.php')) ?>">
                        <i class="bi bi-person-circle me-2"></i>Espace animateur
                    </a>
                <?php else: ?>
                    <a class="nav-link animateur-link" href="<?= e(app_url('public/auth/connexion.php')) ?>">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Connexion
                    </a>
                <?php endif; ?>
                <a class="btn nav-register" href="<?= e(app_url('public/inscription.php')) ?>">
                    <i class="bi bi-person-plus me-2"></i>Inscription
                </a>
            </div>
        </div>
    </div>
</nav>