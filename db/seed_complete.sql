-- ============================================
-- SEED COMPLET GRH - SI-GPRH
-- ============================================
-- Exécuter après schema.sql
-- \c grh;

-- ============================================
-- 1. TABLES RÉFÉRENTIELLES (sans FK)
-- ============================================
CREATE EXTENSION IF NOT EXISTS pgcrypto;


INSERT INTO users (username, password, nom, prenom, role)
VALUES (
  'admin',
  crypt('admin123', gen_salt('bf')),-- hash pour 'admin123'
  'Super',
  'Administrateur',
  0
);

-- Types de congé
INSERT INTO type_conge (typ_code, typ_appelation, typ_ref) VALUES
(1, 'Congé annuel', 'CA'),
(2, 'Repos maladie', 'RM'),
(3, 'Congé maternité', 'CM'),
(4, 'Congé paternité', 'CP');

-- Décisions administratives (règle: décision année YY pour solde année YY-1, 1 décision par employé)
INSERT INTO decision (dec_code, dec_num) VALUES
-- Décisions 2022 (pour soldes 2021) - Format: {emp_code}/ARMP/DG-22
(1, '001/ARMP/DG-22'),  -- Employé 1
(2, '002/ARMP/DG-22'),  -- Employé 2
(3, '003/ARMP/DG-22'),  -- Employé 3
(4, '004/ARMP/DG-22'),  -- Employé 4
(5, '005/ARMP/DG-22'),  -- Employé 5
-- Décisions 2023 (pour soldes 2022)
(6, '001/ARMP/DG-23'),
(7, '002/ARMP/DG-23'),
(8, '003/ARMP/DG-23'),
(9, '004/ARMP/DG-23'),
(10, '005/ARMP/DG-23'),
-- Décisions 2024 (pour soldes 2023)
(11, '001/ARMP/DG-24'),
(12, '002/ARMP/DG-24'),
(13, '003/ARMP/DG-24'),
(14, '004/ARMP/DG-24'),
(15, '005/ARMP/DG-24'),
-- Décisions 2025 (pour soldes 2024)
(16, '001/ARMP/DG-25'),
(17, '002/ARMP/DG-25'),
(18, '003/ARMP/DG-25'),
(19, '004/ARMP/DG-25'),
(20, '005/ARMP/DG-25');
-- NOTE: Pas de décision -26 car on est en 2025, elle sortira en 2026 pour débloquer soldes 2025



-- Régions
INSERT INTO Region (reg_nom) VALUES
('Alaotra-Mangoro'),
('Amoron''i Mania'),
('Analamanga'),
('Analanjirofo'),
('Androy'),
('Anosy'),
('Atsimo-Andrefana'),
('Atsimo-Atsinanana'),
('Atsinanana'),
('Bongolava'),
('Boeny'),
('Betsiboka'),
('Diana'),
('Haute Matsiatra'),
('Ihorombe'),
('Itasy'),
('Melaky'),
('Menabe'),
('Sava'),
('Vakinankaratra'),
('Vatovavy'),
('Fitovinany'),
('Matsiatra Ambony');


-- Types de centre de santé
INSERT INTO type_centre (tp_cen_code, tp_cen) VALUES
(1, 'Hôpital public'),
(2, 'Clinique privée'),
(3, 'Centre de santé de base'),
(4, 'Pharmacie'),
(5, 'Laboratoire');

-- ============================================
-- 2. POSTES (génériques)
-- ============================================
INSERT INTO poste (pst_code, pst_fonction, pst_max) VALUES
(1, 'Directeur Général', 1),
(2, 'Directeur', 5),
(3, 'Chef de Service', 10),
(4, 'Agent', 50),
(5, 'Secrétaire', 10),
(6, 'Comptable', 10),
(7, 'Chauffeur', 5),
(8, 'Gardien', 5);

-- ============================================
-- 3. DIRECTIONS
-- ============================================
INSERT INTO direction (dir_code, dir_nom, dir_abreviation) VALUES
(1, 'Direction Générale', 'DG'),
(2, 'Direction des Affaires Administratives et Financières', 'DAAF'),
(3, 'Direction des Systèmes d''Information', 'DSI'),
(4, 'Service Ressources Humaines', 'SRH'),
(5, 'Service Comptabilité', 'COMPTA'),
(6, 'Service Logistique', 'LOG');

-- ============================================
-- 4. FONCTION_DIREC (Poste → Direction)
-- ============================================
-- Relie un poste générique à une direction avec sa mission spécifique
INSERT INTO fonction_direc (pst_code, dir_code, fonc_mission) VALUES
(1, 1, 'Directeur Général de l''ARMP'),
(2, 2, 'Directeur des Affaires Administratives et Financières'),
(2, 3, 'Directeur des Systèmes d''Information'),
(3, 3, 'Chef de Service DSI'),
(3, 4, 'Chef du Service Ressources Humaines'),
(3, 5, 'Chef du Service Comptabilité'),
(3, 6, 'Chef du Service Logistique'),
(4, 3, 'Agent DSI'),
(4, 4, 'Agent Ressources Humaines'),
(4, 5, 'Agent Comptable'),
(4, 6, 'Agent Logistique'),
(4, 2, 'Agent DAAF'),
(5, 1, 'Secrétaire de Direction Générale'),
(5, 2, 'Secrétaire DAAF'),
(6, 5, 'Comptable principal'),
(7, 6, 'Chauffeur'),
(8, 6, 'Gardien');
-- ============================================
-- 5. OBJETS REMBOURSEMENT
-- ============================================
INSERT INTO objet_remboursement (obj_code, obj_article) VALUES
(1, 'Consultation médicale'),
(2, 'Médicaments'),
(3, 'Analyses médicales'),
(4, 'Radiographie'),
(5, 'Hospitalisation'),
(6, 'Montures et verres correcteurs'),
(7, 'Soins dentaires'),
(8, 'Kinésithérapie');

-- ============================================
-- 6. CENTRES DE SANTÉ
-- ============================================
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

-- ============================================
-- 7. EMPLOYÉS
-- ============================================
INSERT INTO employee (emp_code, emp_nom, emp_prenom, emp_imarmp, emp_sexe, emp_date_embauche, emp_mail, emp_disponibilite) VALUES
-- Direction Généralede
(1, 'IANJARAFANOMEZANTSOA', 'Jean', 'DG001', true, '2015-01-15', 'ianjarafanomezantsoa8@gmail.com', true),
-- Directeurs
(2, 'RANDRIA', 'Marie', 'DIR001', false, '2016-03-01', 'loiclooney7@gmail.com', true), -- Directeur DAAF
(3, 'RASOA', 'Patrick', 'DIR002', true, '2017-06-15', 'steffodin@gmail.com', true), -- Directeur DSI
-- Chefs de Service DSI
(4, 'RABE', 'Hery', 'CS001', true, '2018-02-01', 'catykitkit@gmail.com', true), -- Chef SRH
(5, 'RAVELO', 'Carol', 'CS002', false, '2018-05-10', 'ncarol506@gmail.com', true), -- Chef Sécurité IT
(6, 'ANDRIA', 'Tiana', 'CS003', true, '2019-01-20', 'iomjik53@gmail.com', true), -- Chef Développement
-- Agents DSI
(7, 'RAKOTOSON', 'Niry', 'AG001', false, '2020-03-01', 'uilokan@gmail.com', true), -- Développeur
(8, 'RASOANAIVO', 'Voahangy', 'AG002', false, '2020-06-15', 'jiljoul81@gmail.com', true), -- Admin Réseau
(9, 'ANDRIANAIVO', 'Hery', 'AG003', true, '2021-01-10', 'sme27577@gmail.com', true), -- Développeur
(10, 'RAMANANTSOA', 'Lova', 'AG004', true, '2021-04-01', 'willharingtonw99@gmail.com', true), -- Support IT
-- Autres services
(11, 'RAHARISOA', 'Mialy', 'SEC001', false, '2019-08-01', 'mialy.raharisoa@armp.mg', true),
(12, 'RAZAFY', 'Tahina', 'SEC002', false, '2020-02-15', 'tahina.razafy@armp.mg', true),
(13, 'RATSIMBA', 'Hasina', 'CPT001', true, '2018-09-01', 'hasina.ratsimba@armp.mg', true),
(14, 'RAMAROSON', 'Fidy', 'CHF001', true, '2019-06-01', 'fidy.ramaroson@armp.mg', true),
(15, 'RASENDRA', 'Solo', 'GAR001', true, '2020-01-01', 'solo.rasendra@armp.mg', true);

-- Signatures (rôles signataires pour validation)
INSERT INTO Signature (sign_code, sign_libele, sign_observation, emp_code) VALUES
(1, 'Directeur Général', 'Signataire final', 1),
(2, 'DAAF', 'Validation administrative', 2),
(3, 'RRH', 'Responsable RH', 4),
(4, 'Chef de Service', 'Supérieur hiérarchique', 6),
(5, 'Directeur', 'Directeur de Direction', 3);

-- ============================================
-- 8. AFFECTATIONS (Employee → Poste)
-- ============================================
INSERT INTO affectation (emp_code, pst_code, dir_code, affec_code, affec_date_debut, affec_date_fin, affec_type_contrat) VALUES
(1, 1, 1, 1, '2015-01-15', NULL, 'CDI'),       -- DG -> DG
(2, 2, 2, 2, '2016-03-01', NULL, 'CDI'),       -- DAAF -> DAAF
(3, 2, 3, 3, '2017-06-15', NULL, 'CDI'),       -- DSI -> DSI
(4, 3, 4, 4, '2018-02-01', NULL, 'CDI'),       -- Chef SRH -> SRH
(5, 3, 3, 5, '2018-05-10', NULL, 'CDI'),       -- Chef Secu -> DSI
(6, 3, 3, 6, '2019-01-20', NULL, 'CDI'),       -- Chef Dev -> DSI
(7, 4, 3, 7, '2020-03-01', NULL, 'CDI'),       -- Dev -> DSI
(8, 4, 3, 8, '2020-06-15', NULL, 'CDI'),       -- Admin Sys -> DSI
(9, 4, 3, 9, '2021-01-10', NULL, 'CDD'),       -- Dev -> DSI
(10, 4, 3, 10, '2021-04-01', NULL, 'CDD'),     -- Support -> DSI
(11, 5, 1, 11, '2019-08-01', NULL, 'CDI'),     -- Secrétaire -> DG
(12, 5, 2, 12, '2020-02-15', NULL, 'CDI'),     -- Secrétaire -> DAAF
(13, 6, 5, 13, '2018-09-01', NULL, 'CDI'),     -- Comptable -> Compta
(14, 7, 2, 14, '2019-06-01', NULL, 'CDI'),     -- Chauffeur -> DAAF
(15, 8, 6, 15, '2020-01-01', NULL, 'CDI');     -- Gardien -> Logistique

-- ============================================
-- 9. CONJOINTS
-- ============================================
INSERT INTO conjointe (conj_code, conj_nom, conj_sexe) VALUES
(1, 'RAKO Lalao', false),
(2, 'RANDRI Toky', true),
(3, 'RABE Soa', false),
(4, 'ANDRIA Vola', false),
(5, 'RAKO Haja', true);

-- Association Employé-Conjoint
INSERT INTO emp_conj (emp_code, conj_code) VALUES
(1, 1), -- DG marié à Lalao
(2, 2), -- Marie mariée à Toky
(4, 3), -- Hery marié à Soa
(9, 4), -- Hery A. marié à Vola
(11, 5); -- Mialy mariée à Haja

-- ============================================
-- 10. ENFANTS
-- ============================================
INSERT INTO enfant (enf_code, enf_nom, enf_num, date_naissance, emp_code) VALUES
-- Enfants du DG
(1, 'RAKOTO Miora', 'ENF001', '2010-05-15', 1),
(2, 'RAKOTO Aina', 'ENF002', '2015-08-20', 1),
-- Enfants de Marie
(3, 'RANDRIA Fehizoro', 'ENF003', '2018-03-10', 2),
-- Enfants de Hery Rabe
(4, 'RABE Mahefa', 'ENF004', '2019-11-05', 4),
(5, 'RABE Ony', 'ENF005', '2022-06-15', 4),
-- Enfants de Voahangy
(6, 'RASOANAIVO Tiavina', 'ENF006', '2020-01-20', 8),
-- Enfants de Hery Andrianaivo
(7, 'ANDRIANAIVO Kanto', 'ENF007', '2021-04-12', 9);

-- ============================================
-- 11. SOLDES DE CONGÉ FIFO (avec décision)
-- ============================================
-- Règle: On débite les soldes les plus anciens en premier (FIFO)
-- Actuellement en 2025, on a soldes jusqu'à 2024 seulement (décision -25 débloque 2024)

-- SOLDES 2021 (décision DG-22 sortie en 2022) - Presque épuisés
INSERT INTO solde_conge (sld_code, sld_dispo, sld_anne, sld_initial, sld_restant, sld_maj, emp_code, dec_code) VALUES
(1, 1, 2021, 30.0, 2.0, '2022-01-01 00:00:00', 1, 1),   -- DG: reste 2j
(2, 1, 2021, 30.0, 0.0, '2022-01-01 00:00:00', 2, 2),   -- Marie: épuisé
(3, 1, 2021, 30.0, 1.5, '2022-01-01 00:00:00', 3, 3),   -- Patrick: 1.5j
(4, 1, 2021, 30.0, 0.0, '2022-01-01 00:00:00', 4, 4),   -- Hery: épuisé
(5, 1, 2021, 30.0, 3.0, '2022-01-01 00:00:00', 5, 5);   -- Carol: 3j

-- SOLDES 2022 (décision DG-23 sortie en 2023) - Partiellement utilisés
INSERT INTO solde_conge (sld_code, sld_dispo, sld_anne, sld_initial, sld_restant, sld_maj, emp_code, dec_code) VALUES
(6, 1, 2022, 30.0, 12.0, '2023-01-01 00:00:00', 1, 6),  -- DG: 12j
(7, 1, 2022, 30.0, 8.0, '2023-01-01 00:00:00', 2, 7),   -- Marie: 8j
(8, 1, 2022, 30.0, 15.0, '2023-01-01 00:00:00', 3, 8),  -- Patrick: 15j
(9, 1, 2022, 30.0, 10.0, '2023-01-01 00:00:00', 4, 9),  -- Hery: 10j
(10, 1, 2022, 30.0, 20.0, '2023-01-01 00:00:00', 5, 10); -- Carol: 20j

-- SOLDES 2023 (décision DG-24 sortie en 2024) - Moins utilisés
INSERT INTO solde_conge (sld_code, sld_dispo, sld_anne, sld_initial, sld_restant, sld_maj, emp_code, dec_code) VALUES
(11, 1, 2023, 30.0, 25.0, '2024-01-01 00:00:00', 1, 11), -- DG: 25j
(12, 1, 2023, 30.0, 22.0, '2024-01-01 00:00:00', 2, 12), -- Marie: 22j
(13, 1, 2023, 30.0, 27.0, '2024-01-01 00:00:00', 3, 13), -- Patrick: 27j
(14, 1, 2023, 30.0, 20.0, '2024-01-01 00:00:00', 4, 14), -- Hery: 20j
(15, 1, 2023, 30.0, 28.0, '2024-01-01 00:00:00', 5, 15); -- Carol: 28j

-- SOLDES 2024 (décision DG-25 sortie en 2025) - Récents, peu touchés
INSERT INTO solde_conge (sld_code, sld_dispo, sld_anne, sld_initial, sld_restant, sld_maj, emp_code, dec_code) VALUES
(16, 1, 2024, 30.0, 30.0, '2025-01-01 00:00:00', 1, 16), -- DG: intact
(17, 1, 2024, 30.0, 29.0, '2025-01-01 00:00:00', 2, 17), -- Marie: 29j
(18, 1, 2024, 30.0, 30.0, '2025-01-01 00:00:00', 3, 18), -- Patrick: intact
(19, 1, 2024, 30.0, 28.0, '2025-01-01 00:00:00', 4, 19), -- Hery: 28j
(20, 1, 2024, 30.0, 30.0, '2025-01-01 00:00:00', 5, 20); -- Carol: intact

-- NOTE: Pas de soldes 2025 car décision -26 pas encore sortie!



-- ============================================
-- 13. FACTURES
-- ============================================
INSERT INTO facture (fac_code, fac_num, fac_date) VALUES
(1, 'FAC-2025-001', '2025-02-01'),
(2, 'FAC-2025-002', '2025-02-15');

-- ============================================
-- 14. USERS (système)
-- ============================================

-- ============================================
-- RESET SEQUENCES (PostgreSQL)
-- ============================================
SELECT setval('type_conge_typ_code_seq', (SELECT MAX(typ_code) FROM type_conge));
SELECT setval('decision_dec_code_seq', (SELECT MAX(dec_code) FROM decision));
SELECT setval('signature_sign_code_seq', (SELECT MAX(sign_code) FROM Signature));
SELECT setval('region_reg_code_seq', (SELECT MAX(reg_code) FROM Region));
SELECT setval('type_centre_tp_cen_code_seq', (SELECT MAX(tp_cen_code) FROM type_centre));
SELECT setval('poste_pst_code_seq', (SELECT MAX(pst_code) FROM poste));
SELECT setval('direction_dir_code_seq', (SELECT MAX(dir_code) FROM direction));
SELECT setval('objet_remboursement_obj_code_seq', (SELECT MAX(obj_code) FROM objet_remboursement));
SELECT setval('centre_sante_cen_code_seq', (SELECT MAX(cen_code) FROM centre_sante));
SELECT setval('conjointe_conj_code_seq', (SELECT MAX(conj_code) FROM conjointe));
SELECT setval('enfant_enf_code_seq', (SELECT MAX(enf_code) FROM enfant));
SELECT setval('solde_conge_sld_code_seq', (SELECT MAX(sld_code) FROM solde_conge));
SELECT setval('facture_fac_code_seq', (SELECT MAX(fac_code) FROM facture));
SELECT setval('users_id_seq', (SELECT MAX(id) FROM users));
SELECT setval('affectation_affec_code_seq', (SELECT MAX(affec_code) FROM affectation));

SELECT 'Seed complet inséré avec succès!' AS status;