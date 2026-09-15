<?php
/**
 * Espace animateur — Accueil et catégories de jeux (Types de jeux).
 */
require_once __DIR__ . '/../../Backend/utilitaire.php';
requireAnimateur();

$animateur = currentAnimateur();
$sectionName = (string) ($animateur['nom_section'] ?? 'Section');
$idSection = (int) ($animateur['id_section'] ?? 0);

$connect = getConnection();

// 1. On récupère directement le genre de la section en base de données
$stmtGenre = $connect->prepare("SELECT genre FROM section WHERE id_section = :id LIMIT 1");
$stmtGenre->execute([':id' => $idSection]);
$genreSection = strtolower(trim((string) $stmtGenre->fetchColumn()));

// 2. Récupérer les différents types de jeux et le nombre de jeux par type
$stmtJeux = $connect->query(
    "SELECT type_jeu, COUNT(id) as total 
     FROM jeux 
     GROUP BY type_jeu 
     ORDER BY type_jeu ASC"
);
$typesJeux = $stmtJeux->fetchAll(PDO::FETCH_ASSOC);

// 3. Titre de l'onglet dynamique selon le genre
$pageTitle = ($genreSection === 'fille') 
    ? 'Espace animatrice - Bibliothèque de jeux' 
    : 'Espace animateur - Bibliothèque de jeux';

$assetBase = app_url('Backend/Assets');
$currentPage = 'animateur';

require_once __DIR__ . '/../include/head.php';
?>
<nav class="navbar navbar-expand-lg public-navbar" aria-label="Navigation animateur">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= e(app_url('public/views/animateur.php')) ?>">
            PATRO — <?= ($genreSection === 'fille') ? 'Animatrice' : 'Animateur' ?>
        </a>
        <div class="navbar-nav ms-auto flex-row align-items-center gap-2">
            <span class="nav-link text-muted py-0"><?= e(trim(($animateur['prenom_a'] ?? '') . ' ' . ($animateur['nom_a'] ?? ''))) ?> (<?= e($sectionName) ?>)</span>
            <a class="nav-link animateur-link text-danger" href="<?= e(app_url('public/auth/logout_animateur.php')) ?>">Déconnexion</a>
        </div>
    </div>
</nav>

<main class="container py-5">
    <div class="page-header mb-5 text-center">
        <h1 class="display-5 fw-bold text-primary">Bibliothèque d'animation</h1>
        <p class="lead text-muted">Explorez nos jeux par catégorie pour préparer vos activités.</p>
    </div>

    <?php if (empty($typesJeux)): ?>
        <div class="alert alert-info text-center shadow-sm" role="status">
            <i class="bi bi-info-circle fs-4 d-block mb-2"></i>
            <strong>Aucun jeu disponible.</strong><br>
            La bibliothèque est actuellement vide.
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($typesJeux as $type): 
                $nomType = empty($type['type_jeu']) ? 'Non classé / Autres' : $type['type_jeu'];
                $urlType = empty($type['type_jeu']) ? 'non_classe' : $type['type_jeu'];
            ?>
                <div class="col-md-6 col-lg-4">
                    <a href="jeux_liste.php?type=<?= urlencode($urlType) ?>" class="text-decoration-none">
                        <article class="card h-100 shadow-sm jeu-card border-0 bg-white transition-hover">
                            <div class="card-body text-center p-4">
                                <div class="activity-badge tone-purple mx-auto mb-3" style="width: 60px; height: 60px; display: grid; place-items: center; border-radius: 50%; background: var(--fun-purple); color: white; margin-top: -30px;">
                                    <i class="bi bi-controller fs-3"></i>
                                </div>
                                <h2 class="h4 card-title text-dark fw-bold mb-2"><?= e($nomType) ?></h2>
                                <span class="badge bg-light text-primary border rounded-pill px-3 py-2">
                                    <?= (int)$type['total'] ?> jeu(x) disponible(s)
                                </span>
                            </div>
                        </article>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<style>
.transition-hover { transition: transform 0.3s ease, box-shadow 0.3s ease; }
.transition-hover:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important; }
</style>

<?php require_once __DIR__ . '/../include/foot.php'; ?>