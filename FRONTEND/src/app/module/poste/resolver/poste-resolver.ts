import { Injectable } from '@angular/core';
import { Resolve, ActivatedRouteSnapshot } from '@angular/router';
import { Observable, forkJoin } from 'rxjs';
import { PosteService, PosteStats } from '../service/poste.service';
import { Poste } from '../model/poste.model';

export interface PosteListResolved {
  postes: Poste[];
  stats: PosteStats;
}

@Injectable({ providedIn: 'root' })
export class PostesResolver implements Resolve<PosteListResolved> {
  constructor(private posteService: PosteService) { }
  resolve(route: ActivatedRouteSnapshot): Observable<PosteListResolved> {
    return forkJoin({
      postes: this.posteService.list(route.queryParams),
      stats: this.posteService.getStats()
    });
  }
}
