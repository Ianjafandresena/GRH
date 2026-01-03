ALTER TABLE validation_cng 
    ADD COLUMN IF NOT EXISTS val_token VARCHAR(255) UNIQUE,
    ADD COLUMN IF NOT EXISTS val_token_expires TIMESTAMP,
    ADD COLUMN IF NOT EXISTS val_token_used BOOLEAN DEFAULT FALSE;

ALTER TABLE validation_cng ALTER COLUMN val_observation TYPE VARCHAR(500);

CREATE INDEX IF NOT EXISTS idx_val_token ON validation_cng(val_token);
