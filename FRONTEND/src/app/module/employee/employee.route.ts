import { Routes } from '@angular/router';
import { EmployeeComponent } from './page/employee/employee';
import { EmployeeDetailComponent } from './page/detail/detail';
import { employeeListResolver } from './resolvers/employee-list.resolver';

export const employeeRoutes: Routes = [
    {
        path: '',
        component: EmployeeComponent,
        resolve: { employees: employeeListResolver },
        data: { breadcrumb: 'Liste des employés' }
    },
    {
        path: 'family-list',
        loadComponent: () => import('./page/family-list/family-list').then(m => m.FamilyListComponent),
        data: { breadcrumb: 'Liste des familles' }
    },
    {
        path: ':id',
        component: EmployeeDetailComponent,
        data: { breadcrumb: 'Profil Employé' }
    },
    {
        path: ':id/family',
        loadComponent: () => import('./page/family-mgmt/family-mgmt').then(m => m.FamilyMgmtComponent),
        data: { breadcrumb: 'Gestion de la famille' }
    }
];
