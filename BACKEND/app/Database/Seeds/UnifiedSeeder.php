<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeder unifié — Valeurs par défaut complètes
 * SI-GPRH + CARRIÈRE-STAGIAIRE
 */
class UnifiedSeeder extends Seeder
{
    public function run()
    {
        // =====================================================
        // CARRIÈRE-STAGIAIRE : Référentiels
        // =====================================================

        // motif_affectation
        $this->db->table('motif_affectation')->insertBatch([
            ['m_aff_code' => 1, 'm_aff_motif' => 'Affectation Initiale', 'm_aff_type' => 'Permanente'],
            ['m_aff_code' => 2, 'm_aff_motif' => 'Mutation', 'm_aff_type' => 'Permanente'],
            ['m_aff_code' => 3, 'm_aff_motif' => 'Promotion', 'm_aff_type' => 'Permanente'],
            ['m_aff_code' => 5, 'm_aff_motif' => 'Détachement', 'm_aff_type' => 'Temporaire'],
        ]);

        // direction (7 directions ARMP)
        $this->db->table('direction')->insertBatch([
            ['dir_code' => 1, 'dir_abbreviation' => null, 'dir_nom' => 'Direction Générale'],
            ['dir_code' => 2, 'dir_abbreviation' => null, 'dir_nom' => 'Comité de Recours et de Réglementation'],
            ['dir_code' => 3, 'dir_abbreviation' => null, 'dir_nom' => 'Comité de Règlement des Différends'],
            ['dir_code' => 4, 'dir_abbreviation' => null, 'dir_nom' => "Direction de l'Audit Interne"],
            ['dir_code' => 5, 'dir_abbreviation' => null, 'dir_nom' => 'Direction des Affaires Administratives et Financières'],
            ['dir_code' => 6, 'dir_abbreviation' => null, 'dir_nom' => 'Direction de la Formation et de la Documentation'],
            ['dir_code' => 7, 'dir_abbreviation' => null, 'dir_nom' => "Direction du Système d'Information"],
        ]);

        // rang_hierarchique
        $this->db->table('rang_hierarchique')->insertBatch([
            ['rhq_code' => 1, 'rhq_rang' => 'HEE', 'rhq_niveau' => 'Niveau1'],
            ['rhq_code' => 2, 'rhq_rang' => 'Chef de Service', 'rhq_niveau' => 'Niveau2'],
            ['rhq_code' => 3, 'rhq_rang' => 'Cadre', 'rhq_niveau' => 'Niveau3'],
            ['rhq_code' => 4, 'rhq_rang' => 'Agent exécutant', 'rhq_niveau' => 'Niveau4'],
        ]);

        // service (10 services)
        $this->db->table('service')->insertBatch([
            ['srvc_code' => 1, 'srvc_nom' => 'Agence Comptable', 'dir_code' => 1],
            ['srvc_code' => 2, 'srvc_nom' => 'Service Ressource Humaines', 'dir_code' => 5],
            ['srvc_code' => 3, 'srvc_nom' => 'Service de Suivi Evaluation', 'dir_code' => 4],
            ['srvc_code' => 4, 'srvc_nom' => 'Service Administratif et Financier', 'dir_code' => 5],
            ['srvc_code' => 5, 'srvc_nom' => 'Service Coordination et Régulation', 'dir_code' => 4],
            ['srvc_code' => 6, 'srvc_nom' => 'Service de la Documentation', 'dir_code' => 6],
            ['srvc_code' => 7, 'srvc_nom' => 'Service de la Formation', 'dir_code' => 6],
            ['srvc_code' => 8, 'srvc_nom' => 'Service de Coordination Général des Activités', 'dir_code' => 1],
            ['srvc_code' => 9, 'srvc_nom' => "Service d'Administration Système et Réseau", 'dir_code' => 7],
            ['srvc_code' => 10, 'srvc_nom' => 'Service Section Recours', 'dir_code' => 2],
        ]);

        // poste (38 postes ARMP)
        $postes = [
            ['pst_code' => 1, 'tsup_code' => null, 'rhq_code' => 1, 'pst_fonction' => 'Directeur Général', 'pst_mission' => "Assurer la direction générale, la coordination et le contrôle des activités de l'ARMP.", 'srvc_code' => null, 'dir_code' => 1],
            ['pst_code' => 2, 'tsup_code' => null, 'rhq_code' => 1, 'pst_fonction' => 'Président du Comité de Recours et de Réglementation', 'pst_mission' => "Présider le comité, veiller à l'application des textes et à la qualité des décisions.", 'srvc_code' => null, 'dir_code' => 2],
            ['pst_code' => 3, 'tsup_code' => null, 'rhq_code' => 1, 'pst_fonction' => 'Présidente du Comité de Règlement des Différends', 'pst_mission' => 'Présider le comité, garantir le traitement impartial des différends.', 'srvc_code' => null, 'dir_code' => 3],
            ['pst_code' => 4, 'tsup_code' => null, 'rhq_code' => 1, 'pst_fonction' => "Directeur de l'Audit Interne", 'pst_mission' => "Planifier et superviser les missions d'audit interne, évaluer les risques et proposer des recommandations.", 'srvc_code' => null, 'dir_code' => 4],
            ['pst_code' => 5, 'tsup_code' => null, 'rhq_code' => 1, 'pst_fonction' => 'Directeur des Affaires Administratives et Financières', 'pst_mission' => 'Gérer les ressources financières et administratives, superviser la comptabilité et le budget.', 'srvc_code' => null, 'dir_code' => 5],
            ['pst_code' => 6, 'tsup_code' => null, 'rhq_code' => 1, 'pst_fonction' => 'Directeur de la Formation et de la documentation', 'pst_mission' => 'Développer la politique de formation, gérer le centre de documentation et les ressources pédagogiques.', 'srvc_code' => null, 'dir_code' => 6],
            ['pst_code' => 7, 'tsup_code' => null, 'rhq_code' => 1, 'pst_fonction' => "Directeur du Système d'Information", 'pst_mission' => "Piloter le système d'information, garantir la sécurité et la performance des infrastructures.", 'srvc_code' => null, 'dir_code' => 7],
            ['pst_code' => 8, 'tsup_code' => null, 'rhq_code' => 2, 'pst_fonction' => 'Agent Comptable', 'pst_mission' => 'Tenir la comptabilité, effectuer les opérations financières et assurer le suivi budgétaire.', 'srvc_code' => 1, 'dir_code' => 1],
            ['pst_code' => 9, 'tsup_code' => null, 'rhq_code' => 2, 'pst_fonction' => 'Responsable des Ressources Humaines', 'pst_mission' => 'Gérer le personnel, les carrières, la paie et les relations sociales.', 'srvc_code' => 2, 'dir_code' => 5],
            ['pst_code' => 10, 'tsup_code' => null, 'rhq_code' => 2, 'pst_fonction' => 'Chef de service Suivi Evaluation', 'pst_mission' => "Coordonner le suivi et l'évaluation des projets, analyser les indicateurs de performance.", 'srvc_code' => 3, 'dir_code' => 4],
            ['pst_code' => 11, 'tsup_code' => null, 'rhq_code' => 2, 'pst_fonction' => 'Responsable Administratif et Financier', 'pst_mission' => 'Superviser les activités administratives et financières du service.', 'srvc_code' => 4, 'dir_code' => 5],
            ['pst_code' => 12, 'tsup_code' => null, 'rhq_code' => 2, 'pst_fonction' => 'Chef de service Coordination et Régulation', 'pst_mission' => 'Assurer la coordination des activités de régulation et veiller à leur conformité.', 'srvc_code' => 5, 'dir_code' => 4],
            ['pst_code' => 13, 'tsup_code' => null, 'rhq_code' => 2, 'pst_fonction' => 'Chef de Service de la Documentation', 'pst_mission' => "Gérer les collections documentaires, assurer la veille informationnelle et la diffusion.", 'srvc_code' => 6, 'dir_code' => 6],
            ['pst_code' => 14, 'tsup_code' => null, 'rhq_code' => 2, 'pst_fonction' => 'Chef de Service de la FORMATION', 'pst_mission' => "Organiser et animer les actions de formation, évaluer leur impact.", 'srvc_code' => 7, 'dir_code' => 6],
            ['pst_code' => 15, 'tsup_code' => null, 'rhq_code' => 2, 'pst_fonction' => 'Coordonnateur Général des Activités', 'pst_mission' => "Coordonner l'ensemble des activités opérationnelles et assurer la liaison entre les services.", 'srvc_code' => 8, 'dir_code' => 1],
            ['pst_code' => 16, 'tsup_code' => null, 'rhq_code' => 2, 'pst_fonction' => 'Chef de Service Administration Système et Réseau', 'pst_mission' => 'Administrer les serveurs, réseaux et assurer la maintenance technique.', 'srvc_code' => 9, 'dir_code' => 7],
            ['pst_code' => 17, 'tsup_code' => null, 'rhq_code' => 4, 'pst_fonction' => 'Agent Administratif', 'pst_mission' => 'Assurer les tâches administratives courantes, accueil, traitement des courriers.', 'srvc_code' => 4, 'dir_code' => 5],
            ['pst_code' => 18, 'tsup_code' => null, 'rhq_code' => 4, 'pst_fonction' => "Personnel d'Appui Ressources Humaines", 'pst_mission' => 'Assister le responsable RH dans la gestion administrative du personnel.', 'srvc_code' => 2, 'dir_code' => 5],
            ['pst_code' => 19, 'tsup_code' => null, 'rhq_code' => 4, 'pst_fonction' => "Personnel d'Appui Système Réseau", 'pst_mission' => "Participer à la maintenance et à l'exploitation des systèmes et réseaux.", 'srvc_code' => 9, 'dir_code' => 7],
            ['pst_code' => 20, 'tsup_code' => null, 'rhq_code' => 4, 'pst_fonction' => "Personnel d'Appui Web", 'pst_mission' => 'Contribuer à la gestion et à la mise à jour du site web.', 'srvc_code' => 9, 'dir_code' => 7],
            ['pst_code' => 21, 'tsup_code' => null, 'rhq_code' => 4, 'pst_fonction' => "Personnel d'Appui de l'Agence Comptable", 'pst_mission' => "Assister l'agent comptable dans les tâches de saisie et de suivi.", 'srvc_code' => 1, 'dir_code' => 1],
            ['pst_code' => 22, 'tsup_code' => null, 'rhq_code' => 4, 'pst_fonction' => "Personnel d'Appui de la Documentation", 'pst_mission' => 'Aider à la gestion physique et numérique des documents.', 'srvc_code' => 6, 'dir_code' => 6],
            ['pst_code' => 23, 'tsup_code' => null, 'rhq_code' => 4, 'pst_fonction' => "Personnel d'Appui de la Formation", 'pst_mission' => 'Supporter logistique et administratif des sessions de formation.', 'srvc_code' => 7, 'dir_code' => 6],
            ['pst_code' => 24, 'tsup_code' => null, 'rhq_code' => 4, 'pst_fonction' => "Personnel d'Appui Administratif et Financier", 'pst_mission' => 'Assister le RAF dans les tâches administratives et financières.', 'srvc_code' => 4, 'dir_code' => 5],
            ['pst_code' => 25, 'tsup_code' => null, 'rhq_code' => 4, 'pst_fonction' => 'Webmaster', 'pst_mission' => "Concevoir, développer et maintenir le site web de l'ARMP.", 'srvc_code' => 9, 'dir_code' => 7],
            ['pst_code' => 26, 'tsup_code' => null, 'rhq_code' => 4, 'pst_fonction' => 'Secrétaire Particulière de la Direction Générale', 'pst_mission' => "Assurer le secrétariat et la gestion de l'agenda du Directeur Général.", 'srvc_code' => null, 'dir_code' => 1],
            ['pst_code' => 27, 'tsup_code' => null, 'rhq_code' => 4, 'pst_fonction' => "Personnel d'Appui Communication", 'pst_mission' => 'Participer aux actions de communication interne et externe.', 'srvc_code' => 8, 'dir_code' => 1],
            ['pst_code' => 28, 'tsup_code' => null, 'rhq_code' => 4, 'pst_fonction' => 'Dépositaire Comptable', 'pst_mission' => 'Gérer les fonds et valeurs, assurer la tenue de la caisse.', 'srvc_code' => 4, 'dir_code' => 5],
            ['pst_code' => 29, 'tsup_code' => null, 'rhq_code' => 4, 'pst_fonction' => 'Standardiste / Réceptionniste', 'pst_mission' => 'Accueillir les visiteurs, gérer le standard téléphonique.', 'srvc_code' => 4, 'dir_code' => 5],
            ['pst_code' => 30, 'tsup_code' => null, 'rhq_code' => 4, 'pst_fonction' => 'Aide Comptable', 'pst_mission' => 'Assister le comptable dans les travaux de saisie et de rapprochement.', 'srvc_code' => 4, 'dir_code' => 5],
            ['pst_code' => 31, 'tsup_code' => null, 'rhq_code' => 4, 'pst_fonction' => 'Agent Administratif', 'pst_mission' => 'Effectuer les tâches administratives pour le compte de la direction générale.', 'srvc_code' => 1, 'dir_code' => 1],
            ['pst_code' => 32, 'tsup_code' => null, 'rhq_code' => 4, 'pst_fonction' => 'Chauffeur', 'pst_mission' => 'Conduire les véhicules de service, assurer les déplacements professionnels.', 'srvc_code' => 4, 'dir_code' => 5],
            ['pst_code' => 33, 'tsup_code' => null, 'rhq_code' => 4, 'pst_fonction' => 'Coursier / Vaguemestre', 'pst_mission' => 'Assurer la distribution du courrier et des documents.', 'srvc_code' => 4, 'dir_code' => 5],
            ['pst_code' => 34, 'tsup_code' => null, 'rhq_code' => 4, 'pst_fonction' => 'Technicien de Surface', 'pst_mission' => "Assurer le nettoyage et l'entretien des locaux.", 'srvc_code' => 4, 'dir_code' => 5],
            ['pst_code' => 35, 'tsup_code' => null, 'rhq_code' => 4, 'pst_fonction' => 'Agent de sécurité', 'pst_mission' => 'Surveiller les locaux, contrôler les accès.', 'srvc_code' => 4, 'dir_code' => 5],
            ['pst_code' => 36, 'tsup_code' => null, 'rhq_code' => 3, 'pst_fonction' => 'Juriste', 'pst_mission' => 'Conseiller juridique, rédiger des actes et participer aux contentieux.', 'srvc_code' => 10, 'dir_code' => 2],
            ['pst_code' => 37, 'tsup_code' => null, 'rhq_code' => 3, 'pst_fonction' => 'Economiste', 'pst_mission' => 'Réaliser des études économiques, analyser les données sectorielles.', 'srvc_code' => 10, 'dir_code' => 2],
            ['pst_code' => 38, 'tsup_code' => null, 'rhq_code' => 3, 'pst_fonction' => 'Traducteur/Rédacteur', 'pst_mission' => 'Traduire des documents, rédiger des rapports et comptes-rendus.', 'srvc_code' => 10, 'dir_code' => 2],
        ];
        $this->db->table('poste')->insertBatch($postes);

        // occupation_poste (quotas pour les 38 postes)
        $occupations = [
            ['occpst_code' => 1, 'pst_code' => 1, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 2, 'pst_code' => 2, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 3, 'pst_code' => 3, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 4, 'pst_code' => 4, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 5, 'pst_code' => 5, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 6, 'pst_code' => 6, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 7, 'pst_code' => 7, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 8, 'pst_code' => 8, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 9, 'pst_code' => 9, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 10, 'pst_code' => 10, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 11, 'pst_code' => 11, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 12, 'pst_code' => 12, 'quota' => 1, 'nb_occupe' => 1, 'nb_vacant' => 0, 'nb_encessation' => 0],
            ['occpst_code' => 13, 'pst_code' => 13, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 14, 'pst_code' => 14, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 15, 'pst_code' => 15, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 16, 'pst_code' => 16, 'quota' => 1, 'nb_occupe' => 1, 'nb_vacant' => 0, 'nb_encessation' => 0],
            ['occpst_code' => 17, 'pst_code' => 17, 'quota' => 2, 'nb_occupe' => 0, 'nb_vacant' => 2, 'nb_encessation' => 0],
            ['occpst_code' => 18, 'pst_code' => 18, 'quota' => 2, 'nb_occupe' => 0, 'nb_vacant' => 2, 'nb_encessation' => 0],
            ['occpst_code' => 19, 'pst_code' => 19, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 20, 'pst_code' => 20, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 21, 'pst_code' => 21, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 22, 'pst_code' => 22, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 23, 'pst_code' => 23, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 24, 'pst_code' => 24, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 0, 'nb_encessation' => 1],
            ['occpst_code' => 25, 'pst_code' => 25, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 26, 'pst_code' => 26, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 27, 'pst_code' => 27, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 28, 'pst_code' => 28, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 29, 'pst_code' => 29, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 30, 'pst_code' => 30, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 31, 'pst_code' => 31, 'quota' => 2, 'nb_occupe' => 0, 'nb_vacant' => 2, 'nb_encessation' => 0],
            ['occpst_code' => 32, 'pst_code' => 32, 'quota' => 15, 'nb_occupe' => 0, 'nb_vacant' => 15, 'nb_encessation' => 0],
            ['occpst_code' => 33, 'pst_code' => 33, 'quota' => 2, 'nb_occupe' => 0, 'nb_vacant' => 2, 'nb_encessation' => 0],
            ['occpst_code' => 34, 'pst_code' => 34, 'quota' => 3, 'nb_occupe' => 0, 'nb_vacant' => 3, 'nb_encessation' => 0],
            ['occpst_code' => 35, 'pst_code' => 35, 'quota' => 6, 'nb_occupe' => 0, 'nb_vacant' => 6, 'nb_encessation' => 0],
            ['occpst_code' => 36, 'pst_code' => 36, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
            ['occpst_code' => 37, 'pst_code' => 37, 'quota' => 2, 'nb_occupe' => 0, 'nb_vacant' => 3, 'nb_encessation' => 0],
            ['occpst_code' => 38, 'pst_code' => 38, 'quota' => 1, 'nb_occupe' => 0, 'nb_vacant' => 1, 'nb_encessation' => 0],
        ];
        $this->db->table('occupation_poste')->insertBatch($occupations);

        // position_
        $this->db->table('position_')->insertBatch([
            ['pos_code' => 1, 'pos_type' => 'en service'],
            ['pos_code' => 2, 'pos_type' => 'en cessation'],
            ['pos_code' => 3, 'pos_type' => 'sortie'],
        ]);

        // sortie_type
        $this->db->table('sortie_type')->insertBatch([
            ['s_type_code' => 'RETRAITE', 's_type_motif' => 'Retraite'],
            ['s_type_code' => 'RENVOI', 's_type_motif' => 'Renvoi'],
            ['s_type_code' => 'ABROGATION', 's_type_motif' => 'Abrogation'],
        ]);

        // statut_armp
        $this->db->table('statut_armp')->insertBatch([
            ['stt_armp_code' => 1, 'stt_armp_statut' => 'CNM'],
            ['stt_armp_code' => 2, 'stt_armp_statut' => 'Fonctionnaire/armp'],
            ['stt_armp_code' => 3, 'stt_armp_statut' => 'EFA/armp'],
            ['stt_armp_code' => 4, 'stt_armp_statut' => 'Nomination'],
            ['stt_armp_code' => 5, 'stt_armp_statut' => 'Mis en emploi'],
        ]);

        // type_contrat
        $this->db->table('type_contrat')->insertBatch([
            ['tcontrat_code' => 1, 'tcontrat_nom' => 'Fonctionnaire'],
            ['tcontrat_code' => 2, 'tcontrat_nom' => 'ELD'],
            ['tcontrat_code' => 3, 'tcontrat_nom' => 'EFA'],
        ]);

        // type_document
        $this->db->table('type_document')->insertBatch([
            ['tdoc_code' => 1, 'tdoc_nom' => 'Attestation de non interruption de service'],
            ['tdoc_code' => 2, 'tdoc_nom' => "Attestation d'emploi"],
            ['tdoc_code' => 3, 'tdoc_nom' => 'Certificat de travail'],
            ['tdoc_code' => 4, 'tdoc_nom' => 'Certificat administratif'],
            ['tdoc_code' => 5, 'tdoc_nom' => 'Attestation de stage'],
            ['tdoc_code' => 6, 'tdoc_nom' => 'Convention de stage'],
        ]);

        // type_entree
        $this->db->table('type_entree')->insertBatch([
            ['e_type_code' => '1', 'e_type_motif' => 'Recrutement'],
            ['e_type_code' => '2', 'e_type_motif' => 'Nomination'],
            ['e_type_code' => '3', 'e_type_motif' => 'Transfert'],
            ['e_type_code' => '4', 'e_type_motif' => 'Promotion'],
        ]);

        // =====================================================
        // SI-GPRH : Référentiels
        // =====================================================

        // type_conge
        $this->db->table('type_conge')->insertBatch([
            ['typ_code' => 1, 'typ_appelation' => 'Congé annuel', 'typ_ref' => 'CA'],
            ['typ_code' => 2, 'typ_appelation' => 'Repos maladie', 'typ_ref' => 'RM'],
            ['typ_code' => 3, 'typ_appelation' => 'Congé maternité', 'typ_ref' => 'CM'],
            ['typ_code' => 4, 'typ_appelation' => 'Congé paternité', 'typ_ref' => 'CP'],
        ]);

        // region (23 régions de Madagascar)
        $this->db->table('region')->insertBatch([
            ['reg_code' => 1, 'reg_nom' => 'Alaotra-Mangoro'],
            ['reg_code' => 2, 'reg_nom' => "Amoron'i Mania"],
            ['reg_code' => 3, 'reg_nom' => 'Analamanga'],
            ['reg_code' => 4, 'reg_nom' => 'Analanjirofo'],
            ['reg_code' => 5, 'reg_nom' => 'Androy'],
            ['reg_code' => 6, 'reg_nom' => 'Anosy'],
            ['reg_code' => 7, 'reg_nom' => 'Atsimo-Andrefana'],
            ['reg_code' => 8, 'reg_nom' => 'Atsimo-Atsinanana'],
            ['reg_code' => 9, 'reg_nom' => 'Atsinanana'],
            ['reg_code' => 10, 'reg_nom' => 'Bongolava'],
            ['reg_code' => 11, 'reg_nom' => 'Boeny'],
            ['reg_code' => 12, 'reg_nom' => 'Betsiboka'],
            ['reg_code' => 13, 'reg_nom' => 'Diana'],
            ['reg_code' => 14, 'reg_nom' => 'Haute Matsiatra'],
            ['reg_code' => 15, 'reg_nom' => 'Ihorombe'],
            ['reg_code' => 16, 'reg_nom' => 'Itasy'],
            ['reg_code' => 17, 'reg_nom' => 'Melaky'],
            ['reg_code' => 18, 'reg_nom' => 'Menabe'],
            ['reg_code' => 19, 'reg_nom' => 'Sava'],
            ['reg_code' => 20, 'reg_nom' => 'Vakinankaratra'],
            ['reg_code' => 21, 'reg_nom' => 'Vatovavy'],
            ['reg_code' => 22, 'reg_nom' => 'Fitovinany'],
            ['reg_code' => 23, 'reg_nom' => 'Matsiatra Ambony'],
        ]);

        // type_centre
        $this->db->table('type_centre')->insertBatch([
            ['tp_cen_code' => 1, 'tp_cen' => 'Hôpital public'],
            ['tp_cen_code' => 2, 'tp_cen' => 'Clinique privée'],
            ['tp_cen_code' => 3, 'tp_cen' => 'Centre de santé de base'],
            ['tp_cen_code' => 4, 'tp_cen' => 'Pharmacie'],
            ['tp_cen_code' => 5, 'tp_cen' => 'Laboratoire'],
        ]);

        // centre_sante (7 centres)
        $this->db->table('centre_sante')->insertBatch([
            ['cen_code' => 1, 'cen_nom' => 'Pavillon Sainte Fleur', 'cen_adresse' => 'Antananarivo', 'tp_cen_code' => 1],
            ['cen_code' => 2, 'cen_nom' => 'Hôpital Mère-Enfant de Tsaralalana (HMET)', 'cen_adresse' => 'Tsaralalana, Antananarivo', 'tp_cen_code' => 1],
            ['cen_code' => 3, 'cen_nom' => 'Hôpital Joseph Ravoahangy Andrianavalona (HJRA)', 'cen_adresse' => 'Ampefiloha, Antananarivo', 'tp_cen_code' => 1],
            ['cen_code' => 4, 'cen_nom' => 'Hôpital Joseph Raseta Befelatanana (HJRB)', 'cen_adresse' => 'Befelatanana, Antananarivo', 'tp_cen_code' => 1],
            ['cen_code' => 5, 'cen_nom' => 'Centre Hospitalier de Soavinandriana (CENHOSOA)', 'cen_adresse' => 'Soavinandriana, Antananarivo', 'tp_cen_code' => 1],
            ['cen_code' => 6, 'cen_nom' => 'Institut Pasteur de Madagascar (IPM)', 'cen_adresse' => 'Antananarivo', 'tp_cen_code' => 5],
            ['cen_code' => 7, 'cen_nom' => 'Pharmacie Unité I/HJRB', 'cen_adresse' => 'Befelatanana, Antananarivo', 'tp_cen_code' => 4],
        ]);

        // conj_status
        $this->db->table('conj_status')->insertBatch([
            ['cjs_id' => 1, 'cjs_libelle' => 'MARIÉ'],
            ['cjs_id' => 2, 'cjs_libelle' => 'DIVORCÉ'],
            ['cjs_id' => 3, 'cjs_libelle' => 'DÉCÉDÉ'],
        ]);

        // =====================================================
        // UTILISATEUR ADMIN PAR DÉFAUT
        // =====================================================
        $this->db->table('users')->insert([
            'id' => 1,
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_BCRYPT),
            'nom' => 'Super',
            'prenom' => 'Administrateur',
            'role' => 'admin',
        ]);

        // =====================================================
        // RESET SEQUENCES
        // =====================================================
        $sequences = [
            'type_conge_typ_code_seq' => 'type_conge::typ_code',
            'region_reg_code_seq' => 'region::reg_code',
            'type_centre_tp_cen_code_seq' => 'type_centre::tp_cen_code',
            'direction_dir_code_seq' => 'direction::dir_code',
            'service_srvc_code_seq' => 'service::srvc_code',
            'poste_pst_code_seq' => 'poste::pst_code',
            'rang_hierarchique_rhq_code_seq' => 'rang_hierarchique::rhq_code',
            'type_document_tdoc_code_seq' => 'type_document::tdoc_code',
            'type_contrat_tcontrat_code_seq' => 'type_contrat::tcontrat_code',
            'statut_armp_stt_armp_code_seq' => 'statut_armp::stt_armp_code',
            'position__pos_code_seq' => 'position_::pos_code',
            'motif_affectation_m_aff_code_seq' => 'motif_affectation::m_aff_code',
            'occupation_poste_occpst_code_seq' => 'occupation_poste::occpst_code',
            'centre_sante_cen_code_seq' => 'centre_sante::cen_code',
            'conj_status_cjs_id_seq' => 'conj_status::cjs_id',
            'users_id_seq' => 'users::id',
        ];

        foreach ($sequences as $seqName => $tableCol) {
            [$table, $col] = explode('::', $tableCol);
            $this->db->query("SELECT setval('{$seqName}', (SELECT COALESCE(MAX({$col}), 1) FROM {$table}))");
        }
    }
}
