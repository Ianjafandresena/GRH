-- Correction encodage pour DG
UPDATE Signature SET sign_libele = 'Directeur General' WHERE sign_code = 1;

-- Au cas où, on corrige aussi les autres s'il y a des accents
-- UPDATE Signature SET sign_libele = '...'; 
