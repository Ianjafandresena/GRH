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
    cen_code?: number;
    cen_nom?: string;
    eta_libelle?: string;
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

    getById(id: number): Observable<EtatRemb> {
        return this.http.get<EtatRemb>(`${this.baseUrl}/${id}`, { withCredentials: true });
    }

    create(data: Partial<EtatRemb>): Observable<any> {
        return this.http.post(this.baseUrl, data, { withCredentials: true });
    }

    mandater(id: number): Observable<any> {
        return this.http.post(`${this.baseUrl}/${id}/mandater`, {}, { withCredentials: true });
    }

    agentComptable(id: number): Observable<any> {
        return this.http.post(`${this.baseUrl}/${id}/agent-comptable`, {}, { withCredentials: true });
    }

    exportExcel(id: number): Observable<Blob> {
        return this.http.get(`${this.baseUrl}/${id}/excel`, { responseType: 'blob', withCredentials: true });
    }
}
