import { Routes } from '@angular/router';
import { HomeComponent } from './module/home/page/index';
import { authGuard } from './cors/guards/auth-guard';
import { LayoutComponent } from './shared/layout/layout';
import { dashboardResolver } from './module/home/resolvers/dashboard.resolver';
import { dashboardCarriereResolver } from './module/home/resolvers/dashboard-carriere.resolver';

export const routes: Routes = [
  {
    path: '',
    redirectTo: '/auth/login',
    pathMatch: 'full'
  },
  // Page de choix d'application (après login)
  {
    path: 'dashboard',
    loadComponent: () => import('./module/app-chooser/app-chooser.component').then(m => m.AppChooserComponent),
    canActivate: [authGuard]
  },
  // ===== APPLICATION GRH =====
  {
    path: '',
    component: LayoutComponent,
    canActivate: [authGuard],
    children: [
      {
        path: 'home',
        component: HomeComponent,
        resolve: { dashboardData: dashboardResolver },
        data: { breadcrumb: 'Tableau de bord' }
      },
      {
        path: 'home-carriere',
        loadComponent: () => import('./module/home/page/index-carriere/index').then(m => m.HomeCarriereComponent),
        resolve: { dashboardData: dashboardCarriereResolver },
        data: { breadcrumb: 'Tableau de bord (Carrière)' }
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
      },
      // ===== MODULES CARRIÈRE-STAGIAIRE =====
      {
        path: 'postes',
        loadChildren: () => import('./module/poste/poste.route').then(m => m.posteRoutes),
        data: { breadcrumb: 'Postes' }
      },
      {
        path: 'employes',
        loadChildren: () => import('./module/employe/employe.route').then(m => m.employeRoutes),
        data: { breadcrumb: 'Employés (Carrière)' }
      },
      {
        path: 'affectations',
        loadChildren: () => import('./module/affectation/affectation.route').then(m => m.AFFECTATION_ROUTES),
        data: { breadcrumb: 'Affectations' }
      },
      {
        path: 'stagiaires',
        loadChildren: () => import('./module/stagiaire/stagiaire.route').then(m => m.STAGIAIRE_ROUTES),
        data: { breadcrumb: 'Stagiaires' }
      },
      {
        path: 'stages',
        loadChildren: () => import('./module/stage/stage.route').then(m => m.STAGE_ROUTES),
        data: { breadcrumb: 'Stages' }
      },
      {
        path: 'etablissements',
        loadChildren: () => import('./module/etablissement/etablissement.route').then(m => m.ETABLISSEMENT_ROUTES),
        data: { breadcrumb: 'Établissements' }
      },
      {
        path: 'competences',
        loadChildren: () => import('./module/competence/competence.route').then(m => m.COMPETENCE_ROUTES),
        data: { breadcrumb: 'Compétences' }
      },
      {
        path: 'documents',
        loadChildren: () => import('./module/document/document.route').then(m => m.DOCUMENT_ROUTES),
        data: { breadcrumb: 'Documents' }
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
    redirectTo: '/dashboard'
  }
];

