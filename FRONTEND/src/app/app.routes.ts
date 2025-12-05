import { Routes } from '@angular/router';
import { HomeComponent } from './module/home/page/index';
import { authGuard } from './cors/guards/auth-guard';
import { LayoutComponent } from './module/layout/layout';

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
      { path: 'home', component: HomeComponent },
      { path: 'conge', loadChildren: () => import('./module/conge/conge.route').then(m => m.congeRoutes) },
      { path: 'permission', loadChildren: () => import('./module/permission/permission.route').then(m => m.permissionRoutes) }
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
