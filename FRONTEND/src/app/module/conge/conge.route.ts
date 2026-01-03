import { Routes } from '@angular/router';
import { AjoutCongeComponent } from './page/ajout/ajout';
import { CongeIndexComponent } from './page/index/index';
import { congeListResolver } from './resolvers/conge-list.resolver';

export const congeRoutes: Routes = [
  { path: 'create', component: AjoutCongeComponent },
  { path: 'index', component: CongeIndexComponent, resolve: { conges: congeListResolver } },
  { path: 'detail/:id', loadComponent: () => import('./page/detail/detail').then(m => m.DetailCongeComponent) },
  { path: 'viewer/:id', loadComponent: () => import('./page/viewer/viewer').then(m => m.ViewerCongeComponent) },
  { path: 'interruption/:id', loadComponent: () => import('./page/interruption/interruption').then(m => m.InterruptionComponent) },
  { path: 'validation', loadComponent: () => import('./page/validation/validation').then(m => m.ValidationComponent) },
  { path: 'etat', loadComponent: () => import('./page/etat-conge/etat-conge.component').then(m => m.EtatCongeComponent) },
  { path: '', redirectTo: 'index', pathMatch: 'full' }
];
