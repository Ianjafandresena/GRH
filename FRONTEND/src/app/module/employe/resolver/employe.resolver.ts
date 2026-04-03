import { Injectable } from '@angular/core';
import { Resolve, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { Observable, forkJoin } from 'rxjs';
import { EmployeService, EmployeFilters, EmployeStats } from '../service/employe.service';
import { EmployeWithAffectation } from '../model/employe.model';

export interface EmployeListResolved {
  employes: EmployeWithAffectation[];
  stats: EmployeStats;
}

@Injectable({ providedIn: 'root' })
export class EmployeResolver implements Resolve<EmployeListResolved> {
  constructor(private employeService: EmployeService) { }

  resolve(route: ActivatedRouteSnapshot, state: RouterStateSnapshot): Observable<EmployeListResolved> {
    const filters: EmployeFilters = {};

    if (route.queryParams['pst_code']) filters.pst_code = +route.queryParams['pst_code'];
    if (route.queryParams['srvc_code']) filters.srvc_code = +route.queryParams['srvc_code'];
    if (route.queryParams['statut']) filters.statut = route.queryParams['statut'] as 'actif' | 'inactif';
    if (route.queryParams['with_affectation']) filters.with_affectation = route.queryParams['with_affectation'] === 'true' || route.queryParams['with_affectation'] === '1';
    if (route.queryParams['pos_code']) filters.pos_code = +route.queryParams['pos_code'];
    if (route.queryParams['q']) filters.q = route.queryParams['q'];

    return forkJoin({
      employes: this.employeService.list(filters),
      stats: this.employeService.getStats()
    });
  }
}

