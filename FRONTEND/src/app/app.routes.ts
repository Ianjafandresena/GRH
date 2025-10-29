import { Routes } from '@angular/router';
import { LoginComponent } from './auth/page/login/login';
import { HomeComponent } from './home/page/index';
import { authGuard } from './auth/helpers/guards/auth-guard';

export const routes: Routes = [
  
  {
    path: '',
    redirectTo: '/home',
    pathMatch: 'full'
  },
  
  // Route de connexion (publique)
  {
    path: 'login',
    component: LoginComponent
  },
  
  // Route du home (protégée par le Guard)
  {
    path: 'home',
    component: HomeComponent,
    canActivate: [authGuard]  // Protection par le Guard
  },
  
  // Route 404
  {
    path: '**',
    redirectTo: '/home'
  }
];
