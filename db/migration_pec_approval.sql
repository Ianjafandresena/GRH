-- Migration: Add approval columns to pris_en_charge table
-- Run this in PostgreSQL if columns are missing

-- Check if columns exist and add them if not
DO $$ 
BEGIN
    -- Add pec_date_arrive if not exists
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name = 'pris_en_charge' AND column_name = 'pec_date_arrive') THEN
        ALTER TABLE pris_en_charge ADD COLUMN pec_date_arrive TIMESTAMP;
    END IF;
    
    -- Add pec_date_depart if not exists
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name = 'pris_en_charge' AND column_name = 'pec_date_depart') THEN
        ALTER TABLE pris_en_charge ADD COLUMN pec_date_depart TIMESTAMP;
    END IF;
    
    -- Add pec_creation if not exists
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name = 'pris_en_charge' AND column_name = 'pec_creation') THEN
        ALTER TABLE pris_en_charge ADD COLUMN pec_creation DATE DEFAULT CURRENT_DATE;
    END IF;
    
    -- Add pec_approuver if not exists
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name = 'pris_en_charge' AND column_name = 'pec_approuver') THEN
        ALTER TABLE pris_en_charge ADD COLUMN pec_approuver BOOLEAN DEFAULT FALSE;
    END IF;
END $$;

-- Also make cnv_code nullable in centre_sante
ALTER TABLE centre_sante ALTER COLUMN cnv_code DROP NOT NULL;
