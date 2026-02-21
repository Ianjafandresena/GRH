# Guide du Développeur SI-GPRH

## Standards de Codage
- **Backend (PHP)**:
  - Utiliser le `ResourceController` pour les APIs REST.
  - Toujours encapsuler les opérations de base de données dans des `try/catch`.
  - Retourner du JSON via `$this->respond()`.
  - Logger les erreurs critiques via `log_message('error', ...)`.

- **Frontend (TypeScript/Angular)**:
  - Préférer les `Signals` aux `Behaviorsubjects` pour l'état local des composants.
  - Utiliser `inject()` au lieu de l'injection par constructeur.
  - Garder les composants `standalone`.
  - Utiliser le `LayoutService` pour les notifications utilisateur (Success/Error).

## Workflow de Transformation (Exemple)
Pour ajouter une fonctionnalité :
1. Ajouter la/les colonnes dans PostgreSQL.
2. Mettre à jour le `Model` dans `app/Models`.
3. Créer/Mettre à jour le `Controller` dans `app/Controllers`.
4. Mettre à jour le `Service` Angular dans `src/app/module/.../service`.
5. Créer la page ou le composant Angular.

## Structure des Services
- Les services Backend doivent être dans `app/Services`.
- Ils doivent être isolés et ne pas dépendre directement des contrôleurs.
