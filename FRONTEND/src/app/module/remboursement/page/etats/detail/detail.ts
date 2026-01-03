import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { EtatRembService, EtatRemb } from '../../../service/etat-remb.service';
import { RemboursementService } from '../../../service/remboursement.service';
import { LayoutService } from '../../../../../shared/layout/service/layout.service';

@Component({
    selector: 'app-detail-etat',
    standalone: true,
    imports: [CommonModule],
    templateUrl: './detail.html',
    styleUrls: ['./detail.scss']
})
export class DetailEtatComponent {
    private readonly route = inject(ActivatedRoute);
    private readonly router = inject(Router);
    private readonly etatService = inject(EtatRembService);
    private readonly rembService = inject(RemboursementService);
    private readonly layoutService = inject(LayoutService);

    etat = signal<any>(null);
    demandes = signal<any[]>([]);
    loading = signal(true);
    errorMsg = signal('');

    ngOnInit() {
        this.layoutService.setTitle('Détail État de Remboursement');
        const id = this.route.snapshot.params['id'];
        this.loadDetail(+id);
    }

    loadDetail(etaCode: number) {
        this.loading.set(true);

        console.log('[DEBUG] Loading état details for eta_code:', etaCode, typeof etaCode);

        // Load état details and associated demands
        this.rembService.getDemandes().subscribe({
            next: (allDemandes: any[]) => {
                console.log('[DEBUG] All demandes:', allDemandes);
                console.log('[DEBUG] First demande eta_code:', allDemandes[0]?.eta_code, typeof allDemandes[0]?.eta_code);

                // Filter demands for this état (loose comparison to handle string/number mismatch)
                const demandesForEtat = allDemandes.filter(d => d.eta_code == etaCode);
                console.log('[DEBUG] Filtered demandes for état', etaCode, ':', demandesForEtat);

                this.demandes.set(demandesForEtat);

                // Calculate état summary
                const total = demandesForEtat.reduce((sum, d) => sum + (d.rem_montant || 0), 0);

                this.etat.set({
                    eta_code: etaCode,
                    etat_num: demandesForEtat[0]?.etat_num || `État #${etaCode}`,
                    eta_total: total,
                    count: demandesForEtat.length,
                    emp_nom: demandesForEtat[0]?.nom_emp,
                    emp_prenom: demandesForEtat[0]?.prenom_emp
                });

                this.loading.set(false);
            },
            error: (err) => {
                console.error('[DEBUG] Error loading demandes:', err);
                this.errorMsg.set('Erreur chargement des détails');
                this.loading.set(false);
            }
        });
    }

    downloadPdf() {
        const etaCode = this.etat()?.eta_code;
        if (!etaCode) return;
        window.open(`${this.etatService.baseUrl}/${etaCode}/pdf`, '_blank');
    }

    viewDemande(remCode: number) {
        this.router.navigate(['/remboursement/demandes', remCode]);
    }

    goBack() {
        this.router.navigate(['/remboursement/etats']);
    }

    getStatutLabel(demande: any): string {
        if (demande.rem_status === true) return 'Traité';
        if (demande.rem_status === false) return 'En attente';
        return 'Inconnu';
    }

    getStatutClass(demande: any): string {
        if (demande.rem_status === true) return 'status valide';
        if (demande.rem_status === false) return 'status attente';
        return 'status';
    }
}
