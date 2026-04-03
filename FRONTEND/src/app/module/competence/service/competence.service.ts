import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';

export interface Competence {
    comp_code?: number;
    comp_intitule: string;
    comp_domaine?: string;
    comp_description?: string;
}

@Injectable({ providedIn: 'root' })
export class CompetenceService {
    private baseUrl = `${environment.apiUrl}/competences`;

    constructor(private http: HttpClient) { }

    getAll(filters?: Record<string, any>): Observable<Competence[]> {
        let params = new HttpParams();
        if (filters) {
            Object.keys(filters).forEach((key) => {
                const value = filters[key];
                if (value !== null && value !== undefined && value !== '') {
                    params = params.set(key, String(value));
                }
            });
        }
        return this.http.get<Competence[]>(this.baseUrl, { params });
    }

    getById(id: number): Observable<Competence> {
        return this.http.get<Competence>(`${this.baseUrl}/${id}`);
    }

    create(competence: Competence): Observable<any> {
        return this.http.post(this.baseUrl, competence);
    }

    update(id: number, competence: Competence): Observable<any> {
        return this.http.put(`${this.baseUrl}/${id}`, competence);
    }

    delete(id: number): Observable<any> {
        return this.http.delete(`${this.baseUrl}/${id}`);
    }

    getDomaines(): Observable<string[]> {
        return this.http.get<string[]>(`${this.baseUrl}/domaines`);
    }

    getStats(): Observable<CompetenceStats> {
        return this.http.get<{ status: string; data: CompetenceStats }>(`${this.baseUrl}/stats`).pipe(
            map(res => res.data)
        );
    }
}

export interface CompetenceStats {
    total: number;
    par_domaine: { label: string; value: number }[];
}

