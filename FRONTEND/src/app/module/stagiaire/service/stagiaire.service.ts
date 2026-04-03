import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';

export interface Stagiaire {
    stgr_code?: number;
    stgr_nom: string;
    stgr_prenom: string;
    stgr_nom_prenom?: string;
    stgr_contact: string;
    stgr_filiere?: string;
    stgr_niveau?: string;
    stgr_sexe?: boolean;
    stgr_adresse?: string;
}

@Injectable({ providedIn: 'root' })
export class StagiaireService {
    private baseUrl = `${environment.apiUrl}/stagiaires`;

    constructor(private http: HttpClient) { }

    getAll(filters?: Record<string, any>): Observable<Stagiaire[]> {
        let params = new HttpParams();
        if (filters) {
            Object.keys(filters).forEach((key) => {
                const value = filters[key];
                if (value !== null && value !== undefined && value !== '') {
                    params = params.set(key, String(value));
                }
            });
        }

        return this.http.get<Stagiaire[]>(this.baseUrl, { params });
    }

    create(stagiaire: Stagiaire): Observable<any> {
        return this.http.post(this.baseUrl, stagiaire);
    }

    getStats(): Observable<StagiaireStats> {
        return this.http.get<{ status: string; data: StagiaireStats }>(`${this.baseUrl}/stats`).pipe(
            map(res => res.data)
        );
    }
}

export interface StagiaireStats {
    total: number;
    filieres: { label: string; value: number }[];
    niveaux: { label: string; value: number }[];
}

