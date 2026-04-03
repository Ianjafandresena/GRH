export interface Conge {
  cng_code?: number;
  cng_nb_jour: number;
  cng_debut: string;
  cng_fin: string;
  cng_demande?: string;
  emp_code: number;
  typ_code: number;
  reg_code: number;   // <-- AJOUT OBLIGATOIRE !
}

export interface TypeConge {
  typ_code: number;
  typ_appelation: string;
}

