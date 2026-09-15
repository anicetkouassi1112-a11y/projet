<?php
$routePage = $currentPage ?? requestTextParam('page', 40);
if ($routePage === '') {
    $routePage = 'statistique';
}

if ($routePage === 'statistique') {
    $anneeActive = displayYearFromRequest();
    $typeSessionActive = displaySessionTypeFromRequest();
} else {
    $anneeActive = activeYearFromRequest();
    $typeSessionActive = activeSessionTypeFromRequest();
}
$searchQuery = requestTextParam('search', 80);
$scriptName = basename((string) ($_SERVER['PHP_SELF'] ?? 'home.php'));
$adminRole = currentadminRole();
$homeRoute = app_url('admin/' . defaultadminRoute($adminRole));
$searchAction = app_url('admin/' . $scriptName);
$isRegistrationListPage = $routePage === 'Tee_shirts';
$showSearch = in_array($routePage, ['statistique', 'garcon', 'fille', 'Tee_shirts', 'attente'], true);
?>

<nav class="navbar navbar-expand-lg" aria-label="Navigation principale">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= e($homeRoute) ?>">
            <span>PATRO</span><span class="brand-suffix">admin</span>
        </a>
        <button type="button" class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNavbar" aria-label="Contenu de la navigation principale">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-2">
                <?php if ($showSearch && !$isRegistrationListPage): ?>
                    <li class="nav-item">
                        <form class="d-flex navbar-search-form" method="get" action="<?= e($searchAction) ?>" data-autosubmit-delay="350">
                            <input type="hidden" name="annee" value="<?= e($anneeActive) ?>">
                            <input type="hidden" name="type_session" value="<?= e($typeSessionActive) ?>">
                            <?php if ($routePage !== ''): ?>
                                <input type="hidden" name="page" value="<?= e($routePage) ?>">
                            <?php endif; ?>
                            <input class="form-control me-2" type="search" name="search" placeholder="Nom ou Prenom" value="<?= e($searchQuery) ?>" autocomplete="off">
                            <button class="btn btn-secondary" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </form>
                    </li>
                <?php endif; ?>
                <li class="nav-items">
                    <a class="dropdown-item" href="<?= e(app_url('admin/Auth/logout.php')) ?>">
                        <i class="bi bi-box-arrow-right me-2"></i>Se deconnecter
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>