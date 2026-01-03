-- Fix Rapide : Assigner les Signatures aux Employés
-- Basé sur seed_complete.sql

-- DG (emp_code = 1)
UPDATE signature SET emp_code = 1 WHERE sign_code = 1 AND sign_libele = 'DG';

-- Vérifier le résultat
SELECT s.sign_code, s.sign_libele, s.emp_code, e.emp_nom, e.emp_prenom, e.emp_mail
FROM signature s
LEFT JOIN employee e ON e.emp_code = s.emp_code
ORDER BY s.sign_code;

-- Si vous voulez assigner les autres aussi :
-- DAAF (trouvez l'emp_code du directeur DAAF dans seed_complete.sql)
-- UPDATE signature SET emp_code = X WHERE sign_code = 2;

-- RRH (trouvez l'emp_code du RRH dans seed_complete.sql)  
-- UPDATE signature SET emp_code = Y WHERE sign_code = 3;

-- Après cette correction, créez une NOUVELLE demande de congé pour tester
