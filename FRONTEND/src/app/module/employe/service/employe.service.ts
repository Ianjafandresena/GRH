import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map, catchError } from 'rxjs/operators';
import { Employe, TypeEntree, EmployeWithAffectation } from '../model/employe.model';
import { environment } from '../../../../environments/environment';

interface ApiResponse<T> {
    status: string;
    count?: number;
    data: T;
}

export interface ParcoursAffectation {
    type?: 'affectation' | 'sortie';
    affec_code?: number;
    affec_date_debut?: string;
    affec_date_fin?: string | null;
    affec_type_contrat?: string | null;
    affec_commentaire?: string | null;
    m_aff_code?: number;
    m_aff_motif?: string | null;
    pst_code?: number;
    pst_fonction?: string | null;
    pst_mission?: string | null;
    srvc_nom?: string | null;
    directions?: string[];
    // Pour les sorties
    date_sortie?: string;
    s_type_code?: string;
    s_type_motif?: string | null;
    commentaire?: string | null;
}

export interface EmployeFilters {
    pst_code?: number;
    srvc_code?: number;
    statut?: 'actif' | 'inactif';
    with_affectation?: boolean;
    pos_code?: number;
    q?: string;
}

export interface Position {
    pos_code: number;
    pos_type: string;
}

export interface CompetenceEmploye {
    emp_code: number;
    comp_code: number;
    niveau_acquis: number;
    comp_intitule?: string;
    comp_domaine?: string;
    comp_description?: string;
}

export interface EmployeStats {
    total: number;
    en_service: number;
    en_cessation: number;
    sortie: number;
}

@Injectable({ providedIn: 'root' })
export class EmployeService {
    private apiUrl = `${environment.apiUrl}/employes`;

    constructor(private http: HttpClient) { }

    /**
     * Liste tous les employés avec filtres optionnels
     */
    list(filters?: EmployeFilters): Observable<EmployeWithAffectation[]> {
        let params = new HttpParams();
        if (filters) {
            if (filters.pst_code) params = params.set('pst_code', filters.pst_code.toString());
            if (filters.srvc_code) params = params.set('srvc_code', filters.srvc_code.toString());
            if (filters.statut) params = params.set('statut', filters.statut);
            if (filters.with_affectation) params = params.set('with_affectation', '1');
            if (filters.pos_code) params = params.set('pos_code', filters.pos_code.toString());
            if (filters.q) params = params.set('q', filters.q);
        }

        return this.http.get<ApiResponse<EmployeWithAffectation[]>>(
            this.apiUrl,
            { params }
        ).pipe(
            map(response => response.data || [])
        );
    }

    /**
     * Récupère un employé par son ID avec son affectation active
     */
    get(id: number): Observable<EmployeWithAffectation> {
        return this.http.get<ApiResponse<EmployeWithAffectation>>(
            `${this.apiUrl}/${id}`
        ).pipe(
            map(response => response.data)
        );
    }

    getParcours(id: number): Observable<ParcoursAffectation[]> {
        return this.http.get<ApiResponse<ParcoursAffectation[]>>(
            `${this.apiUrl}/${id}/parcours`
        ).pipe(
            map(response => response.data || [])
        );
    }

    getPositions(): Observable<Position[]> {
        return this.http.get<ApiResponse<Position[]>>(
            `${environment.apiUrl}/positions/list`
        ).pipe(
            map(response => response.data || [])
        );
    }

    /**
     * Crée un employé avec affectation initiale optionnelle
     */
    create(employe: Employe, affectationInitiale?: any): Observable<Employe> {
        const payload: any = { ...employe };
        if (affectationInitiale) {
            payload.affectation_initiale = affectationInitiale;
        }

        return this.http.post<ApiResponse<Employe>>(
            this.apiUrl,
            payload
        ).pipe(
            map(response => response.data)
        );
    }

    /**
     * Met à jour un employé
     */
    update(id: number, employe: Employe): Observable<Employe> {
        return this.http.put<ApiResponse<Employe>>(
            `${this.apiUrl}/${id}`,
            employe
        ).pipe(
            map(response => response.data)
        );
    }

    /**
     * Supprime un employé
     */
    delete(id: number): Observable<any> {
        return this.http.delete(`${this.apiUrl}/${id}`);
    }

    /**
     * Récupérer les types d'entrée pour le select
     */
    getTypesEntree(): Observable<TypeEntree[]> {
        return this.http.get<ApiResponse<TypeEntree[]>>(
            `${environment.apiUrl}/types-entree/list`
        ).pipe(
            map(response => response.data || [])
        );
    }

    // Méthodes de compatibilité (deprecated)
    createEmploye(employe: Employe): Observable<Employe> {
        return this.create(employe);
    }

    getEmployes(filters?: any): Observable<Employe[]> {
        return this.list(filters);
    }

    getEmploye(id: number): Observable<Employe> {
        return this.get(id);
    }

    /**
     * Gestion des compétences de l'employé
     */
    getCompetences(empCode: number): Observable<CompetenceEmploye[]> {
        return this.http.get<ApiResponse<CompetenceEmploye[]>>(
            `${this.apiUrl}/${empCode}/competences`
        ).pipe(
            map(response => response.data || [])
        );
    }

    addCompetence(empCode: number, compCode: number, niveauAcquis: number): Observable<any> {
        return this.http.post(
            `${this.apiUrl}/${empCode}/competences`,
            { comp_code: compCode, niveau_acquis: niveauAcquis }
        );
    }

    removeCompetence(empCode: number, compCode: number): Observable<any> {
        return this.http.delete(
            `${this.apiUrl}/${empCode}/competences/${compCode}`
        );
    }

    /**
     * Finir la carrière d'un employé (mettre date de sortie et type de sortie)
     */
    finirCarriere(empCode: number, dateSortie: string, sTypeCode: string, commentaire?: string): Observable<Employe> {
        return this.http.put<ApiResponse<Employe>>(
            `${this.apiUrl}/${empCode}/finir-carriere`,
            {
                date_sortie: dateSortie,
                s_type_code: sTypeCode,
                commentaire: commentaire || null
            }
        ).pipe(
            map(response => response.data)
        );
    }

    /**
     * Réintégrer un employé (nouvelle période d'activité)
     */
    reintegration(empCode: number, data: {
        pst_code: number,
        date_reintegration: string,
        tcontrat_code: number,
        m_aff_code: number,
        commentaire?: string
    }): Observable<any> {
        return this.http.post<ApiResponse<any>>(
            `${this.apiUrl}/${empCode}/reintegration`,
            data
        );
    }

    /**
     * Récupérer les types de sortie
     */
    getTypesSortie(): Observable<SortieType[]> {
        return this.http.get<ApiResponse<SortieType[]>>(
            `${environment.apiUrl}/sorties-type/list`
        ).pipe(
            map(response => response.data || [])
        );
    }
    /**
     * Récupère la liste des employés pouvant être encadreurs (en service)
     */
    getEncadreurs(): Observable<Employe[]> {
        return this.http.get<ApiResponse<Employe[]>>(
            `${this.apiUrl}/encadreurs`
        ).pipe(
            map(response => response.data || [])
        );
    }

    /**
     * Exporte les employés en format XLSX
     */
    exportXlsx(filters?: any): Observable<Blob> {
        let params = new HttpParams();
        if (filters) {
            Object.keys(filters).forEach(key => {
                if (filters[key] !== null && filters[key] !== undefined && filters[key] !== '') {
                    params = params.set(key, filters[key].toString());
                }
            });
        }

        return this.http.get(`${this.apiUrl}/export/xlsx`, {
            params,
            responseType: 'blob'
        });
    }

    /**
     * Récupère les compteurs de la liste des employés
     */
    getStats(): Observable<EmployeStats> {
        return this.http.get<ApiResponse<EmployeStats>>(`${this.apiUrl}/stats`).pipe(
            map(response => response.data)
        );
    }
}

export interface SortieType {
    s_type_code: string;
    s_type_motif: string;
    commentaire?: string;
}
