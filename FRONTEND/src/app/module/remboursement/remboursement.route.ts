import { Routes } from '@angular/router';
import { inject } from '@angular/core';
import { centresListResolver } from './resolvers/centres-list.resolver';
import { demandesListResolver } from './resolvers/demandes-list.resolver';
import { EtatRembService } from './service/etat-remb.service';

export const remboursementRoutes: Routes = [
    // Centres de santé
    {
        path: 'centres',
        loadComponent: () => import('./page/centres/index/index').then(m => m.CentresIndexComponent),
        resolve: { centres: centresListResolver },
        data: { breadcrumb: 'Centres de santé' }
    },
    {
        path: 'centres/create',
        loadComponent: () => import('./page/centres/ajout/ajout').then(m => m.AjoutCentreComponent),
        data: { breadcrumb: 'Nouveau centre' }
    },
    {
        path: 'centres/:id/edit',
        loadComponent: () => import('./page/centres/ajout/ajout').then(m => m.AjoutCentreComponent),
        data: { breadcrumb: 'Modification' }
    },

    // Demandes de remboursement
    {
        path: 'demandes',
        loadComponent: () => import('./page/demandes/index/index').then(m => m.DemandesIndexComponent),
        resolve: { demandes: demandesListResolver },
        data: { breadcrumb: 'Gestion des demandes' }
    },
    {
        path: 'demandes/create',
        loadComponent: () => import('./page/demandes/ajout/ajout').then(m => m.AjoutDemandeComponent),
        data: { breadcrumb: 'Création de demande' }
    },
    {
        path: 'demandes/:id',
        loadComponent: () => import('./page/demandes/detail/detail').then(m => m.DetailDemandeComponent),
        data: { breadcrumb: 'Détails' }
    },

    // États de Remboursement
    {
        path: 'etats',
        loadComponent: () => import('./page/etats/index/index').then(m => m.EtatsIndexComponent),
        resolve: { etats: () => inject(EtatRembService).getAll() },
        data: { breadcrumb: 'États' }
    },
    {
        path: 'etats/:id',
        loadComponent: () => import('./page/etats/detail/detail').then(m => m.DetailEtatComponent),
        data: { breadcrumb: 'Détails' }
    },

    // Prises en charge
    {
        path: 'prises-en-charge',
        loadComponent: () => import('./page/prises-en-charge/index/index').then(m => m.PrisesEnChargeIndexComponent),
        data: { breadcrumb: 'Prises en charge' }
    },
    {
        path: 'prises-en-charge/create',
        loadComponent: () => import('./page/prises-en-charge/ajout/ajout').then(m => m.AjoutPriseEnChargeComponent),
        data: { breadcrumb: 'Création' }
    },
    {
        path: 'prises-en-charge/:id',
        loadComponent: () => import('./page/prises-en-charge/detail/detail').then(m => m.DetailPriseEnChargeComponent),
        data: { breadcrumb: 'Détails' }
    },

    // Redirect par défaut
    { path: '', redirectTo: 'demandes', pathMatch: 'full' }
];
