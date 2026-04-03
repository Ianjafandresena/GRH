import { Routes } from '@angular/router';
import { CreateEmployeComponent } from './page/create/create-employe.component';
import { DetailEmployeComponent } from './page/detail/detail-employe.component';
import { ListerEmployesComponent } from './page/lister/lister';
import { ParcoursEmployeComponent } from './page/parcours/parcours-employe.component';
import { EmployeResolver } from './resolver/employe.resolver';

export const employeRoutes: Routes = [
    {
        path: '',
        redirectTo: 'lister',
        pathMatch: 'full'
    },
    {
        path: 'creer',
        component: CreateEmployeComponent
    },
    {
        path: 'create',
        redirectTo: 'creer',
        pathMatch: 'full'
    },
    {
        path: 'lister',
        component: ListerEmployesComponent,
        resolve: { employes: EmployeResolver }
    },
    {
        path: ':id/parcours',
        component: ParcoursEmployeComponent
    },
    {
        path: ':id',
        component: DetailEmployeComponent
    }
];
