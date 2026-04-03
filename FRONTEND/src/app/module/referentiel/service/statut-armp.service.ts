import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';

export interface StatutArmp {
  stt_armp_code: number;
  stt_armp_statut: string;
}

interface ApiResponse<T> {
  status: string;
  data: T;
  count?: number;
}

@Injectable({
  providedIn: 'root'
})
export class StatutArmpService {
  private apiUrl = `${environment.apiUrl}/referentiels/statuts-armp`;

  constructor(private http: HttpClient) { }

  /**
   * Récupérer tous les statuts ARMP
   */
  getStatutsArmp(): Observable<StatutArmp[]> {
    return this.http.get<ApiResponse<StatutArmp[]>>(this.apiUrl)
      .pipe(
        map(response => response.data || [])
      );
  }

  /**
   * Récupérer un statut ARMP par son ID
   */
  getStatutArmpById(id: number): Observable<StatutArmp> {
    return this.http.get<ApiResponse<StatutArmp>>(`${this.apiUrl}/${id}`)
      .pipe(
        map(response => response.data)
      );
  }

  /**
   * Créer un nouveau statut ARMP
   */
  createStatutArmp(statut: Partial<StatutArmp>): Observable<StatutArmp> {
    return this.http.post<ApiResponse<StatutArmp>>(this.apiUrl, statut)
      .pipe(
        map(response => response.data)
      );
  }

  /**
   * Mettre à jour un statut ARMP
   */
  updateStatutArmp(id: number, statut: Partial<StatutArmp>): Observable<StatutArmp> {
    return this.http.put<ApiResponse<StatutArmp>>(`${this.apiUrl}/${id}`, statut)
      .pipe(
        map(response => response.data)
      );
  }

  /**
   * Supprimer un statut ARMP
   */
  deleteStatutArmp(id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/${id}`);
  }
}
