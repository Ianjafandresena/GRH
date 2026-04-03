import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';

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
    rhq_niveau?: string; // Replaces nivhq_niveau
    ctgr_code?: number;
    ctgr_statut?: string;
    idrec_code?: number;
    idrec_nom?: string;
    tsup_code?: number;
    tsup_tache?: string;
    dir_nom?: string; // Nouvelle propriété
    directions?: string[]; // @deprecated
    competences?: CompetenceRequise[];
    nb_occupe?: number;
    nb_vacant?: number;
}

interface ApiResponse<T> {
    status: string;
    count?: number;
    data: T;
}

@Injectable({
    providedIn: 'root'
})
export class PosteService {
    private apiUrl = `${environment.apiUrl}/postes`;

    constructor(private http: HttpClient) { }

    /**
     * Liste tous les postes (avec filtres optionnels)
     */
    list(filters?: any): Observable<Poste[]> {
        return this.http.get<ApiResponse<Poste[]>>(
            `${this.apiUrl}`,
            { params: filters || {} }
        ).pipe(
            map(response => response.data || [])
        );
    }

    /**
     * Récupère un poste par son ID avec ses compétences requises
     */
    get(id: number): Observable<Poste> {
        return this.http.get<ApiResponse<Poste>>(
            `${this.apiUrl}/${id}`
        ).pipe(
            map(response => response.data)
        );
    }

    addCompetence(pstCode: number, compCode: number, niveauRequis: number): Observable<any> {
        return this.http.post(
            `${this.apiUrl}/${pstCode}/competences`,
            { comp_code: compCode, niveau_requis: niveauRequis }
        );
    }

    removeCompetence(pstCode: number, compCode: number): Observable<any> {
        return this.http.delete(
            `${this.apiUrl}/${pstCode}/competences/${compCode}`
        );
    }

    /**
     * @deprecated Utiliser list() à la place
     */
    getPostes(filters?: any): Observable<Poste[]> {
        return this.http.get<ApiResponse<Poste[]>>(
            `${this.apiUrl}/list`,
            { params: filters || {} }
        ).pipe(
            map(response => response.data || [])
        );
    }

    getPostesByService(srvcCode: number): Observable<Poste[]> {
        return this.http.get<ApiResponse<Poste[]>>(
            `${this.apiUrl}/by-service/${srvcCode}`
        ).pipe(
            map(response => response.data)
        );
    }

    // Méthodes pour récupérer les valeurs des filtres dynamiquement
    getFonctions(): Observable<string[]> {
        return this.http.get<ApiResponse<string[]>>(`${this.apiUrl}/fonctions`).pipe(
            map(response => response.data)
        );
    }

    getDirections(): Observable<string[]> {
        return this.http.get<ApiResponse<any[]>>(`${environment.apiUrl}/directions/list`).pipe(
            map(response => response.data.map((d: any) => d.dir_nom))
        );
    }

    getServices(): Observable<string[]> {
        return this.http.get<ApiResponse<any[]>>(`${environment.apiUrl}/services/list`).pipe(
            map(response => response.data.map((s: any) => s.srvc_nom))
        );
    }


    getRangs(): Observable<string[]> {
        return this.http.get<ApiResponse<any[]>>(`${environment.apiUrl}/rangs/list`).pipe(
            map(response => response.data.map((r: any) => r.rhq_rang))
        );
    }

    /**
     * Mettre à jour le quota d'un poste
     */
    updateQuota(pstCode: number, nouveauQuota: number): Observable<any> {
        return this.http.put(`${this.apiUrl}/${pstCode}/quota`, { nouveau_quota: nouveauQuota });
    }

    getStats(): Observable<PosteStats> {
        return this.http.get<ApiResponse<PosteStats>>(`${this.apiUrl}/stats`).pipe(
            map(response => response.data)
        );
    }
}

export interface PosteStats {
    total_postes: number;
    total_quota: number;
    total_occupe: number;
    total_vacant: number;
    total_cessation: number;
}
