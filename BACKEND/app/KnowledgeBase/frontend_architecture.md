# Architecture Frontend (Angular)

## Principes de Développement
- **Angular 18+**: Utilisation des `Standalone Components` (pas de `NgModules`).
- **Gestion d'état**: Utilisation massive des `Signals` (`signal`, `computed`, `effect`) pour une réactivité optimale et performante.
- **Style**: CSS/SCSS modulaire. Utilisation de variables CSS pour le thème (Cyan/Dark mode).
- **Communication**: Services injectés via `inject()`.

## Structure des Dossiers (`src/app/`)
- `module/`: Contient les modules métiers (conge, remboursement, employee, etc.). Chaque module a son propre dossier `page/`, `service/` et `model/`.
- `shared/`: Éléments transversaux (layout, UI components, chatbot, pipes).
- `core/`: Logique de base (auth, interceptors, guards).

## Navigation et Routage
Les routes sont définies dans `app.routes.ts`. La navigation se fait via `Router` ou `routerLink`.
Le menu latéral (`SidebarComponent`) gère l'accès aux différents modules.

## Notifications
Un système de "Toasts" est piloté par le `LayoutService` et affiché dans le `HeaderComponent`.
- `showSuccessMessage(msg)`: Notification verte.
- `showErrorMessage(msg)`: Notification rouge.
