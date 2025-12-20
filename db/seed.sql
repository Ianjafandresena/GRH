-- Seed data for initial admin user and basic statuses
-- PostgreSQL only. Ensure you created schema beforehand (see db/schema.sql)

-- Enable pgcrypto for bcrypt hashing
CREATE EXTENSION IF NOT EXISTS pgcrypto;


INSERT INTO users (username, password, nom, prenom, role)
VALUES (
  'admin',
  crypt('admin123', gen_salt('bf')),-- hash pour 'admin123'
  'Super',
  'Administrateur',
  0
);



-- Employés
INSERT INTO employee (emp_nom, emp_prenom, emp_imarmp, emp_sexe, emp_date_embauche, emp_email, emp_disponibilite, sign_code, pst_code) VALUES
('RANDRIANTSIANA', 'Valério', 'H300698', TRUE, '2018-01-15', 'valerio@entreprise.mg', 1),
('RABE', 'Jean', 'H301100', FALSE, '2020-03-05', 'jean.rabe@entreprise.mg', 1),
('RAKOTO', 'Miora', 'H302211', TRUE, '2022-06-10', 'miora.rakoto@entreprise.mg', 1);


INSERT INTO Region (reg_nom) VALUES
('Alaotra-Mangoro'),
('Amoron i Mania'),
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


-- Décisions : une par année (décision unique, reliée plus bas)
INSERT INTO decision (dec_num)
VALUES
('044/ARMP/DG-21'), 
('055/ARMP/DG-22'),  
('022/ARMP/DG-23'),  
('100/ARMP/DG-24'), 
('051/ARMP/DG-25'); 

-- Région
INSERT INTO type_conge (typ_appelation, typ_ref) VALUES
('Congé Annuel', 'CA'), ('Congé Exceptionnel', 'CE'),('Congé de Paternité', 'CP'),('Repos Maladie', 'RM');

-- Attribution soldes pour chaque employé, chaque année, chaque relié à sa décision
-- Valério (1)
INSERT INTO solde_conge (sld_dispo, sld_anne, sld_initial, sld_restant, sld_maj, dec_code, emp_code) VALUES
(20, 2021, 20, 19, '2025-01-01 08:00:00', 1, 1),
(20, 2022, 20, 17, '2025-01-01 08:00:00', 2, 1),
(20, 2023, 20, 20, '2025-01-01 08:00:00', 3, 1),
(20, 2024, 20, 20, '2025-01-01 08:00:00', 4, 1),
(20, 2025, 20, 20, '2025-01-01 08:00:00', 5, 1);

-- Jean (2)
INSERT INTO solde_conge (sld_dispo, sld_anne, sld_initial, sld_restant, sld_maj, dec_code, emp_code) VALUES
(15, 2021, 15, 14, '2025-01-01 08:00:00', 1, 2),
(15, 2022, 15, 15, '2025-01-01 08:00:00', 2, 2),
(15, 2023, 15, 15, '2025-01-01 08:00:00', 3, 2),
(15, 2024, 15, 15, '2025-01-01 08:00:00', 4, 2),
(15, 2025, 15, 15, '2025-01-01 08:00:00', 5, 2);

-- Miora (3)
INSERT INTO solde_conge (sld_dispo, sld_anne, sld_initial, sld_restant, sld_maj, dec_code, emp_code) VALUES
(12, 2021, 12, 11, '2025-01-01 08:00:00', 1, 3),
(12, 2022, 12, 12, '2025-01-01 08:00:00', 2, 3),
(12, 2023, 12, 12, '2025-01-01 08:00:00', 3, 3),
(12, 2024, 12, 12, '2025-01-01 08:00:00', 4, 3),
(12, 2025, 12, 12, '2025-01-01 08:00:00', 5, 3);

ALTER TABLE etat_remb ADD COLUMN eta_libelle VARCHAR(50) NOT NULL DEFAULT 'EN_ATTENTE';

INSERT INTO etat_remb (eta_libelle) VALUES 
  ('EN_ATTENTE'),
  ('VALIDE_RRH'),
  ('VALIDE_DAAF'),
  ('ENGAGE'),
  ('PAYE'),
  ('REFUSE');

SELECT s.sld_restant, s.sld_anne, s.dec_code, d.dec_num
FROM solde_conge s
JOIN decision d ON d.dec_code = s.dec_code
WHERE s.emp_code = 1 AND s.sld_restant > 0
ORDER BY s.sld_anne ASC 
LIMIT 1;
