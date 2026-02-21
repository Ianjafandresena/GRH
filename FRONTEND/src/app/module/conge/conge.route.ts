import { Routes } from '@angular/router';
import { AjoutCongeComponent } from './page/ajout/ajout';
import { CongeIndexComponent } from './page/index/index';
import { congeListResolver } from './resolvers/conge-list.resolver';

export const congeRoutes: Routes = [
  { path: 'create', component: AjoutCongeComponent, data: { breadcrumb: 'Nouvelle demande' } },
  { path: 'index', component: CongeIndexComponent, resolve: { conges: congeListResolver }, data: { breadcrumb: 'Liste' } },
  { path: 'calendar', loadComponent: () => import('./page/calendar/calendar.component').then(m => m.CongeCalendarComponent), data: { breadcrumb: 'Calendrier' } },
  { path: 'detail/:id', loadComponent: () => import('./page/detail/detail').then(m => m.DetailCongeComponent), data: { breadcrumb: 'Détails' } },
  { path: 'viewer/:id', loadComponent: () => import('./page/viewer/viewer').then(m => m.ViewerCongeComponent), data: { breadcrumb: 'Visualisation' } },
  { path: 'interruption/:id', loadComponent: () => import('./page/interruption/interruption').then(m => m.InterruptionComponent), data: { breadcrumb: 'Interruption' } },
  { path: 'validation', loadComponent: () => import('./page/validation/validation').then(m => m.ValidationComponent), data: { breadcrumb: 'Validation' } },
  { path: 'etat', loadComponent: () => import('./page/etat-conge/etat-conge.component').then(m => m.EtatCongeComponent), data: { breadcrumb: 'État des lieux' } },
  { path: '', redirectTo: 'index', pathMatch: 'full' }
];
