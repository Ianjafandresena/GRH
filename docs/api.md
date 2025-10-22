# RH API Specification (CodeIgniter 4 backend, AngularJS frontend)

## Authentification
- **POST** `/api/auth/login`
  - body: `{ "username": "string", "password": "string" }`
  - 200: `{ "accessToken": "jwt", "expiresIn": 3600, "user": {"id": number, "username": "string", "role": "admin|user", "emp_code": number|null} }`
- **POST** `/api/auth/refresh`
  - header: `Authorization: Bearer <token>`
  - 200: `{ "accessToken": "jwt", "expiresIn": 3600 }`
- **POST** `/api/auth/logout`
  - header: `Authorization: Bearer <token>`
  - 204

Notes
- JWT HS256, default expiry 1h. Password: bcrypt hash. Minimal roles: `admin`, `user`.
- Rate limit login and lock account on repeated failures (backend).

## Employés
- **GET** `/api/employees`
  - query: `q?=string`, `matricule?=number`, `is_actif?=0|1`, `sexe?=true|false`, `from?=date`, `to?=date`, `page?`, `size?`
  - 200: `{ "data": Employee[], "total": number }`
- **POST** `/api/employees`
  - body: `EmployeeCreate`
  - 201: `Employee`
- **GET** `/api/employees/{emp_code}` 200: `Employee`
- **PUT** `/api/employees/{emp_code}` 200: `Employee`
- **DELETE** `/api/employees/{emp_code}` 204
- **GET** `/api/employees/{emp_code}/solde-conge` 200: `{ "annee": number, "disponible": number }`
- **GET** `/api/employees/{emp_code}/solde-permission` 200: `{ "annee": number, "disponible": number }`
- **GET** `/api/employees/{emp_code}/enfants` 200: `Enfant[]`
- **POST** `/api/employees/{emp_code}/enfants` 201: `Enfant`
- **DELETE** `/api/employees/{emp_code}/enfants/{enf_code}` 204
- **GET** `/api/employees/{emp_code}/conjoints` 200: `Conjointe[]`
- **POST** `/api/employees/{emp_code}/conjoints` 201: `Conjointe`
- **DELETE** `/api/employees/{emp_code}/conjoints/{conj_code}` 204

Types
```ts
type Employee = {
  emp_code: number
  nom: string
  prenom: string
  matricule: number
  sexe: boolean|null
  date_embauche: string // YYYY-MM-DD
  email: string
  is_actif: number // 0/1
}
```

## Paramètres (CRUD)
- **Type de congé**
  - GET `/api/types-conge` -> `TypeConge[]`
  - POST `/api/types-conge`
  - GET `/api/types-conge/{typ_code}`
  - PUT `/api/types-conge/{typ_code}`
  - DELETE `/api/types-conge/{typ_code}`
- **Statuts** (lecture seule après seed)
  - GET `/api/status` -> `Status[]`
- **Décisions** (table `decision`)
  - CRUD `/api/decisions`
- **Centres de santé**
  - CRUD `/api/centres`
- **Conventions**
  - CRUD `/api/conventions`
- **Éléments justificatifs**
  - CRUD `/api/justificatifs`

## Congés
- **Recherche avancée**: GET `/api/conges`
  - query: `designation?`, `periodeFrom?`, `periodeTo?`, `emp_code?`, `typ_code?`, `stat?` (en cours|valide|rejete)
  - 200: `{ data: Conge[], total: number }`
- **POST** `/api/conges`
  - body: `{ emp_code, typ_code, cng_debut, cng_fin, cng_nb_jour, justificatifs?: number[] }`
  - crée la demande et enregistre `validation_conge` à l'état initial (ex: soumis)
- **GET** `/api/conges/{cng_code}` -> `CongeDetail`
- **PUT** `/api/conges/{cng_code}` -> met à jour si non validé
- **POST** `/api/conges/{cng_code}/valider`
  - body: `{ stat_code, dec_code, validator_emp_code? }`
  - calcule/mise à jour des soldes (`solde_conge`) si approuvé
- **POST** `/api/conges/{cng_code}/interruption`
  - body: `{ interup_date, interup_motif, interup_restant }`
- **POST** `/api/conges/{cng_code}/interim`
  - body: `{ emp_code, int_cong_date_debut, int_cong_date_fin }`

Types
```ts
type Conge = {
  cng_code: number
  emp_code: number
  typ_code: number
  cng_nb_jour: number
  cng_debut: string
  cng_fin: string
  cng_demande: string // timestamp
  val_code: number|null
}
```

## Permissions (absences)
- Recherche GET `/api/permissions`
- POST `/api/permissions` `{ emp_code, prm_date, prm_duree }`
- GET `/api/permissions/{prm_code}`
- PUT `/api/permissions/{prm_code}`
- POST `/api/permissions/{prm_code}/valider` `{ stat_code, dec_code, validator_emp_code? }`
- Interim: POST `/api/permissions/{prm_code}/interim` `{ emp_code, int_prm_date_debut, int_prm_date_fin }`

## Remboursements / Prises en charge
- **Prise en charge**
  - GET `/api/pec`
  - POST `/api/pec`
  - GET `/api/pec/{pec_code}`
  - POST `/api/pec/{pec_code}/signatures` `{ sign_code, date }`
  - GET `/api/pec/{pec_code}/bulletin` -> PDF
- **Demandes de remboursement (employé)**
  - GET `/api/remb`
  - POST `/api/remb` `{ emp_code, pec_code, rem_objet, rem_date, rem_montant }`
  - GET `/api/remb/{rem_code}` (détails + pièces)
  - PUT `/api/remb/{rem_code}`
  - DELETE `/api/remb/{rem_code}`
  - POST `/api/remb/{rem_code}/pieces` (upload) multipart: `file`
  - POST `/api/remb/{rem_code}/etat` `{ eta_code }` (soumis/valide/paye/rejete)
  - GET `/api/remb/{rem_code}/pdf` -> PDF état
- **Demandes centre de santé**
  - GET `/api/remb/centre` (filtrage par centre, période, statut)

## Dashboards & Statistiques
- **GET** `/api/dashboard/conges`
  - query: `from`, `to`
  - 200: `{ en_cours: number, pris: number, historique: Array<{ date: string, count: number }>, par_type: Array<{ typ_code: number, count: number }> }`
- **GET** `/api/dashboard/permissions`
- **GET** `/api/dashboard/remboursements`

## Exports
- **GET** `/api/export/conges.xlsx` query: filters
- **GET** `/api/export/permissions.xlsx`
- **GET** `/api/export/finances.xlsx`
- **GET** `/api/pec/{pec_code}/bulletin.pdf`

## Sécurité & En-têtes
- Toutes les routes `/api/**` requièrent `Authorization: Bearer <jwt>` sauf `/api/auth/login`.
- CORS: allow origin configurable, send `Authorization, Content-Type`.

## Frontend (AngularJS 1.x) routes (indicatif)
- `/login`
- `/employees` (liste + filtre + CRUD)
- `/leaves` (congés)
- `/permissions`
- `/pec` (prises en charge)
- `/remb` (demandes remboursement)
- `/dashboard`
- `/settings` (types de congé, statuts en lecture, décisions, centres, conventions, justificatifs)

## UI/UX
- Thème pro bleu ciel et blanc. Layout responsive avec barre latérale. Tableaux filtrables, pagination, formulaires validés, feedback toasts, modales de confirmation. Charts (bar/line/pie) pour KPIs.
