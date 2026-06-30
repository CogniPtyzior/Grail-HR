# ARCHITECTURE

## Vue d’ensemble

Grail HR suit une architecture hexagonale pragmatique organisée par contextes métier.

WordPress est utilisé comme shell d’exécution : hooks, REST API, CPT, capabilities, admin et persistance.
Les règles métier restent isolées autant que possible des détails WordPress.

## Contextes

```text
IdentityAccess
→ login, tokens, utilisateur actif, capabilities.

CvIntake
→ upload PDF temporaire, validation fichier, extraction texte, nettoyage.

ProfileAnalysis
→ prompt, OpenAI, JSON structuré, validation et normalisation.

ProfileManagement
→ CPT, table custom, workflow, exports, recherche.

Settings
→ options OpenAI, rate limit, configuration admin.

Shared
→ logs, REST, sécurité, persistance, exceptions communes.
```

## Flux création depuis CV

```text
POST /profiles/from-cv
→ créer CPT profil
→ créer ligne analyse initiale
→ vérifier rate limit
→ valider PDF
→ stocker fichier temporaire privé
→ extraire texte
→ supprimer fichier temporaire
→ appeler OpenAI avec Structured Outputs
→ valider/normaliser JSON
→ synchroniser CPT + champs indexés
→ retourner profil
```

Si l’analyse PDF échoue, le profil reste créé avec `analysis_status = error`.
L’erreur technique est consignée dans les logs sécurisés et l’utilisateur peut relancer une analyse depuis la fiche.

## Flux création manuelle

```text
POST /profiles
→ valider DTO
→ créer CPT profil
→ créer ligne analyse vide
→ retourner profil éditable
```

## Authentification

Le frontend Nuxt appelle `/auth/login` avec les identifiants WordPress.
Le backend vérifie l’accès Grail HR et génère un token opaque.

Seul le hash du token est stocké en `wp_user_meta`.
Les contrôleurs REST imposent le bearer token, l'utilisateur actif et les capabilities avant chaque action protégée.

## Frontend

Nuxt est une SPA CSR indépendante du thème WordPress.
En développement, elle tourne sur `http://localhost:3000` et consomme l'API REST réelle du site WordPress DDEV.
En production, le build Nuxt est déployé comme application frontend dédiée et pointe vers `/wp-json/grail-hr/v1`.

Le design system appartient au plugin. Il ne dépend pas du thème WordPress actif.

## Environnement local

L'environnement local recommandé est Windows avec WSL/Ubuntu, Docker Desktop et DDEV.

DDEV est configuré et lancé depuis la racine WordPress :

```text
WP_ROOT
├── .ddev/
├── wp-admin/
├── wp-content/
│   └── plugins/
│       └── grail-hr/
└── wp-includes/
```

Le plugin reste la base de travail applicative dans `wp-content/plugins/grail-hr`. Le frontend Nuxt se trouve dans `frontend/nuxt` et consomme l'API REST WordPress/DDEV en développement.

Les commandes détaillées de création d'environnement, d'installation WordPress, de démarrage DDEV et de lancement Nuxt sont documentées dans `SETUP.md`.
