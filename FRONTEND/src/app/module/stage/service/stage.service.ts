import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';

export interface Stage {
    stg_code?: number;
    stg_duree?: number;
    stg_date_debut: string;
    stg_date_fin?: string;
    stg_theme?: string;
    evstg_code?: number;
    stgr_code: number;
    etab_code?: number;

    // Champs issus des JOINs (liste)
    stgr_nom?: string;
    stgr_prenom?: string;
    stgr_nom_prenom?: string;
    etab_nom?: string;
    encadreur_emp_code?: number;
    encadreur_nom?: string;
    encadreur_prenom?: string;
}

@Injectable({ providedIn: 'root' })
export class StageService {
    private baseUrl = `${environment.apiUrl}/stages`;

    constructor(private http: HttpClient) { }

    getAll(filters?: Record<string, any>): Observable<Stage[]> {
        let params = new HttpParams();
        if (filters) {
            Object.keys(filters).forEach((key) => {
                const value = filters[key];
                if (value !== null && value !== undefined && value !== '') {
                    params = params.set(key, String(value));
                }
            });
        }

        return this.http.get<Stage[]>(this.baseUrl, { params });
    }

    getById(id: number): Observable<Stage> {
        return this.http.get<Stage>(`${this.baseUrl}/${id}`);
    }

    create(stage: Stage): Observable<any> {
        return this.http.post(this.baseUrl, stage);
    }

    update(id: number, stage: Stage): Observable<any> {
        return this.http.put(`${this.baseUrl}/${id}`, stage);
    }

    getEvaluation(stgCode: number): Observable<any> {
        return this.http.get(`${this.baseUrl}/${stgCode}/eval`);
    }

    assignCarriere(stgCode: number, payload: { emp_code: number }): Observable<any> {
        return this.http.post(`${this.baseUrl}/${stgCode}/carriere`, payload);
    }

    telechargerConvention(id: number): Observable<any> {
        return this.http.get(`${this.baseUrl}/${id}/convention`);
    }

    telechargerDemandeAttestation(id: number): Observable<any> {
        return this.http.get(`${this.baseUrl}/${id}/demande-attestation`);
    }

    getStats(): Observable<StageStats> {
        return this.http.get<{ status: string; data: StageStats }>(`${this.baseUrl}/stats`).pipe(
            map(res => res.data)
        );
    }
}

export interface StageStats {
    total: number;
    en_cours: number;
    termines: number;
    a_venir: number;
}

