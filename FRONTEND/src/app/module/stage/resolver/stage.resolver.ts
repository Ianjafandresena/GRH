import { Injectable } from '@angular/core';
import { Resolve, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { Observable, forkJoin } from 'rxjs';
import { StageService, Stage, StageStats } from '../service/stage.service';

export interface StageListResolved {
  stages: Stage[];
  stats: StageStats;
}

@Injectable({ providedIn: 'root' })
export class StageResolver implements Resolve<StageListResolved> {
  constructor(private stageService: StageService) { }

  resolve(route: ActivatedRouteSnapshot, state: RouterStateSnapshot): Observable<StageListResolved> {
    const filters: Record<string, any> = {};

    if (route.queryParams['stagiaire']) filters['stagiaire'] = route.queryParams['stagiaire'];
    if (route.queryParams['encadreur']) filters['encadreur'] = route.queryParams['encadreur'];
    if (route.queryParams['theme']) filters['theme'] = route.queryParams['theme'];
    if (route.queryParams['date_debut_from']) filters['date_debut_from'] = route.queryParams['date_debut_from'];
    if (route.queryParams['date_debut_to']) filters['date_debut_to'] = route.queryParams['date_debut_to'];

    return forkJoin({
      stages: this.stageService.getAll(filters),
      stats: this.stageService.getStats()
    });
  }
}

