-- =====================================================
-- SEED FUSIONNÉ SI-GPRH + GESTION CARRIÈRE
-- Base de données unifiée pour l'ARMP
-- =====================================================
-- Exécuter après schema_fusionne.sql
-- \c grh_unifie;
-- =====================================================

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- =====================================================
-- 1. TABLES RÉFÉRENTIELLES (sans FK)
-- =====================================================

-- Types d'entrée (Gestion Carrière)
INSERT INTO type_entree (e_type_code, e_type_motif) VALUES
('REC', 'Recrutement'),
('NOM', 'Nomination'),
('TRF', 'Transfert'),
('PRO', 'Promotion');

-- Types de sortie (Gestion Carrière)
INSERT INTO sortie_type (s_type_code, s_type_motif, commentaire) VALUES
('DEM', 'Démission', 'Départ volontaire'),
('RET', 'Retraite', 'Fin de carrière'),
('LIC', 'Licenciement', 'Rupture de contrat'),
('DEC', 'Décès', 'Décès de l''employé'),
('FIN', 'Fin de contrat', 'CDD arrivé à terme');

-- Types de contrat (Gestion Carrière)
INSERT INTO type_contrat (tcontrat_code, tcontrat_nom) VALUES
(1, 'CDI'),
(2, 'CDD'),
(3, 'Stage'),
(4, 'Prestataire');

-- Statuts ARMP (Gestion Carrière)
INSERT INTO statut_armp (stt_armp_code, stt_armp_statut) VALUES
(1, 'Fonctionnaire'),
(2, 'Contractuel'),
(3, 'Prestataire'),
(4, 'Détaché');

-- Rangs hiérarchiques (Gestion Carrière)
INSERT INTO rang_hierarchique (rhq_code, rhq_rang, rhq_niveau) VALUES
(1, 'Rang 1', 'Directeur'),
(2, 'Rang 2', 'Chef'),
(3, 'Rang 3', 'Cadre'),
(4, 'Rang 4', 'Agent');

-- Tâches supplémentaires (Gestion Carrière)
INSERT INTO tache_suppl (tsup_code, tsup_tache) VALUES
(1, 'Supervision des équipes'),
(2, 'Gestion de projets'),
(3, 'Rédaction de rapports'),
(4, 'Audit interne'),
(5, 'Formation des agents');

-- Types de documents (Gestion Carrière)
INSERT INTO type_document (tdoc_code, tdoc_nom) VALUES
(1, 'Contrat de travail'),
(2, 'Décision de nomination'),
(3, 'Ordre de mission'),
(4, 'Attestation de travail'),
(5, 'Certificat de stage'),
(6, 'Note de service');

-- Positions administratives (Gestion Carrière)
INSERT INTO position_ (pos_code, pos_type) VALUES
(1, 'Activité'),
(2, 'Détachement'),
(3, 'Disponibilité'),
(4, 'Congé longue durée');

-- Types de congé (SI-GPRH)
INSERT INTO type_conge (typ_code, typ_appelation, typ_ref) VALUES
(1, 'Congé annuel', 'CA'),
(2, 'Repos maladie', 'RM'),
(3, 'Congé maternité', 'CM'),
(4, 'Congé paternité', 'CP');

-- Décisions administratives (SI-GPRH)
-- Règle FIFO: Décision année YY débloque solde année YY-1
-- Format: {num_ordre}/ARMP/DG-{YY}
INSERT INTO decision (dec_code, dec_num) VALUES
-- Décisions 2022 (débloquent soldes 2021)
(1, '001/ARMP/DG-22'),
(2, '002/ARMP/DG-22'),
(3, '003/ARMP/DG-22'),
(4, '004/ARMP/DG-22'),
(5, '005/ARMP/DG-22'),
(6, '006/ARMP/DG-22'),
(7, '007/ARMP/DG-22'),
(8, '008/ARMP/DG-22'),
(9, '009/ARMP/DG-22'),
(10, '010/ARMP/DG-22'),
(11, '011/ARMP/DG-22'),
(12, '012/ARMP/DG-22'),
(13, '013/ARMP/DG-22'),
(14, '014/ARMP/DG-22'),
(15, '015/ARMP/DG-22'),
-- Décisions 2023 (débloquent soldes 2022)
(16, '001/ARMP/DG-23'),
(17, '002/ARMP/DG-23'),
(18, '003/ARMP/DG-23'),
(19, '004/ARMP/DG-23'),
(20, '005/ARMP/DG-23'),
(21, '006/ARMP/DG-23'),
(22, '007/ARMP/DG-23'),
(23, '008/ARMP/DG-23'),
(24, '009/ARMP/DG-23'),
(25, '010/ARMP/DG-23'),
(26, '011/ARMP/DG-23'),
(27, '012/ARMP/DG-23'),
(28, '013/ARMP/DG-23'),
(29, '014/ARMP/DG-23'),
(30, '015/ARMP/DG-23'),
-- Décisions 2024 (débloquent soldes 2023)
(31, '001/ARMP/DG-24'),
(32, '002/ARMP/DG-24'),
(33, '003/ARMP/DG-24'),
(34, '004/ARMP/DG-24'),
(35, '005/ARMP/DG-24'),
(36, '006/ARMP/DG-24'),
(37, '007/ARMP/DG-24'),
(38, '008/ARMP/DG-24'),
(39, '009/ARMP/DG-24'),
(40, '010/ARMP/DG-24'),
(41, '011/ARMP/DG-24'),
(42, '012/ARMP/DG-24'),
(43, '013/ARMP/DG-24'),
(44, '014/ARMP/DG-24'),
(45, '015/ARMP/DG-24'),
-- Décisions 2025 (débloquent soldes 2024)
(46, '001/ARMP/DG-25'),
(47, '002/ARMP/DG-25'),
(48, '003/ARMP/DG-25'),
(49, '004/ARMP/DG-25'),
(50, '005/ARMP/DG-25'),
(51, '006/ARMP/DG-25'),
(52, '007/ARMP/DG-25'),
(53, '008/ARMP/DG-25'),
(54, '009/ARMP/DG-25'),
(55, '010/ARMP/DG-25'),
(56, '011/ARMP/DG-25'),
(57, '012/ARMP/DG-25'),
(58, '013/ARMP/DG-25'),
(59, '014/ARMP/DG-25'),
(60, '015/ARMP/DG-25');
-- NOTE: Pas de décision -26 car on est en 2025, elle sortira en 2026 pour débloquer soldes 2025

-- Régions (SI-GPRH)
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

-- Types de centre de santé (SI-GPRH)
INSERT INTO type_centre (tp_cen_code, tp_cen) VALUES
(1, 'Hôpital public'),
(2, 'Clinique privée'),
(3, 'Centre de santé de base'),
(4, 'Pharmacie'),
(5, 'Laboratoire');

-- Objets remboursement (SI-GPRH)
INSERT INTO objet_remboursement (obj_code, obj_article) VALUES
(1, 'Consultation médicale'),
(2, 'Médicaments'),
(3, 'Analyses médicales'),
(4, 'Radiographie'),
(5, 'Hospitalisation'),
(6, 'Montures et verres correcteurs'),
(7, 'Soins dentaires'),
(8, 'Kinésithérapie');

-- =====================================================
-- 2. STRUCTURE ORGANISATIONNELLE
-- =====================================================

-- Directions
INSERT INTO direction (dir_code, dir_nom, dir_abreviation) VALUES
(1, 'Direction Générale', 'DG'),
(2, 'Direction des Affaires Administratives et Financières', 'DAAF'),
(3, 'Direction des Systèmes d''Information', 'DSI'),
(4, 'Direction des Ressources Humaines', 'DRH'),
(5, 'Direction de la Passation des Appels d''Offres', 'DPA');

-- Services (rattachés aux directions)
INSERT INTO service (srvc_code, srvc_nom, dir_code) VALUES
(1, 'Service Ressources Humaines', 4),
(2, 'Service Comptabilité', 2),
(3, 'Service Logistique', 2),
(4, 'Service Informatique', 3),
(5, 'Service Passation', 5),
(6, 'Secrétariat Général', 1);

-- Postes (enrichis avec les deux projets)
INSERT INTO poste (pst_code, pst_fonction, pst_mission, pst_max, tsup_code, rhq_code, srvc_code, dir_code) VALUES
(1, 'Directeur Général', 'Superviser l''ensemble des directions', 1, 1, 1, NULL, 1),
(2, 'Directeur', 'Diriger une direction', 5, 1, 1, NULL, NULL),
(3, 'Chef de Service', 'Superviser un service', 10, 2, 2, NULL, NULL),
(4, 'Agent', 'Exécuter les tâches opérationnelles', 50, NULL, 4, NULL, NULL),
(5, 'Secrétaire', 'Gérer le secrétariat', 10, NULL, 3, NULL, NULL),
(6, 'Comptable', 'Gérer la comptabilité', 10, 3, 3, 2, 2),
(7, 'Chauffeur', 'Assurer le transport', 5, NULL, 4, 3, 2),
(8, 'Gardien', 'Assurer la sécurité', 5, NULL, 4, 3, 2),
(9, 'Chef de Service RH', 'Gérer les services RH', 1, 2, 2, 1, 4),
(10, 'Chef de Service Informatique', 'Superviser l''infrastructure SI', 1, 2, 2, 4, 3),
(11, 'Chef de Service Passation', 'Superviser les dossiers de passation', 1, 2, 2, 5, 5),
(12, 'Assistant Administratif', 'Assister les services administratifs', 5, 3, 3, NULL, 2),
(13, 'Technicien Réseau', 'Gérer les réseaux et équipements', 3, NULL, 4, 4, 3);

-- Fonction par direction (liaison poste-direction)
INSERT INTO fonction_direc (pst_code, dir_code, fonc_mission) VALUES
(1, 1, 'Directeur Général de l''ARMP'),
(2, 2, 'Directeur des Affaires Administratives et Financières'),
(2, 3, 'Directeur des Systèmes d''Information'),
(2, 4, 'Directeur des Ressources Humaines'),
(2, 5, 'Directeur de la Passation des Appels d''Offres'),
(3, 3, 'Chef de Service DSI'),
(3, 4, 'Chef du Service Ressources Humaines'),
(3, 2, 'Chef du Service Comptabilité'),
(4, 3, 'Agent DSI'),
(4, 4, 'Agent Ressources Humaines'),
(4, 2, 'Agent DAAF'),
(5, 1, 'Secrétaire de Direction Générale'),
(5, 2, 'Secrétaire DAAF'),
(6, 2, 'Comptable principal'),
(7, 2, 'Chauffeur'),
(8, 2, 'Gardien');

-- Motifs d'affectation (Gestion Carrière)
INSERT INTO motif_affectation (m_aff_code, m_aff_motif, m_aff_type) VALUES
(1, 'Affectation Initiale', 'Permanente'),
(2, 'Mutation', 'Permanente'),
(3, 'Promotion', 'Permanente'),
(4, 'Intérim', 'Temporaire'),
(5, 'Détachement', 'Temporaire'),
(6, 'Mise à disposition', 'Temporaire');

-- =====================================================
-- 3. EMPLOYÉS (TABLE UNIFIÉE)
-- =====================================================

INSERT INTO employe (emp_code, emp_matricule, emp_nom, emp_prenom, emp_titre, emp_sexe, emp_datenaissance, emp_im_armp, emp_im_etat, emp_mail, emp_cin, emp_date_embauche, emp_disponibilite, date_entree, e_type_code) VALUES
-- Direction Générale
(1, 'MAT001', 'IANJARAFANOMEZANTSOA', 'Jean', 'M.', true, '1975-05-20', 'DG001', 'E001', 'ianjarafanomezantsoa8@gmail.com', '101 011 001 001', '2015-01-15', true, '2015-01-15', 'NOM'),
-- Directeurs
(2, 'MAT002', 'RANDRIA', 'Marie', 'Mme', false, '1980-03-15', 'DIR001', 'E002', 'loiclooney7@gmail.com', '101 011 001 002', '2016-03-01', true, '2016-03-01', 'NOM'),
(3, 'MAT003', 'RASOA', 'Patrick', 'M.', true, '1978-08-10', 'DIR002', 'E003', 'steffodin@gmail.com', '101 011 001 003', '2017-06-15', true, '2017-06-15', 'NOM'),
(4, 'MAT004', 'RAKOTOMANGA', 'Andry', 'M.', true, '1982-11-25', 'DIR003', 'E004', 'andry.rakotomanga@armp.mg', '101 011 001 004', '2017-09-01', true, '2017-09-01', 'NOM'),
(5, 'MAT005', 'RAZAFINDRAKOTO', 'Hanta', 'Mme', false, '1985-02-18', 'DIR004', 'E005', 'hanta.razafindrakoto@armp.mg', '101 011 001 005', '2018-01-15', true, '2018-01-15', 'NOM'),
-- Chefs de Service
(6, 'MAT006', 'RABE', 'Hery', 'M.', true, '1983-07-12', 'CS001', 'E006', 'catykitkit@gmail.com', '101 011 001 006', '2018-02-01', true, '2018-02-01', 'REC'),
(7, 'MAT007', 'RAVELO', 'Carol', 'Mme', false, '1984-09-05', 'CS002', 'E007', 'ncarol506@gmail.com', '101 011 001 007', '2018-05-10', true, '2018-05-10', 'REC'),
(8, 'MAT008', 'ANDRIA', 'Tiana', 'M.', true, '1986-04-22', 'CS003', 'E008', 'iomjik53@gmail.com', '101 011 001 008', '2019-01-20', true, '2019-01-20', 'REC'),
-- Agents
(9, 'MAT009', 'RAKOTOSON', 'Niry', 'Mme', false, '1990-06-08', 'AG001', 'E009', 'uilokan@gmail.com', '101 011 001 009', '2020-03-01', true, '2020-03-01', 'REC'),
(10, 'MAT010', 'RASOANAIVO', 'Voahangy', 'Mme', false, '1991-12-14', 'AG002', 'E010', 'jiljoul81@gmail.com', '101 011 001 010', '2020-06-15', true, '2020-06-15', 'REC'),
(11, 'MAT011', 'ANDRIANAIVO', 'Hery', 'M.', true, '1992-03-30', 'AG003', 'E011', 'sme27577@gmail.com', '101 011 001 011', '2021-01-10', true, '2021-01-10', 'REC'),
(12, 'MAT012', 'RAMANANTSOA', 'Lova', 'M.', true, '1993-08-17', 'AG004', 'E012', 'willharingtonw99@gmail.com', '101 011 001 012', '2021-04-01', true, '2021-04-01', 'REC'),
-- Autres services
(13, 'MAT013', 'RAHARISOA', 'Mialy', 'Mme', false, '1988-01-25', 'SEC001', 'E013', 'mialy.raharisoa@armp.mg', '101 011 001 013', '2019-08-01', true, '2019-08-01', 'REC'),
(14, 'MAT014', 'RAZAFY', 'Tahina', 'Mme', false, '1989-05-10', 'SEC002', 'E014', 'tahina.razafy@armp.mg', '101 011 001 014', '2020-02-15', true, '2020-02-15', 'REC'),
(15, 'MAT015', 'RATSIMBA', 'Hasina', 'M.', true, '1987-10-03', 'CPT001', 'E015', 'hasina.ratsimba@armp.mg', '101 011 001 015', '2018-09-01', true, '2018-09-01', 'REC');

-- Contacts employés
INSERT INTO contact (id_contact, numero, emp_code) VALUES
(1, '034 00 000 01', 1),
(2, '034 00 000 02', 2),
(3, '034 00 000 03', 3),
(4, '034 00 000 04', 4),
(5, '034 00 000 05', 5),
(6, '034 00 000 06', 6),
(7, '034 00 000 07', 7),
(8, '034 00 000 08', 8),
(9, '034 00 000 09', 9),
(10, '034 00 000 10', 10),
(11, '034 00 000 11', 11),
(12, '034 00 000 12', 12),
(13, '034 00 000 13', 13),
(14, '034 00 000 14', 14),
(15, '034 00 000 15', 15);

-- Statut ARMP des employés
INSERT INTO statut_emp (emp_code, stt_armp_code, date_) VALUES
(1, 1, '2015-01-15'),  -- Fonctionnaire
(2, 1, '2016-03-01'),
(3, 1, '2017-06-15'),
(4, 1, '2017-09-01'),
(5, 1, '2018-01-15'),
(6, 2, '2018-02-01'),  -- Contractuel
(7, 2, '2018-05-10'),
(8, 2, '2019-01-20'),
(9, 2, '2020-03-01'),
(10, 2, '2020-06-15'),
(11, 2, '2021-01-10'),
(12, 2, '2021-04-01'),
(13, 2, '2019-08-01'),
(14, 2, '2020-02-15'),
(15, 2, '2018-09-01');

-- Position administrative
INSERT INTO pos_emp (emp_code, pos_code, date_) VALUES
(1, 1, '2015-01-15'),
(2, 1, '2016-03-01'),
(3, 1, '2017-06-15'),
(4, 1, '2017-09-01'),
(5, 1, '2018-01-15'),
(6, 1, '2018-02-01'),
(7, 1, '2018-05-10'),
(8, 1, '2019-01-20'),
(9, 1, '2020-03-01'),
(10, 1, '2020-06-15'),
(11, 1, '2021-01-10'),
(12, 1, '2021-04-01'),
(13, 1, '2019-08-01'),
(14, 1, '2020-02-15'),
(15, 1, '2018-09-01');

-- =====================================================
-- 4. AFFECTATIONS
-- =====================================================

INSERT INTO affectation (affec_code, emp_code, pst_code, dir_code, affec_date_debut, affec_date_fin, affec_type_contrat, affec_etat, m_aff_code, tcontrat_code) VALUES
(1, 1, 1, 1, '2015-01-15', NULL, 'CDI', 'Actif', 1, 1),    -- DG -> DG
(2, 2, 2, 2, '2016-03-01', NULL, 'CDI', 'Actif', 1, 1),    -- RANDRIA -> DAAF
(3, 3, 2, 3, '2017-06-15', NULL, 'CDI', 'Actif', 1, 1),    -- RASOA -> DSI
(4, 4, 2, 4, '2017-09-01', NULL, 'CDI', 'Actif', 1, 1),    -- RAKOTOMANGA -> DRH
(5, 5, 2, 5, '2018-01-15', NULL, 'CDI', 'Actif', 1, 1),    -- RAZAFINDRAKOTO -> DPA
(6, 6, 9, 4, '2018-02-01', NULL, 'CDI', 'Actif', 1, 1),    -- RABE -> Chef SRH
(7, 7, 10, 3, '2018-05-10', NULL, 'CDI', 'Actif', 1, 1),   -- RAVELO -> Chef SI
(8, 8, 11, 5, '2019-01-20', NULL, 'CDI', 'Actif', 1, 1),   -- ANDRIA -> Chef Passation
(9, 9, 4, 3, '2020-03-01', NULL, 'CDI', 'Actif', 1, 1),    -- RAKOTOSON -> Agent DSI
(10, 10, 13, 3, '2020-06-15', NULL, 'CDI', 'Actif', 1, 1), -- RASOANAIVO -> Technicien
(11, 11, 4, 3, '2021-01-10', NULL, 'CDD', 'Actif', 1, 2),  -- ANDRIANAIVO -> Agent DSI
(12, 12, 4, 3, '2021-04-01', NULL, 'CDD', 'Actif', 1, 2),  -- RAMANANTSOA -> Agent DSI
(13, 13, 5, 1, '2019-08-01', NULL, 'CDI', 'Actif', 1, 1),  -- RAHARISOA -> Secrétaire DG
(14, 14, 5, 2, '2020-02-15', NULL, 'CDI', 'Actif', 1, 1),  -- RAZAFY -> Secrétaire DAAF
(15, 15, 6, 2, '2018-09-01', NULL, 'CDI', 'Actif', 1, 1);  -- RATSIMBA -> Comptable

-- =====================================================
-- 5. SIGNATURES (SI-GPRH)
-- =====================================================

INSERT INTO signature (sign_code, sign_libele, sign_observation, emp_code) VALUES
(1, 'Directeur Général', 'Signataire final', 1),
(2, 'DAAF', 'Validation administrative', 2),
(3, 'RRH', 'Responsable RH', 6),
(4, 'Directeur DSI', 'Directeur de Direction', 3),
(5, 'Directeur DRH', 'Directeur RH', 4);

-- =====================================================
-- 6. FAMILLE EMPLOYÉ (SI-GPRH)
-- =====================================================

-- Conjoints
INSERT INTO conjointe (conj_code, conj_nom, conj_sexe) VALUES
(1, 'RAKO Lalao', false),
(2, 'RANDRI Toky', true),
(3, 'RABE Soa', false),
(4, 'ANDRIA Vola', false),
(5, 'RAKO Haja', true);

-- Association Employé-Conjoint
INSERT INTO emp_conj (emp_code, conj_code) VALUES
(1, 1),   -- DG marié à Lalao
(2, 2),   -- Marie mariée à Toky
(6, 3),   -- Hery marié à Soa
(11, 4),  -- Hery A. marié à Vola
(13, 5);  -- Mialy mariée à Haja

-- Enfants (Format numéro: ENF{XXX})
INSERT INTO enfant (enf_code, enf_nom, enf_num, date_naissance, emp_code) VALUES
-- Enfants du DG
(1, 'RAKOTO Miora', 'ENF001', '2010-05-15', 1),
(2, 'RAKOTO Aina', 'ENF002', '2015-08-20', 1),
-- Enfants de Marie
(3, 'RANDRIA Fehizoro', 'ENF003', '2018-03-10', 2),
-- Enfants de Hery Rabe
(4, 'RABE Mahefa', 'ENF004', '2019-11-05', 6),
(5, 'RABE Ony', 'ENF005', '2022-06-15', 6),
-- Enfants de Voahangy
(6, 'RASOANAIVO Tiavina', 'ENF006', '2020-01-20', 10),
-- Enfants de Hery Andrianaivo
(7, 'ANDRIANAIVO Kanto', 'ENF007', '2021-04-12', 11);

-- =====================================================
-- 7. SOLDES DE CONGÉ FIFO
-- =====================================================
-- Règle FIFO: On débite les soldes les plus anciens en premier
-- Le solde de l'année N ne peut être utilisé qu'à partir de l'année N+1
-- quand la décision DG-{N+1} est sortie
-- En 2025: soldes 2021 à 2024 sont disponibles (décisions 22 à 25)
-- Le solde 2025 sera disponible en 2026 avec la décision DG-26

-- SOLDES 2021 (décision DG-22 sortie en 2022) - Presque épuisés
INSERT INTO solde_conge (sld_code, sld_dispo, sld_anne, sld_initial, sld_restant, sld_maj, emp_code, dec_code) VALUES
(1, 1, 2021, 30.0, 2.0, '2022-01-01 00:00:00', 1, 1),
(2, 1, 2021, 30.0, 0.0, '2022-01-01 00:00:00', 2, 2),
(3, 1, 2021, 30.0, 1.5, '2022-01-01 00:00:00', 3, 3),
(4, 1, 2021, 30.0, 0.0, '2022-01-01 00:00:00', 4, 4),
(5, 1, 2021, 30.0, 3.0, '2022-01-01 00:00:00', 5, 5),
(6, 1, 2021, 30.0, 0.0, '2022-01-01 00:00:00', 6, 6),
(7, 1, 2021, 30.0, 2.0, '2022-01-01 00:00:00', 7, 7),
(8, 1, 2021, 30.0, 1.0, '2022-01-01 00:00:00', 8, 8),
(9, 1, 2021, 30.0, 0.0, '2022-01-01 00:00:00', 9, 9),
(10, 1, 2021, 30.0, 0.0, '2022-01-01 00:00:00', 10, 10),
(11, 1, 2021, 30.0, 5.0, '2022-01-01 00:00:00', 11, 11),
(12, 1, 2021, 30.0, 3.0, '2022-01-01 00:00:00', 12, 12),
(13, 1, 2021, 30.0, 0.0, '2022-01-01 00:00:00', 13, 13),
(14, 1, 2021, 30.0, 1.5, '2022-01-01 00:00:00', 14, 14),
(15, 1, 2021, 30.0, 0.0, '2022-01-01 00:00:00', 15, 15);

-- SOLDES 2022 (décision DG-23 sortie en 2023) - Partiellement utilisés
INSERT INTO solde_conge (sld_code, sld_dispo, sld_anne, sld_initial, sld_restant, sld_maj, emp_code, dec_code) VALUES
(16, 1, 2022, 30.0, 12.0, '2023-01-01 00:00:00', 1, 16),
(17, 1, 2022, 30.0, 8.0, '2023-01-01 00:00:00', 2, 17),
(18, 1, 2022, 30.0, 15.0, '2023-01-01 00:00:00', 3, 18),
(19, 1, 2022, 30.0, 10.0, '2023-01-01 00:00:00', 4, 19),
(20, 1, 2022, 30.0, 20.0, '2023-01-01 00:00:00', 5, 20),
(21, 1, 2022, 30.0, 18.0, '2023-01-01 00:00:00', 6, 21),
(22, 1, 2022, 30.0, 22.0, '2023-01-01 00:00:00', 7, 22),
(23, 1, 2022, 30.0, 14.0, '2023-01-01 00:00:00', 8, 23),
(24, 1, 2022, 30.0, 25.0, '2023-01-01 00:00:00', 9, 24),
(25, 1, 2022, 30.0, 20.0, '2023-01-01 00:00:00', 10, 25),
(26, 1, 2022, 30.0, 28.0, '2023-01-01 00:00:00', 11, 26),
(27, 1, 2022, 30.0, 26.0, '2023-01-01 00:00:00', 12, 27),
(28, 1, 2022, 30.0, 16.0, '2023-01-01 00:00:00', 13, 28),
(29, 1, 2022, 30.0, 19.0, '2023-01-01 00:00:00', 14, 29),
(30, 1, 2022, 30.0, 21.0, '2023-01-01 00:00:00', 15, 30);

-- SOLDES 2023 (décision DG-24 sortie en 2024) - Moins utilisés
INSERT INTO solde_conge (sld_code, sld_dispo, sld_anne, sld_initial, sld_restant, sld_maj, emp_code, dec_code) VALUES
(31, 1, 2023, 30.0, 25.0, '2024-01-01 00:00:00', 1, 31),
(32, 1, 2023, 30.0, 22.0, '2024-01-01 00:00:00', 2, 32),
(33, 1, 2023, 30.0, 27.0, '2024-01-01 00:00:00', 3, 33),
(34, 1, 2023, 30.0, 20.0, '2024-01-01 00:00:00', 4, 34),
(35, 1, 2023, 30.0, 28.0, '2024-01-01 00:00:00', 5, 35),
(36, 1, 2023, 30.0, 24.0, '2024-01-01 00:00:00', 6, 36),
(37, 1, 2023, 30.0, 26.0, '2024-01-01 00:00:00', 7, 37),
(38, 1, 2023, 30.0, 23.0, '2024-01-01 00:00:00', 8, 38),
(39, 1, 2023, 30.0, 29.0, '2024-01-01 00:00:00', 9, 39),
(40, 1, 2023, 30.0, 27.0, '2024-01-01 00:00:00', 10, 40),
(41, 1, 2023, 30.0, 30.0, '2024-01-01 00:00:00', 11, 41),
(42, 1, 2023, 30.0, 28.0, '2024-01-01 00:00:00', 12, 42),
(43, 1, 2023, 30.0, 25.0, '2024-01-01 00:00:00', 13, 43),
(44, 1, 2023, 30.0, 26.0, '2024-01-01 00:00:00', 14, 44),
(45, 1, 2023, 30.0, 24.0, '2024-01-01 00:00:00', 15, 45);

-- SOLDES 2024 (décision DG-25 sortie en 2025) - Récents, peu touchés
INSERT INTO solde_conge (sld_code, sld_dispo, sld_anne, sld_initial, sld_restant, sld_maj, emp_code, dec_code) VALUES
(46, 1, 2024, 30.0, 30.0, '2025-01-01 00:00:00', 1, 46),
(47, 1, 2024, 30.0, 29.0, '2025-01-01 00:00:00', 2, 47),
(48, 1, 2024, 30.0, 30.0, '2025-01-01 00:00:00', 3, 48),
(49, 1, 2024, 30.0, 28.0, '2025-01-01 00:00:00', 4, 49),
(50, 1, 2024, 30.0, 30.0, '2025-01-01 00:00:00', 5, 50),
(51, 1, 2024, 30.0, 29.0, '2025-01-01 00:00:00', 6, 51),
(52, 1, 2024, 30.0, 30.0, '2025-01-01 00:00:00', 7, 52),
(53, 1, 2024, 30.0, 28.0, '2025-01-01 00:00:00', 8, 53),
(54, 1, 2024, 30.0, 30.0, '2025-01-01 00:00:00', 9, 54),
(55, 1, 2024, 30.0, 30.0, '2025-01-01 00:00:00', 10, 55),
(56, 1, 2024, 30.0, 30.0, '2025-01-01 00:00:00', 11, 56),
(57, 1, 2024, 30.0, 30.0, '2025-01-01 00:00:00', 12, 57),
(58, 1, 2024, 30.0, 29.0, '2025-01-01 00:00:00', 13, 58),
(59, 1, 2024, 30.0, 30.0, '2025-01-01 00:00:00', 14, 59),
(60, 1, 2024, 30.0, 28.0, '2025-01-01 00:00:00', 15, 60);

-- NOTE: Pas de soldes 2025 car décision DG-26 pas encore sortie!
-- Ces soldes seront ajoutés en 2026

-- =====================================================
-- 8. CENTRES DE SANTÉ (SI-GPRH)
-- =====================================================

INSERT INTO centre_sante (cen_code, cen_nom, cen_adresse, tp_cen_code) VALUES
-- Hôpitaux et Centres médicaux
(1, 'Pavillon Sainte Fleur', 'Antananarivo', 1),
(2, 'Hôpital Mère-Enfant de Tsaralalana (HMET)', 'Tsaralalana, Antananarivo', 1),
(3, 'Hôpital Joseph Ravoahangy Andrianavalona (HJRA)', 'Ampefiloha, Antananarivo', 1),
(4, 'Hôpital Joseph Raseta Befelatanana (HJRB)', 'Befelatanana, Antananarivo', 1),
(5, 'Centre Hospitalier de Soavinandriana (CENHOSOA)', 'Soavinandriana, Antananarivo', 1),
(6, 'Centre de Stomatologie de Befelatanana', 'Befelatanana, Antananarivo', 1),
(7, 'Service Gynécologie et Obstétrique de Befelatanana', 'Befelatanana, Antananarivo', 1),
(8, 'Centre Médical Tsiazotafo (CMT)', 'Tsiazotafo, Antananarivo', 1),
(9, 'Dispensaire du Ministère des Finances et du Budget (MFB)', 'Antaninarenina, Antananarivo', 1),
(10, 'EUSSPA Analakely', 'Analakely, Antananarivo', 1),
(11, 'Institut Malgache de Recherches Appliquées (IMRA)', 'Antananarivo', 1),
(12, 'Centre Hospitalier Universitaire Andohotapenaka', 'Andohotapenaka, Antananarivo', 1),
-- Laboratoires
(13, 'Institut Pasteur de Madagascar (IPM)', 'Antananarivo', 5),
(14, 'Laboratoire Pharmacie Hanitra Ankorahotra', 'Ankorahotra, Antananarivo', 5),
(15, 'Laboratoire de Formation et de Recherche en Biologie Médicale (LBM)', 'Antananarivo', 5),
(16, 'Institut Médical de Madagascar (IMM)', 'Antananarivo', 5),
(17, 'EUSSPA Analakely - Labo', 'Analakely, Antananarivo', 5),
(18, 'IMRA - Laboratoire', 'Antananarivo', 5),
-- Pharmacies conventionnées
(19, 'Pharmacie Unité I/HJRB', 'Befelatanana, Antananarivo', 4),
(20, 'Pharmacie ANYMA/HJRA', 'Ampefiloha, Antananarivo', 4),
(21, 'Pharmacie Payante/CENHOSOA', 'Soavinandriana, Antananarivo', 4),
(22, 'Pharmacie Andohotapenaka', 'Andohotapenaka, Antananarivo', 4),
(23, 'Pharmacie Pavillon Sainte Fleur', 'Antananarivo', 4);

-- =====================================================
-- 9. FACTURES (SI-GPRH)
-- =====================================================
-- Format numéro: FAC-{YYYY}-{XXX}

INSERT INTO facture (fac_code, fac_num, fac_date) VALUES
(1, 'FAC-2025-001', '2025-02-01'),
(2, 'FAC-2025-002', '2025-02-15');

-- =====================================================
-- 10. COMPÉTENCES (GESTION CARRIÈRE)
-- =====================================================

INSERT INTO competence (comp_code, comp_intitule, comp_domaine, comp_description) VALUES
(1, 'Développement Web', 'Informatique', 'HTML, CSS, JavaScript, PHP'),
(2, 'Administration Réseau', 'Informatique', 'Réseaux, Serveurs, Sécurité'),
(3, 'Gestion de Projet', 'Management', 'Planification, Suivi, Reporting'),
(4, 'Comptabilité', 'Finance', 'Comptabilité générale et analytique'),
(5, 'Ressources Humaines', 'RH', 'Gestion du personnel, Paie'),
(6, 'Communication', 'Transversal', 'Communication écrite et orale'),
(7, 'Bureautique', 'Transversal', 'Word, Excel, PowerPoint');

-- Compétences des employés
INSERT INTO comp_employe (emp_code, comp_code, niveau_acquis) VALUES
(9, 1, 4),   -- Niry: Développement Web niveau 4
(9, 3, 2),   -- Niry: Gestion de Projet niveau 2
(10, 2, 4),  -- Voahangy: Admin Réseau niveau 4
(11, 1, 3),  -- Hery A.: Développement Web niveau 3
(12, 1, 2),  -- Lova: Développement Web niveau 2
(15, 4, 4),  -- Hasina: Comptabilité niveau 4
(6, 5, 4),   -- Rabe Hery: RH niveau 4
(13, 6, 3);  -- Mialy: Communication niveau 3

-- Compétences requises par poste
INSERT INTO comp_poste (pst_code, comp_code, niveau_requis) VALUES
(4, 1, 3),   -- Agent: Développement Web niveau 3
(4, 7, 2),   -- Agent: Bureautique niveau 2
(13, 2, 4),  -- Technicien: Admin Réseau niveau 4
(6, 4, 4),   -- Comptable: Comptabilité niveau 4
(9, 5, 4);   -- Chef SRH: RH niveau 4

-- =====================================================
-- 11. STAGES (GESTION CARRIÈRE)
-- =====================================================

-- Établissements
INSERT INTO etablissement (etab_code, etab_nom, etab_adresse) VALUES
(1, 'Université d''Antananarivo', 'Ankatso, Antananarivo'),
(2, 'IT University', 'Andoharanofotsy, Antananarivo'),
(3, 'ESMIA', 'Ivato, Antananarivo'),
(4, 'IST Antananarivo', 'Ampasampito, Antananarivo');

-- Stagiaires
INSERT INTO stagiaire (stgr_code, stgr_nom, stgr_prenom, stgr_nom_prenom, stgr_contact, stgr_filiere, stgr_niveau, stgr_sexe, stgr_adresse) VALUES
(1, 'RAKOTONIRINA', 'Feno', 'RAKOTONIRINA Feno', '034 50 000 01', 'Informatique', 'L3', true, 'Itaosy'),
(2, 'RASOARIVELO', 'Anja', 'RASOARIVELO Anja', '034 50 000 02', 'Gestion', 'M1', false, 'Ambohimanarina'),
(3, 'ANDRIAMAHEFA', 'Sitraka', 'ANDRIAMAHEFA Sitraka', '034 50 000 03', 'Informatique', 'M2', true, 'Ivandry');

-- Assiduité
INSERT INTO assiduite (asdt_code, asdt_remarque, asdt_nb_abscence, asdt_nb_retard) VALUES
(1, 'Très assidu', 0, 1),
(2, 'Bonne assiduité', 2, 3),
(3, 'Assidu', 1, 2);

-- Évaluations de stage
INSERT INTO eval_stage (evstg_code, evstg_lieu, evstg_note, evstg_aptitude, evstg_date_eval, asdt_code) VALUES
(1, 'DSI', 16, 'Bon', '2024-03-15', 1),
(2, 'DAAF', 14, 'Assez bon', '2024-06-30', 2),
(3, 'DSI', 17, 'Très bon', '2025-01-15', 3);

-- Stages
INSERT INTO stage (stg_code, stg_duree, stg_date_debut, stg_date_fin, stg_theme, evstg_code, stgr_code, etab_code) VALUES
(1, 3, '2024-01-15', '2024-03-15', 'Développement d''une application web', 1, 1, 2),
(2, 2, '2024-05-01', '2024-06-30', 'Gestion des ressources humaines', 2, 2, 1),
(3, 6, '2024-07-01', '2025-01-15', 'Système de gestion intégré', 3, 3, 2);

-- Documents de stage (Format: CERT-STG-{YYYY}-{XXX})
INSERT INTO doc_stage (tdoc_code, stg_code, doc_stg_code, doc_stg_date, tdoc_matricule, doc_stage_statut) VALUES
(5, 1, 1, '2024-03-20', 'CERT-STG-2024-001', 'Délivré'),
(5, 2, 2, '2024-07-05', 'CERT-STG-2024-002', 'Délivré'),
(5, 3, 3, '2025-01-20', 'CERT-STG-2025-001', 'En attente');

-- Encadrement de stage (employé -> poste -> stage)
INSERT INTO stage_carriere (emp_code, pst_code, stg_code, stg_carriere_code) VALUES
(9, 4, 1, 1),   -- Niry encadre stage 1
(6, 9, 2, 2),  -- Rabe Hery encadre stage 2
(7, 10, 3, 3); -- Carol encadre stage 3

-- =====================================================
-- 12. DOCUMENTS EMPLOYÉS (GESTION CARRIÈRE)
-- =====================================================
-- Format numéro: {TYPE}-{YYYY}-{XXX}

INSERT INTO doc_emp (tdoc_code, affec_code, doc_emp_code, doc_emp_date, doc_emp_statut, tdoc_matricule, usage, commentaire) VALUES
(1, 1, 1, '2015-01-15', 'Actif', 'CTR-2015-001', 'Contrat initial', 'CDI Directeur Général'),
(1, 2, 2, '2016-03-01', 'Actif', 'CTR-2016-001', 'Contrat initial', 'CDI Directeur DAAF'),
(1, 3, 3, '2017-06-15', 'Actif', 'CTR-2017-001', 'Contrat initial', 'CDI Directeur DSI'),
(2, 1, 4, '2015-01-15', 'Actif', 'DEC-2015-001', 'Nomination DG', 'Décision de nomination'),
(2, 2, 5, '2016-03-01', 'Actif', 'DEC-2016-001', 'Nomination Dir', 'Décision de nomination'),
(4, 6, 6, '2023-06-01', 'Délivré', 'ATT-2023-001', 'Attestation de travail', 'Pour dossier personnel');

-- =====================================================
-- 13. UTILISATEURS SYSTÈME
-- =====================================================

INSERT INTO users (id, username, password, nom, prenom, role) VALUES
(1, 'admin', crypt('admin123', gen_salt('bf')), 'Super', 'Administrateur', 'admin');

-- =====================================================
-- RESET SEQUENCES (PostgreSQL)
-- =====================================================

SELECT setval('type_conge_typ_code_seq', (SELECT COALESCE(MAX(typ_code), 1) FROM type_conge));
SELECT setval('decision_dec_code_seq', (SELECT COALESCE(MAX(dec_code), 1) FROM decision));
SELECT setval('region_reg_code_seq', (SELECT COALESCE(MAX(reg_code), 1) FROM region));
SELECT setval('type_centre_tp_cen_code_seq', (SELECT COALESCE(MAX(tp_cen_code), 1) FROM type_centre));
SELECT setval('direction_dir_code_seq', (SELECT COALESCE(MAX(dir_code), 1) FROM direction));
SELECT setval('service_srvc_code_seq', (SELECT COALESCE(MAX(srvc_code), 1) FROM service));
SELECT setval('poste_pst_code_seq', (SELECT COALESCE(MAX(pst_code), 1) FROM poste));
SELECT setval('rang_hierarchique_rhq_code_seq', (SELECT COALESCE(MAX(rhq_code), 1) FROM rang_hierarchique));
SELECT setval('tache_suppl_tsup_code_seq', (SELECT COALESCE(MAX(tsup_code), 1) FROM tache_suppl));
SELECT setval('type_document_tdoc_code_seq', (SELECT COALESCE(MAX(tdoc_code), 1) FROM type_document));
SELECT setval('type_contrat_tcontrat_code_seq', (SELECT COALESCE(MAX(tcontrat_code), 1) FROM type_contrat));
SELECT setval('statut_armp_stt_armp_code_seq', (SELECT COALESCE(MAX(stt_armp_code), 1) FROM statut_armp));
SELECT setval('position__pos_code_seq', (SELECT COALESCE(MAX(pos_code), 1) FROM position_));
SELECT setval('motif_affectation_m_aff_code_seq', (SELECT COALESCE(MAX(m_aff_code), 1) FROM motif_affectation));
SELECT setval('employe_emp_code_seq', (SELECT COALESCE(MAX(emp_code), 1) FROM employe));
SELECT setval('affectation_affec_code_seq', (SELECT COALESCE(MAX(affec_code), 1) FROM affectation));
SELECT setval('signature_sign_code_seq', (SELECT COALESCE(MAX(sign_code), 1) FROM signature));
SELECT setval('conjointe_conj_code_seq', (SELECT COALESCE(MAX(conj_code), 1) FROM conjointe));
SELECT setval('enfant_enf_code_seq', (SELECT COALESCE(MAX(enf_code), 1) FROM enfant));
SELECT setval('solde_conge_sld_code_seq', (SELECT COALESCE(MAX(sld_code), 1) FROM solde_conge));
SELECT setval('objet_remboursement_obj_code_seq', (SELECT COALESCE(MAX(obj_code), 1) FROM objet_remboursement));
SELECT setval('centre_sante_cen_code_seq', (SELECT COALESCE(MAX(cen_code), 1) FROM centre_sante));
SELECT setval('facture_fac_code_seq', (SELECT COALESCE(MAX(fac_code), 1) FROM facture));
SELECT setval('competence_comp_code_seq', (SELECT COALESCE(MAX(comp_code), 1) FROM competence));
SELECT setval('etablissement_etab_code_seq', (SELECT COALESCE(MAX(etab_code), 1) FROM etablissement));
SELECT setval('stagiaire_stgr_code_seq', (SELECT COALESCE(MAX(stgr_code), 1) FROM stagiaire));
SELECT setval('assiduite_asdt_code_seq', (SELECT COALESCE(MAX(asdt_code), 1) FROM assiduite));
SELECT setval('eval_stage_evstg_code_seq', (SELECT COALESCE(MAX(evstg_code), 1) FROM eval_stage));
SELECT setval('stage_stg_code_seq', (SELECT COALESCE(MAX(stg_code), 1) FROM stage));
SELECT setval('users_id_seq', (SELECT COALESCE(MAX(id), 1) FROM users));
SELECT setval('contact_id_contact_seq', (SELECT COALESCE(MAX(id_contact), 1) FROM contact));

-- =====================================================
-- FIN DU SEED FUSIONNÉ
-- =====================================================

SELECT 'Seed fusionné inséré avec succès!' AS status;
