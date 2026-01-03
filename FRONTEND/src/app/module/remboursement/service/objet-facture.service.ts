import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';

export interface ObjetRemboursement {
    obj_code?: number;
    obj_article: string;
}

export interface Facture {
    fac_code?: number;
    fac_num: string;
    fac_date?: string;
}

@Injectable({ providedIn: 'root' })
export class ObjetFactureService {
    private readonly http = inject(HttpClient);
    private readonly objetUrl = environment.apiUrl + '/objet_remboursement';
    private readonly factureUrl = environment.apiUrl + '/facture';

    // Objets
    getObjets(): Observable<ObjetRemboursement[]> {
        return this.http.get<ObjetRemboursement[]>(this.objetUrl, { withCredentials: true });
    }

    createObjet(article: string): Observable<ObjetRemboursement> {
        return this.http.post<ObjetRemboursement>(this.objetUrl, { obj_article: article }, { withCredentials: true });
    }

    // Factures
    getFactures(): Observable<Facture[]> {
        return this.http.get<Facture[]>(this.factureUrl, { withCredentials: true });
    }

    createFacture(data: { fac_num: string; fac_date?: string }): Observable<Facture> {
        return this.http.post<Facture>(this.factureUrl, data, { withCredentials: true });
    }
}
