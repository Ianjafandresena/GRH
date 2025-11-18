import { Routes } from '@angular/router';
import { HomeComponent } from './module/home/page/index';
import { authGuard } from './cors/guards/auth-guard';

export const routes: Routes = [
  
  {
    path: '',
    redirectTo: '/auth/login',
    pathMatch: 'full'
  },
  
  {
    path: 'home',
    component: HomeComponent,
    canActivate: [authGuard]  
  },

  {
    path: 'auth',
    loadChildren: () => import('./module/auth/auth.route').then(m => m.authRoutes)
  },

   {
    path: 'conge',
    loadChildren: () => import('./module/conge/conge.route').then(m => m.congeRoutes),
    canActivate: [authGuard]
  },
 
 
  {
    path: '**',
    redirectTo: '/home'
  }
];
