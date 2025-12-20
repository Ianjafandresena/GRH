import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { DemandeRemb, EtatRemb } from '../model/demande-remb.model';

@Injectable({ providedIn: 'root' })
export class RemboursementService {
    private readonly http = inject(HttpClient);
    private readonly baseUrl = environment.apiUrl + '/remboursement';
    private readonly etatUrl = environment.apiUrl + '/etat_remb';

    // Liste des demandes
    getDemandes(params: any = {}): Observable<DemandeRemb[]> {
        return this.http.get<DemandeRemb[]>(this.baseUrl, { params, withCredentials: true });
    }

    // Détail d'une demande
    getDemande(id: number): Observable<any> {
        return this.http.get<any>(`${this.baseUrl}/${id}`, { withCredentials: true });
    }

    // Créer une demande mode AGENT (Indirect)
    createIndirect(data: any): Observable<any> {
        return this.http.post<any>(`${this.baseUrl}/indirect`, data, { withCredentials: true });
    }

    // Récupérer les membres de la famille
    getFamilyMembers(empCode: number): Observable<any> {
        return this.http.get<any>(`${this.baseUrl}/family/${empCode}`, { withCredentials: true });
    }

    // Récupérer les PECs de l'employé
    getMyPecs(empCode: number): Observable<any[]> {
        return this.http.get<any[]>(`${environment.apiUrl}/prise_en_charge/employee/${empCode}`, { withCredentials: true });
    }

    // Validation RRH
    validerRRH(id: number, decision: string = 'APPROUVE', montantValide?: number): Observable<any> {
        return this.http.post(`${this.baseUrl}/${id}/valider-rrh`, { decision, montant_valide: montantValide }, { withCredentials: true });
    }

    // Validation DAAF
    validerDAAF(id: number, decision: string = 'APPROUVE'): Observable<any> {
        return this.http.post(`${this.baseUrl}/${id}/valider-daaf`, { decision }, { withCredentials: true });
    }

    // Engagement Finance
    engager(id: number, numEngagement?: string, montantValide?: number): Observable<any> {
        return this.http.post(`${this.baseUrl}/${id}/engager`, { num_engagement: numEngagement, montant_valide: montantValide }, { withCredentials: true });
    }

    // Paiement Finance
    payer(id: number): Observable<any> {
        return this.http.post(`${this.baseUrl}/${id}/payer`, {}, { withCredentials: true });
    }

    // Rejeter
    rejeter(id: number, motif: string): Observable<any> {
        return this.http.post(`${this.baseUrl}/${id}/rejeter`, { motif }, { withCredentials: true });
    }

    // Télécharger le PDF
    downloadPdf(id: number): Observable<Blob> {
        return this.http.get(`${this.baseUrl}/${id}/pdf`, { responseType: 'blob', withCredentials: true });
    }

    // Etat mensuel agent (PDF)
    downloadEtatAgentPdf(empCode: number, annee: number, mois: number): Observable<Blob> {
        const params = { emp_code: empCode, annee, mois };
        return this.http.get(`${this.baseUrl}/etat/agent/pdf`, {
            params: params as any,
            responseType: 'blob',
            withCredentials: true
        });
    }

    // États de remboursement
    getEtats(): Observable<EtatRemb[]> {
        return this.http.get<EtatRemb[]>(this.etatUrl, { withCredentials: true });
    }
}
