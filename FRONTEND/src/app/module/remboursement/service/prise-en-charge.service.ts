import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { PrisEnCharge, Conjointe, Enfant } from '../model/prise-en-charge.model';

@Injectable({ providedIn: 'root' })
export class PrisEnChargeService {
    private readonly http = inject(HttpClient);
    private readonly baseUrl = environment.apiUrl + '/prise_en_charge';
    private readonly beneficiaireUrl = environment.apiUrl + '/beneficiaire';

    // Prises en charge
    getAll(): Observable<PrisEnCharge[]> {
        return this.http.get<PrisEnCharge[]>(this.baseUrl, { withCredentials: true });
    }

    get(id: number): Observable<PrisEnCharge> {
        return this.http.get<PrisEnCharge>(`${this.baseUrl}/${id}`, { withCredentials: true });
    }

    create(data: PrisEnCharge): Observable<PrisEnCharge> {
        return this.http.post<PrisEnCharge>(this.baseUrl, data, { withCredentials: true });
    }

    valider(id: number, decision: string, validateur_emp_code?: number): Observable<any> {
        return this.http.post(`${this.baseUrl}/${id}/valider`, { decision, validateur_emp_code }, { withCredentials: true });
    }

    downloadBulletin(id: number): Observable<Blob> {
        return this.http.get(`${this.baseUrl}/${id}/bulletin`, { responseType: 'blob', withCredentials: true });
    }

    // Bénéficiaires
    getConjoints(empCode: number): Observable<Conjointe[]> {
        return this.http.get<Conjointe[]>(`${this.beneficiaireUrl}/conjoints/${empCode}`, { withCredentials: true });
    }

    getEnfants(empCode: number): Observable<Enfant[]> {
        return this.http.get<Enfant[]>(`${this.beneficiaireUrl}/enfants/${empCode}`, { withCredentials: true });
    }

    addConjoint(empCode: number, conjoint: Conjointe): Observable<Conjointe> {
        return this.http.post<Conjointe>(`${this.beneficiaireUrl}/conjoint/${empCode}`, conjoint, { withCredentials: true });
    }

    addEnfant(empCode: number, enfant: Enfant): Observable<Enfant> {
        return this.http.post<Enfant>(`${this.beneficiaireUrl}/enfant/${empCode}`, enfant, { withCredentials: true });
    }
}
