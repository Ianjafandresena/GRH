export interface DemandeRemb {
    rem_code?: number;
    num_demande?: string;
    rem_objet?: string;
    rem_date?: string;
    rem_montant: number;
    rem_montant_lettre?: string;
    nom_malade: string;
    lien_malade: string;
    has_ordonnance?: boolean;
    has_facture?: boolean;
    has_prise_en_charge?: boolean;
    pec_reference?: string;
    date_consultation?: string;
    montant_valide?: number;
    motif_rejet?: string;
    num_engagement?: string;
    date_engagement?: string;
    date_paiement?: string;
    emp_code: number;
    pec_code?: number;
    eta_code?: number;
    // Joined fields
    nom_emp?: string;
    prenom_emp?: string;
    matricule?: string;
    direction?: string;
    fonction?: string;
    eta_libelle?: string;
}

export interface Piece {
    pc_code?: number;
    pc_piece: string;
    rem_code: number;
}

export interface EtatRemb {
    eta_code: number;
    eta_libelle: string;
}

export interface SignatureDemande {
    rem_code: number;
    sign_code: number;
    sin_dem_code: string;
    date_: string;
    sign_libele?: string;
}
