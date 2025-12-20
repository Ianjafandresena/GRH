import { inject } from '@angular/core';
import { ResolveFn } from '@angular/router';
import { RemboursementService } from '../service/remboursement.service';
import { DemandeRemb } from '../model/demande-remb.model';
import { catchError, of } from 'rxjs';

export const demandesListResolver: ResolveFn<DemandeRemb[]> = (route, state) => {
    const service = inject(RemboursementService);
    return service.getDemandes().pipe(
        catchError(err => {
            console.error('Erreur chargement demandes', err);
            return of([]);
        })
    );
};
