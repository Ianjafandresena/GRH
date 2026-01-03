import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';

export interface EtatRemb {
    eta_code: number;
    eta_date: string;
    eta_total: number;
    etat_num: string;
    emp_code: number;
    // Joined fields
    nom_emp?: string;
    prenom_emp?: string;
    matricule?: string;
    nb_demandes?: number;  // Count of linked demandes
}

@Injectable({ providedIn: 'root' })
export class EtatRembService {
    private readonly http = inject(HttpClient);
    readonly baseUrl = environment.apiUrl + '/etat_remb';  // Public pour accès

    getAll(): Observable<EtatRemb[]> {
        return this.http.get<EtatRemb[]>(this.baseUrl, { withCredentials: true });
    }

    getByAgent(empCode: number): Observable<EtatRemb[]> {
        return this.http.get<EtatRemb[]>(`${this.baseUrl}/agent/${empCode}`, { withCredentials: true });
    }

    create(data: Partial<EtatRemb>): Observable<any> {
        return this.http.post(this.baseUrl, data, { withCredentials: true });
    }
}
