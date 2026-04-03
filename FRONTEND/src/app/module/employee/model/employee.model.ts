export interface Employee {
  emp_code: number;
  emp_nom: string;
  emp_prenom: string;
  emp_im_armp: string;
  emp_sexe?: boolean;
  date_entree?: string;
  emp_mail?: string;
  emp_disponibilite?: boolean;
  sign_code?: number;
  pst_fonction?: string;
  dir_nom?: string;
  dir_abbreviation?: string;
  affec_date_debut?: string;
  is_available?: boolean;
  absence_type?: 'conge' | 'permission';
  absence_end?: string;
}
