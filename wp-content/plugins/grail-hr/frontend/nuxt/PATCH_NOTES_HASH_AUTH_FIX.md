# Patch Nuxt — hash router + session refresh

Correctifs ciblés appliqués au front Nuxt :

- Hydratation de la session Pinia depuis `sessionStorage` dans le middleware global avant toute redirection.
- Hydratation de la session avant chaque appel API pour éviter les faux `401` après refresh.
- Ajout d’un plugin client qui normalise les URLs double-hash `#/#/...` en `#/...` et protège `history.pushState` / `history.replaceState`.
- Simplification de `router.options.ts` avec `createWebHashHistory('/')` sans base dynamique.
- Sécurisation de `pages/index.vue` pour ne pas écraser une route hash déjà valide.
- Nettoyage défensif dans `app.vue` au démarrage.

Aucun changement API, backend ou schéma.
