-- Script de Correction : Assigner les Signatures aux Bons Employés
-- Problème : Le DG ne reçoit pas d'email car la signature n'est pas assignée

-- 1. Vérifier l'état actuel des signatures
SELECT s.sign_code, s.sign_libele, s.emp_code, e.emp_nom, e.emp_prenom, e.emp_mail, p.pst_fonction
FROM signature s
LEFT JOIN employee e ON e.emp_code = s.emp_code
LEFT JOIN affectation a ON a.emp_code = e.emp_code
LEFT JOIN poste p ON p.pst_code = a.pst_code
ORDER BY s.sign_code;

-- 2. Assigner le DG à la signature DG (sign_code = 1)
-- Trouve le DG dans la table employee et l'assigne
UPDATE signature 
SET emp_code = (
    SELECT e.emp_code 
    FROM employee e
    JOIN affectation a ON a.emp_code = e.emp_code
    JOIN poste p ON p.pst_code = a.pst_code
    WHERE p.pst_fonction LIKE '%DIRECTEUR GENERAL%'
    LIMIT 1
)
WHERE sign_code = 1 AND sign_libele = 'DG';

-- 3. Assigner DAAF
UPDATE signature 
SET emp_code = (
    SELECT e.emp_code 
    FROM employee e
    JOIN affectation a ON a.emp_code = e.emp_code
    JOIN poste p ON p.pst_code = a.pst_code
    JOIN direction d ON d.dir_code = a.dir_code
    WHERE d.dir_abreviation = 'DAAF'
    AND (p.pst_fonction LIKE '%DIRECTEUR%' AND p.pst_fonction NOT LIKE '%GENERAL%')
    LIMIT 1
)
WHERE sign_code = 2 AND sign_libele = 'DAAF';

-- 4. Assigner RRH
UPDATE signature 
SET emp_code = (
    SELECT e.emp_code 
    FROM employee e
    JOIN affectation a ON a.emp_code = e.emp_code
    JOIN poste p ON p.pst_code = a.pst_code
    WHERE p.pst_fonction LIKE '%RRH%' OR p.pst_fonction LIKE '%RESSOURCES HUMAINES%'
    LIMIT 1
)
WHERE sign_code = 3 AND sign_libele = 'RRH';

-- 5. Vérifier les résultats
SELECT s.sign_code, s.sign_libele, s.emp_code, e.emp_nom, e.emp_prenom, e.emp_mail, p.pst_fonction
FROM signature s
LEFT JOIN employee e ON e.emp_code = s.emp_code
LEFT JOIN affectation a ON a.emp_code = e.emp_code
LEFT JOIN poste p ON p.pst_code = a.pst_code
ORDER BY s.sign_code;

-- 6. Vérifier les demandes de congé récentes
SELECT c.cng_code, e.emp_nom, e.emp_prenom, p.pst_fonction, c.cng_date_demande, c.cng_status
FROM conge c
JOIN employee e ON e.emp_code = c.emp_code
JOIN affectation a ON a.emp_code = e.emp_code
JOIN poste p ON p.pst_code = a.pst_code
ORDER BY c.cng_date_demande DESC
LIMIT 5;

-- 7. Vérifier si une validation existe pour la dernière demande
SELECT v.*, s.sign_libele 
FROM validation_cng v
JOIN signature s ON s.sign_code = v.sign_code
WHERE v.cng_code = (SELECT cng_code FROM conge ORDER BY cng_date_demande DESC LIMIT 1);
