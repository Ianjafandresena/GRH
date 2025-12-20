import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { CentreSante, Convention } from '../model/centre-sante.model';

@Injectable({ providedIn: 'root' })
export class CentreSanteService {
    private readonly http = inject(HttpClient);
    private readonly centreUrl = environment.apiUrl + '/centre_sante';
    private readonly conventionUrl = environment.apiUrl + '/convention';

    // Centres de santé
    getCentres(): Observable<CentreSante[]> {
        return this.http.get<CentreSante[]>(this.centreUrl, { withCredentials: true });
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

    // Conventions
    getConventions(): Observable<Convention[]> {
        return this.http.get<Convention[]>(this.conventionUrl, { withCredentials: true });
    }

    createConvention(convention: Convention): Observable<Convention> {
        return this.http.post<Convention>(this.conventionUrl, convention, { withCredentials: true });
    }

    updateConvention(id: number, convention: Partial<Convention>): Observable<Convention> {
        return this.http.put<Convention>(`${this.conventionUrl}/${id}`, convention, { withCredentials: true });
    }
}
