<?php

$currentSubPage = requestTextParam('page', 40);
if ($currentSubPage === '') {
    $currentSubPage = 'statistique';
}
?>
<ul class="list-group">
    <li class="list-group-item<?= $currentSubPage === 'statistique' ? ' active' : '' ?>">
        <a href="<?= lien('statistique') ?>">
            <i class="bi bi-bar-chart-line me-2"></i>Tableau de bord
        </a>
    </li>
    <li class="list-group-item<?= $currentSubPage === 'Tee_shirts' ? ' active' : '' ?>">
        <a href="<?= lien('Tee_shirts') ?>">
            <i class="bi bi-card-checklist"></i>Tee-shirts payes
        </a>
    </li>
    <li class="list-group-item<?= $currentSubPage === 'attente' ? ' active' : '' ?>">
        <a href="<?= lien('attente') ?>">
            <i class="bi bi-clock"></i>Inscriptions en attente
        </a>
    </li>
    <li class="list-group-item<?= $currentSubPage === 'add_section' ? ' active' : '' ?>">
        <a href="<?= lien('add_section') ?>">
            <i class="bi bi-collection"></i>Sections
        </a>
    </li>
    <li class="list-group-item<?= $currentSubPage === 'animateurs' ? ' active' : '' ?>">
        <a href="<?= lien('animateurs') ?>">
            <i class="bi bi-people-fill"></i>Animateurs
        </a>
    </li>
    <li class="list-group-item<?= $currentSubPage === 'animateur_session' ? ' active' : '' ?>">
        <a href="<?= lien('animateur_session') ?>">
            <i class="bi bi-diagram-3"></i>Sections animateurs
        </a>
    </li>
    <li class="list-group-item<?= $currentSubPage === 'liste_type_jeux' ? ' active' : '' ?>">
        <a href="<?= lien('liste_type_jeux') ?>">
            <i class="bi bi-controller"></i>Liste des Jeux
        </a>
    </li>
    <li class="list-group-item<?= $currentSubPage === 'accueil_admin' ? ' active' : '' ?>">
        <a href="<?= lien('accueil_admin') ?>">
            <i class="bi bi-house"></i>Accueil public
        </a>
    </li>
    <li class="list-group-item<?= $currentSubPage === 'activite_admin' ? ' active' : '' ?>">
        <a href="<?= lien('activite_admin') ?>">
            <i class="bi bi-activity"></i>Activites public
        </a>
    </li>
</ul>
<ul class="list-group sidebar-profile-menu">
    <li class="list-group-item<?= $currentSubPage === 'admin_config' ? ' active' : '' ?>">
        <a href="<?= lien('admin_config') ?>">
            <i class="bi bi-gear"></i>Configuration
        </a>
    </li>
    <li class="list-group-item<?= $currentSubPage === 'modifier_mot_de_passe' ? ' active' : '' ?>">
        <a href="<?= lien('modifier_mot_de_passe') ?>">
            <i class="bi bi-person-circle"></i>Profil
        </a>
    </li>
</ul>