import { Routes } from '@angular/router';
import { EtablissementListComponent } from './page/lister/etablissement-list.component';
import { EtablissementResolver } from './resolver/etablissement.resolver';

export const ETABLISSEMENT_ROUTES: Routes = [
    {
        path: '',
        component: EtablissementListComponent,
        resolve: { etablissements: EtablissementResolver }
    }
];
