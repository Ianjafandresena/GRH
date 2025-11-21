-- RH Database Schema (PostgreSQL)
-- Source: user-provided MCD + users/auth additions

-- Schema options
CREATE TABLE employee(
   emp_code SMALLINT,
   nom VARCHAR(50) ,
   prenom VARCHAR(50) ,
   matricule INTEGER NOT NULL,
   sexe BOOLEAN,
   date_embauche DATE NOT NULL,
   email VARCHAR(50)  NOT NULL,
   is_actif SMALLINT NOT NULL,
   PRIMARY KEY(emp_code),
   UNIQUE(matricule)
);

CREATE TABLE element_justificatif(
   elm_code SERIAL,
   elm_nom VARCHAR(50)  NOT NULL,
   PRIMARY KEY(elm_code)
);

CREATE TABLE status(
   stat_code SERIAL,
   stat_appelation VARCHAR(50)  NOT NULL,
   stat_ref VARCHAR(10)  NOT NULL,
   PRIMARY KEY(stat_code),
   UNIQUE(stat_ref)
);

CREATE TABLE modification(
   modification_id SMALLINT,
   table_modifier VARCHAR(50)  NOT NULL,
   date_modification DATE NOT NULL,
   champs_modifier VARCHAR(50)  NOT NULL,
   ancienne_valeur VARCHAR(50)  NOT NULL,
   nouvelle_valeur VARCHAR(50) ,
   PRIMARY KEY(modification_id)
);

CREATE TABLE type_conge(
   typ_code SERIAL,
   typ_appelation VARCHAR(50)  NOT NULL,
   typ_ref VARCHAR(50)  NOT NULL,
   is_paid SMALLINT NOT NULL,
   PRIMARY KEY(typ_code),
   UNIQUE(typ_appelation),
   UNIQUE(typ_ref)
);

CREATE TABLE conjointe(
   conj_code SERIAL,
   conj_nom VARCHAR(50) ,
   conj_sexe BOOLEAN,
   PRIMARY KEY(conj_code)
);

CREATE TABLE enfant(
   enf_code SERIAL,
   enf_nom VARCHAR(50) ,
   enf_num VARCHAR(50) ,
   date_naissance DATE,
   PRIMARY KEY(enf_code)
);

CREATE TABLE decision(
   dec_code SERIAL,
   dec_num VARCHAR(50) ,
   PRIMARY KEY(dec_code),
   UNIQUE(dec_num)
);

CREATE TABLE solde_permission(
   sld_prm_code SERIAL,
   sld_prm_dispo NUMERIC(15,2)  ,
   sld_prm_anne INTEGER,
   emp_code SMALLINT NOT NULL,
   PRIMARY KEY(sld_prm_code),
   FOREIGN KEY(emp_code) REFERENCES employee(emp_code)
);

CREATE TABLE Signature(
   sign_code SERIAL,
   sign_libele VARCHAR(50) ,
   PRIMARY KEY(sign_code)
);

CREATE TABLE etat_remb(
   eta_code SERIAL,
   PRIMARY KEY(eta_code)
);

CREATE TABLE convention(
   cnv_code SERIAL,
   cnv_taux_couver NUMERIC(15,2)  ,
   cnv_date_debut DATE,
   cnv_date_fin VARCHAR(50) ,
   PRIMARY KEY(cnv_code)
);

CREATE TABLE Region(
   reg_code SERIAL,
   reg_nom VARCHAR(50) ,
   PRIMARY KEY(reg_code)
);

CREATE TABLE solde_conge(
   sld_code SERIAL,
   sld_dispo SMALLINT,
   sld_anne BIGINT,
   sld_initial NUMERIC(15,2)  ,
   sld_restant NUMERIC(15,2)  ,
   sld_maj TIMESTAMP,
   dec_code INTEGER NOT NULL,
   emp_code SMALLINT NOT NULL,
   PRIMARY KEY(sld_code),
   FOREIGN KEY(dec_code) REFERENCES decision(dec_code),
   FOREIGN KEY(emp_code) REFERENCES employee(emp_code)
);

CREATE TABLE validation_conge(
   val_code SMALLINT,
   val_date TIMESTAMP,
   emp_code_1 SMALLINT,
   stat_code INTEGER NOT NULL,
   PRIMARY KEY(val_code),
   FOREIGN KEY(emp_code_1) REFERENCES employee(emp_code),
   FOREIGN KEY(stat_code) REFERENCES status(stat_code)
);

CREATE TABLE permission(
   prm_code SERIAL,
   prm_duree NUMERIC(15,2)  ,
   prm_date DATE,
   val_code SMALLINT,
   emp_code SMALLINT NOT NULL,
   PRIMARY KEY(prm_code),
   UNIQUE(val_code),
   FOREIGN KEY(val_code) REFERENCES validation_conge(val_code),
   FOREIGN KEY(emp_code) REFERENCES employee(emp_code)
);

CREATE TABLE centre_sante(
   cen_code SERIAL,
   cen_nom VARCHAR(50) ,
   cnv_code INTEGER NOT NULL,
   PRIMARY KEY(cen_code),
   FOREIGN KEY(cnv_code) REFERENCES convention(cnv_code)
);

CREATE TABLE facture(
   fac_code SERIAL,
   fac_objet VARCHAR(50) ,
   fac_total NUMERIC(15,2)  ,
   cen_code INTEGER NOT NULL,
   PRIMARY KEY(fac_code),
   UNIQUE(cen_code),
   FOREIGN KEY(cen_code) REFERENCES centre_sante(cen_code)
);

CREATE TABLE conge(
   cng_code SERIAL,
   cng_nb_jour NUMERIC(2,1)   NOT NULL,
   cng_debut DATE NOT NULL,
   cng_fin DATE NOT NULL,
   cng_demande TIMESTAMP NOT NULL,
   reg_code INTEGER NOT NULL,
   emp_code SMALLINT NOT NULL,
   val_code SMALLINT,
   typ_code INTEGER NOT NULL,
   PRIMARY KEY(cng_code),
   UNIQUE(val_code),
   FOREIGN KEY(reg_code) REFERENCES Region(reg_code),
   FOREIGN KEY(emp_code) REFERENCES employee(emp_code),
   FOREIGN KEY(val_code) REFERENCES validation_conge(val_code),
   FOREIGN KEY(typ_code) REFERENCES type_conge(typ_code)
);

CREATE TABLE pris_en_charge(
   pec_code SERIAL,
   pec_num VARCHAR(50) ,
   cen_code INTEGER,
   enf_code INTEGER,
   conj_code INTEGER,
   emp_code SMALLINT NOT NULL,
   emp_code_1 SMALLINT,
   PRIMARY KEY(pec_code),
   UNIQUE(pec_num),
   FOREIGN KEY(cen_code) REFERENCES centre_sante(cen_code),
   FOREIGN KEY(enf_code) REFERENCES enfant(enf_code),
   FOREIGN KEY(conj_code) REFERENCES conjointe(conj_code),
   FOREIGN KEY(emp_code) REFERENCES employee(emp_code),
   FOREIGN KEY(emp_code_1) REFERENCES employee(emp_code)
);

CREATE TABLE demande_remb(
   rem_code SERIAL,
   rem_objet VARCHAR(50) ,
   rem_date DATE,
   rem_montant NUMERIC(15,2)  ,
   rem_montant_lettre VARCHAR(50) ,
   emp_code SMALLINT NOT NULL,
   pec_code INTEGER NOT NULL,
   eta_code INTEGER,
   PRIMARY KEY(rem_code),
   FOREIGN KEY(emp_code) REFERENCES employee(emp_code),
   FOREIGN KEY(pec_code) REFERENCES pris_en_charge(pec_code),
   FOREIGN KEY(eta_code) REFERENCES etat_remb(eta_code)
);

CREATE TABLE piece(
   pc_code SERIAL,
   pc_piece VARCHAR(50) ,
   rem_code INTEGER NOT NULL,
   PRIMARY KEY(pc_code),
   FOREIGN KEY(rem_code) REFERENCES demande_remb(rem_code)
);

CREATE TABLE interruption(
   interup_code SERIAL,
   interup_date DATE,
   interup_motif VARCHAR(50) ,
   interup_restant INTEGER,
   cng_code INTEGER,
   PRIMARY KEY(interup_code),
   FOREIGN KEY(cng_code) REFERENCES conge(cng_code)
);

CREATE TABLE element_justificatif_conge(
   cng_code INTEGER,
   elm_code INTEGER,
   PRIMARY KEY(cng_code, elm_code),
   FOREIGN KEY(cng_code) REFERENCES conge(cng_code),
   FOREIGN KEY(elm_code) REFERENCES element_justificatif(elm_code)
);

CREATE TABLE emp_enfant(
   emp_code SMALLINT,
   enf_code INTEGER,
   PRIMARY KEY(emp_code, enf_code),
   FOREIGN KEY(emp_code) REFERENCES employee(emp_code),
   FOREIGN KEY(enf_code) REFERENCES enfant(enf_code)
);

CREATE TABLE emp_conj(
   emp_code SMALLINT,
   conj_code INTEGER,
   PRIMARY KEY(emp_code, conj_code),
   FOREIGN KEY(emp_code) REFERENCES employee(emp_code),
   FOREIGN KEY(conj_code) REFERENCES conjointe(conj_code)
);

CREATE TABLE Interim_conge(
   emp_code SMALLINT,
   cng_code INTEGER,
   int_code SERIAL NOT NULL,
   int_debut DATE,
   int_fin DATE,
   PRIMARY KEY(emp_code, cng_code),
   UNIQUE(int_code),
   FOREIGN KEY(emp_code) REFERENCES employee(emp_code),
   FOREIGN KEY(cng_code) REFERENCES conge(cng_code)
);

CREATE TABLE Interim_permission(
   emp_code SMALLINT,
   prm_code INTEGER,
   int_prm_code SERIAL NOT NULL,
   int_prm_debut TIMESTAMP,
   int_prm_fin TIMESTAMP,
   PRIMARY KEY(emp_code, prm_code),
   UNIQUE(int_prm_code),
   FOREIGN KEY(emp_code) REFERENCES employee(emp_code),
   FOREIGN KEY(prm_code) REFERENCES permission(prm_code)
);

CREATE TABLE Asso_30(
   rem_code INTEGER,
   cen_code INTEGER,
   PRIMARY KEY(rem_code, cen_code),
   FOREIGN KEY(rem_code) REFERENCES demande_remb(rem_code),
   FOREIGN KEY(cen_code) REFERENCES centre_sante(cen_code)
);

CREATE TABLE signature_demande(
   rem_code INTEGER,
   sign_code INTEGER,
   sin_dem_code VARCHAR(50)  NOT NULL,
   date_ DATE,
   PRIMARY KEY(rem_code, sign_code),
   UNIQUE(sin_dem_code),
   FOREIGN KEY(rem_code) REFERENCES demande_remb(rem_code),
   FOREIGN KEY(sign_code) REFERENCES Signature(sign_code)
);

CREATE TABLE debit_solde_cng(
   emp_code SMALLINT,
   sld_code INTEGER,
   cng_code INTEGER,
   deb_code SERIAL NOT NULL,
   deb_jr NUMERIC(15,2)  ,
   deb_date DATE,
   PRIMARY KEY(emp_code, sld_code, cng_code),
   UNIQUE(deb_code),
   FOREIGN KEY(emp_code) REFERENCES employee(emp_code),
   FOREIGN KEY(sld_code) REFERENCES solde_conge(sld_code),
   FOREIGN KEY(cng_code) REFERENCES conge(cng_code)
);

CREATE TABLE debit_solde_prm(
   emp_code SMALLINT,
   prm_code INTEGER,
   sld_prm_code INTEGER,
   deb_prm_code SERIAL NOT NULL,
   deb_jr NUMERIC(15,2)  ,
   deb_date TIMESTAMP,
   PRIMARY KEY(emp_code, prm_code, sld_prm_code),
   UNIQUE(deb_prm_code),
   FOREIGN KEY(emp_code) REFERENCES employee(emp_code),
   FOREIGN KEY(prm_code) REFERENCES permission(prm_code),
   FOREIGN KEY(sld_prm_code) REFERENCES solde_permission(sld_prm_code)
);

-- -- Simple trigger to keep updated_at fresh
-- DO $$
-- BEGIN
--   IF NOT EXISTS (
--       SELECT 1 FROM pg_proc WHERE proname = 'users_set_updated_at'
--   ) THEN
--     CREATE OR REPLACE FUNCTION users_set_updated_at() RETURNS TRIGGER AS $$
--     BEGIN
--       NEW.updated_at = NOW();
--       RETURN NEW;
--     END; $$ LANGUAGE plpgsql;
--   END IF;
-- END $$;

-- DO $$
-- BEGIN
--   IF NOT EXISTS (
--     SELECT 1 FROM pg_trigger WHERE tgname = 'trg_users_set_updated_at'
--   ) THEN
--     CREATE TRIGGER trg_users_set_updated_at
--     BEFORE UPDATE ON users
--     FOR EACH ROW
--     EXECUTE PROCEDURE users_set_updated_at();
--   END IF;
-- END $$;

-- -- ========== Helpful Indexes ==========
-- CREATE INDEX IF NOT EXISTS idx_employee_matricule ON employee(matricule);
-- CREATE INDEX IF NOT EXISTS idx_solde_conge_emp ON solde_conge(emp_code);
-- CREATE INDEX IF NOT EXISTS idx_solde_permission_emp ON solde_permission(emp_code);
-- CREATE INDEX IF NOT EXISTS idx_conge_emp ON conge(emp_code);
-- CREATE INDEX IF NOT EXISTS idx_conge_typ ON conge(typ_code);
-- CREATE INDEX IF NOT EXISTS idx_permission_emp ON permission(emp_code);
-- CREATE INDEX IF NOT EXISTS idx_validation_conge_stat ON validation_conge(stat_code);
-- CREATE INDEX IF NOT EXISTS idx_demande_remb_emp ON demande_remb(emp_code);
-- CREATE INDEX IF NOT EXISTS idx_demande_remb_eta ON demande_remb(eta_code);
-- CREATE INDEX IF NOT EXISTS idx_centre_sante_cnv ON centre_sante(cnv_code);

-- -- Seed minimal statuses (optional)
-- INSERT INTO status (stat_appelation, stat_ref)
-- SELECT * FROM (VALUES ('Soumis','SUB'),('Approuvé','APR'),('Rejeté','REJ'),('Payé','PAY')) AS s(app,ref)
-- WHERE NOT EXISTS (SELECT 1 FROM status);

-- -- ========== Functions & Views: Congés / Permissions ==========

-- -- Calculate days for a leave, fallback to stored cng_nb_jour if present
-- DO $$
-- BEGIN
--   IF NOT EXISTS (SELECT 1 FROM pg_proc WHERE proname = 'fn_conge_days') THEN
--     CREATE OR REPLACE FUNCTION fn_conge_days(p_cng_code INTEGER)
--     RETURNS NUMERIC AS $$
--     DECLARE v NUMERIC;
--     BEGIN
--       SELECT COALESCE(c.cng_nb_jour, GREATEST(0, (c.cng_fin - c.cng_debut + 1))) INTO v
--       FROM conge c WHERE c.cng_code = p_cng_code;
--       RETURN COALESCE(v, 0);
--     END; $$ LANGUAGE plpgsql STABLE;
--   END IF;
-- END $$;

-- -- Leave balance: from solde_conge entries of the year minus validated leaves
-- DO $$
-- BEGIN
--   IF NOT EXISTS (SELECT 1 FROM pg_proc WHERE proname = 'fn_solde_conge') THEN
--     CREATE OR REPLACE FUNCTION fn_solde_conge(p_emp INTEGER, p_year INTEGER)
--     RETURNS NUMERIC AS $$
--     DECLARE rights NUMERIC := 0; taken NUMERIC := 0; yr_start DATE; yr_end DATE;
--     BEGIN
--       yr_start := make_date(p_year,1,1);
--       yr_end   := make_date(p_year,12,31);

--       SELECT COALESCE(SUM(s.sld_dispo),0) INTO rights
--       FROM solde_conge s
--       WHERE s.emp_code = p_emp AND EXTRACT(YEAR FROM s.sld_anne) = p_year;

--       SELECT COALESCE(SUM(fn_conge_days(c.cng_code)),0) INTO taken
--       FROM conge c
--       JOIN validation_conge v ON v.val_code = c.val_code
--       JOIN status st ON st.stat_code = v.stat_code
--       WHERE c.emp_code = p_emp
--         AND c.cng_debut >= yr_start AND c.cng_fin <= yr_end
--         AND st.stat_ref = 'APR';

--       RETURN rights - taken;
--     END; $$ LANGUAGE plpgsql STABLE;
--   END IF;
-- END $$;

-- -- Permission hours or units (using prm_duree)
-- DO $$
-- BEGIN
--   IF NOT EXISTS (SELECT 1 FROM pg_proc WHERE proname = 'fn_permission_units') THEN
--     CREATE OR REPLACE FUNCTION fn_permission_units(p_prm_code INTEGER)
--     RETURNS NUMERIC AS $$
--     DECLARE v NUMERIC;
--     BEGIN
--       SELECT COALESCE(p.prm_duree,0) INTO v FROM permission p WHERE p.prm_code = p_prm_code;
--       RETURN COALESCE(v,0);
--     END; $$ LANGUAGE plpgsql STABLE;
--   END IF;
-- END $$;

-- -- Permission balance per year
-- DO $$
-- BEGIN
--   IF NOT EXISTS (SELECT 1 FROM pg_proc WHERE proname = 'fn_solde_permission') THEN
--     CREATE OR REPLACE FUNCTION fn_solde_permission(p_emp INTEGER, p_year INTEGER)
--     RETURNS NUMERIC AS $$
--     DECLARE rights NUMERIC := 0; used NUMERIC := 0; yr_start DATE; yr_end DATE;
--     BEGIN
--       yr_start := make_date(p_year,1,1);
--       yr_end   := make_date(p_year,12,31);

--       SELECT COALESCE(SUM(s.sld_prm_dispo),0) INTO rights
--       FROM solde_permission s
--       WHERE s.emp_code = p_emp AND s.sld_prm_anne = p_year;

--       SELECT COALESCE(SUM(fn_permission_units(p.prm_code)),0) INTO used
--       FROM permission p
--       JOIN validation_conge v ON v.val_code = p.val_code
--       JOIN status st ON st.stat_code = v.stat_code
--       WHERE p.emp_code = p_emp
--         AND p.prm_date >= yr_start AND p.prm_date <= yr_end
--         AND st.stat_ref = 'APR';

--       RETURN rights - used;
--     END; $$ LANGUAGE plpgsql STABLE;
--   END IF;
-- END $$;

-- -- Synthesis view of leaves per employee and period
-- DO $$
-- BEGIN
--   IF NOT EXISTS (SELECT 1 FROM pg_class WHERE relname = 'v_conge_synthese') THEN
--     CREATE OR REPLACE VIEW v_conge_synthese AS
--     SELECT c.emp_code,
--            date_trunc('month', c.cng_debut)::date AS period_start,
--            SUM(fn_conge_days(c.cng_code)) AS days_taken,
--            COUNT(*) FILTER (WHERE st.stat_ref = 'SUB') AS nb_submitted,
--            COUNT(*) FILTER (WHERE st.stat_ref = 'APR') AS nb_approved,
--            COUNT(*) FILTER (WHERE st.stat_ref = 'REJ') AS nb_rejected
--     FROM conge c
--     LEFT JOIN validation_conge v ON v.val_code = c.val_code
--     LEFT JOIN status st ON st.stat_code = v.stat_code
--     GROUP BY c.emp_code, date_trunc('month', c.cng_debut);
--   END IF;
-- END $$;
