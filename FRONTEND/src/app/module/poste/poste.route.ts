import { Routes } from '@angular/router';
import { ListerPostesComponent } from './page/lister/lister';
import { PosteDetailComponent } from './page/detail/detail.component';
import { PostesResolver } from './resolver/poste-resolver';

export const posteRoutes: Routes = [
  {
    path: '',
    redirectTo: 'lister',
    pathMatch: 'full'
  },
  {
    path: 'lister',
    component: ListerPostesComponent,
    resolve: { postes: PostesResolver }
  },
  {
    path: ':id',
    component: PosteDetailComponent
  }
];
