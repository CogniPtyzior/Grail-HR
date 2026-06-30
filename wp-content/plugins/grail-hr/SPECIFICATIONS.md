# SPECIFICATIONS

## Objectif

Grail HR est une application interne de création, analyse, enrichissement et validation de profils candidats à partir de CV PDF.

## Règles produit V1

- Le profil peut être créé avant l’analyse.
- Création manuelle de profil possible.
- Création depuis CV : le profil est créé, puis l’analyse PDF est lancée dans le même process.
- Si l’analyse échoue, le profil est conservé avec le statut d’analyse `error` pour permettre une reprise.
- Nouvelle analyse sur profil existant : l’analyse courante est remplacée, sans historique.
- Pas de portail candidat public.
- Pas d’OCR.
- Pas de matching d’offres.
- Pas de recherche dans `analysis_json`.

## Fichiers CV

- PDF uniquement.
- Taille maximale : 5 Mo.
- Nom de fichier temporaire anonymisé par GUID.
- Fichier supprimé après extraction du texte, avant l’appel OpenAI.
- Texte brut extrait conservé uniquement en mémoire.
- Aucun CV source et aucun texte brut ne sont persistés.

## Statuts

`analysis_status` :

```text
none, pending, extracting, analyzing, validating, completed, error
```

`review_status` :

```text
to_review, edited, validated, archived
```

## Recherche globale

La recherche globale est additive. Elle filtre le contenu du tableau sans réinitialiser les autres filtres ni le tri.

- Debounce : 350 ms.
- Longueur minimale : 2 caractères.
- Changement de recherche : retour à la page 1.
- Champs : titre, contenu, extrait, email, téléphone, localisation, métier principal, seniorité et résumé court.
- Pas de recherche dans le JSON d’analyse.

## Tableaux

Les tableaux sont responsives, paginés côté serveur, filtrés côté serveur et triés côté serveur.

Sur mobile, le tableau des profils passe en affichage carte.

## OpenAI

- Clé configurable dans l’admin WordPress.
- Clé affichable/masquable par icône de visibilité.
- Possibilité de mettre à jour la clé.
- Modèle configurable.
- Prompt et schéma versionnés, avec Structured Outputs JSON Schema côté OpenAI.
- Rate limit par défaut : 10 analyses par utilisateur par heure, configurable.

## Messages utilisateur

L’interface n’affiche jamais de détail technique ou sensible. Les détails techniques vont dans les logs sécurisés.

## Nuxt

- Dev : `http://localhost:3000`.
- Production : build Nuxt indépendant consommant l’API REST WordPress du plugin.
- Routage SPA côté frontend.
- CSS isolé sous `.grail-hr-app`.
- Classes préfixées `ghr-`.
- HTML propre, sémantique et accessible.

## Accès utilisateurs WordPress

- L’accès Grail HR est géré depuis la fiche utilisateur WordPress.
- Un utilisateur doit être actif via `grail_hr_is_active` pour se connecter, sauf administrateur.
- Niveaux V1 : recruteur et manager.
- Le recruteur voit ses propres profils ; le manager voit l’ensemble des profils.
- La désactivation d’un accès révoque le token actif.

## Admin WordPress

Pages prévues : tableau de bord, profils CV, réglages IA, accès utilisateurs, exports, logs et diagnostic.
