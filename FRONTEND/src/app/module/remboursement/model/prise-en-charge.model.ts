export interface PrisEnCharge {
    pec_code?: number;
    pec_num?: string;
    cen_code?: number;
    enf_code?: number;
    conj_code?: number;
    emp_code: number;
    emp_code_1?: number;
    // Joined fields
    nom_emp?: string;
    prenom_emp?: string;
    matricule?: string;
    cen_nom?: string;
    conj_nom?: string;
    enf_nom?: string;
    beneficiaire_type?: string;
    beneficiaire_nom?: string;
    pec_date?: string;
    pec_statut?: string;
}

export interface Conjointe {
    conj_code?: number;
    conj_nom: string;
    conj_sexe?: boolean;
}

export interface Enfant {
    enf_code?: number;
    enf_nom: string;
    enf_num?: string;
    date_naissance?: string;
}
