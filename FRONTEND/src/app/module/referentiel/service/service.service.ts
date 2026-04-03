import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';

export interface Service {
    srvc_code: number;
    srvc_nom: string;
}

interface ApiResponse<T> {
    status: string;
    count: number;
    data: T;
}

@Injectable({
    providedIn: 'root'
})
export class ServiceService {
    private apiUrl = `${environment.apiUrl}/services`;

    constructor(private http: HttpClient) { }

    getServices(): Observable<Service[]> {
        return this.http.get<any>(
            `${this.apiUrl}/list`
        ).pipe(
            map(response => {
                console.log('API Services Response:', response);
                // Gérer les différents formats de réponse possibles
                if (response && response.data) {
                    return response.data;
                } else if (Array.isArray(response)) {
                    return response;
                }
                return [];
            })
        );
    }
}
