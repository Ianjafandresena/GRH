// src/app/auth/auth.routes.ts

import { Routes } from '@angular/router';
import { LoginComponent } from './page/login/login';
import { Register } from './page/register/register';
import { Modification } from './page/modification/modification';
// import { AuthResolver } from './resolvers/auth.resolver';

export const authRoutes: Routes = [
  {
    path: '',
    children: [
      {
        path: 'login',
        component: LoginComponent,
        // resolve: { user: AuthResolver } // Si tu veux pré-résoudre l’utilisateur par exemple
      },

      {
        path: 'register',
        component: Register
      },
      {
        path: 'modification',
        component: Modification,
        // resolve: { user: AuthResolver } // Exemple : modification d’un profil/resolved
      },
      {
        path: '',
        redirectTo: 'login',
        pathMatch: 'full'
      }
    ]
  }
];
