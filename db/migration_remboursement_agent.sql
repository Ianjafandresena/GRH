-- Migration Module Remboursement Agent
-- Exécuter dans PostgreSQL

-- 1. Ajouter rem_status à demande_remb si manquant
DO $$ 
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name = 'demande_remb' AND column_name = 'rem_status') THEN
        ALTER TABLE demande_remb ADD COLUMN rem_status BOOLEAN DEFAULT FALSE;
    END IF;
END $$;

-- 2. Rendre fac_code nullable dans demande_remb
ALTER TABLE demande_remb ALTER COLUMN fac_code DROP NOT NULL;

-- 3. Rendre pec_code nullable dans demande_remb  
ALTER TABLE demande_remb ALTER COLUMN pec_code DROP NOT NULL;

-- 4. Rendre obj_code nullable dans demande_remb
ALTER TABLE demande_remb ALTER COLUMN obj_code DROP NOT NULL;

-- 5. Rendre cen_code nullable dans demande_remb
ALTER TABLE demande_remb ALTER COLUMN cen_code DROP NOT NULL;

-- 6. Ajouter colonnes pris_en_charge si manquantes
DO $$ 
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name = 'pris_en_charge' AND column_name = 'pec_date_arrive') THEN
        ALTER TABLE pris_en_charge ADD COLUMN pec_date_arrive TIMESTAMP;
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name = 'pris_en_charge' AND column_name = 'pec_date_depart') THEN
        ALTER TABLE pris_en_charge ADD COLUMN pec_date_depart TIMESTAMP;
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name = 'pris_en_charge' AND column_name = 'pec_creation') THEN
        ALTER TABLE pris_en_charge ADD COLUMN pec_creation DATE DEFAULT CURRENT_DATE;
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name = 'pris_en_charge' AND column_name = 'pec_approuver') THEN
        ALTER TABLE pris_en_charge ADD COLUMN pec_approuver BOOLEAN DEFAULT FALSE;
    END IF;
END $$;

-- 7. Mettre à jour les PEC existants (pec_approuver = false par défaut)
UPDATE pris_en_charge SET pec_approuver = FALSE WHERE pec_approuver IS NULL;

-- 8. Rendre cnv_code nullable si besoin
DO $$
BEGIN
    ALTER TABLE centre_sante ALTER COLUMN cnv_code DROP NOT NULL;
EXCEPTION WHEN OTHERS THEN
    NULL;
END $$;

SELECT 'Migration complète!' AS status;
