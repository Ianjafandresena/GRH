import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';

interface ApiResponse<T> {
    status: string;
    count?: number;
    data: T;
}

export interface TypeContrat {
    tcontrat_code: number;
    tcontrat_nom: string;
}

@Injectable({ providedIn: 'root' })
export class TypeContratService {
    private apiUrl = `${environment.apiUrl}/types-contrat/list`;

    constructor(private http: HttpClient) { }

    /**
     * Récupère tous les types de contrat
     */
    getTypesContrat(): Observable<TypeContrat[]> {
        return this.http.get<ApiResponse<TypeContrat[]>>(this.apiUrl).pipe(
            map(response => response.data || [])
        );
    }
}

