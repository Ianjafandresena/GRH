export interface CentreSante {
    cen_code?: number;
    cen_nom: string;
    cen_adresse?: string;
    cen_ville?: string;
    cnv_code: number;
    // Joined fields
    cnv_taux_couver?: number;
    cnv_date_debut?: string;
    cnv_date_fin?: string;
}

export interface Convention {
    cnv_code?: number;
    cnv_taux_couver: number;
    cnv_date_debut: string;
    cnv_date_fin?: string;
}
