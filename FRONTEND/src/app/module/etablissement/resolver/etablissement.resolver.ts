import { Injectable } from '@angular/core';
import { Resolve, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { Observable } from 'rxjs';
import { EtablissementService, Etablissement } from '../service/etablissement.service';

@Injectable({ providedIn: 'root' })
export class EtablissementResolver implements Resolve<Etablissement[]> {
  constructor(private etablissementService: EtablissementService) {}

  resolve(route: ActivatedRouteSnapshot, state: RouterStateSnapshot): Observable<Etablissement[]> {
    // Pas de filtres pour les établissements
    return this.etablissementService.getAll();
  }
}

