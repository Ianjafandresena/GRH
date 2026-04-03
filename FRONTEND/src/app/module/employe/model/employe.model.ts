export interface Anciennete {
    annees: number;
    mois: number;
    jours: number;
    total_jours: number;
    formatted: string;
}

export interface AnneeRetraite {
    annee: number | null;
    age_actuel: number | null;
    age_retraite: number;
    deja_retraite: boolean;
    formatted: string;
}

export interface Employe {
    emp_code?: number;
    emp_matricule?: string;
    emp_nom: string;
    emp_prenom: string;
    emp_titre: string;
    emp_sexe: boolean; // true = Homme, false = Femme
    emp_datenaissance: string; // Format: YYYY-MM-DD
    emp_im_armp: string;
    emp_cin?: string;
    emp_im_etat?: string;
    date_entree: string; // Format: YYYY-MM-DD
    date_sortie?: string;
    s_type_code?: string;
    e_type_code: string;
    emp_mail?: string;
    emp_contact?: string;
    pos_code?: number;
    pos_type?: string;
    stt_armp_code?: number;
    stt_armp_statut?: string;
    anciennete?: Anciennete;
    annee_retraite?: AnneeRetraite;
}

export interface TypeEntree {
    e_type_code: string;
    e_type_motif: string;
}

export interface AffectationActive {
    affec_code: number;
    affec_date_debut: string;
    affec_date_fin?: string;
    tcontrat_code?: number;
    tcontrat_nom?: string;
    affec_commentaire?: string;
    pst_code: number;
    pst_fonction?: string;
    pst_mission?: string;
    srvc_nom?: string;
    m_aff_code: number;
    m_aff_motif?: string;
}

export interface EmployeWithAffectation extends Employe {
    affectation_active?: AffectationActive;
    type_entree?: TypeEntree;
}

export interface AffectationInitiale {
    pst_code: number;
    m_aff_code: number;
    affec_date_debut: string;
    affec_date_fin?: string | null;
    tcontrat_code: number;
    affec_commentaire?: string;
}

export interface MotifAffectation {
    m_aff_code: number;
    m_aff_motif: string;
    m_aff_type: string;
}
