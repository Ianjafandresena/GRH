import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { CentreSante, TypeCentre } from '../model/centre-sante.model';

@Injectable({ providedIn: 'root' })
export class CentreSanteService {
    private readonly http = inject(HttpClient);
    private readonly centreUrl = environment.apiUrl + '/centre_sante';

    // Centres de santé
    getCentres(typeCode?: number, search?: string): Observable<CentreSante[]> {
        let params = new HttpParams();
        if (typeCode) params = params.set('tp_cen_code', typeCode.toString());
        if (search) params = params.set('search', search);
        return this.http.get<CentreSante[]>(this.centreUrl, { params, withCredentials: true });
    }

    getCentre(id: number): Observable<CentreSante> {
        return this.http.get<CentreSante>(`${this.centreUrl}/${id}`, { withCredentials: true });
    }

    createCentre(centre: CentreSante): Observable<CentreSante> {
        return this.http.post<CentreSante>(this.centreUrl, centre, { withCredentials: true });
    }

    updateCentre(id: number, centre: Partial<CentreSante>): Observable<CentreSante> {
        return this.http.put<CentreSante>(`${this.centreUrl}/${id}`, centre, { withCredentials: true });
    }

    deleteCentre(id: number): Observable<any> {
        return this.http.delete(`${this.centreUrl}/${id}`, { withCredentials: true });
    }

    // Types de centre
    getTypes(): Observable<TypeCentre[]> {
        return this.http.get<TypeCentre[]>(`${this.centreUrl}/types`, { withCredentials: true });
    }
}
