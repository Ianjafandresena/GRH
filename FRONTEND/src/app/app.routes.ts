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
    canActivate: [authGuard]  // Protection par le Guard
  },

  {
    path: 'auth',
    loadChildren: () => import('./module/auth/auth.route').then(m => m.authRoutes)
  },
  // Route 404
  {
    path: '**',
    redirectTo: '/home'
  }
];
