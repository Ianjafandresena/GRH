import { Routes } from '@angular/router';
import { centresListResolver } from './resolvers/centres-list.resolver';
import { demandesListResolver } from './resolvers/demandes-list.resolver';

export const remboursementRoutes: Routes = [
    // Centres de santé
    {
        path: 'centres',
        loadComponent: () => import('./page/centres/index/index').then(m => m.CentresIndexComponent),
        resolve: { centres: centresListResolver }
    },
    {
        path: 'centres/create',
        loadComponent: () => import('./page/centres/ajout/ajout').then(m => m.AjoutCentreComponent)
    },
    {
        path: 'centres/:id/edit',
        loadComponent: () => import('./page/centres/ajout/ajout').then(m => m.AjoutCentreComponent)
    },

    // Demandes de remboursement
    {
        path: 'demandes',
        loadComponent: () => import('./page/demandes/index/index').then(m => m.DemandesIndexComponent),
        resolve: { demandes: demandesListResolver }
    },
    {
        path: 'demandes/create',
        loadComponent: () => import('./page/demandes/ajout/ajout').then(m => m.AjoutDemandeComponent)
    },
    {
        path: 'demandes/:id',
        loadComponent: () => import('./page/demandes/detail/detail').then(m => m.DetailDemandeComponent)
    },

    // Prises en charge
    {
        path: 'prises-en-charge',
        loadComponent: () => import('./page/prises-en-charge/index/index').then(m => m.PrisesEnChargeIndexComponent)
    },
    {
        path: 'prises-en-charge/create',
        loadComponent: () => import('./page/prises-en-charge/ajout/ajout').then(m => m.AjoutPriseEnChargeComponent)
    },
    {
        path: 'prises-en-charge/:id',
        loadComponent: () => import('./page/prises-en-charge/detail/detail').then(m => m.DetailPriseEnChargeComponent)
    },

    // Redirect par défaut
    { path: '', redirectTo: 'demandes', pathMatch: 'full' }
];
