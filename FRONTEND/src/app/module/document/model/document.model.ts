export interface DocumentDemande {
    emp_code: number;
    tdoc_code: number;
    usage?: 'Bancaire' | 'Administrative';
    commentaire?: string;
    date_demande?: string;
    statut?: 'en_attente' | 'traite' | 'refuse';
}

