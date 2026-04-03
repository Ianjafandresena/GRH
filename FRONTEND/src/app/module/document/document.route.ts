import { Routes } from '@angular/router';
import { DocumentResolver } from './resolver/document.resolver';

export const DOCUMENT_ROUTES: Routes = [
    {
        path: 'demande',
        loadComponent: () => import('./page/demande/demande-document.component').then(m => m.DemandeDocumentComponent)
    },
    {
        path: 'demandes',
        loadComponent: () => import('./page/liste-demandes/liste-demandes.component').then(m => m.ListeDemandesComponent),
        resolve: { data: DocumentResolver }
    },
    {
        path: '',
        redirectTo: 'demande',
        pathMatch: 'full'
    }
];

