drop database grh;
create database grh;
\c grh;

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

CREATE TABLE decision(
   dec_code SERIAL,
   dec_num VARCHAR(50) ,
   PRIMARY KEY(dec_code),
   UNIQUE(dec_num)
);

CREATE TABLE employee(
   emp_code SMALLINT,
   emp_nom VARCHAR(50) ,
   emp_prenom VARCHAR(50) ,
   emp_imarmp VARCHAR(50)  NOT NULL,
   emp_sexe BOOLEAN,
   emp_date_embauche DATE NOT NULL,
   emp_mail VARCHAR(50)  NOT NULL,
   emp_disponibilite BOOLEAN NOT NULL,
   PRIMARY KEY(emp_code),
   UNIQUE(emp_imarmp),
   UNIQUE(emp_mail)
);

CREATE TABLE enfant(
   enf_code SERIAL,
   enf_nom VARCHAR(50) ,
   enf_num VARCHAR(50) ,
   date_naissance DATE,
   emp_code SMALLINT NOT NULL,
   PRIMARY KEY(enf_code),
   FOREIGN KEY(emp_code) REFERENCES employee(emp_code)
);

CREATE TABLE facture(
   fac_code SERIAL,
   fac_num VARCHAR(50) ,
   fac_date DATE,
   PRIMARY KEY(fac_code)
);

CREATE TABLE Signature(
   sign_code SERIAL,
   sign_libele VARCHAR(50) ,
   sign_observation VARCHAR(50) ,
   emp_code SMALLINT NOT NULL,
   PRIMARY KEY(sign_code),
   UNIQUE(emp_code),
   FOREIGN KEY(emp_code) REFERENCES employee(emp_code)
);

CREATE TABLE etat_remb(
   eta_code SERIAL,
   eta_date DATE,
   eta_total NUMERIC(15,2)  ,
   etat_num VARCHAR(150)  NOT NULL,
   emp_code SMALLINT NOT NULL,
   PRIMARY KEY(eta_code),
   UNIQUE(etat_num),
   FOREIGN KEY(emp_code) REFERENCES employee(emp_code)
);


CREATE TABLE engagement(
   eng_code SERIAL,
   eng_date VARCHAR(50) ,
   eta_code INTEGER NOT NULL,
   PRIMARY KEY(eng_code),
   FOREIGN KEY(eta_code) REFERENCES etat_remb(eta_code)
);

CREATE TABLE type_centre(
   tp_cen_code SERIAL,
   tp_cen VARCHAR(50) ,
   PRIMARY KEY(tp_cen_code)
);

CREATE TABLE Region(
   reg_code SERIAL,
   reg_nom VARCHAR(50) ,
   PRIMARY KEY(reg_code)
);

CREATE TABLE users(
   id SERIAL,
   username VARCHAR(150)  NOT NULL,
   password VARCHAR(255)  NOT NULL,
   nom VARCHAR(150) ,
   prenom VARCHAR(150) ,
   role VARCHAR(100) ,
   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
   updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
   PRIMARY KEY(id),
   UNIQUE(username)
);

CREATE TABLE poste(
   pst_code SERIAL,
   pst_fonction VARCHAR(150) ,
   pst_max INTEGER,
   PRIMARY KEY(pst_code)
);

CREATE TABLE direction(
   dir_code SERIAL,
   dir_nom VARCHAR(200) ,
   dir_abreviation VARCHAR(50) ,
   PRIMARY KEY(dir_code)
);

CREATE TABLE objet_remboursement(
   obj_code SERIAL,
   obj_article VARCHAR(50) ,
   PRIMARY KEY(obj_code)
);

CREATE TABLE permission(
   prm_code SERIAL,
   prm_duree NUMERIC(15,2)  ,
   prm_date DATE,
   emp_code SMALLINT NOT NULL,
   PRIMARY KEY(prm_code),
   FOREIGN KEY(emp_code) REFERENCES employee(emp_code)
);

CREATE TABLE solde_permission(
   sld_prm_code SERIAL,
   sld_prm_dispo NUMERIC(15,2)  ,
   sld_prm_anne INTEGER,
   emp_code SMALLINT NOT NULL,
   PRIMARY KEY(sld_prm_code),
   FOREIGN KEY(emp_code) REFERENCES employee(emp_code)
);

CREATE TABLE conge(
   cng_code SERIAL,
   cng_nb_jour NUMERIC(2,1)   NOT NULL,
   cng_debut DATE NOT NULL,
   cng_fin DATE NOT NULL,
   cng_demande TIMESTAMP NOT NULL,
   cng_status BOOLEAN,
   typ_code INTEGER NOT NULL,
   emp_code SMALLINT NOT NULL,
   reg_code INTEGER NOT NULL,
   PRIMARY KEY(cng_code),
   FOREIGN KEY(typ_code) REFERENCES type_conge(typ_code),
   FOREIGN KEY(emp_code) REFERENCES employee(emp_code),
   FOREIGN KEY(reg_code) REFERENCES Region(reg_code)
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

CREATE TABLE solde_conge(
   sld_code SERIAL,
   sld_dispo SMALLINT,
   sld_anne BIGINT,
   sld_initial NUMERIC(15,2)  ,
   sld_restant NUMERIC(15,2)  ,
   sld_maj TIMESTAMP,
   emp_code SMALLINT NOT NULL,
   dec_code INTEGER NOT NULL,
   PRIMARY KEY(sld_code),
   FOREIGN KEY(emp_code) REFERENCES employee(emp_code),
   FOREIGN KEY(dec_code) REFERENCES decision(dec_code)
);

CREATE TABLE centre_sante(
   cen_code SERIAL,
   cen_nom VARCHAR(150) ,
   cen_adresse VARCHAR(150) ,
   tp_cen_code INTEGER NOT NULL,
   PRIMARY KEY(cen_code),
   FOREIGN KEY(tp_cen_code) REFERENCES type_centre(tp_cen_code)
);

CREATE TABLE pris_en_charge(
   pec_code SERIAL,
   pec_num VARCHAR(50) ,
   pec_date_arrive TIMESTAMP,
   pec_date_depart TIMESTAMP,
   pec_creation DATE,
   pec_approuver BOOLEAN,
   emp_code SMALLINT,
   conj_code INTEGER,
   enf_code INTEGER,
   cen_code INTEGER,
   PRIMARY KEY(pec_code),
   UNIQUE(pec_num),
   FOREIGN KEY(emp_code) REFERENCES employee(emp_code),
   FOREIGN KEY(conj_code) REFERENCES conjointe(conj_code),
   FOREIGN KEY(enf_code) REFERENCES enfant(enf_code),
   FOREIGN KEY(cen_code) REFERENCES centre_sante(cen_code)
);

CREATE TABLE demande_remb(
   rem_code SERIAL,
   rem_date DATE,
   rem_montant NUMERIC(15,2)  ,
   rem_montant_lettre VARCHAR(50) ,
   rem_num VARCHAR(50) ,
   rem_status BOOLEAN,
   eta_code INTEGER,
   pec_code INTEGER NOT NULL,
   emp_code SMALLINT NOT NULL,
   cen_code INTEGER NOT NULL,
   obj_code INTEGER NOT NULL,
   fac_code INTEGER NOT NULL,
   PRIMARY KEY(rem_code),
   FOREIGN KEY(eta_code) REFERENCES etat_remb(eta_code),
   FOREIGN KEY(pec_code) REFERENCES pris_en_charge(pec_code),
   FOREIGN KEY(emp_code) REFERENCES employee(emp_code),
   FOREIGN KEY(cen_code) REFERENCES centre_sante(cen_code),
   FOREIGN KEY(obj_code) REFERENCES objet_remboursement(obj_code),
   FOREIGN KEY(fac_code) REFERENCES facture(fac_code)
);

CREATE TABLE piece(
   pc_code SERIAL,
   pc_nom VARCHAR(50) ,
   rem_code INTEGER NOT NULL,
   PRIMARY KEY(pc_code),
   FOREIGN KEY(rem_code) REFERENCES demande_remb(rem_code)
);

CREATE TABLE validation_cng(
   cng_code INTEGER,
   sign_code INTEGER,
   val_code SERIAL NOT NULL,
   val_date DATE,
   val_status BOOLEAN,
   val_observation VARCHAR(50) ,
   PRIMARY KEY(cng_code, sign_code),
   UNIQUE(val_code),
   FOREIGN KEY(cng_code) REFERENCES conge(cng_code),
   FOREIGN KEY(sign_code) REFERENCES Signature(sign_code)
);

CREATE TABLE signature_engagement(
   sign_code INTEGER,
   eng_code INTEGER,
   sign_date DATE,
   PRIMARY KEY(sign_code, eng_code),
   FOREIGN KEY(sign_code) REFERENCES Signature(sign_code),
   FOREIGN KEY(eng_code) REFERENCES engagement(eng_code)
);

CREATE TABLE validation_prm(
   prm_code INTEGER,
   sign_code INTEGER,
   val_code SERIAL NOT NULL,
   val_date DATE,
   val_observation VARCHAR(50) ,
   PRIMARY KEY(prm_code, sign_code),
   UNIQUE(val_code),
   FOREIGN KEY(prm_code) REFERENCES permission(prm_code),
   FOREIGN KEY(sign_code) REFERENCES Signature(sign_code)
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

CREATE TABLE affectation(
   emp_code SMALLINT,
   pst_code INTEGER,
   dir_code INTEGER,
   affec_code SERIAL NOT NULL,
   affec_date_debut DATE,
   affec_date_fin DATE,
   affec_type_contrat VARCHAR(50) ,
   PRIMARY KEY(emp_code, pst_code, dir_code),
   UNIQUE(affec_code),
   FOREIGN KEY(emp_code) REFERENCES employee(emp_code),
   FOREIGN KEY(pst_code) REFERENCES poste(pst_code),
   FOREIGN KEY(dir_code) REFERENCES direction(dir_code)
);

CREATE TABLE fonction_direc(
   pst_code INTEGER,
   dir_code INTEGER,
   fonc_mission VARCHAR(200) ,
   PRIMARY KEY(pst_code, dir_code),
   FOREIGN KEY(pst_code) REFERENCES poste(pst_code),
   FOREIGN KEY(dir_code) REFERENCES direction(dir_code)
);

CREATE OR REPLACE VIEW v_etat_conge AS
SELECT 
  e.emp_code,
  e.emp_nom,
  e.emp_prenom,
  e.emp_imarmp,
  d.dir_nom,
  d.dir_abreviation,
  p.pst_fonction,
  sc.sld_anne,
  sc.sld_initial,
  sc.sld_restant,
  dec.dec_num
FROM employee e
LEFT JOIN affectation a ON a.emp_code = e.emp_code
LEFT JOIN direction d ON d.dir_code = a.dir_code
LEFT JOIN poste p ON p.pst_code = a.pst_code
LEFT JOIN solde_conge sc ON sc.emp_code = e.emp_code
LEFT JOIN decision dec ON dec.dec_code = sc.dec_code
WHERE e.emp_disponibilite = true
ORDER BY e.emp_code, sc.sld_anne ASC;