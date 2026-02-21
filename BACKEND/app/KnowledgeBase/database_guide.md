# Structure de la Base de Données (PostgreSQL)

## Tables Principales

### Employés (`employee`)
- `emp_code`: PK (Serial)
- `emp_nom`, `emp_prenom`, `emp_imarmp` (Matricule)
- `pst_code`, `dir_code` (Lien vers poste et direction)

### Congés (`conge`)
- `cng_code`: PK
- `cng_debut`, `cng_fin`, `cng_nb_jour`
- `cng_status`: Boolean/Null (True=Validé, Null=Attente, False=Rejeté)
- `cng_nb_jour_restitution`: Jours rendus en cas d'interruption.

### Remboursements (`etat_remb`)
- `eta_code`: PK
- `eta_num`: Numéro unique de l'état.
- `eta_total`: Montant cumulé.
- `eta_libelle`: Statut ('EN_ATTENTE', 'MANDATE', 'AGENT_COMPTABLE').

### Prise en Charge (`pec`)
- `pec_code`, `pec_num`, `pec_date_validite`.
- Lie les employés aux centres de santé.
