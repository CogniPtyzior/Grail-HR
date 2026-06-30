# Grail HR

Grail HR est un projet WordPress local orienté portfolio, centré sur un plugin métier d'analyse de CV assistée par IA et une application frontend Nuxt indépendante.

Le plugin permet aux utilisateurs WordPress autorisés de créer des profils candidats, d'importer temporairement un CV PDF, de générer une analyse structurée via OpenAI, puis de relire, modifier, valider, archiver et exporter les profils.

## Périmètre V1

- Plugin WordPress activable depuis l'administration.
- Application Nuxt SPA indépendante consommant l'API REST WordPress du plugin.
- Développement Nuxt sur `http://localhost:3000` avec API WordPress/DDEV réelle.
- Authentification par identifiants WordPress et token bearer stocké hashé en `user_meta`.
- CPT `grail_hr_profile` et table custom pour l'analyse structurée.
- PDF uniquement, 5 Mo maximum, sans OCR.
- CV source et texte extrait non conservés après analyse.
- OpenAI configurable dans l'administration WordPress.
- Recherche globale additive, filtres, tri et pagination serveur.
- Exports JSON/CSV, logs sécurisés et diagnostic.

## Organisation du dépôt

```text
Grail-HR/
├── .ddev/
├── README.md
├── LICENCE.md
├── wp-admin/
├── wp-content/
│   └── plugins/
│       └── grail-hr/
│           ├── grail-hr.php
│           ├── src/
│           ├── resources/
│           ├── frontend/nuxt/
│           └── tests/
└── wp-includes/
```

## Environnement local

L'environnement recommandé est Windows avec WSL/Ubuntu, Docker Desktop et DDEV.

DDEV se pilote depuis la racine WordPress `Grail-HR`. Le développement applicatif se fait principalement dans `wp-content/plugins/grail-hr`.

Voir [`wp-content/plugins/grail-hr/SETUP.md`](wp-content/plugins/grail-hr/SETUP.md) pour l'installation complète : création WordPress, configuration DDEV, installation Composer, démarrage Nuxt, Xdebug et tests.

## Documentation

- [`wp-content/plugins/grail-hr/SPECIFICATIONS.md`](wp-content/plugins/grail-hr/SPECIFICATIONS.md) décrit le périmètre fonctionnel.
- [`wp-content/plugins/grail-hr/ARCHITECTURE.md`](wp-content/plugins/grail-hr/ARCHITECTURE.md) décrit les contextes, couches, flux et choix techniques.
- [`wp-content/plugins/grail-hr/SETUP.md`](wp-content/plugins/grail-hr/SETUP.md) décrit l'installation, le développement et les commandes utiles.
- [`LICENCE.md`](LICENCE.md) précise les conditions d'utilisation propriétaire.

## Notes de sécurité

Le plugin est autonome et ne dépend d'aucun thème WordPress spécifique. L'application Nuxt embarque son propre design system, scopé sous `.grail-hr-app`, afin de limiter les conflits avec le thème actif.

Les accès sont portés par des utilisateurs WordPress actifs pour Grail HR. Les tokens sont opaques et seuls leurs hash sont stockés.
