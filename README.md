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
- `Database/`: schema SQL et migrations.
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

Le code historique charge encore `Backend/functions.php` et `Backend/utilitaire.php`.
Ces fichiers sont conserves comme façades de compatibilite pendant la migration ;
les nouveaux services doivent recevoir leurs dependances par le conteneur du bootstrap.