import { inject } from '@angular/core';
import { ResolveFn } from '@angular/router';
import { DashboardService } from '../service/dashboard.service';
import { forkJoin, of } from 'rxjs';
import { catchError } from 'rxjs/operators';

export const dashboardResolver: ResolveFn<any> = (route, state) => {
    const dashboardService = inject(DashboardService);
    return forkJoin({
        stats: dashboardService.getDashboardStats().pipe(catchError(() => of(null))),
        evolution: dashboardService.getEvolutionStats().pipe(catchError(() => of(null))),
        employeesOnLeave: dashboardService.getEmployeesOnLeave().pipe(catchError(() => of([]))),
        pendingRequests: dashboardService.getPendingReimbursements().pipe(catchError(() => of({ count: 0, total: 0 }))),
        recentActivity: dashboardService.getRecentActivity().pipe(catchError(() => of([]))),
        donutData: dashboardService.getReimbursementDistribution().pipe(catchError(() => of(null)))
    });
};
