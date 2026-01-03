export interface DemandeRemb {
    rem_code?: number;
    rem_num?: string;  // N° auto-généré
    num_demande?: string;  // Ancien champ
    rem_objet?: string;
    rem_date?: string;
    rem_montant: number;
    rem_montant_lettre?: string;
    rem_is_centre?: boolean;  // false = Agent, true = Centre
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
    rem_status?: boolean | 'f' | 't';  // PostgreSQL returns 't'/'f' strings

    // Joined fields - Employee
    nom_emp?: string;
    prenom_emp?: string;
    matricule?: string;
    direction?: string;
    fonction?: string;

    // Joined fields - État
    eta_libelle?: string;
    etat_num?: string;

    // Joined fields - PEC (pour afficher bénéficiaire)
    pec_num?: string;
    beneficiaire_nom?: string;
    beneficiaire_lien?: string;

    // Joined fields - Centre
    cen_nom?: string;
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
