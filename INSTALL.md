# Installation

## Prerequis

- PHP 8.3 ou plus recent avec extensions `pdo_mysql`, `fileinfo`, `mbstring` recommande.
- MySQL/MariaDB.
- Apache avec `mod_rewrite` et `mod_headers` recommande.
- Composer si les dependances PDF doivent etre reinstallees.

## Etapes

1. Copier `Backend/.env.example` vers `Backend/.env`.
2. Renseigner `APP_URL`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
3. Importer `Database/database.sql`. Ce fichier est le schema de reference
   versionne et peut etre rejoue sans supprimer la base ni les donnees existantes.
   Le compte initial `patro` utilise temporairement le mot de passe `1234` uniquement
   pour l'installation locale. Changez-le immédiatement via l'administration avant
   toute exposition réseau.
4. Verifier les permissions:
   - `storage/logs` writable par PHP.
   - `storage/activites` writable par PHP.
   - `Backend/.env` non accessible publiquement.
5. Lancer les controles:

```powershell
php tests/php_syntax_check.php
php tests/security_helpers_test.php
```

## Production

- `APP_DEBUG=false`
- `APP_FORCE_HTTPS=true`
- `ADMIN_SESSION_TIMEOUT_SECONDS=3600` ou moins selon la politique interne.
- Configurer la sauvegarde SQL et la rotation externe des logs si le trafic augmente.

## Nginx minimal

```nginx
location ~ /(?:\.env|\.git|Database|storage|admin/vendor) { deny all; }
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ { include fastcgi_params; fastcgi_pass 127.0.0.1:9000; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; }
```