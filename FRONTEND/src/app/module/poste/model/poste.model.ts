export interface CompetenceRequise {
  comp_code: number;
  pst_code: number;
  niveau_requis: number;
  comp_intitule: string;
  comp_domaine: string;
  comp_description: string;
}

export interface Poste {
  pst_code: number;
  pst_fonction: string;
  pst_mission?: string;
  srvc_code?: number;
  srvc_nom?: string;
  rhq_code?: number;
  rhq_rang?: string;
  rhq_niveau?: string;
  ctgr_code?: number;
  ctgr_statut?: string;
  idrec_code?: number;
  idrec_nom?: string;
  tsup_code?: number;
  tsup_tache?: string;
  dir_nom?: string;
  directions?: string[]; // @deprecated
  competences?: CompetenceRequise[];
  quota?: number;
  nb_occupe?: number;
  nb_vacant?: number;
  nb_encessation?: number;
}
