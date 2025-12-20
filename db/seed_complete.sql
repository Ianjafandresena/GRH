-- ============================================
-- SEED COMPLET - SI-GPRH
-- Compatible avec schema.sql mis à jour
-- ============================================

-- Nettoyage (ordre inverse des dépendances)
TRUNCATE TABLE signature_engagement, debit_solde_cng, fonction_direc, 
    Interim_permission, Interim_conge, validation_prm, emp_conj, validation_cng, 
    piece, demande_remb, pris_en_charge, solde_permission, centre_sante, 
    interruption, permission, enfant, conge, solde_conge, employee,
    objet_remboursement, engagement, direction, poste, type_centre, 
    convention, etat_remb, Signature, facture, decision, conjointe, 
    type_conge, Region, modification CASCADE;

-- Reset des séquences
ALTER SEQUENCE IF EXISTS signature_sign_code_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS poste_pst_code_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS direction_dir_code_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS region_reg_code_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS type_conge_typ_code_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS decision_dec_code_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS solde_conge_sld_code_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS conge_cng_code_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS conjointe_conj_code_seq RESTART WITH 1;
ALTER SEQUENCE IF EXISTS enfant_enf_code_seq RESTART WITH 1;

-- ============================================
-- 1. SIGNATURES (Types de signataires)
-- ============================================
INSERT INTO Signature (sign_libele, sign_observation) VALUES
('CHEF', 'Chef hiérarchique direct'),
('RRH', 'Responsable Ressources Humaines'),
('DAAF', 'Directeur Affaires Administratives et Financières'),
('DG', 'Directeur Général');

-- ============================================
-- 2. DIRECTIONS
-- ============================================
INSERT INTO direction (dir_nom, dir_abreviation) VALUES
('Direction Générale', 'DG'),
('Direction des Affaires Administratives et Financières', 'DAAF'),
('Direction des Systèmes d''Information', 'DSI'),
('Direction des Ressources Humaines', 'DRH'),
('Direction de la Régulation', 'DR');

-- ============================================
-- 3. POSTES (avec fonctions hiérarchiques)
-- ============================================
INSERT INTO poste (pst_mission, pst_fonction) VALUES
-- Direction Générale
('Gestion stratégique de l''ARMP', 'Directeur Général'),
('Assistance à la direction générale', 'Assistant DG'),

-- DAAF
('Gestion administrative et financière', 'Directeur DAAF'),
('Gestion financière', 'Chef Service Financier'),
('Comptabilité', 'Agent comptable Comptable'),
('Gestion du budget', 'Agent Budgétaire'),

-- DSI
('Gestion des systèmes d''information', 'Directeur DSI'),
('Supervision informatique', 'Chef Service Informatique'),
('Développement applications', 'Développeur'),
('Support technique', 'Technicien Support'),

-- DRH / SRH
('Gestion des ressources humaines', 'Responsable RH'),
('Administration du personnel', 'Chef Service RH'),
('Gestion des congés et absences', 'Gestionnaire RH'),
('Paie et avantages sociaux', 'Agent Paie'),

-- Direction Régulation
('Régulation des marchés publics', 'Directeur Régulation'),
('Analyse des dossiers', 'Chef Service Régulation'),
('Instruction des recours', 'Analyste Régulation');

-- ============================================
-- 4. FONCTION_DIREC (Liaison Poste-Direction)
-- ============================================
-- Note: pst_code selon l'ordre d'insertion ci-dessus
INSERT INTO fonction_direc (pst_code, dir_code) VALUES
-- DG (dir_code=1)
(1, 1), (2, 1),
-- DAAF (dir_code=2)
(3, 2), (4, 2), (5, 2), (6, 2),
-- DSI (dir_code=3)
(7, 3), (8, 3), (9, 3), (10, 3),
-- DRH (dir_code=4)
(11, 4), (12, 4), (13, 4), (14, 4),
-- DR (dir_code=5)
(15, 5), (16, 5), (17, 5);

-- ============================================
-- 5. EMPLOYES
-- ============================================
-- emp_sexe: TRUE=Homme, FALSE=Femme
-- emp_disponibilite: TRUE=Disponible
-- sign_code: NULL si pas autorise a signer, sinon FK vers Signature

INSERT INTO employee (emp_code, emp_nom, emp_prenom, emp_imarmp, emp_sexe, emp_date_embauche, emp_mail, emp_disponibilite, sign_code) VALUES
-- Direction Generale
(1, 'ANDRIANAIVO', 'Hery', 'H100001', TRUE, '2010-01-15', 'hery.andrianaivo@armp.mg', TRUE, 4),
(2, 'RASOANAIVO', 'Mirana', 'H100002', FALSE, '2015-03-20', 'mirana.rasoanaivo@armp.mg', TRUE, NULL),
-- DAAF
(3, 'RAKOTOMALALA', 'Jean', 'H200001', TRUE, '2012-06-10', 'jean.rakotomalala@armp.mg', TRUE, 3),
(4, 'RAZAFINDRAKOTO', 'Lalao', 'H200002', FALSE, '2016-09-01', 'lalao.razafindrakoto@armp.mg', TRUE, 1),
(5, 'RABE', 'Njaka', 'H200003', TRUE, '2018-02-15', 'njaka.rabe@armp.mg', TRUE, NULL),
(6, 'RANDRIA', 'Faly', 'H200004', TRUE, '2020-05-10', 'faly.randria@armp.mg', TRUE, NULL),
-- DSI
(7, 'RAHARISON', 'Tiana', 'H300001', TRUE, '2013-04-05', 'tiana.raharison@armp.mg', TRUE, NULL),
(8, 'ANDRIAMAHEFA', 'Lova', 'H300002', TRUE, '2017-08-20', 'lova.andriamahefa@armp.mg', TRUE, 1),
(9, 'RANDRIANTSIANA', 'Valerio', 'H300698', TRUE, '2018-01-15', 'valerio.randriantsiana@armp.mg', TRUE, NULL),
(10, 'RAKOTO', 'Miora', 'H300699', FALSE, '2022-06-10', 'miora.rakoto@armp.mg', TRUE, NULL),
-- DRH / SRH
(11, 'RAZAFIMANDIMBY', 'Danielle', 'H400001', FALSE, '2011-11-01', 'danielle.razafimandimby@armp.mg', TRUE, 2),
(12, 'RAMAROSON', 'Haja', 'H400002', TRUE, '2016-03-15', 'haja.ramaroson@armp.mg', TRUE, 1),
(13, 'RATSIMBA', 'Nivo', 'H400003', FALSE, '2019-07-01', 'nivo.ratsimba@armp.mg', TRUE, NULL),
(14, 'ANDRIA', 'Tojo', 'H400004', TRUE, '2021-01-20', 'tojo.andria@armp.mg', TRUE, NULL),
-- Direction Regulation
(15, 'RABEMANANJARA', 'Solo', 'H500001', TRUE, '2014-02-10', 'solo.rabemananjara@armp.mg', TRUE, NULL),
(16, 'RANDRIAMARO', 'Voahirana', 'H500002', FALSE, '2018-10-05', 'steffodin@gmail.com', TRUE, 1),
(17, 'RASOAMANANA', 'Fara', 'H500003', FALSE, '2020-03-25', 'fara.rahirana.randriamaro@armp.mgsoamanana@armp.mg', TRUE, NULL);

-- ============================================
-- 5b. AFFECTATIONS (liaison employe-poste)
-- ============================================
INSERT INTO affectation (emp_code, pst_code, affec_date_debut, affec_type_contrat) VALUES
(1, 1, '2010-01-15', 'CDI'),
(2, 2, '2015-03-20', 'CDI'),
(3, 3, '2012-06-10', 'CDI'),
(4, 4, '2016-09-01', 'CDI'),
(5, 5, '2018-02-15', 'CDI'),
(6, 6, '2020-05-10', 'CDD'),
(7, 7, '2013-04-05', 'CDI'),
(8, 8, '2017-08-20', 'CDI'),
(9, 9, '2018-01-15', 'CDI'),
(10, 10, '2022-06-10', 'CDD'),
(11, 11, '2011-11-01', 'CDI'),
(12, 12, '2016-03-15', 'CDI'),
(13, 13, '2019-07-01', 'CDI'),
(14, 14, '2021-01-20', 'CDD'),
(15, 15, '2014-02-10', 'CDI'),
(16, 16, '2018-10-05', 'CDI'),
(17, 17, '2020-03-25', 'CDD');

-- ============================================
-- 6. RÉGIONS
-- ============================================
INSERT INTO Region (reg_nom) VALUES
('Analamanga'),
('Vakinankaratra'),
('Itasy'),
('Bongolava'),
('Haute Matsiatra'),
('Amoron''i Mania'),
('Vatovavy'),
('Fitovinany'),
('Ihorombe'),
('Atsimo-Atsinanana'),
('Atsinanana'),
('Analanjirofo'),
('Alaotra-Mangoro'),
('Boeny'),
('Sofia'),
('Betsiboka'),
('Melaky'),
('Atsimo-Andrefana'),
('Androy'),
('Anosy'),
('Menabe'),
('Diana'),
('Sava');

-- ============================================
-- 7. TYPES DE CONGÉ
-- ============================================
INSERT INTO type_conge (typ_appelation, typ_ref) VALUES
('Congé Annuel', 'CA'),
('Congé Exceptionnel', 'CE'),
('Congé de Paternité', 'CP'),
('Repos Maladie', 'RM'),
('Congé de Maternité', 'CM'),
('Congé sans Solde', 'CSS');

-- ============================================
-- 8. DÉCISIONS (pour attribution des soldes)
-- Format: XXX/ARMP/DG-YY ou YY = annee de la decision
-- La decision de l annee N attribue les soldes de l annee N-1
-- ============================================
INSERT INTO decision (dec_num) VALUES
('044/ARMP/DG-22'),
('055/ARMP/DG-23'),
('066/ARMP/DG-24'),
('077/ARMP/DG-25');

-- ============================================
-- 9. SOLDES CONGE (par employe/decision/annee)
-- sld_dispo = 1 (disponible)
-- sld_initial = 30 jours/an, sld_restant = solde actuel
-- ============================================
-- Employes Direction Generale (1,2)
INSERT INTO solde_conge (sld_dispo, sld_anne, sld_initial, sld_restant, sld_maj, dec_code, emp_code) VALUES
(1, 2023, 30, 20, NOW(), 3, 1),
(1, 2024, 30, 30, NOW(), 4, 1),
(1, 2023, 30, 18, NOW(), 3, 2),
(1, 2024, 30, 30, NOW(), 4, 2);

-- Employes DAAF (3,4,5,6)
INSERT INTO solde_conge (sld_dispo, sld_anne, sld_initial, sld_restant, sld_maj, dec_code, emp_code) VALUES
(1, 2023, 30, 15, NOW(), 3, 3),
(1, 2024, 30, 30, NOW(), 4, 3),
(1, 2023, 30, 22, NOW(), 3, 4),
(1, 2024, 30, 30, NOW(), 4, 4),
(1, 2023, 30, 28, NOW(), 3, 5),
(1, 2024, 30, 30, NOW(), 4, 5),
(1, 2023, 30, 25, NOW(), 3, 6),
(1, 2024, 30, 30, NOW(), 4, 6);

-- Employes DSI (7,8,9,10)
INSERT INTO solde_conge (sld_dispo, sld_anne, sld_initial, sld_restant, sld_maj, dec_code, emp_code) VALUES
(1, 2023, 30, 12, NOW(), 3, 7),
(1, 2024, 30, 30, NOW(), 4, 7),
(1, 2023, 30, 18, NOW(), 3, 8),
(1, 2024, 30, 30, NOW(), 4, 8),
(1, 2021, 30, 5, NOW(), 1, 9),
(1, 2022, 30, 15, NOW(), 2, 9),
(1, 2023, 30, 25, NOW(), 3, 9),
(1, 2024, 30, 30, NOW(), 4, 9),
(1, 2023, 30, 20, NOW(), 3, 10),
(1, 2024, 30, 30, NOW(), 4, 10);

-- Employes DRH (11,12,13,14)
INSERT INTO solde_conge (sld_dispo, sld_anne, sld_initial, sld_restant, sld_maj, dec_code, emp_code) VALUES
(1, 2023, 30, 16, NOW(), 3, 11),
(1, 2024, 30, 30, NOW(), 4, 11),
(1, 2023, 30, 14, NOW(), 3, 12),
(1, 2024, 30, 30, NOW(), 4, 12),
(1, 2023, 30, 22, NOW(), 3, 13),
(1, 2024, 30, 30, NOW(), 4, 13),
(1, 2023, 30, 28, NOW(), 3, 14),
(1, 2024, 30, 30, NOW(), 4, 14);

-- Employes Direction Regulation (15,16,17)
INSERT INTO solde_conge (sld_dispo, sld_anne, sld_initial, sld_restant, sld_maj, dec_code, emp_code) VALUES
(1, 2023, 30, 10, NOW(), 3, 15),
(1, 2024, 30, 30, NOW(), 4, 15),
(1, 2023, 30, 19, NOW(), 3, 16),
(1, 2024, 30, 30, NOW(), 4, 16),
(1, 2023, 30, 15, NOW(), 3, 17),
(1, 2024, 30, 30, NOW(), 4, 17);

-- ============================================
-- 10. CONJOINTS
-- ============================================
INSERT INTO conjointe (conj_nom, conj_sexe) VALUES
('ANDRIANAIVO Voahangy', FALSE),   -- Conjoint de Hery (emp_code=1)
('RASOANAIVO Patrick', TRUE),      -- Conjoint de Mirana (emp_code=2)
('RAKOTOMALALA Marie', FALSE),     -- Conjoint de Jean (emp_code=3)
('RANDRIANTSIANA Soa', FALSE),     -- Conjoint de Valério (emp_code=9)
('RAZAFIMANDIMBY Andry', TRUE);    -- Conjoint de Danielle (emp_code=11)

-- Liaison employé-conjoint
INSERT INTO emp_conj (emp_code, conj_code) VALUES
(1, 1),
(2, 2),
(3, 3),
(9, 4),
(11, 5);

-- ============================================
-- 11. ENFANTS
-- ============================================
INSERT INTO enfant (enf_nom, enf_num, date_naissance, emp_code) VALUES
-- Enfants de Hery (emp_code=1)
('ANDRIANAIVO Kanto', '001', '2015-03-10', 1),
('ANDRIANAIVO Tahina', '002', '2018-07-22', 1),

-- Enfants de Jean (emp_code=3)
('RAKOTOMALALA Nomena', '001', '2010-11-05', 3),
('RAKOTOMALALA Aina', '002', '2014-02-18', 3),
('RAKOTOMALALA Rado', '003', '2019-09-30', 3),

-- Enfants de Valério (emp_code=9)
('RANDRIANTSIANA Kely', '001', '2020-06-15', 9),
('RANDRIANTSIANA Bodo', '002', '2023-01-08', 9),

-- Enfants de Danielle (emp_code=11)
('RAZAFIMANDIMBY Tsiky', '001', '2012-08-20', 11),
('RAZAFIMANDIMBY Mamy', '002', '2016-12-03', 11);

-- ============================================
-- 12. REMBOURSEMENT: Types, Conventions, Centres
-- ============================================
INSERT INTO type_centre (tp_cen) VALUES
('Hôpital Public'),
('Clinique Privée'),
('Cabinet Médical'),
('Pharmacie'),
('Laboratoire');

INSERT INTO convention (cnv_taux_couver, cnv_date_debut, cnv_date_fin) VALUES
(80.00, '2020-01-01', '2025-12-31'),
(70.00, '2021-01-01', '2025-12-31'),
(90.00, '2022-01-01', '2025-12-31');

INSERT INTO centre_sante (cen_nom, cen_adresse, tp_cen_code, cnv_code) VALUES
('HJRA Ampefiloha', 'Lot II A 45 Ampefiloha', 1, 1),
('Clinique Sainte Fleur', 'Antaninarenina', 2, 2),
('Cabinet Dr. Rabe', 'Analakely', 3, 2),
('Pharmacie Centrale', 'Isoraka', 4, 3),
('Laboratoire SALFA', 'Antanimena', 5, 1);

INSERT INTO objet_remboursement (obj_article) VALUES
('Consultation médicale'),
('Achat médicaments'),
('Analyses laboratoire'),
('Hospitalisation'),
('Radiologie/Imagerie'),
('Soins dentaires'),
('Montures et verres correcteurs');

-- ============================================
-- 13. SOLDES PERMISSION
-- ============================================
INSERT INTO solde_permission (sld_prm_dispo, sld_prm_anne, emp_code) VALUES
(10, 2024, 9),
(10, 2024, 10),
(10, 2024, 5),
(10, 2024, 13),
(10, 2024, 17);

-- ============================================
-- FIN DU SEED
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

SELECT 'Seed complet exécuté avec succès!' AS message;
