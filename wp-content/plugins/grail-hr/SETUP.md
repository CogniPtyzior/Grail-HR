# SETUP

Ce document décrit la création d'un environnement local WordPress/DDEV pour Grail HR, puis le démarrage du plugin et de son frontend Nuxt indépendant.

## Environnement recommandé

Le setup local recommandé sous Windows est :

- Windows 11 avec WSL 2.
- Ubuntu comme shell de développement.
- Docker Desktop installé côté Windows, avec intégration WSL activée pour Ubuntu.
- DDEV installé dans Ubuntu.
- Composer et Node.js disponibles dans Ubuntu.
- Node.js installé via `nvm` pour le frontend Nuxt.

Toutes les commandes ci-dessous sont à lancer depuis le terminal Ubuntu/WSL, sauf mention contraire.

## Variables de chemin

Adaptez ces chemins à votre machine :

```bash
export WINDOWS_USER="<WINDOWS_USER>"
export WP_ROOT="/mnt/c/Users/${WINDOWS_USER}/Desktop/Dev/Grail-HR"
export PLUGIN_DIR="${WP_ROOT}/wp-content/plugins/grail-hr"
export FRONTEND_DIR="${PLUGIN_DIR}/frontend/nuxt"
```

Dans ce guide :

- `WP_ROOT` désigne la racine WordPress et la racine DDEV.
- `PLUGIN_DIR` désigne le répertoire du plugin Grail HR.
- `FRONTEND_DIR` désigne l'application Nuxt indépendante.

## Installer les prérequis

### Docker Desktop

Installer Docker Desktop côté Windows, puis activer l'intégration WSL pour la distribution Ubuntu utilisée.

Vérifier depuis Ubuntu :

```bash
docker ps
```

### DDEV

Installer DDEV dans Ubuntu :

```bash
curl -fsSL https://ddev.com/install.sh | bash
```

Vérifier l'installation :

```bash
ddev version
ddev debug doctor
```

### Node.js avec nvm

Si `nvm` est déjà installé, charger l'environnement Node :

```bash
source ~/.nvm/nvm.sh
node -v
npm -v
```

Sinon, installer Node.js LTS avec la méthode habituelle de votre environnement, puis vérifier `node` et `npm` depuis Ubuntu.

## Créer ou préparer le projet WordPress

Créer le répertoire projet si nécessaire :

```bash
mkdir -p "$WP_ROOT"
cd "$WP_ROOT"
```

Si le dépôt existe déjà, se placer simplement à la racine WordPress :

```bash
cd "$WP_ROOT"
pwd
```

Le chemin attendu doit correspondre à votre projet, par exemple :

```text
/mnt/c/Users/<WINDOWS_USER>/Desktop/Dev/Grail-HR
```

## Configurer DDEV

A lancer seulement si le projet DDEV n'est pas encore configuré ou si la configuration doit être régénérée :

```bash
ddev config \
  --project-name=grail-hr \
  --project-type=wordpress \
  --docroot=. \
  --php-version=8.3 \
  --database=mariadb:10.6
```

Démarrer l'environnement :

```bash
ddev start
```

Vérifier l'état et les URLs :

```bash
ddev describe
```

La configuration DDEV du projet installe `poppler-utils` afin de fournir `pdftotext`, utilisé par l'extraction de texte des PDF.

## Installer WordPress

Télécharger WordPress en français :

```bash
ddev wp core download --locale=fr_FR
```

Créer `wp-config.php` avec les identifiants DDEV par défaut :

```bash
ddev wp config create \
  --dbname=db \
  --dbuser=db \
  --dbpass=db \
  --dbhost=db \
  --locale=fr_FR
```

Si `wp-config.php` existe déjà, vérifier les constantes de base :

```bash
grep "DB_NAME\|DB_USER\|DB_PASSWORD\|DB_HOST" wp-config.php
```

Valeurs attendues en DDEV :

```php
define( 'DB_NAME', 'db' );
define( 'DB_USER', 'db' );
define( 'DB_PASSWORD', 'db' );
define( 'DB_HOST', 'db' );
```

Installer WordPress :

```bash
ddev wp core install \
  --url=https://grail-hr.ddev.site \
  --title="Grail HR" \
  --admin_user=admin \
  --admin_password=admin \
  --admin_email=admin@grail-hr.local \
  --locale=fr_FR
```

Vérifier l'installation :

```bash
ddev wp core version
ddev wp core is-installed
```

Ouvrir le site et l'administration :

```bash
ddev launch
ddev launch /wp-admin
```

Identifiants locaux par défaut dans ce guide :

```text
Utilisateur : admin
Mot de passe : admin
```

## Installer le plugin Grail HR

Le plugin doit se trouver dans :

```text
wp-content/plugins/grail-hr
```

Installer les dépendances PHP depuis le répertoire du plugin :

```bash
cd "$PLUGIN_DIR"
composer install
```

Activer le plugin depuis la racine WordPress :

```bash
cd "$WP_ROOT"
ddev wp plugin activate grail-hr
```

Rafraîchir les règles de réécriture WordPress :

```bash
ddev wp rewrite flush
```

Vérifier la liste des plugins :

```bash
ddev wp plugin list
```

## Configurer l'application

Dans l'administration WordPress :

```text
https://grail-hr.ddev.site/wp-admin
```

Renseigner les paramètres nécessaires au plugin Grail HR, notamment la configuration API utilisée pour l'analyse de CV.

L'API REST locale est exposée sous :

```text
https://grail-hr.ddev.site/wp-json/grail-hr/v1
```

## Démarrer le frontend Nuxt

Installer les dépendances frontend :

```bash
cd "$FRONTEND_DIR"
source ~/.nvm/nvm.sh
npm install
```

Lancer l'application Nuxt en développement :

```bash
npm run dev
```

L'application dev est disponible sur :

```text
http://localhost:3000
```

Elle appelle l'API WordPress/DDEV réelle :

```text
https://grail-hr.ddev.site/wp-json/grail-hr/v1
```

## Commandes quotidiennes

Démarrer WordPress/DDEV :

```bash
cd "$WP_ROOT"
ddev start
ddev launch /wp-admin
```

Démarrer le frontend :

```bash
cd "$FRONTEND_DIR"
source ~/.nvm/nvm.sh
npm run dev
```

Voir les logs DDEV :

```bash
cd "$WP_ROOT"
ddev logs
```

Voir les logs en continu :

```bash
cd "$WP_ROOT"
ddev logs -f
```

Arrêter l'environnement :

```bash
cd "$WP_ROOT"
ddev stop
```

## Tests et qualité

Backend PHP depuis le conteneur DDEV :

```bash
cd "$WP_ROOT"
ddev exec --dir /var/www/html/wp-content/plugins/grail-hr composer test
```

Lint backend :

```bash
cd "$WP_ROOT"
ddev exec --dir /var/www/html/wp-content/plugins/grail-hr composer lint
```

Frontend :

```bash
cd "$FRONTEND_DIR"
source ~/.nvm/nvm.sh
npm test
```

Autres commandes frontend disponibles si nécessaire :

```bash
npm run typecheck
npm run lint
npm run build
```

## WP-CLI utile

Lister les utilisateurs :

```bash
cd "$WP_ROOT"
ddev wp user list
```

Lister les pages :

```bash
cd "$WP_ROOT"
ddev wp post list --post_type=page
```

Voir l'URL du site :

```bash
cd "$WP_ROOT"
ddev wp option get siteurl
ddev wp option get home
```

Vider le cache WordPress :

```bash
cd "$WP_ROOT"
ddev wp cache flush
```

## Base de données

Ouvrir MariaDB :

```bash
cd "$WP_ROOT"
ddev mysql
```

Exporter la base :

```bash
cd "$WP_ROOT"
ddev export-db --file=backup-grail-hr.sql.gz
```

Importer une base :

```bash
cd "$WP_ROOT"
ddev import-db --file=backup-grail-hr.sql.gz
```

Voir les tables WordPress :

```bash
cd "$WP_ROOT"
ddev wp db tables
```

## Debug

Activer Xdebug :

```bash
cd "$WP_ROOT"
ddev xdebug on
```

Désactiver Xdebug :

```bash
cd "$WP_ROOT"
ddev xdebug off
```

Entrer dans le conteneur web :

```bash
cd "$WP_ROOT"
ddev ssh
```

Vérifier PHP dans le conteneur :

```bash
cd "$WP_ROOT"
ddev exec php -v
ddev exec php -m
```

## Diagnostic

Diagnostic DDEV complet :

```bash
cd "$WP_ROOT"
ddev debug doctor
```

Voir la configuration DDEV :

```bash
cd "$WP_ROOT"
ddev debug configyaml
```

Voir les conteneurs Docker du projet :

```bash
docker ps --filter "label=com.ddev.site-name=grail-hr"
```

Vérifier les fichiers racine WordPress :

```bash
cd "$WP_ROOT"
ls -la index.php wp-admin wp-content wp-includes
```

Si WordPress a été téléchargé dans un sous-dossier `wordpress/`, déplacer son contenu à la racine WordPress avant de redémarrer DDEV :

```bash
cd "$WP_ROOT"
mv wordpress/* .
rmdir wordpress
ddev restart
```

## Architecture locale

```text
WP_ROOT
├── .ddev/
├── index.php
├── wp-admin/
├── wp-content/
│   └── plugins/
│       └── grail-hr/
│           ├── src/
│           ├── frontend/nuxt/
│           ├── public/
│           └── tests/
└── wp-includes/
```

DDEV se pilote depuis `WP_ROOT`. Le développement du plugin se fait dans `PLUGIN_DIR`. Le frontend Nuxt se lance depuis `FRONTEND_DIR`.
