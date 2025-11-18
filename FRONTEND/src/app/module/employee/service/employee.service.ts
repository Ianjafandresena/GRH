import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { Employee } from '../model/employee.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class EmployeeService {
  private apiUrl = environment.apiUrl + '/employee/';

  constructor(private http: HttpClient) {}

  getEmployees(): Observable<Employee[]> {
    return this.http.get<Employee[]>(this.apiUrl);
  }
}
