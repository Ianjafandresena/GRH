import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { Employee } from '../model/employee.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class EmployeeService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl + '/employee';

  getEmployees(): Observable<Employee[]> {
    return this.http.get<Employee[]>(this.apiUrl, { withCredentials: true }).pipe(
      catchError(err => {
        console.error('Error fetching employees:', err);
        return of([]);
      })
    );
  }

  getEmployeeDetail(emp_code: number): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}/${emp_code}`, { withCredentials: true }).pipe(
      catchError(err => {
        console.error('Error fetching employee detail:', err);
        return of(null);
      })
    );
  }

  // ========== FAMILY METHODS ==========
  getFamilyList(): Observable<any[]> {
    return this.http.get<any[]>(`${environment.apiUrl}/beneficiaire/familles`, { withCredentials: true });
  }

  getSpouses(empCode: number): Observable<any[]> {
    return this.http.get<any[]>(`${environment.apiUrl}/beneficiaire/conjoints/${empCode}`, { withCredentials: true });
  }

  getChildren(empCode: number): Observable<any[]> {
    return this.http.get<any[]>(`${environment.apiUrl}/beneficiaire/enfants/${empCode}`, { withCredentials: true });
  }

  addSpouse(empCode: number, data: any): Observable<any> {
    return this.http.post(`${environment.apiUrl}/beneficiaire/conjoint/${empCode}`, data, { withCredentials: true });
  }

  updateSpouseStatus(conjId: number, cjs_id: number): Observable<any> {
    return this.http.put(`${environment.apiUrl}/beneficiaire/conjoint/status/${conjId}`, { cjs_id }, { withCredentials: true });
  }

  getSpouseStatuses(): Observable<any[]> {
    return this.http.get<any[]>(`${environment.apiUrl}/beneficiaire/conjoint/statuses`, { withCredentials: true });
  }

  addChild(empCode: number, data: any): Observable<any> {
    return this.http.post(`${environment.apiUrl}/beneficiaire/enfant/${empCode}`, data, { withCredentials: true });
  }

  removeChild(childId: number): Observable<any> {
    return this.http.delete(`${environment.apiUrl}/beneficiaire/enfant/${childId}`, { withCredentials: true });
  }
}
