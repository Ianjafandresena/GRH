import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';

export interface Etablissement {
    etab_code?: number;
    etab_nom: string;
    etab_adresse?: string;
}

@Injectable({ providedIn: 'root' })
export class EtablissementService {
    private baseUrl = `${environment.apiUrl}/etablissements`;

    constructor(private http: HttpClient) {}

    getAll(): Observable<Etablissement[]> {
        return this.http.get<Etablissement[]>(this.baseUrl);
    }

    create(payload: Etablissement): Observable<any> {
        return this.http.post(this.baseUrl, payload);
    }

    update(id: number, payload: Partial<Etablissement>): Observable<any> {
        return this.http.put(`${this.baseUrl}/${id}`, payload);
    }

    delete(id: number): Observable<any> {
        return this.http.delete(`${this.baseUrl}/${id}`);
    }
}
