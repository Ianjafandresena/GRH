-- Vérifier si la validation a été créée et si l'email du DG est correct

-- 1. Trouver le congé récent du Directeur
SELECT c.cng_code, c.emp_code, e.emp_nom, e.emp_prenom, p.pst_fonction, c.cng_date_demande
FROM conge c
JOIN employee e ON e.emp_code = c.emp_code
JOIN affectation a ON a.emp_code = e.emp_code
JOIN poste p ON p.pst_code = a.pst_code
WHERE p.pst_fonction LIKE '%DIRECTEUR%'
ORDER BY c.cng_date_demande DESC
LIMIT 5;

-- 2. Vérifier les validations créées pour ce congé
SELECT v.*, s.sign_libele, e.emp_nom, e.emp_prenom, e.emp_mail
FROM validation_cng v
JOIN signature s ON s.sign_code = v.sign_code
LEFT JOIN employee e ON e.emp_code = (
    SELECT emp_code FROM employee 
    WHERE emp_code IN (
        SELECT emp_code FROM affectation WHERE pst_code IN (
            SELECT pst_code FROM poste WHERE pst_fonction LIKE '%DIRECTEUR GENERAL%'
        )
    )
    LIMIT 1
)
WHERE v.cng_code = (
    SELECT cng_code FROM conge ORDER BY cng_date_demande DESC LIMIT 1
);

-- 3. Vérifier l'email du DG dans la base
SELECT e.emp_code, e.emp_nom, e.emp_prenom, e.emp_mail, p.pst_fonction
FROM employee e
JOIN affectation a ON a.emp_code = e.emp_code
JOIN poste p ON p.pst_code = a.pst_code
WHERE p.pst_fonction LIKE '%DIRECTEUR GENERAL%';

-- 4. Vérifier les signatures assignées au DG
SELECT s.*, e.emp_nom, e.emp_prenom, e.emp_mail
FROM signature s
LEFT JOIN employee e ON e.emp_code = s.emp_code
WHERE s.sign_libele LIKE '%DG%' OR s.sign_libele LIKE '%GENERAL%';
