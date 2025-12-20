export interface Employee {
  emp_code: number;
  emp_nom: string;
  emp_prenom: string;
  emp_imarmp: string;
  emp_sexe?: boolean;
  emp_date_embauche?: string;
  emp_mail?: string;
  emp_disponibilite?: boolean;
  sign_code?: number;
  pst_fonction?: string;
  dir_nom?: string;
  dir_abreviation?: string;
  affec_date_debut?: string;
}
