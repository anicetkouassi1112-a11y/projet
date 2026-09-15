# Projet Patro

Application PHP/MySQL de gestion des inscriptions, animateurs, activites publiques et administration du centre Patro.

## Points forts actuels

- PHP 8.3 compatible, PDO avec requetes preparees.
- Sessions durcies: `HttpOnly`, `SameSite=Lax`, mode strict et regeneration apres connexion.
- CSRF sur les formulaires sensibles.
- Roles administrateur (`directeur`, `suppleant_1`, `suppleant_2`).
- Stockage des images d'activite hors webroot via `storage/activites`.
- Headers de securite HTTP, pages d'erreur, logs applicatifs et logs securite.
- Bootstrap 5.3 et Bootstrap Icons.

## Structure

- `public/`: portail public, auth animateur, diffusion media securisee.
- `admin/`: interface d'administration.
- `Backend/`: configuration, helpers, logique metier et assets.
- `Backend/bootstrap/app.php`: bootstrap central, session, erreurs et conteneur de dependances.
- `Backend/src/`: classes PSR-4 (`Domain`, `Http`, `Infrastructure`, services metier et securite).
- `Database/`: schema SQL de reference, rejouable sans suppression de la base.
- `storage/`: logs et fichiers uploades hors webroot.
- `tests/`: tests de fumee et de validation.

## Verification rapide

```powershell
php tests/php_syntax_check.php
php tests/bootstrap_test.php
php tests/security_helpers_test.php
```

## Securite

Ne versionnez jamais `Backend/.env`. Configurez HTTPS en production avec `APP_FORCE_HTTPS=true`, gardez `APP_DEBUG=false` et limitez les permissions d'ecriture a `storage/`.

Les points d'entree historiques chargent encore `Backend/functions.php` et
`Backend/utilitaire.php` comme façades HTTP temporaires. La connexion PDO n'est
plus accessible via un singleton legacy : les services applicatifs et CinetPay
recoivent desormais leurs dependances depuis le conteneur du bootstrap.