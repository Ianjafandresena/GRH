import { inject } from '@angular/core';
import { ResolveFn } from '@angular/router';
import { PrisEnChargeService } from '../service/prise-en-charge.service';
import { catchError, of } from 'rxjs';

export const pecListResolver: ResolveFn<any[]> = (route, state) => {
    const pecService = inject(PrisEnChargeService);
    return pecService.getAll().pipe(
        catchError(() => of([]))
    );
};
