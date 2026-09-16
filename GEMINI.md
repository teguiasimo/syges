# Instructions Projet - SYGES

## Règles de développement et de documentation du code

- **Commentaires de code obligatoires** :
  - **Toujours commenter le code écrit** : Tout code ajouté, modifié ou généré (fonctions, méthodes, classes, composants React, hooks, routes, migrations, modèles, contrôleurs) doit être systématiquement et clairement documenté.
  - Expliquer le rôle, l'intention et le fonctionnement de chaque bloc de code ou logique complexe.
  - Fournir des DocBlocks / PHPDoc pour les méthodes et classes PHP (rôle, types de paramètres `@param`, type de retour `@return`).
  - Fournir des commentaires JSDoc / TSDoc pour les fonctions, types et composants TypeScript / React.

## Règles d'automatisation et d'actions système

- **Pas de commit ni de push automatique** : Ne JAMAIS exécuter de `git commit` ou de `git push` sans demande explicite de l'utilisateur.
- **Pas de tests live dans le navigateur** : Ne JAMAIS se connecter en direct ou lancer de tests dans le navigateur (`browser_subagent`) sans demande explicite préalable de l'utilisateur.

## Règles d'interface et d'expérience utilisateur (UI/UX)

- **Position et style des Toasts de notification** :
  - Tous les toasts de notification doivent TOUJOURS apparaître en **haut à droite** (`top-right`).
  - Les toasts de succès/validation doivent obligatoirement être de couleur **verte** (fond/bordure/icône verte).

- **Composant de tableau de données (Tables)** :
  - TOUS les tableaux de données de la plateforme SYGES doivent OBLIGATOIREMENT être implémentés avec la bibliothèque React **TanStack Table** (`@tanstack/react-table`).

