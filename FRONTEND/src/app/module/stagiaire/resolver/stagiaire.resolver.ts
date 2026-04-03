import { Injectable } from '@angular/core';
import { Resolve, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { Observable, forkJoin } from 'rxjs';
import { StagiaireService, Stagiaire, StagiaireStats } from '../service/stagiaire.service';

export interface StagiaireListResolved {
  stagiaires: Stagiaire[];
  stats: StagiaireStats;
}

@Injectable({ providedIn: 'root' })
export class StagiaireResolver implements Resolve<StagiaireListResolved> {
  constructor(private stagiaireService: StagiaireService) { }

  resolve(route: ActivatedRouteSnapshot, state: RouterStateSnapshot): Observable<StagiaireListResolved> {
    const filters: Record<string, any> = {};

    if (route.queryParams['q']) filters['q'] = route.queryParams['q'];
    if (route.queryParams['contact']) filters['contact'] = route.queryParams['contact'];
    if (route.queryParams['filiere']) filters['filiere'] = route.queryParams['filiere'];
    if (route.queryParams['niveau']) filters['niveau'] = route.queryParams['niveau'];

    return forkJoin({
      stagiaires: this.stagiaireService.getAll(filters),
      stats: this.stagiaireService.getStats()
    });
  }
}

