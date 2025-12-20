import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';

export interface InterruptionPreview {
    jours_utilises: number;
    jours_a_restituer: number;
    details_restitution: {
        decision: string;
        annee: number;
        jours_a_restituer: number;
        solde_actuel: number;
        solde_apres: number;
    }[];
}

export interface Interruption {
    interup_code?: number;
    interup_date: string;
    interup_motif: string;
    interup_restant: number;
    cng_code: number;
}

@Injectable({ providedIn: 'root' })
export class InterruptionService {
    private baseUrl = environment.apiUrl + '/interruption';

    constructor(private http: HttpClient) { }

    /**
     * Get interruption for a specific leave
     */
    getByConge(cngCode: number): Observable<Interruption | null> {
        return this.http.get<Interruption | null>(`${this.baseUrl}/conge/${cngCode}`);
    }

    /**
     * Get active leaves for an employee (leaves that can be interrupted)
     */
    getActiveLeaves(empCode: number): Observable<any[]> {
        return this.http.get<any[]>(`${this.baseUrl}/active/${empCode}`);
    }

    /**
     * Preview restoration before creating interruption
     */
    previewRestoration(cngCode: number, interupDate: string): Observable<InterruptionPreview> {
        return this.http.post<InterruptionPreview>(`${this.baseUrl}/preview`, {
            cng_code: cngCode,
            interup_date: interupDate
        });
    }

    /**
     * Create an interruption
     */
    create(data: { cng_code: number; interup_date: string; interup_motif?: string }): Observable<any> {
        return this.http.post<any>(this.baseUrl, data);
    }

    /**
     * Download interruption attestation PDF
     */
    downloadAttestation(cngCode: number): Observable<Blob> {
        return this.http.get(`${this.baseUrl}/attestation/${cngCode}`, { responseType: 'blob' });
    }
}
