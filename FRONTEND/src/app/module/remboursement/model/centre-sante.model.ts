export interface CentreSante {
    cen_code?: number;
    cen_nom: string;
    cen_adresse?: string;
    tp_cen_code: number;
    // Joined fields
    tp_cen?: string;
}

export interface TypeCentre {
    tp_cen_code?: number;
    tp_cen: string;
}
