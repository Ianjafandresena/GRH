# GRH — Documentation projet

## Présentation
- **Objectif**: Application de gestion RH avec authentification sécurisée.
- **Stacks**:
  - Backend: `CodeIgniter 4`, PHP 8.1+, PostgreSQL
  - Frontend: `Angular 20` (standalone), thème fluide bleu/blanc
- **Base de données**: `PostgreSQL` base `grh`, user `postgres`, mot de passe `3435` (modifiable)

## Fonctionnalités principales
- **Authentification JWT**: login, me, logout via `/api/auth/*`
- **Sécurité**: hashage bcrypt, CORS configuré pour Angular dev, possibilité d’activer HTTPS et CSRF selon besoins
- **Schéma RH**: tables employé, congés, permissions, remboursements, etc. (voir `db/schema.sql`)
- **Seed initial**: création d’un compte admin (voir `db/seed.sql`)

## Pré-requis
- PHP 8.1+ avec extensions `pgsql`/`pdo_pgsql`
- Composer
- Node.js 18+ / npm
- PostgreSQL 14+ (ou compatible)

## Installation
1. Backend
   - Se placer dans `BACK/`
   - Installer dépendances: `composer install`
   - Lancer: `php spark serve --port 8080`
2. Frontend
   - Se placer dans `FRONT/`
   - `npm install`
   - Démarrer: `npm start` (http://localhost:4200)

## Configuration base de données
- Fichier: `BACK/app/Config/Database.php`
  - Déjà paramétré pour PostgreSQL: host `localhost`, base `grh`, user `postgres`, pass `3435`, port `5432`
  - Option recommandée: passer en variables d’environnement (`.env`) pour prod

## Initialisation de la base
1. Créer la base `grh` si nécessaire
2. Appliquer le schéma et seed (idempotents):
   - `psql -U postgres -d grh -f d:/GRH/db/schema.sql`
   - `psql -U postgres -d grh -f d:/GRH/db/seed.sql`
3. Compte admin par défaut (seed):
   - username: `admin`
   - mot de passe: `Admin@123`

## Sécurité & CORS
- CORS: `BACK/app/Config/Cors.php`
  - Origine autorisée: `http://localhost:4200`
  - Credentials: activés
- Filtres: `BACK/app/Config/Filters.php`
  - `cors` activé globalement
  - `forcehttps` désactivé en dev (à réactiver en prod)
- JWT
  - Secret: `env('JWT_SECRET', 'dev_change_me_secret')` dans `Auth.php`
  - Mettre `JWT_SECRET` en variable d’environnement pour la prod

## Routage
- Backend: `BACK/app/Config/Routes.php`
  - `/api/auth/login` (POST)
  - `/api/auth/me` (GET)
  - `/api/auth/logout` (POST)
- Frontend: `FRONT/src/app/app.routes.ts`
  - `/login` (page de connexion)

## Thème Frontend
- Composant login: `FRONT/src/app/login/`
  - `login.html`: thème professionnel bleu/blanc, layout fluide
- Intercepteur JWT: `FRONT/src/app/app.config.ts` attache `Authorization: Bearer <token>` si présent

## Structure du dépôt
```
GRH/
├─ BACK/
│  ├─ app/Config/{Database.php, Routes.php, Cors.php, Filters.php}
│  ├─ app/Controllers/{Auth.php, Home.php}
│  └─ app/Models/{UserModel.php}
├─ FRONT/
│  └─ src/app/{login/, services/auth.ts, app.routes.ts, app.config.ts}
└─ db/{schema.sql, seed.sql}
```

## Commandes utiles
- Backend: `php spark serve --port 8080`
- Frontend: `npm start`
- Test API me: `GET http://localhost:8080/api/auth/me` avec header `Authorization: Bearer <token>`

## Déploiement
- Activer HTTPS (`forcehttps`) et définir `JWT_SECRET`
- Externaliser les secrets en `.env`
- Configurer CORS selon le domaine de prod
