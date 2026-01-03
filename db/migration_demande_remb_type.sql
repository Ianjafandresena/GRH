-- Migration: Ajouter colonne rem_is_centre et améliorer demande_remb
-- Date: 2025-12-28

-- 1. Ajouter colonne pour distinguer Agent vs Centre
ALTER TABLE demande_remb 
ADD COLUMN IF NOT EXISTS rem_is_centre BOOLEAN DEFAULT FALSE;

-- 2. Commenter la colonne pour documentation
COMMENT ON COLUMN demande_remb.rem_is_centre IS 'FALSE = Demande Agent, TRUE = Demande Centre de Santé';

-- Note: Les demandes existantes seront automatiquement marquées comme "Agent" (false)
