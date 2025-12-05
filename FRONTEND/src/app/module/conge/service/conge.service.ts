import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { InterimConge } from '../model/interimconge.model';
import { TypeConge } from '../model/conge.model';
import { Conge } from '../model/conge.model';
import { Region } from '../model/region.model';


@Injectable({ providedIn: 'root' })
export class CongeService {
  private congeUrl = environment.apiUrl + '/conge/';
  private interimUrl = environment.apiUrl + '/interim_conge/';
  private typeCongeUrl = environment.apiUrl + '/type_conge/';
  private regionUrl = environment.apiUrl + '/region/';
  private soldeCongeUrl = environment.apiUrl + '/solde_conge/';
  private exportUrl = environment.apiUrl + '/conge/export';
  private importUrl = environment.apiUrl + '/conge/import';
  private exportExcelUrl = environment.apiUrl + '/conge/export-excel';
  
  constructor(private http: HttpClient) {}

  createConge(data: Conge): Observable<any> {
    return this.http.post<any>(this.congeUrl, data);
  }

  getConges(params?: Record<string, any>): Observable<Conge[]> {
    return this.http.get<Conge[]>(this.congeUrl, { params });
  }

  getConge(id: number): Observable<Conge> {
    return this.http.get<Conge>(this.congeUrl + id);
  }

  getCongeDetail(id: number): Observable<any> {
    return this.http.get<any>(this.congeUrl + 'detail/' + id);
  }

  downloadAttestationPdf(id: number): Observable<Blob> {
    return this.http.get(this.congeUrl + 'attestation/' + id, { responseType: 'blob' });
  }

  createInterimConge(data: InterimConge): Observable<any> {
    return this.http.post<any>(this.interimUrl, data);
  }

  getInterimConges(): Observable<InterimConge[]> {
    return this.http.get<InterimConge[]>(this.interimUrl);
  }

  getTypesConge(): Observable<TypeConge[]> {
    return this.http.get<TypeConge[]>(this.typeCongeUrl);
  }

  getRegions(): Observable<Region[]> {
    return this.http.get<Region[]>(this.regionUrl);
  }

  getSoldesConge(emp_code: number): Observable<any[]> {
    return this.http.get<any[]>(this.soldeCongeUrl + '?emp_code=' + emp_code);
  }
  getDecision(dec_code: number): Observable<any> {
    return this.http.get<any>(environment.apiUrl + '/decision/' + dec_code);
  }

  getLastSoldeDispo(emp_code: number): Observable<any> {
  return this.http.get<any>(this.soldeCongeUrl + 'last_dispo/' + emp_code); 
}

  exportCongesCsv(): Observable<Blob> {
    return this.http.get(this.exportUrl, { responseType: 'blob' });
  }

  importCongesCsv(formData: FormData): Observable<any> {
    return this.http.post(this.importUrl, formData);
  }

  exportCongesExcel(): Observable<Blob> {
    return this.http.get(this.exportExcelUrl, { responseType: 'blob' });
  }

}
