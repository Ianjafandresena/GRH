import { inject } from '@angular/core';
import { ResolveFn } from '@angular/router';
import { DashboardService } from '../service/dashboard.service';
import { catchError } from 'rxjs/operators';
import { of } from 'rxjs';

export const dashboardCarriereResolver: ResolveFn<any> = (route, state) => {
    const dashboardService = inject(DashboardService);
    return dashboardService.getDashboardCarriereStats().pipe(
        catchError(() => of(null))
    );
};
