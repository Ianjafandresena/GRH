import { Routes } from '@angular/router';
import { EmployeeComponent } from './page/employee/employee';

export const employeeRoutes: Routes = [
    {
        path: '',
        component: EmployeeComponent,
        data: { breadcrumb: 'Liste des employés' }
    }
];
