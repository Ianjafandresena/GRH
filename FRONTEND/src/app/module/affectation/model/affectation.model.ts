export interface Affectation {
    affec_code?: number;
    affec_date_debut: string;
    affec_date_fin?: string;
    affec_commentaire?: string;
    tcontrat_code: number;
    affec_etat?: 'active' | 'cloture';
    m_aff_code: number;
    emp_code: number;
    pst_code: number;

    // Jointures pour affichage
    emp_nom?: string;
    emp_prenom?: string;
    pst_fonction?: string;
    m_aff_motif?: string;
    tcontrat_nom?: string;
}

export interface MotifAffectation {
    m_aff_code: number;
    m_aff_motif: string;
    m_aff_type: string;
}
