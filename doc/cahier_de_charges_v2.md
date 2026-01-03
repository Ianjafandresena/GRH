# CAHIER DE CHARGES - SI-GPRH
## Système d'Information de Gestion des Ressources Humaines

**Version** : 2.0  
**Date** : 29 Décembre 2025  
**Organisation** : ARMP (Autorité de Régulation des Marchés Publics)

---

## TABLE DES MATIÈRES

1. [Présentation Générale](#1-présentation-générale)
2. [Architecture Technique](#2-architecture-technique)
3. [Module Gestion des Congés](#3-module-gestion-des-congés)
4. [Module Remboursements](#4-module-remboursements)
5. [Module Permissions](#5-module-permissions)
6. [Module Dashboard](#6-module-dashboard)
7. [Module Paramètres](#7-module-paramètres)
8. [Sécurité et Authentification](#8-sécurité-et-authentification)

---

## 1. PRÉSENTATION GÉNÉRALE

### 1.1 Objectif du Système

Le SI-GPRH est une solution complète de gestion des ressources humaines permettant :
- La gestion automatisée des congés et permissions
- Le suivi des remboursements médicaux
- La validation hiérarchique dynamique des demandes
- Un tableau de bord temps réel avec statistiques avancées

### 1.2 Périmètre Fonctionnel

- **Gestion des Congés** : Demandes, validation multi-niveaux, gestion des soldes
- **Remboursements Médicaux** : PEC, demandes agents/centres, états de remboursement
- **Permissions** : Demandes et approbations de permissions de courte durée
- **Tableau de Bord** : Statistiques, graphiques, widgets interactifs
- **Administration** : Paramètres, types de congés, utilisateurs

### 1.3 Utilisateurs Cibles

- **Employés** : Création de demandes (congés, permissions, remboursements)
- **Validateurs** : Chefs de service, Directeurs, RRH, DAAF, DG
- **Administrateurs RH** : Gestion complète du système
- **Centres de Santé** : Saisie des demandes de remboursement

---

## 2. ARCHITECTURE TECHNIQUE

### 2.1 Stack Technologique

#### Backend
- **Framework** : CodeIgniter 4 (PHP 8.1+)
- **Base de données** : PostgreSQL
- **API** : RESTful avec authentification JWT
- **Bibliothèques** :
  - `firebase/php-jwt` : Authentification
  - `dompdf/dompdf` : Génération PDF
  - `phpmailer/phpmailer` : Notifications email (optionnel)

#### Frontend
- **Framework** : Angular 17+ avec Signals
- **Architecture** : Standalone Components
- **Styling** : SCSS avec design system moderne
- **État** : Angular Signals (reactive programming)

### 2.2 Principes de Conception

- **Séparation des préoccupations** : Backend API / Frontend SPA
- **Fail-Safe** : Système résilient (emails optionnels)
- **Modern Angular** : Signals pour la réactivité
- **Sécurité** : JWT, CORS, validation des données
- **Responsive** : Mobile-first design

---

## 3. MODULE GESTION DES CONGÉS

### 3.1 Fonctionnalités Principales

#### 3.1.1 Création de Demande de Congé

**Formulaire** : `demandes/ajout`

**Champs** :
- Employé (sélection avec recherche)
- Type de congé (Annuel, Maladie, Maternité, etc.)
- Date début / Date fin
- Nombre de jours (calculé automatiquement)
- Motif / Observations
- Pièces justificatives (upload)

**Validation Formulaire** :
- Vérification du solde disponible
- Détection des chevauchements
- Validation des dates (début < fin)
- Contrôle des pièces requises selon le type

#### 3.1.2 Workflow de Validation Hiérarchique **DYNAMIQUE**

**Concept Clé** : La chaîne de validation s'adapte automatiquement selon :
- **Poste de l'employé** : Agent, Chef de Service, Directeur, DG
- **Direction** : DG, DAAF, SRH, etc.

**Détermination de la Chaîne** :

```
Service: CongeValidationService::getValidationSteps()
```

**Exemples de Chaînes** :

| Profil Employé | Chaîne de Validation |
|----------------|----------------------|
| Agent DG | Chef → Directeur → RRH → DAAF → **DG** |
| Agent DAAF | Chef → RRH → **DAAF** → DG |
| Agent SRH | Chef → **RRH** → DAAF → DG |
| Chef de Service | Directeur → RRH → DAAF → DG |
| Directeur | **DG** uniquement |
| Directeur Général | *(Aucune validation)* |

**Logique de Filtrage** :
1. **Construction de la chaîne complète** : Chef → Directeur → RRH → DAAF → DG
2. **Adaptation par Direction** :
   - Si Direction = DAAF → Retirer "Directeur" (DAAF est son propre directeur)
   - Si Direction = SRH → Retirer "Directeur" (RRH est son propre directeur)
3. **Filtrage par Poste** :
   - DG : Aucune validation
   - Directeur : Démarre à DG
   - Chef : Saute "Chef de Service"

#### 3.1.3 Système de Tokens Email (Fail-Safe)

**Génération Token** :
```php
EmailService::generateToken() // 64 caractères héxadécimaux
```

**Stockage** : Table `validation_cng`
- `val_token` : Token unique
- `val_token_expires` : Expiration (7 jours)
- `val_token_used` : Statut d'utilisation

**Modes de Validation** :

1. **Par Email** (Optionnel)
   - Lien avec token dans email
   - Actions : Approuver / Rejeter
   - Route : `GET /api/conge/email-validate?token=xxx&action=approve`

2. **Dans l'Application** (Toujours disponible)
   - Interface de validation
   - Route : `POST /api/validation_conge/approve`
   - Authentification JWT requise

**Fail-Safe** :
- ✅ Si email échoue → Validation enregistrée quand même en BD
- ✅ Système continue de fonctionner sans email
- ✅ Logs d'erreur mais pas de crash

#### 3.1.4 Gestion des Soldes

**Table** : `solde_cng`

**Champs** :
- `emp_code` : Employé
- `tp_cng_code` : Type de congé
- `sold_solde_init` : Solde initial (annuel)
- `sold_solde_dispo` : Solde disponible
- `sold_annee` : Année de référence

**Opérations** :
- **Débit automatique** : Lors de l'approbation finale
- **Crédit automatique** : En début d'année (job batch)
- **Historique** : Table `debit_solde_cng`

#### 3.1.5 Décisions et Suivi

**Interface de Liste** : `conge/index`

**Filtres** :
- Par statut (En attente, Validé, Rejeté)
- Par employé
- Par date
- Par type de congé

**Actions** :
- Consulter détails
- Valider (si validateur actuel)
- Rejeter avec motif
- Historique des validations

**Affichage Détails** : `conge/detail/:id`
- Informations complètes
- Timeline de validation
- Statut de chaque étape
- Documents joints
- Décisions prises

### 3.2 API Backend

#### Endpoints Principaux

```
POST   /api/conge/                     Créer une demande
GET    /api/conge/                     Liste des congés
GET    /api/conge/:id                  Détails d'un congé
PUT    /api/conge/:id                  Modifier un congé
DELETE /api/conge/:id                  Supprimer un congé

GET    /api/validation_conge/status/:id           Statut validation
GET    /api/validation_conge/current/:id          Étape actuelle
POST   /api/validation_conge/approve              Approuver
POST   /api/validation_conge/reject               Rejeter
GET    /api/validation_conge/pending/:emp_code    En attente pour validateur

GET    /api/conge/email-validate                  Validation par email (token)
GET    /api/conge/:id/attestation                 PDF attestation
```

### 3.3 Base de Données

#### Tables Principales

**`conge`**
- `cng_code` : PK
- `emp_code` : FK → employee
- `tp_cng_code` : FK → type_conge
- `cng_debut`, `cng_fin` : Dates
- `cng_nb_jour` : Nombre de jours
- `cng_motif` : Motif
- `cng_status` : Boolean (validé/non)
- `cng_date_demande` : Date création

**`validation_cng`**
- `val_code` : PK
- `cng_code` : FK → conge
- `sign_code` : FK → signature (type de validateur)
- `emp_validateur` : Employé validateur
- `val_status` : Boolean NULL (en attente), TRUE (approuvé), FALSE (rejeté)
- `val_date` : Date de décision
- `val_motif_rejet` : Si rejeté
- `val_token` : Token email
- `val_token_expires` : Expiration token

**`solde_cng`**
- `sold_code` : PK
- `emp_code` : FK
- `tp_cng_code` : FK
- `sold_solde_init` : Solde départ
- `sold_solde_dispo` : Solde restant
- `sold_annee` : Année

**`debit_solde_cng`**
- Historique des débits/crédits

---

## 4. MODULE REMBOURSEMENTS

### 4.1 Fonctionnalités Principales

#### 4.1.1 Modes de Demande

**Deux Modes Distincts** :

1. **Mode Agent** (`rem_is_centre = false`)
   - L'employé crée sa demande
   - Factures personnelles
   - Validation classique

2. **Mode Centre de Santé** (`rem_is_centre = true`)
   - Le centre saisit pour plusieurs employés
   - Factures groupées
   - Workflow spécifique

**Basculement** : Toggle dans `demandes/ajout`

#### 4.1.2 Prise en Charge (PEC)

**Concept** : Autorisation préalable de soins

**Formulaire PEC** : `pris-en-charge/ajout`

**Champs** :
- **Employé** : Titulaire
- **Bénéficiaire** : Agent, Conjoint, Enfant
- **Centre de Santé** : Où les soins seront effectués
- **Montant plafonné** : Limite de remboursement
- **Dates validité** : Début / Fin
- **Type de soins** : Consultation, Chirurgie, etc.

**Numérotation Automatique** :
```
Format : NNN/ARMP/DG/DAAF/[SERVICE]/[MOIS]-YY
Exemple : 001/ARMP/DG/DAAF/SRH/FC-25
```

**Statuts** :
- ⚠️ **Non Validée** : Créée mais non approuvée
- ✅ **Validée** : Approuvée par RH
- ❌ **Expirée** : Date de fin dépassée

**Workflow PEC** :
1. Employé crée PEC
2. RH valide (`pec_approuver = true`)
3. Assignation automatique du centre choisi (`cen_code`)
4. Utilisation dans demandes de remboursement

#### 4.1.3 Demandes de Remboursement

**Mode Agent** :

Formulaire : `demandes/ajout` (toggle "Agent")

1. Sélection employé
2. Sélection PEC (uniquement celles validées de l'employé)
3. Ajout factures :
   - Objet de la facture
   - Montant
   - Upload scan

**Mode Centre de Santé** :

Formulaire : `demandes/ajout` (toggle "Centre")

1. Sélection Centre de Santé
2. Sélection PEC :
   - **PEC validées** du centre choisi
   - **PEC non validées** (tous centres) → Validation inline avec centre verrouillé
3. Ajout factures pour le bénéficiaire

**Numérotation Automatique des Demandes** :
```
Format : NNN/ARMP/DG/DAAF/[SERVICE]/[MOIS]-YY
```

Génération : `DemandeRembController::generateNumDemande()`
- Séquentiel global
- Intègre le service de l'employé
- Intègre le mois de création

**Validation PEC Inline** (Mode Centre) :

Si PEC non validée sélectionnée :
1. Modal s'ouvre automatiquement
2. **Centre verrouillé** sur celui choisi
3. Validation enregistrée
4. Demande créée avec PEC nouvellement validée

#### 4.1.4 États de Remboursement

**Concept** : Regroupement de demandes pour paiement

**Création État** : `etats/create`

- Sélection des demandes approuvées non encore payées
- Génération automatique du numéro d'état :
  ```
  Format : NNN/ARMP/DG/DAAF/SERVICE/MOIS-YY
  ```
- Création de l'état avec liste des demandes
- Changement statut demandes → "Dans état"

**PDF État de Remboursement** :

Route : `GET /api/etat_remb/:id/pdf`

**Format du PDF** :
- Orientation : Paysage (A4)
- En-tête : Informations état + mois
- Tableau :
  | N° Facture | Acte | N° PEC | PEC N° | Agent | Malade | Lien | Montant |
- Pied : Total

Technologie : `Dompdf`

**Liste États** : `etats/index`
- Filtres par date, statut
- Bouton "Télécharger PDF" pour chaque état
- Détails avec liste des demandes incluses

#### 4.1.5 Vue Détails Demande

Route : `demandes/detail/:id`

**Informations Affichées** :
- **N° Demande** : `rem_num` (auto-généré)
- **Badge Type** : 👤 Agent ou 🏥 Centre
- **Employé** : Nom, matricule
- **Bénéficiaire** :
  - N° PEC (`pec_num`)
  - Nom bénéficiaire
  - Lien (Agent, Conjoint, Enfant)
  - Centre de santé associé
- **Factures** : Liste avec objets et montants
- **Montant Total**
- **Statut** : En attente / Approuvé / Rejeté

### 4.2 API Backend

```
POST   /api/pris_en_charge/                       Créer PEC
GET    /api/pris_en_charge/                       Liste PECs
GET    /api/pris_en_charge/:id                    Détails PEC
POST   /api/pris_en_charge/:id/validate           Valider PEC

POST   /api/demande_remb/batch                    Créer demandes (mode batch)
GET    /api/demande_remb/                         Liste demandes
GET    /api/demande_remb/:id                      Détails demande

POST   /api/etat_remb/                            Créer état
GET    /api/etat_remb/                            Liste états
GET    /api/etat_remb/:id/pdf                     PDF état

GET    /api/centre_sante/                         Liste centres
```

### 4.3 Base de Données

**`pris_en_charge`**
- `pec_code` : PK
- `pec_num` : N° auto-généré
- `emp_code` : FK → employee
- `beneficiaire_code` : FK → beneficiaire
- `cen_code` : FK → centre_sante
- `pec_approuver` : Boolean (validé ou non)
- `pec_montant_plafond` : Limite
- `pec_date_debut`, `pec_date_fin` : Validité

**`demande_remb`**
- `rem_code` : PK
- `rem_num` : N° auto-généré
- `emp_code` : FK → employee
- `pec_code` : FK → pris_en_charge
- `cen_code` : FK → centre_sante (si mode centre)
- **`rem_is_centre`** : Boolean (Agent = false, Centre = true)
- `rem_montant` : Total
- `rem_status` : Boolean (traité/non)
- `rem_date` : Date création

**`facture`**
- `fac_code` : PK
- `rem_code` : FK → demande_remb
- `obj_code` : FK → objet_facture
- `fac_montant` : Montant
- `fac_fichier` : Scan (chemin)

**`etat_remb`**
- `eta_code` : PK
- `etat_num` : N° auto-généré
- `eta_date_creation` : Date
- Liste des demandes liées

---

## 5. MODULE PERMISSIONS

### 5.1 Fonctionnalités

**Concept** : Autorisations de courte durée (quelques heures)

**Formulaire** : `permission/create`

**Champs** :
- Employé
- Date et heure début
- Date et heure fin
- Durée (calculée en heures)
- Motif

**Validation** :
- Similaire aux congés mais simplifiée
- Généralement 1-2 niveaux (Chef → Directeur)

**API** :
```
POST   /api/permission/
GET    /api/permission/
GET    /api/permission/:id
```

---

## 6. MODULE DASHBOARD

### 6.1 Tableau de Bord Principal

Route : `/` (page d'accueil après login)

#### 6.1.1 Cards Statistiques

**4 Cards Principales** :

1. **Total Employés**
   - Nombre total
   - Nombre actifs
   - Évolution vs mois dernier

2. **Congés en Cours**
   - Nombre d'employés actuellement en congé
   - Évolution

3. **Permissions**
   - En attente de validation
   - Statut

4. **Remboursements en Attente** *(NOUVEAU)*
   - Nombre de demandes non traitées
   - Montant total

#### 6.1.2 Graphique Évolution

**Type** : Line Chart (SVG natif)

**Données** :
- Congés par mois (12 derniers mois)
- Permissions par mois

**Interactivité** :
- Tooltip au survol
- Ligne verticale active
- Points de données mis en évidence

**Technologie** : SVG + Angular Signals

#### 6.1.3 Widget "Employés en Congé" *(NOUVEAU)*

**Position** : À droite du graphique principal

**Contenu** :
- Liste des 5 employés actuellement en congé
- Avatar avec initiale
- Nom + Prénom
- Type de congé

**API** : `GET /api/dashboard/employees-on-leave`

**Critères** :
- `cng_debut <= AUJOURD'HUI`
- `cng_fin >= AUJOURD'HUI`
- `cng_status = true` (validé)

#### 6.1.4 Graphique Donut Remboursements *(NOUVEAU)*

**Type** : Donut Chart (SVG)

**Données** :
- Approuvés (vert)
- En attente (jaune)

**Centre** : Nombre de demandes en attente

**Légende** :
- Nombre par catégorie
- Montant total en attente

**API** : `GET /api/dashboard/rem boursement-distribution`

#### 6.1.5 Timeline "Activité Récente" *(NOUVEAU)*

**Position** : Bas de page

**Contenu** : 5 dernières actions

**Types d'activités** :
- 🗓️ Demande de congé (avec statut)
- 🏥 Demande de remboursement (avec statut)

**Affichage** :
- Icône type d'action
- Titre de l'action
- Nom de l'employé
- Badge statut (Approuvé / En attente / Rejeté)
- Temps relatif ("Il y a 2 heures")

**API** : `GET /api/dashboard/recent-activity`

**Tri** : Par date décroissante (mix congés + remboursements)

### 6.2 Technologies Modernes

**Angular Signals** :
```typescript
employeesOnLeave = signal<any[]>([]);
pendingRequests = signal<any>({ count: 0, total: 0 });
recentActivity = signal<any[]>([]);
donutData = signal<any>({ stats: {...}, montants: {...} });
```

**Réactivité** :
- Mise à jour automatique
- Computed properties
- Performance optimisée

**SCSS Moderne** :
- Variables CSS
- Animations fluides
- Responsive design
- Dark mode ready

---

## 7. MODULE PARAMÈTRES

### 7.1 Gestion des Types de Congés

**Interface** : `parametres/types-conge`

**Opérations** :
- Créer nouveau type
- Modifier existant
- Activer/Désactiver
- Définir solde annuel par défaut

### 7.2 Gestion des Signatures

**Interface** : `parametres/signatures`

**Concept** : Définition des types de validateurs
- DG, DAAF, RRH, Chef, Directeur

**Liaison** : Utilisé dans le workflow de validation dynamique

### 7.3 Gestion des Centres de Santé

**Interface** : `parametres/centres`

**Champs** :
- Nom du centre
- Type (Public, Privé)
- Adresse
- Contact

### 7.4 Autres Paramétrages

- **Directions** : Structure organisationnelle
- **Postes** : Fonctions employés
- **Objets de Facture** : Types de soins remboursables
- **Bénéficiaires** : Gestion des ayants droit

---

## 8. SÉCURITÉ ET AUTHENTIFICATION

### 8.1 Authentification JWT

**Principe** :
1. Login : `POST /api/auth/login`
   - Username + Password
   - Retour : JWT Token + Refresh Token

2. Stockage Token :
   - `localStorage` ou `sessionStorage`
   - Envoi dans header : `Authorization: Bearer {token}`

3. Refresh :
   - Endpoint : `POST /api/auth/refresh`
   - Génération nouveau token avant expiration

### 8.2 Gestion des Rôles

**Rôles** :
- ADMIN : Accès complet
- RH : Gestion RH + validation
- VALIDATEUR : Validation congés/permissions
- EMPLOYEE : Création de demandes
- CENTRE : Saisie remboursements centres

**Vérification** :
- Backend : Middleware sur routes sensibles
- Frontend : Guards Angular

### 8.3 Sécurité Backend

**Protections** :
- CORS configuré
- Validation des entrées
- Requêtes préparées (SQL injection)
- Hash des mots de passe
- Rate limiting (recommandé)

### 8.4 Sécurité Frontend

- Sanitization des inputs
- CSRF protection
- XSS prevention
- Validation formulaires
- Route guards

---

## 9. ARCHITECTURE FAIL-SAFE

### 9.1 Système Email Optionnel

**Principe** : Le système fonctionne **indépendamment** des emails

**Implémentation** :

1. **EmailService** :
   ```php
   try {
       $this->mailer = new PHPMailer(true);
   } catch (\Throwable $e) {
       log_message('error', 'Email init failed');
       // Continue sans email
   }
   ```

2. **CongeValidationService** :
   - Validation enregistrée **avant** envoi email
   - Email échoue → Log erreur mais `return true;`
   - Workflow continue normalement

**Scénarios** :
- ✅ PHPMailer absent → Système fonctionne
- ✅ SMTP mal configuré → Système fonctionne
- ✅ Pas de connexion → Système fonctionne
- ✅ Email OK → Notifications envoyées

### 9.2 Logs et Monitoring

**Fichiers de Log** :
- `writable/logs/log-YYYY-MM-DD.log`

**Types de Messages** :
- `[EmailService]` : Statut service email
- `[Validation]` : Workflow validation
- `[Email]` : Envois réussis/échoués

---

## 10. DÉPLOIEMENT

### 10.1 Prérequis Backend

```bash
- PHP >= 8.1
- PostgreSQL >= 12
- Composer
- Extensions PHP : pdo_pgsql, mbstring, intl, gd
```

### 10.2 Prérequis Frontend

```bash
- Node.js >= 18
- Angular CLI >= 17
```

### 10.3 Installation

#### Backend
```bash
cd BACKEND
composer install
cp env .env
# Configurer .env (DB, JWT_SECRET, SMTP_PASS)
php spark migrate
php spark db:seed DatabaseSeeder
```

#### Frontend
```bash
cd FRONTEND
npm install
# Configurer environment.ts (apiUrl)
ng serve
```

### 10.4 Configuration Production

**Backend** :
- Mode production dans `.env`
- HTTPS obligatoire
- Logs sécurisés

**Frontend** :
```bash
ng build --configuration production
```

---

## 11. TESTS ET VALIDATION

### 11.1 Tests Fonctionnels

#### Module Congé
- [ ] Création demande
- [ ] Validation hiérarchique dynamique
- [ ] Emails (fonctionnement + fail-safe)
- [ ] Débit solde
- [ ] Attestation PDF

#### Module Remboursement
- [ ] Mode Agent
- [ ] Mode Centre
- [ ] PEC validation inline
- [ ] Numérotation automatique
- [ ] État PDF

#### Dashboard
- [ ] Stats temps réel
- [ ] Graphiques interactifs
- [ ] Widgets modernes
- [ ] Responsive

### 11.2 Tests de Sécurité

- [ ] Authentification JWT
- [ ] Autorisations par rôle
- [ ] Injection SQL
- [ ] XSS
- [ ] CSRF

---

## 12. ÉVOLUTIONS FUTURES

### 12.1 Court Terme

- Notifications push temps réel (WebSockets)
- Export Excel des états
- Signature électronique
- Mobile app (Ionic)

### 12.2 Moyen Terme

- Intégration paie
- Gestion des formations
- Évaluations de performance
- Planning prévisionnel congés

### 12.3 Long Terme

- IA pour prédiction congés
- Chatbot RH
- Analytics avancés
- Intégration ERP

---

## 13. GLOSSAIRE

| Terme | Définition |
|-------|----------|
| **PEC** | Prise En Charge - Autorisation préalable de soins |
| **JWT** | JSON Web Token - Authentification |
| **Signal** | Primitive réactive Angular 17+ |
| **Fail-Safe** | Système continuant à fonctionner même en cas d'erreur partielle |
| **DAAF** | Direction des Affaires Administratives et Financières |
| **RRH** | Responsable Ressources Humaines |
| **DG** | Directeur Général |

---

## ANNEXES

### A. Schéma Base de Données

*(À ajouter : Diagramme ERD complet)*

### B. Architecture API

*(À ajouter : Liste complète des endpoints)*

### C. Guide Utilisateur

*(Voir : manuel.md)*

---

**Fin du Cahier de Charges**
