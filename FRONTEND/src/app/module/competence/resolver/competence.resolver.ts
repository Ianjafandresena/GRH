import { Injectable } from '@angular/core';
import { Resolve, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { Observable, forkJoin } from 'rxjs';
import { CompetenceService, Competence, CompetenceStats } from '../service/competence.service';

export interface CompetenceListResolved {
  competences: Competence[];
  stats: CompetenceStats;
}

@Injectable({ providedIn: 'root' })
export class CompetenceResolver implements Resolve<CompetenceListResolved> {
  constructor(private competenceService: CompetenceService) { }

  resolve(route: ActivatedRouteSnapshot, state: RouterStateSnapshot): Observable<CompetenceListResolved> {
    const filters: Record<string, any> = {};

    if (route.queryParams['q']) filters['q'] = route.queryParams['q'];
    if (route.queryParams['intitule']) filters['intitule'] = route.queryParams['intitule'];
    if (route.queryParams['domaine']) filters['domaine'] = route.queryParams['domaine'];

    return forkJoin({
      competences: this.competenceService.getAll(filters),
      stats: this.competenceService.getStats()
    });
  }
}

