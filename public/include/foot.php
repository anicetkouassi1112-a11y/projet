<?php
if (!empty($GLOBALS['patroFooterRendered'])) {
    return;
}

$GLOBALS['patroFooterRendered'] = true;
$assetBase = rtrim($assetBase ?? '../Backend/Assets', '/');
$assetVersion = '20260630';
?>
<footer class="fun-footer">
    <div class="fun-footer-inner">
        <section class="footer-brand" aria-label="Fun&Loisirs">
            <div class="navbar-brand">PATRO</div>
            <p>Un centre de loisirs professionnel dédié à l'épanouissement, à la créativité et à l'aventure de vos enfants.</p>
        </section>

        <section>
            <h2>Liens rapides</h2>
            <ul>
                <li><a href="<?= e(lien('accueil', [], true)) ?>">Accueil</a></li>
                <li><a href="<?= e(lien('apropos', [], true)) ?>">À propos</a></li>
                <li><a href="<?= e(lien('activites', [], true)) ?>">Activités</a></li>
            </ul>
        </section>

        <section>
            <h2>Infos pratiques</h2>
            <ul>
                <li>Horaires</li>
                <li>Règlement intérieur</li>
                <li>FAQ</li>
            </ul>
        </section>

        <section>
            <h2>Nous contacter</h2>
            <ul>
                <li>123, rue des Loisirs<br>75000 Paris</li>
                <li>01 23 45 67 89</li>
                <li>contact@fun-loisirs.fr</li>
                <li class="socials">
                    <a href="https://wa.me/+2250594236341?/text=Bonjour" aria-label="Whatsapp"><i class="bi bi-whatsapp"></i></a>
                </li>
            </ul>
        </section>

        <section>
            <h2>Nous Suivre</h2>
            <div class="socials" aria-label="Réseaux sociaux">
                <a href="https://www.facebook.com/" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                <a href="https://www.instagram.com/marcel_palivino" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
            </div>
        </section>
    </div>
    <div class="footer-bottom">
        © <?= date('Y') ?> Fun&Loisirs - Tous droits réservés&nbsp;&nbsp; | <a href="<?= e(lien('mentions_legales', [], true)) ?>">Mentions légales</a>&nbsp;&nbsp; | <a href="<?= e(lien('politique_confidentialite', [], true)) ?>">Politique de confidentialité</a>
    </div>
</footer>
<script src="<?= e($assetBase) ?>/js/bootstrap.bundle.min.js?v=<?= e($assetVersion) ?>"></script>
<script src="<?= e($assetBase) ?>/js/anime.min.js?v=<?= e($assetVersion) ?>"></script>
<script src="<?= e($assetBase) ?>/js/animations.js?v=<?= e($assetVersion) ?>"></script>
<script src="<?= e($assetBase) ?>/js/script.js?v=<?= e($assetVersion) ?>"></script>
</body>
</html>