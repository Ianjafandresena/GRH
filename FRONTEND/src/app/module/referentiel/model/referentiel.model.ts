export interface Service {
    srvc_code: number;
    srvc_nom: string;
}

export interface Direction {
    dir_code: number;
    dir_nom: string;
    dir_abbreviation: string;
    srvc_code: number;
}
