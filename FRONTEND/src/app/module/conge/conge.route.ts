import { Routes } from '@angular/router';
import { AjoutCongeComponent } from './page/ajout/ajout';
import { CongeIndexComponent } from './page/index/index'; // à créer/importer

export const congeRoutes: Routes = [
  { path: 'create', component: AjoutCongeComponent },
  { path: 'index', component: CongeIndexComponent },
  { path: '', redirectTo: 'index', pathMatch: 'full' }
];
