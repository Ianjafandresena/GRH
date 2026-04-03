import { inject } from '@angular/core';
import { ResolveFn } from '@angular/router';
import { CongeService } from '../service/conge.service';
import { catchError, of } from 'rxjs';

export const calendarInitialResolver: ResolveFn<any[]> = (route, state) => {
    const congeService = inject(CongeService);

    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();

    const startDate = new Date(year, month - 1, 20);
    const endDate = new Date(year, month + 1, 10);

    const formatDate = (date: Date) => {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    };

    return congeService.getConges({
        start: formatDate(startDate),
        end: formatDate(endDate)
    }).pipe(
        catchError(() => of([]))
    );
};
