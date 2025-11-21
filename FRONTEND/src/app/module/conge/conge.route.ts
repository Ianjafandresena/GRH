import { Routes } from '@angular/router';
import { AjoutCongeComponent } from './page/ajout/ajout';
import { CongeIndexComponent } from './page/index/index';
import { congeListResolver } from './resolvers/conge-list.resolver';

export const congeRoutes: Routes = [
  { path: 'create', component: AjoutCongeComponent },
  { path: 'index', component: CongeIndexComponent, resolve: { conges: congeListResolver } },
  { path: '', redirectTo: 'index', pathMatch: 'full' }
];
