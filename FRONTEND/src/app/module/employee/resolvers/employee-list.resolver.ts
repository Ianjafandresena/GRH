import { inject } from '@angular/core';
import { ResolveFn } from '@angular/router';
import { EmployeeService } from '../service/employee.service';
import { CongeService } from '../../conge/service/conge.service';
import { forkJoin, of } from 'rxjs';
import { catchError } from 'rxjs/operators';

export const employeeListResolver: ResolveFn<any> = (route, state) => {
    const employeeService = inject(EmployeeService);
    const congeService = inject(CongeService);

    return forkJoin({
        employees: employeeService.getEmployees().pipe(catchError(() => of([]))),
        absences: congeService.getAbsences().pipe(catchError(() => of([])))
    });
};
