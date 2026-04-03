# USE CASE - SI-GPRH (Système d'Information de Gestion des Ressources Humaines)

**Organisation** : ARMP (Autorité de Régulation des Marchés Publics)  
**Date** : Janvier 2026  
**Version** : 3.0

---

## 1. MODULE GESTION DES CONGÉS

| USE CASE (cas d'utilisation) | Module | Description | Fonctionnalités détaillées |
|------------------------------|--------|-------------|----------------------------|
| Création / modification d'une demande de congé | Gestion des congés | Création et modification des demandes de congé avec validation hiérarchique dynamique | - Formulaire de demande avec sélection employé, type de congé, période, motif. - Calcul automatique du nombre de jours (hors weekends/fériés). - Vérification automatique du solde disponible. - Détection des chevauchements de congés. - Upload de pièces justificatives (certificat médical, etc.). - Sélection d'un intérimaire pendant l'absence. - Modification possible si demande non encore validée. |
| Validation hiérarchique dynamique | Gestion des congés | Workflow de validation adapté selon le poste et la direction de l'employé | - Chaîne de validation automatique (Chef → Directeur → RRH → DAAF → DG). - Adaptation selon le poste : Agent (tous niveaux), Chef (saute "Chef"), Directeur (DG uniquement). - Filtrage selon direction (DAAF/SRH = pas de "Directeur"). - Validation dans l'application avec authentification JWT. - Validation par email avec token sécurisé (expiration 7 jours). - Système fail-safe : fonctionne même si email échoue. - Approbation/Rejet avec motif obligatoire. - Timeline complète de suivi des validations. |
| Consultation détaillée + recherche multicritère | Gestion des congés | Accès à la fiche détaillée d'un congé et recherche avancée | - Historique complet des congés avec filtres avancés. - Recherche par nom employé, période, type de congé, statut. - Filtre par direction et service. - Affichage des informations : employé, type, dates, durée, motif, pièces jointes. - Timeline de validation avec statuts (En attente/Validé/Rejeté). - Détails du validateur actuel et historique complet. - Actions disponibles selon rôle (Approuver, Rejeter, Modifier, Supprimer). |
| Vue calendrier des congés | Gestion des congés | Visualisation mensuelle des congés sur calendrier | - Affichage calendrier mensuel avec congés sous forme de barres horizontales. - Navigation mois précédent/suivant. - Filtres par mois, service, direction. - Affichage du nom de l'employé et type de congé sur chaque barre. - Code couleur selon type de congé. - Basculement entre vue Tableau et vue Calendrier. - Tooltip au survol avec détails (employé, dates, durée, statut). - Clic sur congé pour accéder aux détails complets. |
| Export / Impression des données | Gestion des congés | Export des congés avec les filtres appliqués | - Export CSV de la liste des congés filtrés. - Export Excel avec mise en forme. - Impression PDF de la liste. - Conservation des filtres actifs lors de l'export. - Colonnes : N° demande, Employé, Type, Période, Durée, Statut. |
| Gestion des paramètres de congés (CRUD) | Gestion des congés | Configuration des types de congés et règles associées | - Création de nouveaux types de congés (Annuel, Maladie, Maternité, Paternité, Sans solde, Exceptionnel, etc.). - Définition du cumule annuel par défaut (ex: 30 jours pour Annuel). - Configuration "avec solde" ou "sans solde". - Définition des pièces justificatives requises par type. - Activation/Désactivation des types. - Personnalisation de la couleur du badge pour chaque type. - Modification et suppression (soft delete) des types. |
| Suivi des soldes et état des congés par employé | Gestion des congés | Gestion automatique des soldes et vue complète des congés par employé | - Calcul automatique des soldes après validation finale. - Débit automatique du solde lors de l'approbation DG. - Crédit annuel automatique en début d'année (job batch). - Fiche employé avec avatar, nom, matricule, direction. - Tableau récapitulatif des soldes par type (Initial, Consommé, Disponible). - Liste chronologique de tous les congés (en cours, passés, futurs). - Statistiques annuelles : Total jours pris, Nombre de congés, Moyenne. - Historique complet des débits/crédits avec justifications. - Ajustement manuel possible (réservé admin RH). - Report de congés non pris (maximum réglementaire). - Filtrage par année. - Export PDF de l'état individuel. |
| Interruption de congé | Gestion des congés | Arrêt anticipé d'un congé en cours pour raison professionnelle | - Formulaire d'interruption avec date effective de retour. - Motif obligatoire (urgence, rappel service, etc.). - Upload obligatoire d'une attestation justificative. - Recalcul automatique du solde (re-crédit jours non consommés). - Mise à jour du statut congé : "Interrompu". - Génération d'une attestation de reprise. - Notification de l'employé et des validateurs. |
| Report de congé | Gestion des congés | Décalage des dates d'un congé validé pour nécessité de service | - Demande de report avec nouvelles dates proposées. - Justification obligatoire (nécessité de service). - Attestation du supérieur hiérarchique requise. - Nouveau workflow de validation sur les nouvelles dates. - Annulation de l'ancien congé et création du nouveau. - Conservation de l'historique (congé initial + report). |
| Génération automatique de documents | Gestion des congés | Production de documents officiels PDF | - Attestation de congé au format officiel ARMP (logo, cachet). - Bulletin de décision avec timeline de validation. - Ordre de mission si congé à l'étranger. - Certificat de reprise après interruption. - Format A4 portrait avec en-tête organisation. - Technologie : Dompdf pour génération PDF. - Téléchargement direct depuis l'application. |

---

## 2. MODULE REMBOURSEMENTS MÉDICAUX

| USE CASE (cas d'utilisation) | Module | Description | Fonctionnalités détaillées |
|------------------------------|--------|-------------|----------------------------|
| Création / modification Prise en Charge (PEC) | Remboursements médicaux | Gestion des prises en charge médicales préalables | - CRUD Prise en charge (création, modification si non validée). - Sélection employé et bénéficiaire (Agent, Conjoint, Enfant à charge). - Sélection centre de santé. - Définition montant plafonné de remboursement. - Période de validité (date début/fin). - Type de soins concernés. - Génération automatique du numéro PEC (format : NNN/ARMP/DG/DAAF/SERVICE/MOIS-YY). - Statuts : Non validée, Validée, Expirée. |
| Validation des PEC | Remboursements médicaux | Approbation administrative des prises en charge | - Interface de validation pour admin RH. - Approbation/Rejet avec motif. - Assignation automatique du centre de santé choisi. - Mise à jour statut PEC (pec_approuver = true). - Notification de l'employé. - Génération du bulletin PEC PDF au format officiel ARMP. |
| Création demande de remboursement (Mode Agent) | Remboursements médicaux | Demande de remboursement par l'employé | - Formulaire de demande avec sélection employé. - Sélection PEC validée de l'employé. - Ajout de factures : objet, montant, scan (upload). - Numérotation automatique demande (NNN/ARMP/DG/DAAF/SERVICE/MOIS-YY). - Vérification montant total ≤ plafond PEC. - Calcul automatique du montant total. - Modification possible si demande non traitée. |
| Création demande de remboursement (Mode Centre de Santé) | Remboursements médicaux | Saisie des demandes par les centres de santé | - Formulaire avec sélection Centre de santé. - Sélection PEC : validées du centre OU non validées (tous centres). - Si PEC non validée : validation inline avec centre verrouillé. - Ajout factures pour le bénéficiaire. - Numérotation automatique. - Plusieurs demandes possibles par centre. |
| Consultation détaillée + recherche multicritère | Remboursements médicaux | Accès aux détails d'une demande et recherche avancée | - Historique complet avec filtres avancés. - Recherche par employé, PEC, centre, montant, statut. - Affichage détails : N° demande, Badge type (Agent/Centre), Employé, Bénéficiaire, N° PEC, Lien parenté, Centre de santé, Liste des factures, Montant total, Statut. - Actions selon rôle : Approuver, Rejeter, Modifier, Supprimer. |
| Validation et traitement des demandes | Remboursements médicaux | Workflow d'approbation des remboursements | - Validation par admin RH/DAAF. - Approbation avec changement statut (rem_status = true). - Rejet avec motif obligatoire. - Historique des validations. - Notification employé et centre. |
| Création et gestion des États de Remboursement | Remboursements médicaux | Regroupement de demandes pour paiement | - Sélection des demandes approuvées non encore payées. - Génération automatique du numéro d'état (NNN/ARMP/DG/DAAF/SERVICE/MOIS-YY). - Création état avec liste des demandes. - Changement statut demandes → "Dans état". - Calcul montant total de l'état. - Suivi par employé. |
| Génération PDF État de Remboursement | Remboursements médicaux | Production du document officiel de paiement | - PDF au format paysage A4. - En-tête : Informations état + mois + année. - Tableau : N° Facture, Acte, N° PEC, PEC N°, Agent, Malade, Lien, Montant. - Pied de page : Total général. - Technologie : Dompdf. - Téléchargement direct. |
| Consultation liste des États | Remboursements médicaux | Visualisation et suivi des états de paiement | - Liste des états avec pagination. - Filtres par date, statut, employé. - Affichage : N° état, Date, Employé, Nombre de demandes, Montant total. - Bouton "Télécharger PDF" pour chaque état. - Accès aux détails avec liste des demandes incluses. |
| Gestion des Centres de Santé (CRUD) | Remboursements médicaux | Administration du référentiel des centres | - CRUD centres de santé (création, modification, suppression). - Informations : Nom, Type (Public/Privé/Conventionné), Adresse, Contact, Email. - Activation/Désactivation. - Association aux PEC. - Liste avec recherche et filtres. |
| Gestion des Bénéficiaires | Remboursements médicaux | Gestion des ayants droit de l'employé | - CRUD bénéficiaires (Agent lui-même, Conjoint, Enfants). - Informations : Nom, Prénom, Lien de parenté, Date de naissance. - Association à un employé (emp_code). - Vérification limite d'âge enfants (selon règlement). - Liste par employé. |
| Gestion des Objets de Facture | Remboursements médicaux | Référentiel des types de soins remboursables | - CRUD objets de facture (Consultation, Analyses, Médicaments, Radiographie, Hospitalisation, etc.). - Activation/Désactivation. - Utilisation dans saisie des factures. |
| Export et statistiques | Remboursements médicaux | Extraction et analyse des données de remboursement | - Export CSV/Excel des demandes avec filtres. - Export des états de remboursement. - Statistiques : Total remboursé par période, Par employé, Par centre, Par type de soins. - Graphiques d'évolution. |

---

## 3. MODULE PERMISSIONS

| USE CASE (cas d'utilisation) | Module | Description | Fonctionnalités détaillées |
|------------------------------|--------|-------------|----------------------------|
| Création / modification demande de permission | Permissions | Gestion des autorisations d'absence de courte durée | - CRUD demande de permission (création, modification si non validée). - Sélection employé. - Date et heure de début et de fin. - Calcul automatique de la durée en heures. - Saisie du motif obligatoire. - Vérification des chevauchements avec autres permissions/congés. - Modification possible si non validée. |
| Validation des permissions | Permissions | Workflow d'approbation des permissions | - Chaîne de validation simplifiée (Chef de Service → Directeur). - Approbation/Rejet avec motif. - Notification du demandeur. - Timeline de suivi des validations. - Validation dans l'application avec authentification JWT. |
| Suivi des soldes de permissions | Permissions | Gestion du crédit annuel de permissions en heures | - Calcul automatique du crédit annuel (ex: 40 heures/an). - Débit automatique lors de l'approbation. - Consultation du solde disponible par employé. - Affichage : Crédit initial, Consommé, Disponible. - Historique complet des débits. - Ajustement manuel (réservé admin RH). - Report éventuel des heures non utilisées. |
| Consultation et historique des permissions | Permissions | Visualisation et recherche des permissions | - Liste complète avec pagination. - Filtres : employé, période, statut (En attente/Approuvée/Rejetée). - Recherche multicritère. - Affichage détails : employé, date/heure début-fin, durée, motif, statut. - Historique des validations avec dates et validateurs. |
| Export des données permissions | Permissions | Extraction des données | - Export CSV/Excel de la liste avec filtres appliqués. - Export de l'historique par employé. - Colonnes : N° permission, Employé, Date, Durée, Motif, Statut. |
| Gestion des types de permissions (CRUD) | Permissions | Configuration des types de permissions | - CRUD types (Permission administrative, Permission médicale, Permission familiale, etc.). - Définition crédit annuel par type. - Activation/Désactivation. - Personnalisation couleur badge. |

---

## 4. MODULE DASHBOARD

| USE CASE (cas d'utilisation) | Module | Description | Fonctionnalités détaillées |
|------------------------------|--------|-------------|----------------------------|
| Tableau de bord principal | Dashboard | Vue synthétique temps réel des indicateurs RH | - 4 Cards statistiques : Total Employés (avec actifs), Congés en cours (nombre d'employés actuellement absents), Permissions en attente, Remboursements en attente (nombre + montant total). - Évolution vs période précédente (%). - Mise à jour automatique en temps réel. - Design moderne avec icônes et couleurs. |
| Graphique d'évolution des absences | Dashboard | Visualisation tendances congés et permissions | - Line Chart SVG natif (12 derniers mois). - 2 courbes : Congés par mois, Permissions par mois. - Interactivité : Tooltip au survol avec détails. - Ligne verticale active pour le mois survolé. - Points de données mis en évidence. - Technologie : SVG + Angular Signals. |
| Widget Employés en congé | Dashboard | Liste en temps réel des absents | - Liste des employés actuellement en congé (5 premiers). - Avatar avec initiale. - Nom + Prénom. - Type de congé avec badge coloré. - Dates du congé. - Critères : cng_debut ≤ AUJOURD'HUI ≤ cng_fin ET cng_status = true. |
| Graphique Donut Remboursements | Dashboard | Répartition des demandes de remboursement | - Donut Chart SVG. - 2 segments : Approuvés (vert), En attente (jaune). - Centre : Nombre de demandes en attente. - Légende : Nombre par catégorie + Montant total en attente. - Cliquable pour accéder à la liste filtrée. |
| Timeline Activité Récente | Dashboard | Flux des 5 dernières actions système | - Liste chronologique des actions récentes (congés, remboursements, permissions). - Icônes par type : 🗓️ Congé, 🏥 Remboursement, ⏱️ Permission. - Titre action + Nom employé. - Badge statut (Approuvé/En attente/Rejeté). - Temps relatif ("Il y a 2 heures"). - Tri : Par date décroissante (mix tous modules). |
| Filtres et export dashboard | Dashboard | Personnalisation et extraction des données | - Filtre par période (semaine, mois, année, personnalisé). - Filtre par direction/service. - Export PDF du dashboard complet. - Rafraîchissement manuel ou automatique (toutes les 5 min). |

---

## 5. MODULE PARAMÈTRES

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

---

**TOTAL : 44 FONCTIONNALITÉS DOCUMENTÉES**
- Module Gestion des Congés : 10 fonctionnalités
- Module Remboursements Médicaux : 13 fonctionnalités  
- Module Permissions : 6 fonctionnalités
- Module Dashboard : 6 fonctionnalités
- Module Paramètres : 9 fonctionnalités
