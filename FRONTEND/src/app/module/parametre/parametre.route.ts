import { Routes } from '@angular/router';
import { ParametreIndexComponent } from './page/index/index';

export const parametreRoutes: Routes = [
    {
        path: 'index',
        component: ParametreIndexComponent
    },
    {
        path: '',
        redirectTo: 'index',
        pathMatch: 'full'
    }
];
