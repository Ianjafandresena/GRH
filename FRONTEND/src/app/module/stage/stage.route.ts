import { Routes } from '@angular/router';
import { ListerStagesComponent } from './page/lister/lister-stages.component';
import { CreerStageComponent } from './page/creer/creer-stage.component';
import { StageResolver } from './resolver/stage.resolver';

export const STAGE_ROUTES: Routes = [
    {
        path: '',
        component: ListerStagesComponent,
        resolve: { stages: StageResolver }
    },
    {
        path: 'creer',
        component: CreerStageComponent
    },
    {
        path: 'modifier/:id',
        component: CreerStageComponent
    }
];
