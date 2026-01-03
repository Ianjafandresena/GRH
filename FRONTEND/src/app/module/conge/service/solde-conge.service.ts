import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface SoldeDetail {
    sld_code: number;
    sld_anne: number;
    sld_initial: number;
    sld_restant: number;
    sld_maj: string;
    dec_num: string;
    emp_code: number;
}

@Injectable({
    providedIn: 'root'
})
export class SoldeCongeService {
    private apiUrl = '/api/solde_conge';

    constructor(public http: HttpClient) { }

    /**
     * Récupérer tous les soldes d'un employé (multi-années FIFO)
     */
    getSoldesByEmployee(empCode: number): Observable<SoldeDetail[]> {
        return this.http.get<SoldeDetail[]>(`${this.apiUrl}?emp_code=${empCode}`);
    }

    /**
     * Récupérer le reliquat le plus ancien (FIFO)
     */
    getLastDispo(empCode: number): Observable<any> {
        return this.http.get(`${this.apiUrl}/last_dispo/${empCode}`);
    }

    /**
     * Total disponible tous soldes confondus
     */
    getTotalDisponible(soldes: SoldeDetail[]): number {
        return soldes.reduce((sum, s) => sum + (s.sld_restant || 0), 0);
    }
}
