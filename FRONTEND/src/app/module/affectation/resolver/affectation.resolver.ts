import { Injectable } from '@angular/core';
import { Resolve, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { Observable, forkJoin, map } from 'rxjs';
import { AffectationService, AffectationStats } from '../service/affectation.service';
import { Affectation } from '../model/affectation.model';

export interface AffectationListResolved {
  affectations: Affectation[];
  stats: AffectationStats;
}

@Injectable({ providedIn: 'root' })
export class AffectationResolver implements Resolve<AffectationListResolved> {
  constructor(private affectationService: AffectationService) { }

  resolve(route: ActivatedRouteSnapshot, state: RouterStateSnapshot): Observable<AffectationListResolved> {
    const filters: Record<string, any> = {};

    if (route.queryParams['emp']) filters['emp'] = route.queryParams['emp'];
    if (route.queryParams['poste']) filters['poste'] = route.queryParams['poste'];
    if (route.queryParams['motif']) filters['motif'] = route.queryParams['motif'];
    if (route.queryParams['type_contrat']) filters['type_contrat'] = route.queryParams['type_contrat'];

    return forkJoin({
      affectations: this.affectationService.getAll(filters),
      stats: this.affectationService.getStats()
    });
  }
}

