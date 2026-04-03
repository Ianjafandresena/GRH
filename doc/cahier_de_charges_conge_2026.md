# CAHIER DE CHARGES - MODULE GESTION DES CONGÉS
## SI-GPRH - Système d'Information de Gestion des Ressources Humaines

**Version** : 3.0  
**Date** : 06 Janvier 2026  
**Organisation** : ARMP (Autorité de Régulation des Marchés Publics)  
**Module** : Gestion des Absences et Congés

---

## a. USE CASE (cas d'utilisation)

| Module | Description | Fonctionnalités |
|--------|-------------|-----------------|
| **Gestion des Congés** | Module complet de gestion des demandes de congé avec workflow de validation hiérarchique dynamique, gestion automatique des soldes et suivi en temps réel. | **• Moteur de recherche (filtre avancé) :**<br>  ○ Par désignation (nom employé)<br>  ○ Par période (date début/fin)<br>  ○ Par type de congé<br>  ○ Par statut (En attente, Validé, Rejeté, Interrompu)<br> ○ Par direction<br><br>**• Enregistrement/Export des congés :**<br>  ○ Export CSV de l'historique des congés<br>  ○ Export Excel des données filtrées<br>  ○ Impression de la liste<br><br>**• Gestion des paramètres de congés (CRUD) :**<br>  ○ Types de congés (Annuel, Maladie, Maternité, Paternité, Sans solde, etc.)<br>  ○ Définition du cumule annuel par type<br>  ○ Configuration de la décision (avec/sans solde)<br>  ○ Gestion des pièces justificatives requises<br>  ○ Activation/Désactivation des types<br><br>**• Suivi du congé :**<br>  ○ Calcul automatique des soldes de congés par employé après justification et décision<br>  ○ Report de congé (nécessité de service avec attestation)<br>  ○ Interruption de congé en cours<br>  ○ Reprise anticipée de congé<br>  ○ État des soldes de congés par employé<br>  ○ Historique complet des congés<br>  ○ Attestation de congé PDF téléchargeable<br><br>**• Workflow de validation hiérarchique dynamique :**<br>  ○ Chaîne de validation adaptée selon le poste de l'employé<br>  ○ Validation multi-niveaux (Chef → Directeur → RRH → DAAF → DG)<br>  ○ Filtrage automatique des validateurs selon la direction<br>  ○ Système de tokens email pour validation à distance<br>  ○ Validation dans l'application avec authentification<br>  ○ Approbation/Rejet avec motifs<br>  ○ Notification des validateurs par email (optionnel fail-safe)<br>  ○ Timeline de suivi des validations<br><br>**• Génération automatique de documents :**<br>  ○ Attestation de congé PDF (format officiel ARMP)<br>  ○ Bulletin de décision PDF<br>  ○ Ordre de mission pour congé à l'étranger<br>  ○ Certificat de reprise |

---

## DÉTAILS TECHNIQUES DES FONCTIONNALITÉS

### 1. CRÉATION DE DEMANDE DE CONGÉ

**Interface** : `/conge/ajout`

#### Formulaire de Saisie

| Champ | Type | Validation | Description |
|-------|------|------------|-------------|
| Employé | Autocomplete | Requis | Recherche par nom/matricule avec suggestions |
| Type de congé | Select | Requis | Liste des types actifs (Annuel, Maladie, etc.) |
| Date début | Date Picker | Requis | Date de début du congé |
| Date fin | Date Picker | Requis | Date de fin (doit être ≥ date début) |
| Nombre de jours | Number | Auto-calculé | Calcul automatique (jours ouvrables uniquement) |
| Motif/Observations | Textarea | Optionnel | Raison détaillée de la demande |
| Pièces justificatives | File Upload | Conditionnel | Requis selon type (ex: certificat médical pour maladie) |
| Intérimaire | Autocomplete | Optionnel | Employé assurant l'intérim pendant l'absence |
| Destination | Text | Conditionnel | Requis si congé à l'étranger |

#### Validations Automatiques

| Validation | Description | Comportement |
|------------|-------------|--------------|
| Vérification du solde | Contrôle solde disponible pour le type demandé | Alert si solde insuffisant, blocage de la soumission |
| Détection des chevauchements | Vérifie si l'employé a déjà un congé sur la période | Warning avec détails du conflit |
| Contrôle des dates | Date fin ≥ Date début | Erreur inline en temps réel |
| Pièces justificatives | Selon type de congé (certificat médical, etc.) | Upload obligatoire si requis par le type |
| Calcul automatique | Jours ouvrables entre début et fin | Exclusion des weekends et jours fériés |

### 2. WORKFLOW DE VALIDATION HIÉRARCHIQUE DYNAMIQUE

#### Principe de Fonctionnement

Le système détermine **automatiquement** la chaîne de validation en fonction :
- **Poste de l'employé** : Agent, Chef de Service, Directeur, DG
- **Direction d'appartenance** : DG, DAAF, SRH, DSI, etc.

#### Matrice de Validation

| Profil Employé | Chaîne de Validation Complète | Validateurs Effectifs |
|----------------|-------------------------------|----------------------|
| Agent - Direction Générale | Chef → Directeur → RRH → DAAF → **DG** | Tous les niveaux |
| Agent - DAAF | Chef → ~~Directeur~~ → RRH → **DAAF** → DG | Sans Directeur (DAAF = directeur) |
| Agent - SRH | Chef → ~~Directeur~~ → **RRH** → DAAF → DG | Sans Directeur (RRH = directeur) |
| Chef de Service | ~~Chef~~ → Directeur → RRH → DAAF → DG | Saute niveau "Chef" |
| Directeur | ~~Chef~~ → ~~Directeur~~ → ~~RRH~~ → **DG** | Validation directe par DG |
| Directeur Général | Aucune validation | Auto-approuvé |

#### États de Validation

| Statut | Description | Icône/Badge |
|--------|-------------|-------------|
| `null` | En attente de décision | 🟡 En attente |
| `true` | Approuvé | ✅ Validé |
| `false` | Rejeté | ❌ Rejeté |

### 3. SYSTÈME DE TOKENS EMAIL (FAIL-SAFE)

#### Génération et Sécurité

| Élément | Spécification |
|---------|---------------|
| **Format token** | 64 caractères hexadécimaux (SHA-256) |
| **Expiration** | 7 jours après création |
| **Stockage** | Table `validation_cng` (val_token, val_token_expires, val_token_used) |
| **Unicité** | Token unique par validation |

#### Modes de Validation

| Mode | Route | Auth Required | Description |
|------|-------|---------------|-------------|
| **Par Email** | `GET /api/conge/email-validate?token=xxx&action=approve` | ❌ Non (token = auth) | Lien direct dans email avec actions Approuver/Rejeter |
| **Dans l'Application** | `POST /api/validation_conge/approve` | ✅ Oui (JWT) | Interface web authentifiée |

#### Architecture Fail-Safe

```
Flux de Validation:
1. Création demande congé → Insertion en BD ✅
2. Génération token → Stockage en BD ✅
3. Tentative envoi email:
   SI email échoue:
     - Log l'erreur ⚠️
     - Continue le workflow ✅
     - Validation reste accessible via app 💻
   SINON:
     - Email envoyé 📧
     - Double mode de validation disponible ✅
```

**Garantie** : Le système fonctionne **indépendamment** du service email

### 4. GESTION DES SOLDES

#### Table des Soldes

| Champ | Type | Description |
|-------|------|-------------|
| `emp_code` | FK | Référence employé |
| `tp_cng_code` | FK | Type de congé |
| `sold_solde_init` | Decimal | Solde annuel de départ (ex: 30 jours) |
| `sold_solde_dispo` | Decimal | Solde disponible (mis à jour dynamiquement) |
| `sold_annee` | Integer | Année de référence |

#### Opérations sur Soldes

| Opération | Déclencheur | Impact Solde | Traçabilité |
|-----------|-------------|--------------|-------------|
| **Crédit initial** | Début d'année (job batch) | +30 jours (annuel) | Table `debit_solde_cng` |
| **Débit congé** | Approbation finale DG | -nb_jours demandés | Enregistré avec ref congé |
| **Crédit report** | Congé non pris (fin année) | +jours reportables (max 10) | Avec limite réglementaire |
| **Ajustement RH** | Correction manuelle RH | +/- selon ajustement | Avec justification |

#### Vérifications Automatiques

- **Avant création demande** : Vérification solde ≥ nb jours
- **Pendant validation** : Re-vérification du solde
- **Après approbation finale** : Débit automatique + historique

### 5. DÉCISIONS ET INTERRUPTIONS

#### Types d'Actions

| Action | Déclencheur | Validateur | Impact |
|--------|-------------|------------|--------|
| **Approbation** | Congé validé par tous | Dernier validateur (DG) | Débit solde, congé actif |
| **Rejet** | Refus d'un validateur | N'importe quel validateur | Workflow stoppé, solde inchangé |
| **Report** | Nécessité de service | RH/DAAF | Nouvelles dates, nouveau workflow |
| **Interruption** | Urgence professionnelle | RH avec attestation | Fin anticipée, recalcul solde |

#### Formulaire d'Interruption

**Interface** : `/conge/interruption/:id`

| Champ | Description |
|-------|-------------|
| Date d'interruption | Date effective de retour au travail |
| Motif | Raison de l'interruption (urgence, rappel, etc.) |
| Attestation | Document justificatif (upload obligatoire) |
| Jours non consommés | Calcul automatique pour re-crédit |

### 6. VISUALISATION ET EXPORTS

#### Historique des Congés

**Interface** : `/conge/index`

**Colonnes Affichées** :

| Colonne | Données | Tri/Filtre |
|---------|---------|-----------|
| N° Demande | Référence unique | ✅ Recherche |
| Employé | Nom + Prénom + Matricule | ✅ Autocomplete |
| Type | Badge type congé | ✅ Select |
| Période | Dates début → fin | ✅ Date range |
| Durée | Nombre de jours | - |
| Statut | Badge visuel (En attente/Validé/Rejeté/Interrompu) | ✅ Multi-select |
| Actions | Détails | Éditer | Supprimer | Valider | - |

#### État des Congés

**Interface** : `/conge/etat-conge`

**Vue par Employé** :

| Section | Contenu |
|---------|---------|
| **En-tête employé** | Avatar + Nom + Prénom + Matricule + Direction |
| **Tableau soldes** | Type congé | Solde initial | Consommé | Disponible |
| **Détails annuels** | Total congés pris | Total jours | Moyenne par congé |
| **Historique** | Liste chronologique des congés avec statuts |

#### Export de Données

| Format | Contenu | Déclencheur |
|--------|---------|-------------|
| **CSV** | Données tableau avec filtres appliqués | Bouton "Export CSV" |
| **Excel** | Feuille formatée + graphiques | Bouton "Export Excel" |
| **PDF** | Liste imprimable avec en-tête org | Bouton "Imprimer" |

### 7. GÉNÉRATION DE DOCUMENTS PDF

#### Attestation de Congé

**Route** : `GET /api/conge/:id/attestation`

**Format** :
- Orientation : Portrait (A4)
- En-tête : Logo ARMP + Titre organisation
- Corps :
  - Informations employé (Nom, Prénom, Matricule, Fonction, Direction)
  - Type de congé
  - Période (du X au Y)
  - Durée (N jours)
  - Mentions légales
  - Signature et cachet
- Technologie : `Dompdf`

#### Bulletin de Décision

Similaire à l'attestation avec ajout de :
- Décision finale (Approuvé par...)
- Date de décision
- Validations intermédiaires (timeline)

---

## ARCHITECTURE BACKEND (API)

### Endpoints Principaux

| Méthode | Route | Description | Auth |
|---------|-------|-------------|------|
| `POST` | `/api/conge/` | Créer une demande de congé | ✅ JWT |
| `GET` | `/api/conge/` | Liste des congés (avec filtres query params) | ✅ JWT |
| `GET` | `/api/conge/:id` | Détails d'un congé | ✅ JWT |
| `PUT` | `/api/conge/:id` | Modifier un congé (si non validé) | ✅ JWT |
| `DELETE` | `/api/conge/:id` | Supprimer un congé (si non validé) | ✅ JWT |
| `POST` | `/api/conge/interrupt/:id` | Interrompre un congé en cours | ✅ JWT |
| `GET` | `/api/conge/:id/attestation` | Générer PDF attestation | ✅ JWT |
| **Validation** ||||
| `GET` | `/api/validation_conge/status/:id` | Statut global de validation | ✅ JWT |
| `GET` | `/api/validation_conge/current/:id` | Étape actuelle de validation | ✅ JWT |
| `POST` | `/api/validation_conge/approve` | Approuver une demande | ✅ JWT |
| `POST` | `/api/validation_conge/reject` | Rejeter une demande avec motif | ✅ JWT |
| `GET` | `/api/validation_conge/pending/:emp_code` | Demandes en attente pour validateur | ✅ JWT |
| `GET` | `/api/conge/email-validate` | Validation par email (token) | ❌ Token |
| **Soldes** ||||
| `GET` | `/api/solde_conge/:emp_code` | Soldes d'un employé | ✅ JWT |
| `GET` | `/api/solde_conge/:emp_code/:type` | Solde pour un type spécifique | ✅ JWT |
| `POST` | `/api/solde_conge/adjust` | Ajustement manuel (RH admin) | ✅ JWT + Role |
| **Types de Congés** ||||
| `GET` | `/api/type_conge/` | Liste des types actifs | ✅ JWT |
| `POST` | `/api/type_conge/` | Créer nouveau type | ✅ JWT + Role |
| `PUT` | `/api/type_conge/:id` | Modifier un type | ✅ JWT + Role |
| `DELETE` | `/api/type_conge/:id` | Supprimer (désactiver) un type | ✅ JWT + Role |

### Services Backend

| Service | Responsabilité | Méthodes Clés |
|---------|----------------|---------------|
| **CongeService** | Gestion CRUD congés | `createConge()`, `validateDates()`, `checkOverlap()` |
| **CongeValidationService** | Workflow de validation | `getValidationSteps()`, `approveStep()`, `rejectDemand()` |
| **EmailService** | Envoi emails (fail-safe) | `sendValidationEmail()`, `generateToken()` |
| **SoldeCongeService** | Gestion soldes | `debitSolde()`, `creditSolde()`, `checkAvailability()` |
| **PdfService** | Génération documents | `generateAttestation()`, `generateDecision()` |

---

## BASE DE DONNÉES

### Schéma des Tables Principales

#### Table `conge`

| Colonne | Type | Contraintes | Description |
|---------|------|-------------|-------------|
| `cng_code` | SERIAL | PRIMARY KEY | Identifiant unique |
| `emp_code` | INTEGER | FOREIGN KEY → employee | Employé demandeur |
| `tp_cng_code` | INTEGER | FOREIGN KEY → type_conge | Type de congé |
| `cng_debut` | DATE | NOT NULL | Date de début |
| `cng_fin` | DATE | NOT NULL, CHECK(cng_fin ≥ cng_debut) | Date de fin |
| `cng_nb_jour` | INTEGER | NOT NULL, CHECK(> 0) | Nombre de jours |
| `cng_motif` | TEXT | NULLABLE | Motif/observations |
| `cng_status` | BOOLEAN | DEFAULT NULL | Statut final (NULL=en cours, true=validé, false=rejeté) |
| `cng_date_demande` | TIMESTAMP | DEFAULT NOW() | Date de création |
| `cng_is_interrupted` | BOOLEAN | DEFAULT FALSE | Congé interrompu ? |
| `cng_date_interruption` | DATE | NULLABLE | Si interrompu, date d'arrêt |
| `cng_destination` | VARCHAR(255) | NULLABLE | Si congé à l'étranger |
| `interim_emp_code` | INTEGER | FOREIGN KEY → employee, NULLABLE | Intérimaire |

#### Table `validation_cng`

| Colonne | Type | Contraintes | Description |
|---------|------|-------------|-------------|
| `val_code` | SERIAL | PRIMARY KEY | Identifiant unique |
| `cng_code` | INTEGER | FOREIGN KEY → conge | Congé concerné |
| `sign_code` | INTEGER | FOREIGN KEY → signature | Type de validateur (Chef, RRH, DG, etc.) |
| `emp_validateur` | INTEGER | FOREIGN KEY → employee, NULLABLE | Employé qui doit/a validé |
| `val_status` | BOOLEAN | DEFAULT NULL | Statut (NULL=attente, true=approuvé, false=rejeté) |
| `val_date` | TIMESTAMP | NULLABLE | Date de décision |
| `val_motif_rejet` | TEXT | NULLABLE | Motif si rejeté |
| `val_token` | VARCHAR(64) | UNIQUE, NULLABLE | Token email validation |
| `val_token_expires` | TIMESTAMP | NULLABLE | Expiration token |
| `val_token_used` | BOOLEAN | DEFAULT FALSE | Token déjà utilisé ? |
| `val_order` | INTEGER | NOT NULL | Ordre dans la chaîne (1, 2, 3...) |

#### Table `solde_cng`

| Colonne | Type | Contraintes | Description |
|---------|------|-------------|-------------|
| `sold_code` | SERIAL | PRIMARY KEY | Identifiant unique |
| `emp_code` | INTEGER | FOREIGN KEY → employee | Employé |
| `tp_cng_code` | INTEGER | FOREIGN KEY → type_conge | Type de congé |
| `sold_solde_init` | DECIMAL(5,2) | NOT NULL | Solde annuel initial |
| `sold_solde_dispo` | DECIMAL(5,2) | NOT NULL, CHECK(≥ 0) | Solde disponible |
| `sold_annee` | INTEGER | NOT NULL | Année de référence |
| **CONSTRAINT** | UNIQUE(emp_code, tp_cng_code, sold_annee) | | Un solde par employé/type/année |

#### Table `debit_solde_cng`

| Colonne | Type | Contraintes | Description |
|---------|------|-------------|-------------|
| `debit_code` | SERIAL | PRIMARY KEY | Identifiant unique |
| `sold_code` | INTEGER | FOREIGN KEY → solde_cng | Solde concerné |
| `cng_code` | INTEGER | FOREIGN KEY → conge, NULLABLE | Si débit pour congé |
| `debit_montant` | DECIMAL(5,2) | NOT NULL | Montant (positif=débit, négatif=crédit) |
| `debit_type` | VARCHAR(50) | NOT NULL | Type (CONGE, CREDIT_ANNUEL, AJUSTEMENT, etc.) |
| `debit_date` | TIMESTAMP | DEFAULT NOW() | Date de l'opération |
| `debit_justification` | TEXT | NULLABLE | Motif/justification |

#### Table `type_conge`

| Colonne | Type | Contraintes | Description |
|---------|------|-------------|-------------|
| `tp_cng_code` | SERIAL | PRIMARY KEY | Identifiant unique |
| `tp_cng_nom` | VARCHAR(100) | NOT NULL, UNIQUE | Nom du type (Annuel, Maladie, etc.) |
| `tp_cng_cumule_annuel` | INTEGER | DEFAULT 0 | Nombre de jours par an |
| `tp_cng_decision` | BOOLEAN | DEFAULT TRUE | Avec solde (true) ou sans solde (false) |
| `tp_cng_pieces_requises` | TEXT[] | NULLABLE | Liste des pièces justificatives |
| `tp_cng_actif` | BOOLEAN | DEFAULT TRUE | Type activé ? |
| `tp_cng_couleur` | VARCHAR(7) | DEFAULT '#3498db' | Couleur badge (hex) |

---

## FRONTEND (Angular)

### Architecture des Composants

```
app/module/conge/
├── page/
│   ├── ajout/                  # Création demande
│   │   ├── ajout.ts
│   │   ├── ajout.html
│   │   └── ajout.scss
│   ├── index/                  # Liste/Historique
│   │   ├── index.ts
│   │   ├── index.html
│   │   └── index.scss
│   ├── detail/                 # Détails congé
│   │   ├── detail.ts
│   │   ├── detail.html
│   │   └── detail.scss
│   ├── validation/             # Interface validation
│   │   ├── validation.ts
│   │   ├── validation.html
│   │   └── validation.scss
│   ├── etat-conge/            # État des soldes
│   │   ├── etat-conge.component.ts
│   │   ├── etat-conge.component.html
│   │   └── etat-conge.component.scss
│   ├── interruption/           # Interruption congé
│   │   ├── interruption.ts
│   │   ├── interruption.html
│   │   └── interruption.scss
│   └── modif/                  # Modification demande
│       ├── modif.ts
│       ├── modif.html
│       └── modif.scss
├── service/
│   ├── conge.service.ts       # CRUD congés
│   ├── validation.service.ts  # Workflow validation
│   └── solde.service.ts       # Gestion soldes
└── conge.route.ts             # Routing module
```

### Services Frontend (Angular Signals)

#### CongeService

```typescript
export class CongeService {
  private conges = signal<Conge[]>([]);
  
  // Signals réactifs
  conges$ = this.conges.asReadonly();
  
  // Méthodes
  getAll(filters?: CongeFilters): Observable<Conge[]>
  getById(id: number): Observable<Conge>
  create(data: CongeCreateDto): Observable<Conge>
  update(id: number, data: CongeUpdateDto): Observable<Conge>
  delete(id: number): Observable<void>
  interrupt(id: number, data: InterruptionDto): Observable<Conge>
  downloadAttestation(id: number): void
}
```

#### ValidationService

```typescript
export class ValidationService {
  getValidationStatus(congeId: number): Observable<ValidationStatus>
  approve(validationId: number, comment?: string): Observable<void>
  reject(validationId: number, motif: string): Observable<void>
  getCurrentStep(congeId: number): Observable<ValidationStep>
  getPendingForValidator(empCode: number): Observable<Conge[]>
}
```

### Gestion d'État (Signals)

| Signal | Type | Utilisation |
|--------|------|-------------|
| `conges()` | `Signal<Conge[]>` | Liste des congés |
| `loading()` | `Signal<boolean>` | État de chargement |
| `currentConge()` | `Signal<Conge \| null>` | Congé sélectionné |
| `soldes()` | `Signal<Solde[]>` | Soldes employé |
| `validationSteps()` | `Signal<ValidationStep[]>` | Étapes validation |
| `filters()` | `Signal<CongeFilters>` | Filtres actifs |

### Design System

**Fichier** : `shared-styles.scss`

**Variables** :
```scss
$primary-cyan: #17a2b8;
$primary-blue: #007bff;
$success-green: #28a745;
$warning-yellow: #ffc107;
$danger-red: #dc3545;
$neutral-gray: #6c757d;
```

**Classes Réutilisables** :
- `.btn`, `.btn-primary`, `.btn-outline`
- `.card`, `.form-section`
- `.badge`, `.badge-success`, `.badge-warning`
- `.table`, `.data-table`
- `.status-valide`, `.status-attente`, `.status-rejete`

---

## SÉCURITÉ

### Authentification et Autorisation

| Niveau | Mécanisme | Description |
|--------|-----------|-------------|
| **Backend** | JWT (JSON Web Token) | Token signé avec secret, expiration 24h |
| **Frontend** | JWT Interceptor | Ajout automatique du token dans header `Authorization: Bearer` |
| **Refresh** | Refresh Token | Renouvellement automatique avant expiration |

### Rôles et Permissions

| Rôle | Accès Congés | Actions Autorisées |
|------|-------------|-------------------|
| **EMPLOYEE** | Ses propres congés | Créer, Consulter, Modifier (si non validé) |
| **VALIDATEUR** | Congés à valider + ses propres | Approuver, Rejeter, Consulter |
| **RH** | Tous les congés | CRUD complet, Interruption, Ajustement soldes |
| **ADMIN** | Tous les congés | Actions RH + Gestion paramètres système |

### Contrôles de Sécurité

| Contrôle | Implémentation |
|----------|----------------|
| **SQL Injection** | Prepared statements (Query Builder ORM) |
| **XSS** | Sanitization Angular + validation backend |
| **CSRF** | Token CSRF sur formulaires sensibles |
| **File Upload** | Validation type MIME, taille max, dossier sécurisé |
| **Rate Limiting** | Limitation requêtes par IP (recommandé production) |

---

## TESTS ET VALIDATION

### Scénarios de Test Fonctionnels

| ID | Scénario | Résultat Attendu | Statut |
|----|----------|------------------|--------|
| TC01 | Création demande congé avec solde suffisant | Demande créée, workflow démarré | ✅ |
| TC02 | Création demande avec solde insuffisant | Alert solde insuffisant, blocage | ✅ |
| TC03 | Validation par Chef (1er niveau) | Passage à niveau suivant (Directeur) | ✅ |
| TC04 | Rejet par RRH | Workflow arrêté, notification employé | ✅ |
| TC05 | Validation complète (DG final) | Débit solde, congé actif, attestation disponible | ✅ |
| TC06 | Validation par email (token) | Mise à jour BD, notification suivant | ✅ |
| TC07 | Email fail (SMTP down) | Workflow continue, validation possible via app | ✅ |
| TC08 | Interruption congé en cours | Nouveau calcul solde, attestation mise à jour | ✅ |
| TC09 | Export CSV historique | Fichier téléchargé avec données filtrées | ✅ |
| TC10 | Génération PDF attestation | PDF conforme template officiel ARMP | ✅ |

### Tests de Charge

| Métrique | Objectif | Mesuré |
|----------|----------|--------|
| Temps réponse API (liste) | < 500ms | ~ |
| Temps réponse API (création) | < 1s | ~ |
| Génération PDF | < 3s | ~ |
| Utilisateurs simultanés | 50+ | ~ |

---

## DÉPLOIEMENT

### Prérequis Système

**Backend** :
- PHP ≥ 8.1 (avec extensions : `pdo_pgsql`, `mbstring`, `intl`, `gd`, `zip`)
- PostgreSQL ≥ 12
- Composer
- Serveur web : Apache/Nginx

**Frontend** :
- Node.js ≥ 18
- Angular CLI ≥ 17
- Navigateurs : Chrome, Firefox, Edge (dernières versions)

### Procédure d'Installation

#### Backend
```bash
cd BACKEND
composer install
cp env .env
# Éditer .env (DB, JWT_SECRET, SMTP si email activé)
php spark migrate
php spark db:seed DatabaseSeeder
php spark serve
```

#### Frontend
```bash
cd FRONTEND
npm install
# Éditer src/environments/environment.ts (apiUrl)
ng serve
# Production: ng build --configuration production
```

### Configuration Production

**Backend** `.env` :
```ini
CI_ENVIRONMENT = production
app.baseURL = 'https://gprh.armp.mg'
database.default.hostname = 'prod-db-server'
jwt.secret = 'CHANGE_ME_PRODUCTION_SECRET_256_BIT'
email.fromEmail = 'noreply@armp.mg'
email.smtp.host = 'smtp.office365.com'
```

**Frontend** `environment.prod.ts` :
```typescript
export const environment = {
  production: true,
  apiUrl: 'https://gprh.armp.mg/api'
};
```

---

## MAINTENANCE ET ÉVOLUTION

### Tâches Planifiées (Cron Jobs)

| Tâche | Fréquence | Description |
|-------|-----------|-------------|
| Crédit soldes annuels | 01 Janvier 00:00 | Attribution des nouveaux soldes |
| Nettoyage tokens expirés | Quotidien | Suppression tokens > 7 jours |
| Archivage congés anciens | Mensuel | Déplacement congés > 2 ans vers archive |
| Rapport statistiques | Mensuel | Génération rapport RH automatique |

### Évolutions Prévues

- [ ] Application mobile (React Native)
- [ ] Notifications push
- [ ] Intégration calendrier (Google Calendar, Outlook)
- [ ] Dashboard analytics avancé
- [ ] Import/Export Excel massif

---

**FIN DU CAHIER DE CHARGES MODULE CONGÉS**
