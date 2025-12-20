-- SCRIPT DE POPULATION DES DONNEES (POSTGRESQL)
-- A EXECUTER DANS L'ORDRE

-- 1. INSERTION DES DIRECTIONS (Si elles n'existent pas déjà)
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM direction WHERE dir_abreviation = 'DAAF') THEN
        INSERT INTO direction (dir_nom, dir_abreviation) VALUES ('Direction des Affaires Admin. et Fin.', 'DAAF');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM direction WHERE dir_abreviation = 'DSI') THEN
        INSERT INTO direction (dir_nom, dir_abreviation) VALUES ('Direction des Systèmes d''Information', 'DSI');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM direction WHERE dir_abreviation = 'DRH') THEN
        INSERT INTO direction (dir_nom, dir_abreviation) VALUES ('Direction des Ressources Humaines', 'DRH');
    END IF;
END $$;

-- 2. INSERTION DES POSTES (Si ils n'existent pas déjà)
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM poste WHERE pst_fonction = 'Chef de Service Financier') THEN
        INSERT INTO poste (pst_mission, pst_fonction) VALUES ('Gestion financière', 'Chef de Service Financier');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM poste WHERE pst_fonction = 'Développeur Fullstack') THEN
        INSERT INTO poste (pst_mission, pst_fonction) VALUES ('Développement Logiciel', 'Développeur Fullstack');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM poste WHERE pst_fonction = 'Responsable Paie') THEN
        INSERT INTO poste (pst_mission, pst_fonction) VALUES ('Gestion RH', 'Responsable Paie');
    END IF;
END $$;

-- 3. LIENS FONCTION - DIRECTION (Nettoyage préalable pour éviter doublons puis insertion)
DELETE FROM fonction_direc WHERE pst_code IN (
    SELECT pst_code FROM poste WHERE pst_fonction IN ('Chef de Service Financier', 'Développeur Fullstack', 'Responsable Paie')
);

INSERT INTO fonction_direc (pst_code, dir_code) VALUES 
((SELECT pst_code FROM poste WHERE pst_fonction = 'Chef de Service Financier' LIMIT 1), (SELECT dir_code FROM direction WHERE dir_abreviation = 'DAAF' LIMIT 1)),
((SELECT pst_code FROM poste WHERE pst_fonction = 'Développeur Fullstack' LIMIT 1), (SELECT dir_code FROM direction WHERE dir_abreviation = 'DSI' LIMIT 1)),
((SELECT pst_code FROM poste WHERE pst_fonction = 'Responsable Paie' LIMIT 1), (SELECT dir_code FROM direction WHERE dir_abreviation = 'DRH' LIMIT 1));

-- 4. INSERTION DE LA DECISION
INSERT INTO decision (dec_num) VALUES ('DEC-2025-001') ON CONFLICT (dec_num) DO NOTHING;

-- 5. CREATION DES EMPLOYES
INSERT INTO employee (emp_code, nom, prenom, matricule, sexe, date_embauche, email, is_actif) VALUES 
(201, 'RAKOTO', 'Jean', 10001, true, '2020-01-15', 'jean.rakoto@example.com', 1),
(202, 'RASOA', 'Marie', 10002, false, '2021-03-10', 'marie.rasoa@example.com', 1),
(203, 'ANDRIAMANANTSOA', 'Paul', 10003, true, '2019-11-05', 'paul.andria@example.com', 1)
ON CONFLICT (emp_code) DO NOTHING;

-- 6. AFFECTATIONS (On supprime les anciennes pour ces employés pour éviter doublons de PK)
DELETE FROM affectation WHERE emp_code IN (201, 202, 203);

INSERT INTO affectation (emp_code, pst_code, afec_date) VALUES 
(201, (SELECT pst_code FROM poste WHERE pst_fonction = 'Développeur Fullstack' LIMIT 1), '2020-01-15'),
(202, (SELECT pst_code FROM poste WHERE pst_fonction = 'Chef de Service Financier' LIMIT 1), '2021-03-10'),
(203, (SELECT pst_code FROM poste WHERE pst_fonction = 'Responsable Paie' LIMIT 1), '2019-11-05');

-- 7. SOLDE CONGE
-- Nettoyage préalable pour ces employés et cette décision
DELETE FROM solde_conge WHERE emp_code IN (201, 202, 203) AND sld_anne = 2025;

INSERT INTO solde_conge (sld_dispo, sld_anne, sld_initial, sld_restant, sld_maj, dec_code, emp_code) VALUES 
(1, 2025, 30.00, 30.00, NOW(), (SELECT dec_code FROM decision WHERE dec_num = 'DEC-2025-001' LIMIT 1), 201),
(1, 2025, 30.00, 30.00, NOW(), (SELECT dec_code FROM decision WHERE dec_num = 'DEC-2025-001' LIMIT 1), 202),
(1, 2025, 30.00, 30.00, NOW(), (SELECT dec_code FROM decision WHERE dec_num = 'DEC-2025-001' LIMIT 1), 203);

-- 8. SOLDE PERMISSION
DELETE FROM solde_permission WHERE emp_code IN (201, 202, 203) AND sld_prm_anne = 2025;

INSERT INTO solde_permission (sld_prm_dispo, sld_prm_anne, emp_code) VALUES 
(10.00, 2025, 201),
(10.00, 2025, 202),
(10.00, 2025, 203);

-- 9. INSERSION DES FAMILLES (CONJOINTS ET ENFANTS)

-- 9.1 CONJOINTS
-- Insertion des conjoints
INSERT INTO conjointe (conj_code, conj_nom, conj_sexe) VALUES 
(301, 'Mme RAKOTO Alice', false),
(302, 'M. RASOA Pierre', true),
(303, 'Mme ANDRIA Sarah', false)
ON CONFLICT (conj_code) DO NOTHING;

-- Liaison Employé -> Conjoint
-- Nettoyage préalable
DELETE FROM emp_conj WHERE emp_code IN (201, 202, 203);

INSERT INTO emp_conj (emp_code, conj_code) VALUES 
(201, 301), -- Jean avec Alice
(202, 302), -- Marie avec Pierre
(203, 303); -- Paul avec Sarah

-- 9.2 ENFANTS
-- Insertion des enfants (Lien direct via emp_code maintenant)
INSERT INTO enfant (enf_code, enf_nom, enf_num, date_naissance, emp_code) VALUES 
(401, 'RAKOTO Koto', 'ENF-001', '2015-06-01', 201),
(402, 'RAKOTO Soa', 'ENF-002', '2018-09-15', 201),
(403, 'RASOA Raly', 'ENF-003', '2020-02-10', 202),
(404, 'ANDRIA Lita', 'ENF-004', '2010-12-25', 203)
ON CONFLICT (enf_code) DO NOTHING;

-- (Table emp_enfant supprimée du schéma, plus d'insert ici)

