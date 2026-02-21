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
}
