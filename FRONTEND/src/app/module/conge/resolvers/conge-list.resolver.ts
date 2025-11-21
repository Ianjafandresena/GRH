import { inject } from '@angular/core';
import { ResolveFn } from '@angular/router';
import { of } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { CongeService } from '../service/conge.service';

export const congeListResolver: ResolveFn<any[]> = () => {
  const service = inject(CongeService);
  return service.getConges().pipe(catchError(() => of([])));
};
