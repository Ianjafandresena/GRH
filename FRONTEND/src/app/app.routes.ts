import { Routes } from '@angular/router';
import { HomeComponent } from './module/home/page/index';
import { authGuard } from './cors/guards/auth-guard';
import { LayoutComponent } from './shared/layout/layout';
import { dashboardResolver } from './module/home/resolvers/dashboard.resolver';

export const routes: Routes = [
  {
    path: '',
    redirectTo: '/auth/login',
    pathMatch: 'full'
  },
  {
    path: '',
    component: LayoutComponent,
    canActivate: [authGuard],
    children: [
      {
        path: 'home',
        component: HomeComponent,
        data: { breadcrumb: 'Tableau de bord' }
      },
      {
        path: 'employee',
        loadChildren: () => import('./module/employee/employee.route').then(m => m.employeeRoutes),
        data: { breadcrumb: 'Employés' }
      },
      {
        path: 'conge',
        loadChildren: () => import('./module/conge/conge.route').then(m => m.congeRoutes),
        data: { breadcrumb: 'Congés' }
      },
      {
        path: 'permission',
        loadChildren: () => import('./module/permission/permission.route').then(m => m.permissionRoutes),
        data: { breadcrumb: 'Permissions' }
      },
      {
        path: 'remboursement',
        loadChildren: () => import('./module/remboursement/remboursement.route').then(m => m.remboursementRoutes),
        data: { breadcrumb: 'Remboursements' }
      }
    ]
  },
  {
    path: 'auth',
    loadChildren: () => import('./module/auth/auth.route').then(m => m.authRoutes)
  },
  {
    path: 'parametre',
    component: LayoutComponent,
    canActivate: [authGuard],
    children: [
      { path: '', loadChildren: () => import('./module/parametre/parametre.route').then(m => m.parametreRoutes) }
    ]
  },
  {
    path: '**',
    redirectTo: '/home'
  }
];
