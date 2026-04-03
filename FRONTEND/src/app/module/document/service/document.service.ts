import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';
import { DocumentDemande } from '../model/document.model';

@Injectable({ providedIn: 'root' })
export class DocumentService {
    private apiUrl = `${environment.apiUrl}/documents`;

    constructor(private http: HttpClient) { }

    /**
     * Créer une demande de document et télécharger le PDF
     */
    creerDemande(demande: DocumentDemande): Observable<any> {
        return this.http.post(`${this.apiUrl}/demande`, demande, {
            responseType: 'json'
        });
    }

    /**
     * Télécharger le PDF depuis la réponse
     */
    telechargerPDF(pdfBase64: string, filename: string): void {
        const byteCharacters = atob(pdfBase64);
        const byteNumbers = new Array(byteCharacters.length);
        for (let i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
        }
        const byteArray = new Uint8Array(byteNumbers);
        const blob = new Blob([byteArray], { type: 'application/pdf' });

        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    }

    /**
     * Lister les demandes de documents
     */
    listerDemandes(filters?: any): Observable<DocumentDemande[]> {
        return this.http.get<any>(`${this.apiUrl}/demandes`, { params: filters }).pipe(
            map((response) => {
                if (Array.isArray(response)) return response as DocumentDemande[];
                const data = response?.data;
                return Array.isArray(data) ? (data as DocumentDemande[]) : [];
            })
        );
    }

    /**
     * Valider une demande (statut = traite)
     */
    validerDemande(id: number): Observable<any> {
        return this.http.put(`${this.apiUrl}/demandes/${id}/valider`, {}, { responseType: 'json' });
    }

    /**
     * Télécharger le PDF final d'une demande validée
     */
    telechargerPdfDemande(id: number): Observable<any> {
        return this.http.get(`${this.apiUrl}/demandes/${id}/pdf`, { responseType: 'json' });
    }

    /**
     * Stage Documents
     */
    listerDemandesStage(): Observable<any[]> {
        return this.http.get<any>(`${environment.apiUrl}/stages/demandes`).pipe(
            map(res => res.data || [])
        );
    }

    validerDemandeStage(id: number): Observable<any> {
        return this.http.put(`${environment.apiUrl}/stages/demandes/${id}/valider`, {});
    }

    getStatsStage(): Observable<DocumentStats> {
        return this.http.get<{ status: string; data: DocumentStats }>(`${environment.apiUrl}/stages/demandes/stats`).pipe(
            map(res => res.data)
        );
    }

    telechargerConvention(stgId: number): Observable<any> {
        return this.http.get(`${environment.apiUrl}/stages/${stgId}/convention`);
    }

    telechargerDemandeAttestation(stgId: number): Observable<any> {
        return this.http.get(`${environment.apiUrl}/stages/${stgId}/demande-attestation`);
    }

    /**
     * Obtenir une demande par ID
     */
    obtenirDemande(id: number): Observable<DocumentDemande> {
        return this.http.get<DocumentDemande>(`${this.apiUrl}/demande/${id}`);
    }

    getStats(): Observable<DocumentStats> {
        return this.http.get<{ status: string; data: DocumentStats }>(`${this.apiUrl}/stats`).pipe(
            map(res => res.data)
        );
    }
}

export interface DocumentStats {
    total: number;
    attente: number;
    traite: number;
}


