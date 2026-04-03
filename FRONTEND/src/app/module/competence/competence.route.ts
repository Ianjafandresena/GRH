import { Routes } from '@angular/router';
import { ListerCompetencesComponent } from './page/lister/lister-competences.component';
import { CreerCompetenceComponent } from './page/creer/creer-competence.component';
import { CompetenceResolver } from './resolver/competence.resolver';

export const COMPETENCE_ROUTES: Routes = [
    {
        path: '',
        component: ListerCompetencesComponent,
        resolve: { competences: CompetenceResolver }
    },
    {
        path: 'creer',
        component: CreerCompetenceComponent
    },
    {
        path: 'modifier/:id',
        component: CreerCompetenceComponent
    }
];
