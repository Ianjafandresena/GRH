-- =====================================================
-- SCHÉMA FUSIONNÉ SI-GPRH + GESTION CARRIÈRE
-- Base de données unifiée pour l'ARMP
-- PostgreSQL
-- =====================================================
-- Ce schéma réunit :
-- 1. SI-GPRH : Congés, Permissions, Remboursements médicaux
-- 2. Gestion Carrière : Carrières, Compétences, Stages
-- =====================================================

DROP DATABASE IF EXISTS grh_unifie;
CREATE DATABASE grh_unifie;
\c grh_unifie;

-- =========================
-- EXTENSION REQUISE
-- =========================
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- =========================
-- TABLES DE RÉFÉRENCE COMMUNES
-- =========================

-- Régions de Madagascar
CREATE TABLE region (
    reg_code SERIAL PRIMARY KEY,
    reg_nom VARCHAR(50) NOT NULL
);

-- Types d'entrée (recrutement, nomination, etc.)
CREATE TABLE type_entree (
    e_type_code VARCHAR(50) PRIMARY KEY,
    e_type_motif VARCHAR(50)
);

-- Types de sortie (démission, retraite, etc.)
CREATE TABLE sortie_type (
    s_type_code VARCHAR(50) PRIMARY KEY,
    s_type_motif VARCHAR(50),
    commentaire VARCHAR(255)
);

-- Types de contrat
CREATE TABLE type_contrat (
    tcontrat_code SERIAL PRIMARY KEY,
    tcontrat_nom VARCHAR(50)
);

-- Statut ARMP (fonctionnaire, contractuel, etc.)
CREATE TABLE statut_armp (
    stt_armp_code SERIAL PRIMARY KEY,
    stt_armp_statut VARCHAR(50)
);

-- Rang hiérarchique
CREATE TABLE rang_hierarchique (
    rhq_code SERIAL PRIMARY KEY,
    rhq_rang VARCHAR(50),
    rhq_niveau VARCHAR(10)
);

-- Tâches supplémentaires
CREATE TABLE tache_suppl (
    tsup_code SERIAL PRIMARY KEY,
    tsup_tache VARCHAR(100)
);

-- Types de documents
CREATE TABLE type_document (
    tdoc_code SERIAL PRIMARY KEY,
    tdoc_nom VARCHAR(50)
);

-- =========================
-- STRUCTURE ORGANISATIONNELLE
-- =========================

-- Directions
CREATE TABLE direction (
    dir_code SERIAL PRIMARY KEY,
    dir_nom VARCHAR(200),
    dir_abreviation VARCHAR(50)
);

-- Services (rattachés aux directions)
CREATE TABLE service (
    srvc_code SERIAL PRIMARY KEY,
    srvc_nom VARCHAR(100),
    dir_code INTEGER REFERENCES direction(dir_code)
);

-- Postes (enrichi avec les deux projets)
CREATE TABLE poste (
    pst_code SERIAL PRIMARY KEY,
    pst_fonction VARCHAR(255),
    pst_mission VARCHAR(255),
    pst_max INTEGER,
    tsup_code INTEGER REFERENCES tache_suppl(tsup_code),
    rhq_code INTEGER REFERENCES rang_hierarchique(rhq_code),
    srvc_code INTEGER REFERENCES service(srvc_code),
    dir_code INTEGER REFERENCES direction(dir_code)
);

-- Occupation des postes (quotas)
CREATE TABLE occupation_poste (
    occpst_code SERIAL PRIMARY KEY,
    pst_code INTEGER UNIQUE REFERENCES poste(pst_code),
    quota INTEGER,
    nb_occupe INTEGER,
    nb_vacant INTEGER,
    nb_encessation INTEGER
);

-- Fonction par direction (liaison poste-direction)
CREATE TABLE fonction_direc (
    pst_code INTEGER REFERENCES poste(pst_code),
    dir_code INTEGER REFERENCES direction(dir_code),
    fonc_mission VARCHAR(200),
    PRIMARY KEY(pst_code, dir_code)
);

-- Position administrative
CREATE TABLE position_ (
    pos_code SERIAL PRIMARY KEY,
    pos_type VARCHAR(50)
);

-- =========================
-- EMPLOYÉS (TABLE PRINCIPALE UNIFIÉE)
-- =========================

CREATE TABLE employe (
    emp_code SERIAL PRIMARY KEY,
    emp_matricule VARCHAR(50),
    emp_nom VARCHAR(50),
    emp_prenom VARCHAR(50),
    emp_titre VARCHAR(50),
    emp_sexe BOOLEAN,
    emp_datenaissance DATE,
    emp_im_armp VARCHAR(50) UNIQUE,           -- Matricule ARMP (ex: H300698)
    emp_im_etat VARCHAR(50) UNIQUE,           -- Matricule État
    emp_mail VARCHAR(100) UNIQUE,
    emp_cin VARCHAR(100),
    emp_date_embauche DATE,
    emp_disponibilite BOOLEAN DEFAULT TRUE,   -- Présent ou non
    date_entree DATE,
    date_sortie DATE,
    s_type_code VARCHAR(50) REFERENCES sortie_type(s_type_code),
    e_type_code VARCHAR(50) REFERENCES type_entree(e_type_code)
);

-- Position de l'employé
CREATE TABLE pos_emp (
    emp_code INTEGER REFERENCES employe(emp_code),
    pos_code INTEGER REFERENCES position_(pos_code),
    date_ DATE,
    PRIMARY KEY (emp_code, pos_code)
);

-- Contacts employé
CREATE TABLE contact (
    id_contact SERIAL PRIMARY KEY,
    numero VARCHAR(50) UNIQUE,
    emp_code INTEGER NOT NULL REFERENCES employe(emp_code)
);

-- Statut employé ARMP
CREATE TABLE statut_emp (
    emp_code INTEGER REFERENCES employe(emp_code),
    stt_armp_code INTEGER REFERENCES statut_armp(stt_armp_code),
    date_ DATE,
    PRIMARY KEY (emp_code, stt_armp_code)
);

-- =========================
-- AFFECTATIONS (UNIFIÉE)
-- =========================

-- Motifs d'affectation
CREATE TABLE motif_affectation (
    m_aff_code SERIAL PRIMARY KEY,
    m_aff_motif VARCHAR(50),
    m_aff_type VARCHAR(50)
);

-- Affectations des employés aux postes
CREATE TABLE affectation (
    affec_code SERIAL PRIMARY KEY,
    emp_code INTEGER NOT NULL REFERENCES employe(emp_code),
    pst_code INTEGER NOT NULL REFERENCES poste(pst_code),
    dir_code INTEGER REFERENCES direction(dir_code),
    affec_date_debut DATE,
    affec_date_fin DATE,
    affec_type_contrat VARCHAR(50),
    affec_commentaire VARCHAR(50),
    affec_etat VARCHAR(255),
    m_aff_code INTEGER REFERENCES motif_affectation(m_aff_code),
    tcontrat_code INTEGER REFERENCES type_contrat(tcontrat_code)
);

-- Historique des sorties
CREATE TABLE sortie (
    emp_code INTEGER REFERENCES employe(emp_code),
    s_type_code VARCHAR(50) REFERENCES sortie_type(s_type_code),
    commentaire VARCHAR(255),
    date_sortie DATE,
    PRIMARY KEY (emp_code, s_type_code, date_sortie)
);

-- =========================
-- COMPÉTENCES (GESTION CARRIÈRE)
-- =========================

CREATE TABLE competence (
    comp_code SERIAL PRIMARY KEY,
    comp_intitule VARCHAR(50),
    comp_domaine VARCHAR(50),
    comp_description VARCHAR(50)
);

CREATE TABLE comp_employe (
    emp_code INTEGER REFERENCES employe(emp_code),
    comp_code INTEGER REFERENCES competence(comp_code),
    niveau_acquis INTEGER,
    PRIMARY KEY (emp_code, comp_code)
);

CREATE TABLE comp_poste (
    pst_code INTEGER REFERENCES poste(pst_code),
    comp_code INTEGER REFERENCES competence(comp_code),
    niveau_requis INTEGER,
    PRIMARY KEY (pst_code, comp_code)
);

-- =========================
-- DOCUMENTS EMPLOYÉS (GESTION CARRIÈRE)
-- =========================

CREATE TABLE doc_emp (
    tdoc_code INTEGER REFERENCES type_document(tdoc_code),
    affec_code INTEGER REFERENCES affectation(affec_code),
    doc_emp_code SERIAL UNIQUE,
    doc_emp_date DATE,
    doc_emp_statut VARCHAR(50),
    tdoc_matricule VARCHAR(50) UNIQUE,
    usage VARCHAR(100),
    commentaire VARCHAR(255),
    PRIMARY KEY (tdoc_code, affec_code)
);

-- =========================
-- STAGES (GESTION CARRIÈRE)
-- =========================

CREATE TABLE etablissement (
    etab_code SERIAL PRIMARY KEY,
    etab_nom VARCHAR(50),
    etab_adresse VARCHAR(50)
);

CREATE TABLE stagiaire (
    stgr_code SERIAL PRIMARY KEY,
    stgr_nom VARCHAR(50),
    stgr_prenom VARCHAR(50),
    stgr_nom_prenom VARCHAR(50),
    stgr_contact VARCHAR(50) UNIQUE,
    stgr_filiere VARCHAR(50),
    stgr_niveau VARCHAR(50),
    stgr_sexe BOOLEAN,
    stgr_adresse VARCHAR(255)
);

CREATE TABLE assiduite (
    asdt_code SERIAL PRIMARY KEY,
    asdt_remarque VARCHAR(50),
    asdt_nb_abscence INTEGER,
    asdt_nb_retard INTEGER
);

CREATE TABLE eval_stage (
    evstg_code SERIAL PRIMARY KEY,
    evstg_lieu VARCHAR(50),
    evstg_note INTEGER,
    evstg_aptitude VARCHAR(50),
    evstg_date_eval VARCHAR(50),
    asdt_code INTEGER REFERENCES assiduite(asdt_code)
);

CREATE TABLE stage (
    stg_code SERIAL PRIMARY KEY,
    stg_duree INTEGER,
    stg_date_debut DATE,
    stg_date_fin DATE,
    stg_theme VARCHAR(50),
    evstg_code INTEGER REFERENCES eval_stage(evstg_code),
    stgr_code INTEGER REFERENCES stagiaire(stgr_code),
    etab_code INTEGER REFERENCES etablissement(etab_code)
);

CREATE TABLE doc_stage (
    tdoc_code INTEGER REFERENCES type_document(tdoc_code),
    stg_code INTEGER REFERENCES stage(stg_code),
    doc_stg_code SERIAL UNIQUE,
    doc_stg_date DATE,
    tdoc_matricule VARCHAR(50),
    doc_stage_statut VARCHAR(50) DEFAULT 'en attente',
    PRIMARY KEY (tdoc_code, stg_code)
);

CREATE TABLE stage_carriere (
    emp_code INTEGER REFERENCES employe(emp_code),
    pst_code INTEGER REFERENCES poste(pst_code),
    stg_code INTEGER REFERENCES stage(stg_code),
    stg_carriere_code SERIAL UNIQUE,
    PRIMARY KEY (emp_code, pst_code, stg_code)
);

-- =========================
-- FAMILLE EMPLOYÉ (SI-GPRH)
-- =========================

CREATE TABLE conjointe (
    conj_code SERIAL PRIMARY KEY,
    conj_nom VARCHAR(50),
    conj_sexe BOOLEAN
);

CREATE TABLE emp_conj (
    emp_code INTEGER REFERENCES employe(emp_code),
    conj_code INTEGER REFERENCES conjointe(conj_code),
    PRIMARY KEY(emp_code, conj_code)
);

CREATE TABLE enfant (
    enf_code SERIAL PRIMARY KEY,
    enf_nom VARCHAR(50),
    enf_num VARCHAR(50),
    date_naissance DATE,
    emp_code INTEGER NOT NULL REFERENCES employe(emp_code)
);

-- =========================
-- CONGÉS (SI-GPRH)
-- =========================

CREATE TABLE type_conge (
    typ_code SERIAL PRIMARY KEY,
    typ_appelation VARCHAR(50) NOT NULL UNIQUE,
    typ_ref VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE decision (
    dec_code SERIAL PRIMARY KEY,
    dec_num VARCHAR(50) UNIQUE
);

CREATE TABLE conge (
    cng_code SERIAL PRIMARY KEY,
    cng_nb_jour NUMERIC(2,1) NOT NULL,
    cng_debut DATE NOT NULL,
    cng_fin DATE NOT NULL,
    cng_demande TIMESTAMP NOT NULL,
    cng_status BOOLEAN,
    typ_code INTEGER NOT NULL REFERENCES type_conge(typ_code),
    emp_code INTEGER NOT NULL REFERENCES employe(emp_code),
    reg_code INTEGER NOT NULL REFERENCES region(reg_code)
);

CREATE TABLE interruption (
    interup_code SERIAL PRIMARY KEY,
    interup_date DATE,
    interup_motif VARCHAR(50),
    interup_restant INTEGER,
    cng_code INTEGER REFERENCES conge(cng_code)
);

CREATE TABLE solde_conge (
    sld_code SERIAL PRIMARY KEY,
    sld_dispo SMALLINT,
    sld_anne BIGINT,
    sld_initial NUMERIC(15,2),
    sld_restant NUMERIC(15,2),
    sld_maj TIMESTAMP,
    emp_code INTEGER NOT NULL REFERENCES employe(emp_code),
    dec_code INTEGER NOT NULL REFERENCES decision(dec_code)
);

CREATE TABLE debit_solde_cng (
    emp_code INTEGER REFERENCES employe(emp_code),
    sld_code INTEGER REFERENCES solde_conge(sld_code),
    cng_code INTEGER REFERENCES conge(cng_code),
    deb_code SERIAL UNIQUE,
    deb_jr NUMERIC(15,2),
    deb_date DATE,
    PRIMARY KEY(emp_code, sld_code, cng_code)
);

CREATE TABLE interim_conge (
    emp_code INTEGER REFERENCES employe(emp_code),
    cng_code INTEGER REFERENCES conge(cng_code),
    int_code SERIAL UNIQUE,
    int_debut DATE,
    int_fin DATE,
    PRIMARY KEY(emp_code, cng_code)
);

-- =========================
-- PERMISSIONS (SI-GPRH)
-- =========================

CREATE TABLE permission (
    prm_code SERIAL PRIMARY KEY,
    prm_duree NUMERIC(15,2),
    prm_date DATE,
    emp_code INTEGER NOT NULL REFERENCES employe(emp_code)
);

CREATE TABLE solde_permission (
    sld_prm_code SERIAL PRIMARY KEY,
    sld_prm_dispo NUMERIC(15,2),
    sld_prm_anne INTEGER,
    emp_code INTEGER NOT NULL REFERENCES employe(emp_code)
);

CREATE TABLE interim_permission (
    emp_code INTEGER REFERENCES employe(emp_code),
    prm_code INTEGER REFERENCES permission(prm_code),
    int_prm_code SERIAL UNIQUE,
    int_prm_debut TIMESTAMP,
    int_prm_fin TIMESTAMP,
    PRIMARY KEY(emp_code, prm_code)
);

-- =========================
-- SIGNATURES & VALIDATIONS (SI-GPRH)
-- =========================

CREATE TABLE signature (
    sign_code SERIAL PRIMARY KEY,
    sign_libele VARCHAR(50),
    sign_observation VARCHAR(50),
    emp_code INTEGER NOT NULL UNIQUE REFERENCES employe(emp_code)
);

CREATE TABLE validation_cng (
    cng_code INTEGER REFERENCES conge(cng_code),
    sign_code INTEGER REFERENCES signature(sign_code),
    val_code SERIAL UNIQUE,
    val_date DATE,
    val_status BOOLEAN,
    val_observation VARCHAR(50),
    PRIMARY KEY(cng_code, sign_code)
);

CREATE TABLE validation_prm (
    prm_code INTEGER REFERENCES permission(prm_code),
    sign_code INTEGER REFERENCES signature(sign_code),
    val_code SERIAL UNIQUE,
    val_date DATE,
    val_observation VARCHAR(50),
    PRIMARY KEY(prm_code, sign_code)
);

-- =========================
-- REMBOURSEMENTS MÉDICAUX (SI-GPRH)
-- =========================

CREATE TABLE type_centre (
    tp_cen_code SERIAL PRIMARY KEY,
    tp_cen VARCHAR(50)
);

CREATE TABLE centre_sante (
    cen_code SERIAL PRIMARY KEY,
    cen_nom VARCHAR(150),
    cen_adresse VARCHAR(150),
    tp_cen_code INTEGER NOT NULL REFERENCES type_centre(tp_cen_code)
);

CREATE TABLE objet_remboursement (
    obj_code SERIAL PRIMARY KEY,
    obj_article VARCHAR(50)
);

CREATE TABLE facture (
    fac_code SERIAL PRIMARY KEY,
    fac_num VARCHAR(50),
    fac_date DATE
);

CREATE TABLE etat_remb (
    eta_code SERIAL PRIMARY KEY,
    eta_date DATE,
    eta_total NUMERIC(15,2),
    etat_num VARCHAR(150) NOT NULL UNIQUE,
    eta_libelle VARCHAR(50) NOT NULL DEFAULT 'EN_ATTENTE',
    emp_code INTEGER NOT NULL REFERENCES employe(emp_code)
);

CREATE TABLE engagement (
    eng_code SERIAL PRIMARY KEY,
    eng_date VARCHAR(50),
    eta_code INTEGER NOT NULL REFERENCES etat_remb(eta_code)
);

CREATE TABLE pris_en_charge (
    pec_code SERIAL PRIMARY KEY,
    pec_num VARCHAR(50) UNIQUE,
    pec_date_arrive TIMESTAMP,
    pec_date_depart TIMESTAMP,
    pec_creation DATE,
    pec_approuver BOOLEAN,
    emp_code INTEGER REFERENCES employe(emp_code),
    conj_code INTEGER REFERENCES conjointe(conj_code),
    enf_code INTEGER REFERENCES enfant(enf_code),
    cen_code INTEGER REFERENCES centre_sante(cen_code)
);

CREATE TABLE demande_remb (
    rem_code SERIAL PRIMARY KEY,
    rem_date DATE,
    rem_montant NUMERIC(15,2),
    rem_montant_lettre VARCHAR(50),
    rem_num VARCHAR(50),
    rem_status BOOLEAN,
    eta_code INTEGER REFERENCES etat_remb(eta_code),
    pec_code INTEGER NOT NULL REFERENCES pris_en_charge(pec_code),
    emp_code INTEGER NOT NULL REFERENCES employe(emp_code),
    cen_code INTEGER NOT NULL REFERENCES centre_sante(cen_code),
    obj_code INTEGER NOT NULL REFERENCES objet_remboursement(obj_code),
    fac_code INTEGER NOT NULL REFERENCES facture(fac_code)
);

CREATE TABLE piece (
    pc_code SERIAL PRIMARY KEY,
    pc_nom VARCHAR(50),
    rem_code INTEGER NOT NULL REFERENCES demande_remb(rem_code)
);

CREATE TABLE signature_demande (
    rem_code INTEGER REFERENCES demande_remb(rem_code),
    sign_code INTEGER REFERENCES signature(sign_code),
    sin_dem_code VARCHAR(50) UNIQUE,
    date_ DATE,
    PRIMARY KEY(rem_code, sign_code)
);

CREATE TABLE signature_engagement (
    sign_code INTEGER REFERENCES signature(sign_code),
    eng_code INTEGER REFERENCES engagement(eng_code),
    sign_date DATE,
    PRIMARY KEY(sign_code, eng_code)
);

-- =========================
-- UTILISATEURS SYSTÈME
-- =========================

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nom VARCHAR(150),
    prenom VARCHAR(150),
    role VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- TRAÇABILITÉ
-- =========================

CREATE TABLE modification (
    modification_id SERIAL PRIMARY KEY,
    table_modifier VARCHAR(50) NOT NULL,
    date_modification DATE NOT NULL,
    champs_modifier VARCHAR(50) NOT NULL,
    ancienne_valeur VARCHAR(50) NOT NULL,
    nouvelle_valeur VARCHAR(50)
);

-- =========================
-- INDEX POUR PERFORMANCE
-- =========================

CREATE INDEX idx_employe_matricule ON employe(emp_matricule);
CREATE INDEX idx_employe_im_armp ON employe(emp_im_armp);
CREATE INDEX idx_employe_nom_prenom ON employe(emp_nom, emp_prenom);
CREATE INDEX idx_affectation_emp ON affectation(emp_code);
CREATE INDEX idx_affectation_poste ON affectation(pst_code);
CREATE INDEX idx_affectation_dates ON affectation(affec_date_debut, affec_date_fin);
CREATE INDEX idx_conge_emp ON conge(emp_code);
CREATE INDEX idx_conge_dates ON conge(cng_debut, cng_fin);
CREATE INDEX idx_permission_emp ON permission(emp_code);
CREATE INDEX idx_demande_remb_emp ON demande_remb(emp_code);
CREATE INDEX idx_stage_dates ON stage(stg_date_debut, stg_date_fin);
CREATE INDEX idx_stagiaire_contact ON stagiaire(stgr_contact);

-- =========================
-- VUE : ÉTAT DES CONGÉS
-- =========================

CREATE OR REPLACE VIEW v_etat_conge AS
SELECT 
  e.emp_code,
  e.emp_nom,
  e.emp_prenom,
  e.emp_im_armp,
  d.dir_nom,
  d.dir_abreviation,
  p.pst_fonction,
  sc.sld_anne,
  sc.sld_initial,
  sc.sld_restant,
  dec.dec_num
FROM employe e
LEFT JOIN affectation a ON a.emp_code = e.emp_code
LEFT JOIN direction d ON d.dir_code = a.dir_code
LEFT JOIN poste p ON p.pst_code = a.pst_code
LEFT JOIN solde_conge sc ON sc.emp_code = e.emp_code
LEFT JOIN decision dec ON dec.dec_code = sc.dec_code
WHERE e.emp_disponibilite = true
ORDER BY e.emp_code, sc.sld_anne ASC;

-- =========================
-- COMMENTAIRES
-- =========================

COMMENT ON TABLE direction IS 'Directions de l''organisation ARMP';
COMMENT ON TABLE service IS 'Services rattachés aux directions';
COMMENT ON TABLE employe IS 'Table principale unifiée des employés';
COMMENT ON TABLE affectation IS 'Historique des affectations aux postes';
COMMENT ON TABLE conge IS 'Demandes de congés';
COMMENT ON TABLE permission IS 'Demandes de permissions';
COMMENT ON TABLE demande_remb IS 'Demandes de remboursement médical';
COMMENT ON TABLE stage IS 'Gestion des stages';
COMMENT ON TABLE competence IS 'Référentiel des compétences';

-- =====================================================
-- FIN DU SCHÉMA FUSIONNÉ
-- =====================================================
