# USE CASE - MODULE PERMISSIONS

## a. USE CASE (cas d'utilisation)

| USE CASE (cas d'utilisation) | Module | Description | Fonctionnalités détaillées |
|------------------------------|--------|-------------|----------------------------|
| Création / modification demande de permission | Permissions | Gestion des autorisations d'absence de courte durée | - CRUD demande de permission (création, modification si non validée). - Sélection employé. - Date et heure de début et de fin. - Calcul automatique de la durée en heures. - Saisie du motif obligatoire. - Vérification des chevauchements avec autres permissions/congés. - Modification possible si non validée. |
| Validation des permissions | Permissions | Workflow d'approbation des permissions | - Chaîne de validation simplifiée (Chef de Service → Directeur). - Approbation/Rejet avec motif. - Notification du demandeur. - Timeline de suivi des validations. - Validation dans l'application avec authentification JWT. |
| Suivi des soldes de permissions | Permissions | Gestion du crédit annuel de permissions en heures | - Calcul automatique du crédit annuel (ex: 40 heures/an). - Débit automatique lors de l'approbation. - Consultation du solde disponible par employé. - Affichage : Crédit initial, Consommé, Disponible. - Historique complet des débits. - Ajustement manuel (réservé admin RH). - Report éventuel des heures non utilisées. |
| Consultation et historique des permissions | Permissions | Visualisation et recherche des permissions | - Liste complète avec pagination. - Filtres : employé, période, statut (En attente/Approuvée/Rejetée). - Recherche multicritère. - Affichage détails : employé, date/heure début-fin, durée, motif, statut. - Historique des validations avec dates et validateurs. |
| Export des données permissions | Permissions | Extraction des données | - Export CSV/Excel de la liste avec filtres appliqués. - Export de l'historique par employé. - Colonnes : N° permission, Employé, Date, Durée, Motif, Statut. |
| Gestion des types de permissions (CRUD) | Permissions | Configuration des types de permissions | - CRUD types (Permission administrative, Permission médicale, Permission familiale, etc.). - Définition crédit annuel par type. - Activation/Désactivation. - Personnalisation couleur badge. |
