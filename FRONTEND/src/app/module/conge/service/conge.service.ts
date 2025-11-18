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
  
  constructor(private http: HttpClient) {}

  createConge(data: Conge): Observable<any> {
    return this.http.post<any>(this.congeUrl, data);
  }

  getConges(): Observable<Conge[]> {
    return this.http.get<Conge[]>(this.congeUrl);
  }

  getConge(id: number): Observable<Conge> {
    return this.http.get<Conge>(this.congeUrl + id);
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

}