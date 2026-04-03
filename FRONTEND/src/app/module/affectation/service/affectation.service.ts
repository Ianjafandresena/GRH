import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';
import { Affectation, MotifAffectation } from '../model/affectation.model';

@Injectable({
    providedIn: 'root'
})
export class AffectationService {
    private apiUrl = `${environment.apiUrl}/affectations`;
    private motifsUrl = `${environment.apiUrl}/motifs-affectation`;

    constructor(private http: HttpClient) { }

    create(affectation: Affectation): Observable<any> {
        return this.http.post(this.apiUrl, affectation);
    }

    getAll(filters?: Record<string, any>): Observable<Affectation[]> {
        let params = new HttpParams();
        if (filters) {
            Object.keys(filters).forEach((key) => {
                const value = filters[key];
                if (value !== null && value !== undefined && value !== '') {
                    params = params.set(key, String(value));
                }
            });
        }

        return this.http.get<Affectation[]>(`${this.apiUrl}/list`, { params });
    }

    getMotifs(): Observable<MotifAffectation[]> {
        return this.http.get<MotifAffectation[]>(this.motifsUrl);
    }

    cloturer(id: number, dateFin: string, statutPoste: 'vacant' | 'cessation' = 'vacant'): Observable<any> {
        return this.http.put(`${this.apiUrl}/${id}/cloturer`, {
            affec_date_fin: dateFin,
            statut_poste: statutPoste
        });
    }

    getStats(): Observable<AffectationStats> {
        return this.http.get<{ status: string; data: AffectationStats }>(`${this.apiUrl}/stats`).pipe(
            map(res => res.data)
        );
    }
}

export interface AffectationStats {
    total: number;
    actives: number;
    cloturees: number;
    par_contrat: { label: string; value: number }[];
    mouvements: number;
}
