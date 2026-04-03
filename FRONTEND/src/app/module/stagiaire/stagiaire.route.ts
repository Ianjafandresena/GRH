import { Routes } from '@angular/router';
import { ListerStagiairesComponent } from './page/lister/lister-stagiaires.component';
import { CreerStagiaireComponent } from './page/creer/creer-stagiaire.component';
import { StagiaireResolver } from './resolver/stagiaire.resolver';

export const STAGIAIRE_ROUTES: Routes = [
    {
        path: '',
        component: ListerStagiairesComponent,
        resolve: { stagiaires: StagiaireResolver }
    },
    {
        path: 'creer',
        component: CreerStagiaireComponent
    }
];
