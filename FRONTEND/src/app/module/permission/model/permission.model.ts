export interface Permission {
  per_code?: number;
  emp_code: number;
  reg_code: number;
  per_debut: string; // ISO datetime
  per_fin: string;   // ISO datetime
  per_tranche?: 'matin' | 'apres_midi' | null;
  per_nb_jour?: number | null;
  per_nb_heures?: number | null;
}
