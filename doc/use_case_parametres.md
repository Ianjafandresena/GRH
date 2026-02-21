# USE CASE - MODULE PARAMÈTRES

## a. USE CASE (cas d'utilisation)

| USE CASE (cas d'utilisation) | Module | Description | Fonctionnalités détaillées |
|------------------------------|--------|-------------|----------------------------|
| Gestion des Types de Congés (CRUD) | Paramètres | Configuration du référentiel des types de congés | - CRUD types de congés (Annuel, Maladie, Maternité, Paternité, Sans solde, Exceptionnel, etc.). - Définition cumul annuel par défaut. - Configuration "avec solde" ou "sans solde". - Définition pièces justificatives requises par type. - Activation/Désactivation. - Personnalisation couleur badge. - Ordre d'affichage. |
| Gestion des Signatures / Validateurs | Paramètres | Définition des types d'approbateurs | - CRUD signatures (DG, DAAF, RRH, Chef de Service, Directeur). - Définition de l'ordre hiérarchique. - Association aux workflows de validation. - Activation/Désactivation. |
| Gestion des Directions | Paramètres | Référentiel de la structure organisationnelle | - CRUD directions (DG, DAAF, SRH, DSI, etc.). - Informations : Code, Intitulé, Responsable. - Activation/Désactivation. - Association aux employés via affectations. |
| Gestion des Postes / Fonctions | Paramètres | Référentiel des postes de l'organisation | - CRUD postes (Agent, Chef de Service, Directeur, DG, etc.). - Informations : Code, Intitulé, Description. - Niveau hiérarchique (pour workflow validation). - Activation/Désactivation. |
| Gestion des Services | Paramètres | Référentiel des services rattachés aux directions | - CRUD services. - Informations : Code, Intitulé, Direction de rattachement. - Responsable de service. - Activation/Désactivation. |
| Gestion des Utilisateurs et Rôles | Paramètres | Administration des accès et permissions | - CRUD utilisateurs. - Attribution de rôles (ADMIN, RH, VALIDATEUR, EMPLOYEE, CENTRE). - Activation/Désactivation des comptes. - Réinitialisation mot de passe. - Gestion des droits d'accès par module. - Historique des connexions. |
| Paramètres Généraux Système | Paramètres | Configuration globale de l'application | - Logo de l'organisation (upload). - Nom de l'organisation. - Adresse et contacts. - Format de numérotation des documents (congés, PEC, états). - Délai d'expiration tokens email. - Activation/Désactivation des notifications email. - Configuration SMTP (si emails activés). |
| Gestion des Jours Fériés | Paramètres | Calendrier des jours non travaillés | - CRUD jours fériés. - Date et libellé. - Année de référence. - Import/Export CSV du calendrier annuel. - Utilisation dans calcul des jours de congé. |
| Sauvegarde et Restauration | Paramètres | Gestion des sauvegardes de la base de données | - Création sauvegarde manuelle. - Export base de données (SQL). - Restauration à partir d'une sauvegarde. - Historique des sauvegardes. - Configuration sauvegarde automatique (quotidienne). |
