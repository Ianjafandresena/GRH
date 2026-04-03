import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { Poste } from '../model/poste.model';
import { environment } from '../../../../environments/environment';

interface ApiResponse<T> {
  status: string;
  count: number;
  data: T;
}

@Injectable({ providedIn: 'root' })
export class PosteService {

  constructor(private http: HttpClient) { }

  getPostes(filters: any): Observable<Poste[]> {
    return this.http.get<ApiResponse<Poste[]>>(
      `${environment.apiUrl}/postes/list`,
      { params: filters }
    ).pipe(
      map(response => response.data) // ✅ Extraire data de la réponse
    );
  }

  // Méthodes pour récupérer les valeurs des filtres dynamiquement
  getDirections(): Observable<string[]> {
    return this.http.get<ApiResponse<any[]>>(`${environment.apiUrl}/directions/list`).pipe(
      map(response => response.data.map((d: any) => d.dir_nom))
    );
  }

  getServices(): Observable<string[]> {
    return this.http.get<ApiResponse<any[]>>(`${environment.apiUrl}/services/list`).pipe(
      map(response => response.data.map((s: any) => s.srvc_nom))
    );
  }

  getStatuts(): Observable<string[]> {
    return this.http.get<ApiResponse<any[]>>(`${environment.apiUrl}/statuts-poste/list`).pipe(
      map(response => response.data.map((s: any) => s.stpst_statut))
    );
  }

  getRangs(): Observable<string[]> {
    return this.http.get<ApiResponse<any[]>>(`${environment.apiUrl}/rangs/list`).pipe(
      map(response => response.data.map((r: any) => r.rhq_rang))
    );
  }

  getFonctions(): Observable<string[]> {
    return this.http.get<ApiResponse<any[]>>(`${environment.apiUrl}/postes/fonctions`).pipe(
      map(response => response.data)
    );
  }
}

