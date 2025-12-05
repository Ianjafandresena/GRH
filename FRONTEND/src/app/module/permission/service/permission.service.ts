import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { Permission } from '../model/permission.model';

@Injectable({ providedIn: 'root' })
export class PermissionService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = environment.apiUrl + '/permission';
  private readonly soldeUrl = environment.apiUrl + '/solde_permission';

  getPermissions(params: any = {}): Observable<any[]> {
    return this.http.get<any[]>(this.baseUrl, { params, withCredentials: true });
  }

  createPermission(payload: Permission): Observable<any> {
    return this.http.post(this.baseUrl, payload, { withCredentials: true });
  }

  getSoldesPermission(emp_code: number): Observable<any[]> {
    return this.http.get<any[]>(this.soldeUrl + '?emp_code=' + emp_code, { withCredentials: true });
  }

  getLastSoldeDispo(emp_code: number): Observable<any> {
    return this.http.get<any>(this.soldeUrl + '/last_dispo/' + emp_code, { withCredentials: true });
  }
}
