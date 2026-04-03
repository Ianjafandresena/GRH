import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';

export interface TypeDocument {
    tdoc_code: number;
    tdoc_nom: string;
    tdoc_matricule: string;
}

interface ApiResponse<T> {
    status: string;
    count?: number;
    data: T;
}

@Injectable({ providedIn: 'root' })
export class TypeDocumentService {
    private apiUrl = `${environment.apiUrl}/types-document/list`;

    constructor(private http: HttpClient) { }

    /**
     * Récupère tous les types de documents
     */
    getTypesDocument(): Observable<TypeDocument[]> {
        return this.http.get<ApiResponse<TypeDocument[]>>(this.apiUrl).pipe(
            map(response => response.data || [])
        );
    }
}

