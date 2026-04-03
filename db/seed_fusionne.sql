-- =====================================================
-- SEED FUSIONNÉ — VALEURS PAR DÉFAUT UNIQUEMENT
-- SI-GPRH + CARRIÈRE-STAGIAIRE
-- =====================================================
-- Exécuter après schema_fusionne.sql
-- =====================================================

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- =====================================================
-- CARRIÈRE-STAGIAIRE : InitialSeeder.php
-- =====================================================

-- motif_affectation
INSERT INTO motif_affectation (m_aff_code, m_aff_motif, m_aff_type) VALUES
(1, 'Affectation Initiale', 'Permanente'),
(2, 'Mutation', 'Permanente'),
(3, 'Promotion', 'Permanente'),
(5, 'Détachement', 'Temporaire');

-- direction (7 directions ARMP)
INSERT INTO direction (dir_code, dir_abbreviation, dir_nom) VALUES
(1, NULL, 'Direction Générale'),
(2, NULL, 'Comité de Recours et de Réglementation'),
(3, NULL, 'Comité de Règlement des Différends'),
(4, NULL, 'Direction de l''Audit Interne'),
(5, NULL, 'Direction des Affaires Administratives et Financières'),
(6, NULL, 'Direction de la Formation et de la Documentation'),
(7, NULL, 'Direction du Système d''Information');

-- rang_hierarchique
INSERT INTO rang_hierarchique (rhq_code, rhq_rang, rhq_niveau) VALUES
(1, 'HEE', 'Niveau1'),
(2, 'Chef de Service', 'Niveau2'),
(3, 'Cadre', 'Niveau3'),
(4, 'Agent exécutant', 'Niveau4');

-- service (10 services)
INSERT INTO service (srvc_code, srvc_nom, dir_code) VALUES
(1, 'Agence Comptable', 1),
(2, 'Service Ressource Humaines', 5),
(3, 'Service de Suivi Evaluation', 4),
(4, 'Service Administratif et Financier', 5),
(5, 'Service Coordination et Régulation', 4),
(6, 'Service de la Documentation', 6),
(7, 'Service de la Formation', 6),
(8, 'Service de Coordination Général des Activités', 1),
(9, 'Service d''Administration Système et Réseau', 7),
(10, 'Service Section Recours', 2);

-- poste (38 postes ARMP)
INSERT INTO poste (pst_code, tsup_code, rhq_code, pst_fonction, pst_mission, srvc_code, dir_code) VALUES
(1, NULL, 1, 'Directeur Général', 'Assurer la direction générale, la coordination et le contrôle des activités de l''ARMP.', NULL, 1),
(2, NULL, 1, 'Président du Comité de Recours et de Réglementation', 'Présider le comité, veiller à l''application des textes et à la qualité des décisions.', NULL, 2),
(3, NULL, 1, 'Présidente du Comité de Règlement des Différends', 'Présider le comité, garantir le traitement impartial des différends.', NULL, 3),
(4, NULL, 1, 'Directeur de l''Audit Interne', 'Planifier et superviser les missions d''audit interne, évaluer les risques et proposer des recommandations.', NULL, 4),
(5, NULL, 1, 'Directeur des Affaires Administratives et Financières', 'Gérer les ressources financières et administratives, superviser la comptabilité et le budget.', NULL, 5),
(6, NULL, 1, 'Directeur de la Formation et de la documentation', 'Développer la politique de formation, gérer le centre de documentation et les ressources pédagogiques.', NULL, 6),
(7, NULL, 1, 'Directeur du Système d''Information', 'Piloter le système d''information, garantir la sécurité et la performance des infrastructures.', NULL, 7),
(8, NULL, 2, 'Agent Comptable', 'Tenir la comptabilité, effectuer les opérations financières et assurer le suivi budgétaire.', 1, 1),
(9, NULL, 2, 'Responsable des Ressources Humaines', 'Gérer le personnel, les carrières, la paie et les relations sociales.', 2, 5),
(10, NULL, 2, 'Chef de service Suivi Evaluation', 'Coordonner le suivi et l''évaluation des projets, analyser les indicateurs de performance.', 3, 4),
(11, NULL, 2, 'Responsable Administratif et Financier', 'Superviser les activités administratives et financières du service.', 4, 5),
(12, NULL, 2, 'Chef de service Coordination et Régulation', 'Assurer la coordination des activités de régulation et veiller à leur conformité.', 5, 4),
(13, NULL, 2, 'Chef de Service de la Documentation', 'Gérer les collections documentaires, assurer la veille informationnelle et la diffusion.', 6, 6),
(14, NULL, 2, 'Chef de Service de la FORMATION', 'Organiser et animer les actions de formation, évaluer leur impact.', 7, 6),
(15, NULL, 2, 'Coordonnateur Général des Activités', 'Coordonner l''ensemble des activités opérationnelles et assurer la liaison entre les services.', 8, 1),
(16, NULL, 2, 'Chef de Service Administration Système et Réseau', 'Administrer les serveurs, réseaux et assurer la maintenance technique.', 9, 7),
(17, NULL, 4, 'Agent Administratif', 'Assurer les tâches administratives courantes, accueil, traitement des courriers.', 4, 5),
(18, NULL, 4, 'Personnel d''Appui Ressources Humaines', 'Assister le responsable RH dans la gestion administrative du personnel.', 2, 5),
(19, NULL, 4, 'Personnel d''Appui Système Réseau', 'Participer à la maintenance et à l''exploitation des systèmes et réseaux.', 9, 7),
(20, NULL, 4, 'Personnel d''Appui Web', 'Contribuer à la gestion et à la mise à jour du site web.', 9, 7),
(21, NULL, 4, 'Personnel d''Appui de l''Agence Comptable', 'Assister l''agent comptable dans les tâches de saisie et de suivi.', 1, 1),
(22, NULL, 4, 'Personnel d''Appui de la Documentation', 'Aider à la gestion physique et numérique des documents.', 6, 6),
(23, NULL, 4, 'Personnel d''Appui de la Formation', 'Supporter logistique et administratif des sessions de formation.', 7, 6),
(24, NULL, 4, 'Personnel d''Appui Administratif et Financier', 'Assister le RAF dans les tâches administratives et financières.', 4, 5),
(25, NULL, 4, 'Webmaster', 'Concevoir, développer et maintenir le site web de l''ARMP.', 9, 7),
(26, NULL, 4, 'Secrétaire Particulière de la Direction Générale', 'Assurer le secrétariat et la gestion de l''agenda du Directeur Général.', NULL, 1),
(27, NULL, 4, 'Personnel d''Appui Communication', 'Participer aux actions de communication interne et externe.', 8, 1),
(28, NULL, 4, 'Dépositaire Comptable', 'Gérer les fonds et valeurs, assurer la tenue de la caisse.', 4, 5),
(29, NULL, 4, 'Standardiste / Réceptionniste', 'Accueillir les visiteurs, gérer le standard téléphonique.', 4, 5),
(30, NULL, 4, 'Aide Comptable', 'Assister le comptable dans les travaux de saisie et de rapprochement.', 4, 5),
(31, NULL, 4, 'Agent Administratif', 'Effectuer les tâches administratives pour le compte de la direction générale.', 1, 1),
(32, NULL, 4, 'Chauffeur', 'Conduire les véhicules de service, assurer les déplacements professionnels.', 4, 5),
(33, NULL, 4, 'Coursier / Vaguemestre', 'Assurer la distribution du courrier et des documents.', 4, 5),
(34, NULL, 4, 'Technicien de Surface', 'Assurer le nettoyage et l''entretien des locaux.', 4, 5),
(35, NULL, 4, 'Agent de sécurité', 'Surveiller les locaux, contrôler les accès.', 4, 5),
(36, NULL, 3, 'Juriste', 'Conseiller juridique, rédiger des actes et participer aux contentieux.', 10, 2),
(37, NULL, 3, 'Economiste', 'Réaliser des études économiques, analyser les données sectorielles.', 10, 2),
(38, NULL, 3, 'Traducteur/Rédacteur', 'Traduire des documents, rédiger des rapports et comptes-rendus.', 10, 2);

-- occupation_poste (quotas)
INSERT INTO occupation_poste (occpst_code, pst_code, quota, nb_occupe, nb_vacant, nb_encessation) VALUES
(1, 1, 1, 0, 1, 0),
(2, 2, 1, 0, 1, 0),
(3, 3, 1, 0, 1, 0),
(4, 4, 1, 0, 1, 0),
(5, 5, 1, 0, 1, 0),
(6, 6, 1, 0, 1, 0),
(7, 7, 1, 0, 1, 0),
(8, 8, 1, 0, 1, 0),
(9, 9, 1, 0, 1, 0),
(10, 10, 1, 0, 1, 0),
(11, 11, 1, 0, 1, 0),
(12, 12, 1, 1, 0, 0),
(13, 13, 1, 0, 1, 0),
(14, 14, 1, 0, 1, 0),
(15, 15, 1, 0, 1, 0),
(16, 16, 1, 1, 0, 0),
(17, 17, 2, 0, 2, 0),
(18, 18, 2, 0, 2, 0),
(19, 19, 1, 0, 1, 0),
(20, 20, 1, 0, 1, 0),
(21, 21, 1, 0, 1, 0),
(22, 22, 1, 0, 1, 0),
(23, 23, 1, 0, 1, 0),
(24, 24, 1, 0, 0, 1),
(25, 25, 1, 0, 1, 0),
(26, 26, 1, 0, 1, 0),
(27, 27, 1, 0, 1, 0),
(28, 28, 1, 0, 1, 0),
(29, 29, 1, 0, 1, 0),
(30, 30, 1, 0, 1, 0),
(31, 31, 2, 0, 2, 0),
(32, 32, 15, 0, 15, 0),
(33, 33, 2, 0, 2, 0),
(34, 34, 3, 0, 3, 0),
(35, 35, 6, 0, 6, 0),
(36, 36, 1, 0, 1, 0),
(37, 37, 2, 0, 3, 0),
(38, 38, 1, 0, 1, 0);

-- position_
INSERT INTO position_ (pos_code, pos_type) VALUES
(1, 'en service'),
(2, 'en cessation'),
(3, 'sortie');

-- sortie_type
INSERT INTO sortie_type (s_type_code, s_type_motif) VALUES
('RETRAITE', 'Retraite'),
('RENVOI', 'Renvoi'),
('ABROGATION', 'Abrogation');

-- statut_armp
INSERT INTO statut_armp (stt_armp_code, stt_armp_statut) VALUES
(1, 'CNM'),
(2, 'Fonctionnaire/armp'),
(3, 'EFA/armp'),
(4, 'Nomination'),
(5, 'Mis en emploi');

-- type_contrat
INSERT INTO type_contrat (tcontrat_code, tcontrat_nom) VALUES
(1, 'Fonctionnaire'),
(2, 'ELD'),
(3, 'EFA');

-- type_document
INSERT INTO type_document (tdoc_code, tdoc_nom) VALUES
(1, 'Attestation de non interruption de service'),
(2, 'Attestation d''emploi'),
(3, 'Certificat de travail'),
(4, 'Certificat administratif'),
(5, 'Attestation de stage'),
(6, 'Convention de stage');

-- type_entree
INSERT INTO type_entree (e_type_code, e_type_motif) VALUES
('1', 'Recrutement'),
('2', 'Nomination'),
('3', 'Transfert'),
('4', 'Promotion');

-- users (carrière-stagiaire)
INSERT INTO users (id, username, password, nom, prenom, role, created_at, updated_at) VALUES
(1, 'admin', '$2a$06$4lW7fvxHgGjrhzraqiD8yeqLySdn5Ps0u/mqt9LhxKDW2/aIst9O2', 'Super', 'Administrateur', '0', '2025-12-15 23:19:48', '2025-12-15 23:19:48');

-- =====================================================
-- SI-GPRH : Seeders (6 fichiers)
-- =====================================================

-- TypeCongeSeeder
INSERT INTO type_conge (typ_code, typ_appelation, typ_ref) VALUES
(1, 'Congé annuel', 'CA'),
(2, 'Repos maladie', 'RM'),
(3, 'Congé maternité', 'CM'),
(4, 'Congé paternité', 'CP');

-- RegionSeeder (23 régions de Madagascar)
INSERT INTO region (reg_code, reg_nom) VALUES
(1, 'Alaotra-Mangoro'),
(2, 'Amoron''i Mania'),
(3, 'Analamanga'),
(4, 'Analanjirofo'),
(5, 'Androy'),
(6, 'Anosy'),
(7, 'Atsimo-Andrefana'),
(8, 'Atsimo-Atsinanana'),
(9, 'Atsinanana'),
(10, 'Bongolava'),
(11, 'Boeny'),
(12, 'Betsiboka'),
(13, 'Diana'),
(14, 'Haute Matsiatra'),
(15, 'Ihorombe'),
(16, 'Itasy'),
(17, 'Melaky'),
(18, 'Menabe'),
(19, 'Sava'),
(20, 'Vakinankaratra'),
(21, 'Vatovavy'),
(22, 'Fitovinany'),
(23, 'Matsiatra Ambony');

-- TypeCentreSeeder
INSERT INTO type_centre (tp_cen_code, tp_cen) VALUES
(1, 'Hôpital public'),
(2, 'Clinique privée'),
(3, 'Centre de santé de base'),
(4, 'Pharmacie'),
(5, 'Laboratoire');

-- CentreSanteSeeder (7 centres)
INSERT INTO centre_sante (cen_code, cen_nom, cen_adresse, tp_cen_code) VALUES
(1, 'Pavillon Sainte Fleur', 'Antananarivo', 1),
(2, 'Hôpital Mère-Enfant de Tsaralalana (HMET)', 'Tsaralalana, Antananarivo', 1),
(3, 'Hôpital Joseph Ravoahangy Andrianavalona (HJRA)', 'Ampefiloha, Antananarivo', 1),
(4, 'Hôpital Joseph Raseta Befelatanana (HJRB)', 'Befelatanana, Antananarivo', 1),
(5, 'Centre Hospitalier de Soavinandriana (CENHOSOA)', 'Soavinandriana, Antananarivo', 1),
(6, 'Institut Pasteur de Madagascar (IPM)', 'Antananarivo', 5),
(7, 'Pharmacie Unité I/HJRB', 'Befelatanana, Antananarivo', 4);

-- =====================================================
-- RESET SEQUENCES
-- =====================================================

SELECT setval('type_conge_typ_code_seq', (SELECT COALESCE(MAX(typ_code), 1) FROM type_conge));
SELECT setval('region_reg_code_seq', (SELECT COALESCE(MAX(reg_code), 1) FROM region));
SELECT setval('type_centre_tp_cen_code_seq', (SELECT COALESCE(MAX(tp_cen_code), 1) FROM type_centre));
SELECT setval('direction_dir_code_seq', (SELECT COALESCE(MAX(dir_code), 1) FROM direction));
SELECT setval('service_srvc_code_seq', (SELECT COALESCE(MAX(srvc_code), 1) FROM service));
SELECT setval('poste_pst_code_seq', (SELECT COALESCE(MAX(pst_code), 1) FROM poste));
SELECT setval('rang_hierarchique_rhq_code_seq', (SELECT COALESCE(MAX(rhq_code), 1) FROM rang_hierarchique));
SELECT setval('type_document_tdoc_code_seq', (SELECT COALESCE(MAX(tdoc_code), 1) FROM type_document));
SELECT setval('type_contrat_tcontrat_code_seq', (SELECT COALESCE(MAX(tcontrat_code), 1) FROM type_contrat));
SELECT setval('statut_armp_stt_armp_code_seq', (SELECT COALESCE(MAX(stt_armp_code), 1) FROM statut_armp));
SELECT setval('position__pos_code_seq', (SELECT COALESCE(MAX(pos_code), 1) FROM position_));
SELECT setval('motif_affectation_m_aff_code_seq', (SELECT COALESCE(MAX(m_aff_code), 1) FROM motif_affectation));
SELECT setval('occupation_poste_occpst_code_seq', (SELECT COALESCE(MAX(occpst_code), 1) FROM occupation_poste));
SELECT setval('centre_sante_cen_code_seq', (SELECT COALESCE(MAX(cen_code), 1) FROM centre_sante));
SELECT setval('users_id_seq', (SELECT COALESCE(MAX(id), 1) FROM users));

-- =====================================================
-- FIN
-- =====================================================

SELECT 'Seed fusionné (valeurs par défaut) inséré avec succès!' AS status;
