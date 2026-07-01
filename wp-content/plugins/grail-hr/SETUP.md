# SETUP

Ce document décrit la mise en place d’un environnement local pour Grail HR.

Grail HR est un projet WordPress local orienté portfolio, centré sur :

- un plugin WordPress métier d’analyse de CV assistée par IA ;
- une API REST WordPress exposée par le plugin ;
- une application frontend Nuxt indépendante ;
- une intégration OpenAI configurable depuis l’administration WordPress.

Le dépôt contient une structure WordPress complète et la configuration DDEV du projet.

## Architecture locale attendue

```text
Grail-HR/
├── .ddev/
├── README.md
├── wp-admin/
├── wp-content/
│   └── plugins/
│       └── grail-hr/
│           ├── grail-hr.php
│           ├── src/
│           ├── resources/
│           ├── frontend/
│           │   └── nuxt/
│           ├── tests/
│           ├── composer.json
│           └── SETUP.md
└── wp-includes/
```

Dans ce guide :

- `WP_ROOT` désigne la racine WordPress et la racine DDEV du projet.
- `PLUGIN_DIR` désigne le répertoire du plugin Grail HR.
- `FRONTEND_DIR` désigne l’application Nuxt indépendante.

## Environnement recommandé

Le setup local recommandé sous Windows est :

- Windows 11 avec WSL 2 ;
- Ubuntu comme shell de développement ;
- Docker Desktop installé côté Windows ;
- intégration WSL activée dans Docker Desktop pour Ubuntu ;
- DDEV installé dans Ubuntu ;
- Composer disponible dans l’environnement DDEV ;
- Node.js et npm disponibles dans Ubuntu, idéalement via `nvm`.

Toutes les commandes ci-dessous sont à lancer depuis le terminal Ubuntu/WSL, sauf mention contraire.

## Variables de chemin

Adaptez ces chemins à votre machine :

```bash
export WINDOWS_USER="<votre-utilisateur-windows>"
export WP_ROOT="/mnt/c/Users/${WINDOWS_USER}/Desktop/Dev/Grail-HR"
export PLUGIN_DIR="${WP_ROOT}/wp-content/plugins/grail-hr"
export FRONTEND_DIR="${PLUGIN_DIR}/frontend/nuxt"
```

Vérifier les chemins :

```bash
echo "$WP_ROOT"
echo "$PLUGIN_DIR"
echo "$FRONTEND_DIR"
```

## Installer les prérequis

### Docker Desktop

Installer Docker Desktop côté Windows, puis activer l’intégration WSL pour la distribution Ubuntu utilisée.

Vérifier depuis Ubuntu :

```bash
docker ps
```

Si Docker répond sans erreur, l’intégration WSL est active.

### DDEV

Installer DDEV dans Ubuntu :

```bash
curl -fsSL https://ddev.com/install.sh | bash
```

Vérifier l’installation :

```bash
ddev version
ddev debug doctor
```

### Node.js avec nvm

Si `nvm` est déjà installé :

```bash
source ~/.nvm/nvm.sh
node -v
npm -v
```

Si `nvm` n’est pas installé, installer Node.js LTS avec la méthode habituelle de votre environnement, puis vérifier que `node` et `npm` sont disponibles depuis Ubuntu.

## Récupérer le projet

### Cas recommandé : cloner le dépôt complet

Depuis Ubuntu/WSL :

```bash
mkdir -p "$(dirname "$WP_ROOT")"
git clone https://github.com/CogniPtyzior/Grail-HR.git "$WP_ROOT"
cd "$WP_ROOT"
```

Si le dépôt existe déjà :

```bash
cd "$WP_ROOT"
git pull
```

Vérifier que le dépôt contient bien WordPress :

```bash
ls -la index.php wp-admin wp-content wp-includes
```

Si `wp-admin/`, `wp-content/` et `wp-includes/` existent, WordPress est déjà présent dans le dépôt. Ne relancez pas `wp core download`, sauf si vous souhaitez réinstaller WordPress volontairement.

### Cas alternatif : partir d’un WordPress vierge

Ce cas est utile uniquement si vous ne clonez pas le dépôt complet et souhaitez installer WordPress manuellement avant d’ajouter le plugin.

```bash
mkdir -p "$WP_ROOT"
cd "$WP_ROOT"
```

Puis suivre la section “Installer WordPress manuellement”.

## Configurer DDEV

La configuration DDEV est déjà fournie dans le dépôt :

```text
.ddev/config.yaml
```

Elle utilise notamment :

- type projet : `wordpress` ;
- docroot : `.` ;
- PHP 8.3 ;
- nginx-fpm ;
- MariaDB 10.6 ;
- Composer 2 ;
- `poppler-utils` pour fournir `pdftotext`.

Si la configuration existe déjà, démarrer simplement DDEV :

```bash
cd "$WP_ROOT"
ddev start
```

Vérifier l’état :

```bash
ddev describe
```

L’URL locale attendue est :

```text
https://grail-hr.ddev.site
```

### Régénérer la configuration DDEV

À utiliser seulement si `.ddev/` est absent ou si vous voulez régénérer la configuration :

```bash
cd "$WP_ROOT"

ddev config \
  --project-name=grail-hr \
  --project-type=wordpress \
  --docroot=. \
  --php-version=8.3 \
  --database=mariadb:10.6

ddev start
```

## Installer WordPress manuellement

Cette section est uniquement nécessaire si WordPress n’est pas déjà présent ou pas encore installé en base.

Télécharger WordPress en français :

```bash
cd "$WP_ROOT"
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

Vérifier l’installation :

```bash
ddev wp core version
ddev wp core is-installed
```

Ouvrir le site :

```bash
ddev launch
ddev launch /wp-admin
```

Identifiants locaux utilisés dans ce guide :

```text
Utilisateur : admin
Mot de passe : admin
```

## Installer les dépendances PHP du plugin

Le plugin doit se trouver dans :

```text
wp-content/plugins/grail-hr
```

Installer les dépendances PHP :

```bash
cd "$PLUGIN_DIR"
composer install
```

Si Composer n’est pas disponible côté hôte, lancer Composer dans le conteneur DDEV :

```bash
cd "$WP_ROOT"
ddev exec --dir /var/www/html/wp-content/plugins/grail-hr composer install
```

## Activer le plugin Grail HR

Depuis la racine WordPress :

```bash
cd "$WP_ROOT"
ddev wp plugin activate grail-hr
ddev wp rewrite flush
```

Vérifier l’activation :

```bash
ddev wp plugin status grail-hr
ddev wp plugin list
```

L’activation du plugin installe les réglages par défaut, les capabilities, les répertoires privés nécessaires et la table custom d’analyse.

## Configurer Grail HR dans WordPress

Ouvrir l’administration :

```text
https://grail-hr.ddev.site/wp-admin
```

Puis aller dans le menu Grail HR.

Configurer au minimum :

- la clé API OpenAI ;
- le modèle OpenAI ;
- la version de prompt ;
- la limite d’analyses par heure ;
- l’option CORS dev si l’application Nuxt tourne sur `localhost:3000`.

L’API REST locale est exposée sous :

```text
https://grail-hr.ddev.site/wp-json/grail-hr/v1
```

Important :

- la création manuelle de profil peut être testée sans clé OpenAI ;
- l’import et l’analyse de CV nécessitent une clé OpenAI valide ;
- en cas de quota ou billing OpenAI non configuré, l’analyse CV échouera côté backend.

## Accès utilisateur Grail HR

L’utilisateur administrateur local peut accéder à Grail HR grâce aux permissions administrateur.

Pour un autre utilisateur WordPress :

1. ouvrir sa fiche utilisateur dans l’administration WordPress ;
2. activer l’accès Grail HR ;
3. choisir le niveau d’accès ;
4. enregistrer.

Les niveaux fonctionnels V1 sont :

- recruteur ;
- manager.

## Démarrer le frontend Nuxt

Installer les dépendances frontend :

```bash
cd "$FRONTEND_DIR"
source ~/.nvm/nvm.sh
npm install
```

Lancer Nuxt en développement :

```bash
npm run dev
```

L’application est disponible sur :

```text
http://localhost:3000/#/login
```

Elle utilise le backend WordPress/DDEV réel :

```text
https://grail-hr.ddev.site/wp-json/grail-hr/v1
```

Si besoin, créer un fichier `.env` dans le dossier Nuxt :

```bash
cd "$FRONTEND_DIR"
cp .env.example .env
```

Valeur attendue en local :

```env
NUXT_PUBLIC_GRAIL_HR_API_BASE=https://grail-hr.ddev.site/wp-json/grail-hr/v1
```

## Vérification rapide après installation

Depuis la racine WordPress :

```bash
cd "$WP_ROOT"
ddev wp plugin status grail-hr
ddev wp rewrite flush
```

Tester l’API REST :

```bash
curl -k https://grail-hr.ddev.site/wp-json/grail-hr/v1/analysis/status
```

Ouvrir le frontend :

```text
http://localhost:3000/#/login
```

Se connecter avec :

```text
Utilisateur : admin
Mot de passe : admin
```

Tester dans cet ordre :

1. connexion ;
2. ouverture de la liste des profils ;
3. création d’un profil manuel ;
4. ouverture de la fiche créée ;
5. sauvegarde d’une modification ;
6. import CV PDF uniquement si OpenAI est configuré.

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

Voir les URLs DDEV :

```bash
cd "$WP_ROOT"
ddev describe
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

Arrêter l’environnement :

```bash
cd "$WP_ROOT"
ddev stop
```

Redémarrer complètement :

```bash
cd "$WP_ROOT"
ddev restart
```

## Logs Grail HR

Les logs applicatifs Grail HR sont écrits dans :

```text
wp-content/grail-hr-private/logs/
```

Afficher les derniers logs du jour :

```bash
cd "$WP_ROOT"
tail -n 80 wp-content/grail-hr-private/logs/grail-hr-$(date -u +%F).log
```

Si le fichier n’existe pas encore, déclencher une action dans l’application, puis réessayer.

## Tests et qualité

### Backend PHP

Depuis le conteneur DDEV :

```bash
cd "$WP_ROOT"
ddev exec --dir /var/www/html/wp-content/plugins/grail-hr composer test
```

Lint PHP :

```bash
cd "$WP_ROOT"
ddev exec --dir /var/www/html/wp-content/plugins/grail-hr composer lint
```

Correction automatique si disponible :

```bash
cd "$WP_ROOT"
ddev exec --dir /var/www/html/wp-content/plugins/grail-hr composer lint:fix
```

### Frontend Nuxt

Depuis le dossier frontend :

```bash
cd "$FRONTEND_DIR"
source ~/.nvm/nvm.sh
npm test
```

Autres commandes utiles :

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

Voir les profils Grail HR :

```bash
cd "$WP_ROOT"
ddev wp post list --post_type=grail_hr_profile
```

Voir l’URL du site :

```bash
cd "$WP_ROOT"
ddev wp option get siteurl
ddev wp option get home
```

Voir les réglages Grail HR :

```bash
cd "$WP_ROOT"
ddev wp option get grail_hr_settings
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

Vérifier la table d’analyse Grail HR :

```bash
cd "$WP_ROOT"
ddev wp db query "SHOW TABLES LIKE '%grail_hr_profile_analyses%';"
```

## PDF et extraction de texte

Grail HR accepte uniquement les fichiers PDF jusqu’à 5 Mo.

L’extraction de texte utilise `pdftotext`, fourni par `poppler-utils` dans la configuration DDEV du projet.

Vérifier la présence de `pdftotext` dans le conteneur :

```bash
cd "$WP_ROOT"
ddev exec which pdftotext
ddev exec pdftotext -v
```

Limites V1 :

- PDF uniquement ;
- pas d’OCR ;
- les PDF scannés ou sans texte exploitable peuvent échouer ;
- le fichier PDF temporaire est supprimé après extraction ;
- le texte brut extrait n’est pas conservé.

## CORS local

Le frontend Nuxt tourne sur :

```text
http://localhost:3000
```

L’API WordPress tourne sur :

```text
https://grail-hr.ddev.site
```

En local, l’option CORS dev doit autoriser :

```text
http://localhost:3000
http://127.0.0.1:3000
```

Le frontend utilise un token bearer. Les cookies cross-origin ne sont pas nécessaires.

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

Voir la configuration DDEV :

```bash
cd "$WP_ROOT"
ddev debug configyaml
```

Diagnostic DDEV complet :

```bash
cd "$WP_ROOT"
ddev debug doctor
```

Voir les conteneurs Docker du projet :

```bash
docker ps --filter "label=com.ddev.site-name=grail-hr"
```

## Dépannage courant

### La commande `ddev` est introuvable

Vérifier que vous êtes bien dans Ubuntu/WSL, pas dans PowerShell.

```bash
which ddev
ddev version
```

Si DDEV n’est pas installé dans Ubuntu, l’installer avec le script officiel.

### Docker ne répond pas depuis Ubuntu

Vérifier que Docker Desktop est lancé côté Windows et que l’intégration WSL est activée pour Ubuntu.

```bash
docker ps
```

### WordPress affiche une erreur base de données

Vérifier `wp-config.php` :

```bash
cd "$WP_ROOT"
grep "DB_NAME\|DB_USER\|DB_PASSWORD\|DB_HOST" wp-config.php
```

Valeurs attendues :

```php
define( 'DB_NAME', 'db' );
define( 'DB_USER', 'db' );
define( 'DB_PASSWORD', 'db' );
define( 'DB_HOST', 'db' );
```

### Le plugin n’est pas visible

Vérifier l’emplacement :

```bash
cd "$WP_ROOT"
ls -la wp-content/plugins/grail-hr/grail-hr.php
```

Puis :

```bash
ddev wp plugin list
```

### L’application Nuxt ne charge pas les profils

Vérifier :

```bash
cd "$FRONTEND_DIR"
cat .env
```

La variable attendue est :

```env
NUXT_PUBLIC_GRAIL_HR_API_BASE=https://grail-hr.ddev.site/wp-json/grail-hr/v1
```

Vérifier aussi que le backend est démarré :

```bash
cd "$WP_ROOT"
ddev describe
```

### Erreur CORS

Vérifier que l’option CORS dev est activée dans les réglages Grail HR.

L’origin attendue en local est :

```text
http://localhost:3000
```

### Erreur OpenAI 401 ou 429

Vérifier dans l’administration Grail HR :

- clé API OpenAI ;
- modèle configuré ;
- projet OpenAI ;
- billing ;
- quota.

Puis consulter les logs Grail HR :

```bash
cd "$WP_ROOT"
tail -n 80 wp-content/grail-hr-private/logs/grail-hr-$(date -u +%F).log
```

### PDF refusé

Vérifier :

- extension `.pdf` ;
- MIME PDF valide ;
- taille inférieure ou égale à 5 Mo ;
- PDF contenant du texte extractible ;
- pas de PDF scanné nécessitant OCR.

### WordPress a été téléchargé dans un sous-dossier `wordpress/`

Si WordPress a été téléchargé dans `wordpress/` au lieu de la racine :

```bash
cd "$WP_ROOT"
mv wordpress/* .
rmdir wordpress
ddev restart
```

Vérifier ensuite :

```bash
ls -la index.php wp-admin wp-content wp-includes
```

## Nettoyage local

Supprimer les caches Nuxt :

```bash
cd "$FRONTEND_DIR"
rm -rf .nuxt .output node_modules/.vite
```

Réinstaller les dépendances frontend :

```bash
cd "$FRONTEND_DIR"
source ~/.nvm/nvm.sh
npm install
```

Redémarrer DDEV :

```bash
cd "$WP_ROOT"
ddev restart
```

## URLs utiles

WordPress :

```text
https://grail-hr.ddev.site
```

Administration WordPress :

```text
https://grail-hr.ddev.site/wp-admin
```

API REST Grail HR :

```text
https://grail-hr.ddev.site/wp-json/grail-hr/v1
```

Frontend Nuxt :

```text
http://localhost:3000/#/login
```

Mailpit DDEV, si utilisé :

```bash
cd "$WP_ROOT"
ddev launch -m
```

## Résumé du lancement quotidien

Terminal 1, WordPress/DDEV :

```bash
export WINDOWS_USER="<votre-utilisateur-windows>"
export WP_ROOT="/mnt/c/Users/${WINDOWS_USER}/Desktop/Dev/Grail-HR"
export PLUGIN_DIR="${WP_ROOT}/wp-content/plugins/grail-hr"
export FRONTEND_DIR="${PLUGIN_DIR}/frontend/nuxt"

cd "$WP_ROOT"
ddev start
```

Terminal 2, Nuxt :

```bash
export WINDOWS_USER="<votre-utilisateur-windows>"
export WP_ROOT="/mnt/c/Users/${WINDOWS_USER}/Desktop/Dev/Grail-HR"
export PLUGIN_DIR="${WP_ROOT}/wp-content/plugins/grail-hr"
export FRONTEND_DIR="${PLUGIN_DIR}/frontend/nuxt"

cd "$FRONTEND_DIR"
source ~/.nvm/nvm.sh
npm run dev
```

Puis ouvrir :

```text
http://localhost:3000/#/login
```
