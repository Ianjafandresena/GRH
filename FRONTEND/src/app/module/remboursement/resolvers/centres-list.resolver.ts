import { inject } from '@angular/core';
import { ResolveFn } from '@angular/router';
import { CentreSanteService } from '../service/centre-sante.service';
import { CentreSante } from '../model/centre-sante.model';
import { catchError, of } from 'rxjs';

export const centresListResolver: ResolveFn<CentreSante[]> = (route, state) => {
    const service = inject(CentreSanteService);
    return service.getCentres().pipe(
        catchError(err => {
            console.error('Erreur chargement centres', err);
            return of([]);
        })
    );
};
