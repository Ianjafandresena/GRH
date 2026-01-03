-- Migration: Make cnv_code nullable in centre_sante
-- Run this in PostgreSQL

ALTER TABLE demande_remb ALTER COLUMN rem_observatio DROP NOT NULL;
-- Migration: Make cnv_code nullable in centre_sante
-- Run this in PostgreSQL

ALTER TABLE demande_remb ADD COLUMN rem_status BOOLEAN DROP NOT NULL;
