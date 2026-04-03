import { Routes } from '@angular/router';
import { CreerAffectationComponent } from './page/creer/creer-affectation.component';
import { ListerAffectationsComponent } from './page/lister/lister-affectations.component';
import { AffectationResolver } from './resolver/affectation.resolver';

export const AFFECTATION_ROUTES: Routes = [
    {
        path: '',
        component: ListerAffectationsComponent,
        resolve: { affectations: AffectationResolver }
    },
    {
        path: 'creer',
        component: CreerAffectationComponent
    }
];
