import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { EtatRembService } from '../../../service/etat-remb.service';
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

    // Workflow state
    processing = signal(false);
    showConfirmModal = signal(false);
    confirmAction: 'mandater' | 'agentComptable' | null = null;
    confirmTitle = '';
    confirmMessage = '';

    constructor() {
        this.route.paramMap.pipe(takeUntilDestroyed()).subscribe(params => {
            const id = params.get('id');
            if (id) this.loadDetail(+id);
        });
    }

    ngOnInit() {
        this.layoutService.setTitle('Détail État de Remboursement');
    }

    loadDetail(etaCode: number) {
        this.loading.set(true);

        this.etatService.getById(etaCode).subscribe({
            next: (etat) => {
                this.etat.set({
                    ...etat,
                    count: etat.nb_demandes || 0
                });

                // Fetch associated demands
                this.rembService.getDemandes().subscribe({
                    next: (allDemandes: any[]) => {
                        this.demandes.set(allDemandes.filter(d => d.eta_code == etaCode));
                        this.loading.set(false);
                    },
                    error: () => {
                        this.errorMsg.set('Erreur chargement des demandes');
                        this.loading.set(false);
                    }
                });
            },
            error: () => {
                this.errorMsg.set('Erreur chargement de l\'état');
                this.loading.set(false);
            }
        });
    }

    openConfirm(action: 'mandater' | 'agentComptable') {
        this.confirmAction = action;
        if (action === 'mandater') {
            this.confirmTitle = 'Mandater l\'État';
            this.confirmMessage = 'Confirmez-vous le mandatement de cet état ?';
        } else {
            this.confirmTitle = 'Agent Comptable';
            this.confirmMessage = 'Envoyer cet état à l\'agent comptable ?';
        }
        this.showConfirmModal.set(true);
    }

    closeConfirm() {
        this.showConfirmModal.set(false);
        this.confirmAction = null;
    }

    onConfirm() {
        const action = this.confirmAction;
        const id = this.etat().eta_code;
        if (!action) return;

        this.processing.set(true);
        this.closeConfirm();

        const request = action === 'mandater'
            ? this.etatService.mandater(id)
            : this.etatService.agentComptable(id);

        request.subscribe({
            next: (res) => {
                this.etat.update(e => ({ ...e, eta_libelle: res.status }));
                this.processing.set(false);
                this.layoutService.showSuccessMessage(res.message || 'Action réussie');
            },
            error: (err) => {
                this.processing.set(false);
                const msg = err.error?.message || 'Erreur lors de l\'action';
                this.layoutService.showErrorMessage(msg);
            }
        });
    }

    getStepStatus(step: number): 'completed' | 'current' | 'pending' {
        const status = this.etat()?.eta_libelle;

        if (step === 1) { // Mandater
            if (status === 'MANDATE' || status === 'AGENT_COMPTABLE') return 'completed';
            return 'current';
        }

        if (step === 2) { // Agent Comptable
            if (status === 'AGENT_COMPTABLE') return 'completed';
            if (status === 'MANDATE') return 'current';
            return 'pending';
        }

        return 'pending';
    }

    downloadPdf() {
        const etaCode = this.etat()?.eta_code;
        if (!etaCode) return;
        window.open(`${this.etatService.baseUrl}/${etaCode}/pdf`, '_blank');
    }

    downloadExcel() {
        const id = this.etat()?.eta_code;
        if (!id) return;
        
        this.etatService.exportExcel(id).subscribe(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            const filename = (this.etat()?.etat_num || `etat_${id}`).replace(/\//g, '_');
            a.download = `${filename}.xls`;
            a.click();
            window.URL.revokeObjectURL(url);
        });
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
