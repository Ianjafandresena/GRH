-- =====================================================
-- SCHÉMA FUSIONNÉ SI-GPRH + CARRIÈRE-STAGIAIRE
-- Base de données unifiée pour l'ARMP
-- PostgreSQL
-- =====================================================
-- Structure commune :
--   • employe, poste, direction, service, affectation → du projet CARRIÈRE-STAGIAIRE
--   • congés, permissions, remboursements, PEC, signatures → du projet SI-GPRH
--   • stages, compétences, documents → du projet CARRIÈRE-STAGIAIRE
-- =====================================================

-- =========================
-- EXTENSION REQUISE
-- =========================
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- =========================================================
-- PARTIE A : TABLES DE RÉFÉRENCE (CARRIÈRE-STAGIAIRE)
-- =========================================================

-- 1. Types d'entrée (carrière)
CREATE TABLE type_entree (
    e_type_code VARCHAR(50) PRIMARY KEY,
    e_type_motif VARCHAR(50)
);

-- 2. Types de sortie (carrière)
CREATE TABLE sortie_type (
    s_type_code VARCHAR(50) PRIMARY KEY,
    s_type_motif VARCHAR(50)
);

-- 3. Types de contrat (carrière)
CREATE TABLE type_contrat (
    tcontrat_code SERIAL PRIMARY KEY,
    tcontrat_nom VARCHAR(50)
);

-- 4. Statut ARMP (carrière)
CREATE TABLE statut_armp (
    stt_armp_code SERIAL PRIMARY KEY,
    stt_armp_statut VARCHAR(50)
);

-- 5. Rang hiérarchique (carrière)
CREATE TABLE rang_hierarchique (
    rhq_code SERIAL PRIMARY KEY,
    rhq_rang VARCHAR(50),
    rhq_niveau VARCHAR(10)
);

-- 6. Tâches supplémentaires (carrière)
CREATE TABLE tache_suppl (
    tsup_code SERIAL PRIMARY KEY,
    tsup_tache VARCHAR(100)
);

-- 7. Types de documents (carrière)
CREATE TABLE type_document (
    tdoc_code SERIAL PRIMARY KEY,
    tdoc_nom VARCHAR(50)
);

-- 8. Position administrative (carrière)
CREATE TABLE position_ (
    pos_code SERIAL PRIMARY KEY,
    pos_type VARCHAR(50)
);

-- 9. Motifs d'affectation (carrière)
CREATE TABLE motif_affectation (
    m_aff_code SERIAL PRIMARY KEY,
    m_aff_motif VARCHAR(50),
    m_aff_type VARCHAR(50)
);

-- 10. Établissements pour stages (carrière)
CREATE TABLE etablissement (
    etab_code SERIAL PRIMARY KEY,
    etab_nom VARCHAR(50),
    etab_adresse VARCHAR(50)
);

-- =========================================================
-- PARTIE B : TABLES DE RÉFÉRENCE (SI-GPRH)
-- =========================================================

-- 11. Régions de Madagascar (SI-GPRH)
CREATE TABLE region (
    reg_code SERIAL PRIMARY KEY,
    reg_nom VARCHAR(50)
);

-- 12. Types de congé (SI-GPRH)
CREATE TABLE type_conge (
    typ_code SERIAL PRIMARY KEY,
    typ_appelation VARCHAR(50) NOT NULL UNIQUE,
    typ_ref VARCHAR(50) NOT NULL UNIQUE
);

-- 13. Décisions administratives (SI-GPRH)
CREATE TABLE decision (
    dec_code SERIAL PRIMARY KEY,
    dec_num VARCHAR(50) UNIQUE
);

-- 14. Types de centre de santé (SI-GPRH)
CREATE TABLE type_centre (
    tp_cen_code SERIAL PRIMARY KEY,
    tp_cen VARCHAR(50)
);

-- 15. Objets remboursement (SI-GPRH)
CREATE TABLE objet_remboursement (
    obj_code SERIAL PRIMARY KEY,
    obj_article VARCHAR(50)
);

-- =========================================================
-- PARTIE C : STRUCTURE ORGANISATIONNELLE (CARRIÈRE-STAGIAIRE)
-- =========================================================

-- 16. Directions (CARRIÈRE-STAGIAIRE)
CREATE TABLE direction (
    dir_code SERIAL PRIMARY KEY,
    dir_abbreviation VARCHAR(50),
    dir_nom VARCHAR(100)
);

-- 17. Services rattachés aux directions (CARRIÈRE-STAGIAIRE)
CREATE TABLE service (
    srvc_code SERIAL PRIMARY KEY,
    srvc_nom VARCHAR(100),
    dir_code INTEGER REFERENCES direction(dir_code)
);

-- 18. Postes enrichis (CARRIÈRE-STAGIAIRE)
CREATE TABLE poste (
    pst_code SERIAL PRIMARY KEY,
    pst_fonction VARCHAR(255),
    pst_mission VARCHAR(255),
    tsup_code INTEGER REFERENCES tache_suppl(tsup_code),
    rhq_code INTEGER REFERENCES rang_hierarchique(rhq_code),
    srvc_code INTEGER REFERENCES service(srvc_code),
    dir_code INTEGER REFERENCES direction(dir_code)
);

-- 19. Occupation des postes / quotas (CARRIÈRE-STAGIAIRE)
CREATE TABLE occupation_poste (
    occpst_code SERIAL PRIMARY KEY,
    pst_code INTEGER UNIQUE REFERENCES poste(pst_code),
    quota INTEGER,
    nb_occupe INTEGER,
    nb_vacant INTEGER,
    nb_encessation INTEGER
);

-- =========================================================
-- PARTIE D : EMPLOYÉ UNIFIÉ (CARRIÈRE-STAGIAIRE)
-- =========================================================

-- 21. Table employé principale (CARRIÈRE-STAGIAIRE)
-- NOTE: le nom est "employe" (sans double 'e'), PK SERIAL
CREATE TABLE employe (
    emp_code SERIAL PRIMARY KEY,
    emp_matricule VARCHAR(50),
    emp_nom VARCHAR(50),
    emp_prenom VARCHAR(50),
    emp_titre VARCHAR(50),
    emp_sexe BOOLEAN,
    emp_datenaissance DATE,
    emp_im_armp VARCHAR(50) UNIQUE,
    emp_im_etat VARCHAR(50) UNIQUE,
    emp_mail VARCHAR(100) UNIQUE,
    emp_cin VARCHAR(100),
    emp_disponibilite BOOLEAN DEFAULT TRUE,  -- (SI-GPRH) disponibilité pour congés
    date_entree DATE,
    date_sortie DATE,
    s_type_code VARCHAR(50) REFERENCES sortie_type(s_type_code),
    e_type_code VARCHAR(50) REFERENCES type_entree(e_type_code)
);

-- 22. Position de l'employé (CARRIÈRE-STAGIAIRE)
CREATE TABLE pos_emp (
    emp_code INTEGER REFERENCES employe(emp_code),
    pos_code INTEGER REFERENCES position_(pos_code),
    date_ DATE,
    PRIMARY KEY (emp_code, pos_code)
);

-- 23. Contacts employé (CARRIÈRE-STAGIAIRE)
CREATE TABLE contact (
    id_contact SERIAL PRIMARY KEY,
    numero VARCHAR(50) UNIQUE,
    emp_code INTEGER NOT NULL REFERENCES employe(emp_code)
);

-- 24. Statut employé ARMP (CARRIÈRE-STAGIAIRE)
CREATE TABLE statut_emp (
    emp_code INTEGER REFERENCES employe(emp_code),
    stt_armp_code INTEGER REFERENCES statut_armp(stt_armp_code),
    date_ DATE,
    PRIMARY KEY (emp_code, stt_armp_code)
);

-- =========================================================
-- PARTIE E : AFFECTATIONS (CARRIÈRE-STAGIAIRE)
-- =========================================================

-- 25. Affectations des employés aux postes (CARRIÈRE-STAGIAIRE)
-- NOTE: PK SERIAL (pas composite comme l'ancien schema.sql)
CREATE TABLE affectation (
    affec_code SERIAL PRIMARY KEY,
    emp_code INTEGER NOT NULL REFERENCES employe(emp_code),
    pst_code INTEGER NOT NULL REFERENCES poste(pst_code),
    affec_date_debut DATE,
    affec_date_fin DATE,
    affec_commentaire VARCHAR(255),
    affec_etat VARCHAR(255),
    m_aff_code INTEGER REFERENCES motif_affectation(m_aff_code),
    tcontrat_code INTEGER REFERENCES type_contrat(tcontrat_code)
);

-- 26. Historique des sorties (CARRIÈRE-STAGIAIRE)
CREATE TABLE sortie (
    emp_code INTEGER REFERENCES employe(emp_code),
    s_type_code VARCHAR(50) REFERENCES sortie_type(s_type_code),
    commentaire VARCHAR(255),
    date_sortie DATE,
    PRIMARY KEY (emp_code, s_type_code, date_sortie)
);

-- =========================================================
-- PARTIE F : COMPÉTENCES (CARRIÈRE-STAGIAIRE)
-- =========================================================

-- 27. Référentiel de compétences
CREATE TABLE competence (
    comp_code SERIAL PRIMARY KEY,
    comp_intitule VARCHAR(50),
    comp_domaine VARCHAR(50),
    comp_description VARCHAR(255)
);

-- 28. Compétences acquises par employé
CREATE TABLE comp_employe (
    emp_code INTEGER REFERENCES employe(emp_code),
    comp_code INTEGER REFERENCES competence(comp_code),
    niveau_acquis INTEGER,
    PRIMARY KEY (emp_code, comp_code)
);

-- 29. Compétences requises par poste
CREATE TABLE comp_poste (
    pst_code INTEGER REFERENCES poste(pst_code),
    comp_code INTEGER REFERENCES competence(comp_code),
    niveau_requis INTEGER,
    PRIMARY KEY (pst_code, comp_code)
);

-- =========================================================
-- PARTIE G : DOCUMENTS EMPLOYÉS (CARRIÈRE-STAGIAIRE)
-- =========================================================

-- 30. Documents liés aux employés
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

-- =========================================================
-- PARTIE H : STAGES & STAGIAIRES (CARRIÈRE-STAGIAIRE)
-- =========================================================

-- 31. Stagiaires
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

-- 32. Assiduité
CREATE TABLE assiduite (
    asdt_code SERIAL PRIMARY KEY,
    asdt_remarque VARCHAR(50),
    asdt_nb_abscence INTEGER,
    asdt_nb_retard INTEGER
);

-- 33. Évaluation de stage
CREATE TABLE eval_stage (
    evstg_code SERIAL PRIMARY KEY,
    evstg_lieu VARCHAR(50),
    evstg_note INTEGER,
    evstg_aptitude VARCHAR(50),
    evstg_date_eval VARCHAR(50),
    asdt_code INTEGER REFERENCES assiduite(asdt_code)
);

-- 34. Stages
CREATE TABLE stage (
    stg_code SERIAL PRIMARY KEY,
    stg_duree INTEGER,
    stg_date_debut DATE,
    stg_date_fin DATE,
    stg_theme VARCHAR(255),
    evstg_code INTEGER REFERENCES eval_stage(evstg_code),
    stgr_code INTEGER REFERENCES stagiaire(stgr_code),
    etab_code INTEGER REFERENCES etablissement(etab_code)
);

-- 35. Documents de stage
CREATE TABLE doc_stage (
    tdoc_code INTEGER REFERENCES type_document(tdoc_code),
    stg_code INTEGER REFERENCES stage(stg_code),
    doc_stg_code SERIAL UNIQUE,
    doc_stg_date DATE,
    tdoc_matricule VARCHAR(50),
    doc_stage_statut VARCHAR(50) DEFAULT 'en attente',
    PRIMARY KEY (tdoc_code, stg_code)
);

-- 36. Lien stage ↔ carrière (encadrement)
CREATE TABLE stage_carriere (
    emp_code INTEGER REFERENCES employe(emp_code),
    pst_code INTEGER REFERENCES poste(pst_code),
    stg_code INTEGER REFERENCES stage(stg_code),
    stg_carriere_code SERIAL UNIQUE,
    PRIMARY KEY (emp_code, pst_code, stg_code)
);

-- =========================================================
-- PARTIE I : FAMILLE EMPLOYÉ (SI-GPRH)
-- =========================================================

-- 37. Statuts Conjoint (Référentiel)
CREATE TABLE conj_status (
    cjs_id SERIAL PRIMARY KEY,
    cjs_libelle VARCHAR(50) NOT NULL UNIQUE
);

-- 37b. Conjoints
CREATE TABLE conjointe (
    conj_code SERIAL PRIMARY KEY,
    conj_nom VARCHAR(50),
    conj_prenom VARCHAR(50),
    conj_sexe BOOLEAN,
    cjs_id INTEGER REFERENCES conj_status(cjs_id),
    conj_date_statut DATE DEFAULT CURRENT_DATE
);

-- 38. Association Employé-Conjoint
CREATE TABLE emp_conj (
    emp_code INTEGER REFERENCES employe(emp_code),
    conj_code INTEGER REFERENCES conjointe(conj_code),
    PRIMARY KEY(emp_code, conj_code)
);

-- 39. Enfants
CREATE TABLE enfant (
    enf_code SERIAL PRIMARY KEY,
    enf_nom VARCHAR(50),
    enf_num VARCHAR(50),
    date_naissance DATE,
    emp_code INTEGER NOT NULL REFERENCES employe(emp_code)
);

-- =========================================================
-- PARTIE J : CONGÉS & PERMISSIONS (SI-GPRH)
-- =========================================================

-- 40. Congés
CREATE TABLE conge (
    cng_code SERIAL PRIMARY KEY,
    cng_nb_jour NUMERIC(5,2) NOT NULL,
    cng_debut DATE NOT NULL,
    cng_fin DATE NOT NULL,
    cng_demande TIMESTAMP NOT NULL,
    cng_status BOOLEAN,
    typ_code INTEGER NOT NULL REFERENCES type_conge(typ_code),
    emp_code INTEGER NOT NULL REFERENCES employe(emp_code),
    reg_code INTEGER NOT NULL REFERENCES region(reg_code)
);

-- 41. Interruptions de congé
CREATE TABLE interruption (
    interup_code SERIAL PRIMARY KEY,
    interup_date DATE,
    interup_motif VARCHAR(50),
    interup_restant INTEGER,
    cng_code INTEGER REFERENCES conge(cng_code)
);

-- 42. Soldes de congé (FIFO)
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

-- 43. Débit solde congé
CREATE TABLE debit_solde_cng (
    emp_code INTEGER REFERENCES employe(emp_code),
    sld_code INTEGER REFERENCES solde_conge(sld_code),
    cng_code INTEGER REFERENCES conge(cng_code),
    deb_code SERIAL UNIQUE,
    deb_jr NUMERIC(15,2),
    deb_date DATE,
    PRIMARY KEY(emp_code, sld_code, cng_code)
);

-- 44. Intérimaires congé
CREATE TABLE interim_conge (
    emp_code INTEGER REFERENCES employe(emp_code),
    cng_code INTEGER REFERENCES conge(cng_code),
    int_code SERIAL UNIQUE,
    int_debut DATE,
    int_fin DATE,
    PRIMARY KEY(emp_code, cng_code)
);

-- 45. Permissions
CREATE TABLE permission (
    prm_code SERIAL PRIMARY KEY,
    prm_duree NUMERIC(15,2),
    prm_date DATE,
    prm_debut TIMESTAMP,
    prm_fin TIMESTAMP,
    prm_status BOOLEAN DEFAULT FALSE,
    emp_code INTEGER NOT NULL REFERENCES employe(emp_code)
);

-- 46. Soldes de permission
CREATE TABLE solde_permission (
    sld_prm_code SERIAL PRIMARY KEY,
    sld_prm_dispo NUMERIC(15,2),
    sld_prm_anne INTEGER,
    emp_code INTEGER NOT NULL REFERENCES employe(emp_code)
);

-- 46b. Débit solde permission
CREATE TABLE debit_solde_prm (
    emp_code INTEGER REFERENCES employe(emp_code),
    sld_prm_code INTEGER REFERENCES solde_permission(sld_prm_code),
    prm_code INTEGER REFERENCES permission(prm_code),
    deb_prm_code SERIAL UNIQUE,
    deb_jr NUMERIC(15,2),
    deb_date TIMESTAMP,
    PRIMARY KEY(emp_code, sld_prm_code, prm_code)
);

-- 47. Intérimaires permission
CREATE TABLE interim_permission (
    emp_code INTEGER REFERENCES employe(emp_code),
    prm_code INTEGER REFERENCES permission(prm_code),
    int_prm_code SERIAL UNIQUE,
    int_prm_debut TIMESTAMP,
    int_prm_fin TIMESTAMP,
    PRIMARY KEY(emp_code, prm_code)
);

-- =========================================================
-- PARTIE K : SIGNATURES & VALIDATIONS (SI-GPRH)
-- =========================================================

-- 48. Signatures (rôles signataires)
CREATE TABLE signature (
    sign_code SERIAL PRIMARY KEY,
    sign_libele VARCHAR(50),
    sign_observation VARCHAR(50),
    emp_code INTEGER NOT NULL UNIQUE REFERENCES employe(emp_code)
);

-- 49. Validation congé
CREATE TABLE validation_cng (
    cng_code INTEGER REFERENCES conge(cng_code),
    sign_code INTEGER REFERENCES signature(sign_code),
    val_code SERIAL UNIQUE,
    val_date DATE,
    val_status BOOLEAN,
    val_observation VARCHAR(500),
    val_token VARCHAR(255) UNIQUE,
    val_token_expires TIMESTAMP,
    val_token_used BOOLEAN DEFAULT FALSE,
    val_by_emp INTEGER REFERENCES employe(emp_code),
    PRIMARY KEY(cng_code, sign_code)
);

-- 50. Validation permission
CREATE TABLE validation_prm (
    prm_code INTEGER REFERENCES permission(prm_code),
    sign_code INTEGER REFERENCES signature(sign_code),
    val_code SERIAL UNIQUE,
    val_date DATE,
    val_observation VARCHAR(50),
    PRIMARY KEY(prm_code, sign_code)
);

-- =========================================================
-- PARTIE L : REMBOURSEMENTS MÉDICAUX (SI-GPRH)
-- =========================================================

-- 51. Centres de santé
CREATE TABLE centre_sante (
    cen_code SERIAL PRIMARY KEY,
    cen_nom VARCHAR(150),
    cen_adresse VARCHAR(150),
    tp_cen_code INTEGER NOT NULL REFERENCES type_centre(tp_cen_code)
);

-- 52. Factures
CREATE TABLE facture (
    fac_code SERIAL PRIMARY KEY,
    fac_num VARCHAR(50),
    fac_date DATE
);

-- 53. État remboursement
CREATE TABLE etat_remb (
    eta_code SERIAL PRIMARY KEY,
    eta_date DATE,
    eta_total NUMERIC(15,2),
    etat_num VARCHAR(150) NOT NULL UNIQUE,
    eta_libelle VARCHAR(50) DEFAULT 'EN_COURS',
    emp_code INTEGER NOT NULL REFERENCES employe(emp_code)
);

-- 54. Engagement
CREATE TABLE engagement (
    eng_code SERIAL PRIMARY KEY,
    eng_date VARCHAR(50),
    eta_code INTEGER NOT NULL REFERENCES etat_remb(eta_code)
);

-- 55. Prise en charge
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

-- 56. Demande remboursement
CREATE TABLE demande_remb (
    rem_code SERIAL PRIMARY KEY,
    rem_date DATE,
    rem_montant NUMERIC(15,2),
    rem_montant_lettre VARCHAR(50),
    rem_num VARCHAR(50),
    rem_status BOOLEAN,
    rem_is_centre BOOLEAN DEFAULT FALSE,
    eta_code INTEGER REFERENCES etat_remb(eta_code),
    pec_code INTEGER REFERENCES pris_en_charge(pec_code),
    emp_code INTEGER NOT NULL REFERENCES employe(emp_code),
    cen_code INTEGER REFERENCES centre_sante(cen_code),
    obj_code INTEGER REFERENCES objet_remboursement(obj_code),
    fac_code INTEGER REFERENCES facture(fac_code)
);

-- 57. Pièces justificatives
CREATE TABLE piece (
    pc_code SERIAL PRIMARY KEY,
    pc_nom VARCHAR(50),
    rem_code INTEGER NOT NULL REFERENCES demande_remb(rem_code)
);

-- 58. Signature demande remboursement
CREATE TABLE signature_demande (
    rem_code INTEGER REFERENCES demande_remb(rem_code),
    sign_code INTEGER REFERENCES signature(sign_code),
    sin_dem_code VARCHAR(50) UNIQUE,
    date_ DATE,
    PRIMARY KEY(rem_code, sign_code)
);

-- 59. Signature engagement
CREATE TABLE signature_engagement (
    sign_code INTEGER REFERENCES signature(sign_code),
    eng_code INTEGER REFERENCES engagement(eng_code),
    sign_date DATE,
    PRIMARY KEY(sign_code, eng_code)
);

-- =========================================================
-- PARTIE M : TRAÇABILITÉ & UTILISATEURS
-- =========================================================

-- 60. Traçabilité des modifications (SI-GPRH)
CREATE TABLE modification (
    modification_id SERIAL PRIMARY KEY,
    table_modifier VARCHAR(50) NOT NULL,
    date_modification DATE NOT NULL,
    champs_modifier VARCHAR(50) NOT NULL,
    ancienne_valeur VARCHAR(50) NOT NULL,
    nouvelle_valeur VARCHAR(50)
);

-- 61. Utilisateurs système (partagé)
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

-- =========================================================
-- INDEX POUR PERFORMANCE
-- =========================================================

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
CREATE INDEX idx_val_token ON validation_cng(val_token);

-- =========================================================
-- VUE : ÉTAT DES CONGÉS
-- =========================================================

CREATE OR REPLACE VIEW v_etat_conge AS
SELECT
  e.emp_code,
  e.emp_nom,
  e.emp_prenom,
  e.emp_im_armp,
  s.srvc_nom,
  d.dir_nom,
  d.dir_abbreviation,
  p.pst_fonction,
  sc.sld_anne,
  sc.sld_initial,
  sc.sld_restant,
  dec.dec_num
FROM employe e
LEFT JOIN affectation a ON a.emp_code = e.emp_code AND a.affec_etat = 'active'
LEFT JOIN poste p ON p.pst_code = a.pst_code
LEFT JOIN service s ON s.srvc_code = p.srvc_code
LEFT JOIN direction d ON d.dir_code = p.dir_code
LEFT JOIN solde_conge sc ON sc.emp_code = e.emp_code
LEFT JOIN decision dec ON dec.dec_code = sc.dec_code
WHERE e.emp_disponibilite = true
ORDER BY e.emp_code, sc.sld_anne ASC;

-- =========================================================
-- COMMENTAIRES
-- =========================================================

COMMENT ON TABLE direction IS 'Directions de l''organisation ARMP (source: carrière-stagiaire)';
COMMENT ON TABLE service IS 'Services rattachés aux directions (source: carrière-stagiaire)';
COMMENT ON TABLE employe IS 'Table principale unifiée des employés (source: carrière-stagiaire, remplace l''ancienne "employee")';
COMMENT ON TABLE poste IS 'Postes enrichis avec mission, rang et rattachement (source: carrière-stagiaire)';
COMMENT ON TABLE affectation IS 'Historique des affectations avec état et motif (source: carrière-stagiaire)';
COMMENT ON TABLE conge IS 'Demandes de congés (source: SI-GPRH)';
COMMENT ON TABLE permission IS 'Demandes de permissions (source: SI-GPRH)';
COMMENT ON TABLE demande_remb IS 'Demandes de remboursement médical (source: SI-GPRH)';
COMMENT ON TABLE stage IS 'Gestion des stages (source: carrière-stagiaire)';
COMMENT ON TABLE competence IS 'Référentiel des compétences (source: carrière-stagiaire)';
COMMENT ON COLUMN demande_remb.rem_is_centre IS 'FALSE = Demande Agent, TRUE = Demande Centre de Santé';

-- =====================================================
-- FIN DU SCHÉMA FUSIONNÉ — 61 tables, 1 vue, index
-- =====================================================
