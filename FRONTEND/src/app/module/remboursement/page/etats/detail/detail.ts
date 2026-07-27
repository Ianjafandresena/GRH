import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { PdfPreviewDialogComponent } from '../../../../document/component/pdf-preview/pdf-preview-dialog.component';
import { EtatRembService } from '../../../service/etat-remb.service';
import { RemboursementService } from '../../../service/remboursement.service';
import { LayoutService } from '../../../../../shared/layout/service/layout.service';

@Component({
    selector: 'app-detail-etat',
    standalone: true,
    imports: [CommonModule, MatDialogModule],
    templateUrl: './detail.html',
    styleUrls: ['./detail.scss']
})
export class DetailEtatComponent {
    private readonly route = inject(ActivatedRoute);
    private readonly router = inject(Router);
    private readonly etatService = inject(EtatRembService);
    private readonly rembService = inject(RemboursementService);
    private readonly layoutService = inject(LayoutService);
    private readonly dialog = inject(MatDialog);

    etat = signal<any>(null);
    demandes = signal<any[]>([]);
    loading = signal(true);
    errorMsg = signal('');

    // Workflow state
    processing = signal(false);
    showConfirmModal = signal(false);
    confirmAction: 'mandater' | 'agentComptable' | 'validerPaiement' | 'retourCorrection' | null = null;
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

    openConfirm(action: 'mandater' | 'agentComptable' | 'validerPaiement' | 'retourCorrection') {
        this.confirmAction = action;
        if (action === 'mandater') {
            this.confirmTitle = 'Mandater l\'État';
            this.confirmMessage = 'Confirmez-vous le mandatement de cet état ?';
        } else if (action === 'agentComptable') {
            this.confirmTitle = 'Transmission Comptable';
            this.confirmMessage = 'Transmettre cet état à l\'agent comptable ?';
        } else if (action === 'validerPaiement') {
            this.confirmTitle = 'Validation du Paiement';
            this.confirmMessage = 'Confirmer que le paiement a été effectué pour cet état ?';
        } else if (action === 'retourCorrection') {
            this.confirmTitle = 'Retour pour Correction';
            this.confirmMessage = 'Retourner cet état au service RH pour correction ?';
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

        let request;
        switch (action) {
            case 'mandater': request = this.etatService.mandater(id); break;
            case 'agentComptable': request = this.etatService.agentComptable(id); break;
            case 'validerPaiement': request = this.etatService.validerPaiement(id); break;
            case 'retourCorrection': request = this.etatService.retourCorrection(id); break;
        }

        if (!request) return;

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
            if (['MANDATE', 'AGENT_COMPTABLE', 'TRAITE'].includes(status)) return 'completed';
            return 'current';
        }

        if (step === 2) { // Transmission Comptable
            if (['AGENT_COMPTABLE', 'TRAITE'].includes(status)) return 'completed';
            if (status === 'MANDATE') return 'current';
            return 'pending';
        }

        if (step === 3) { // Validation Paiement
            if (status === 'TRAITE') return 'completed';
            if (status === 'AGENT_COMPTABLE') return 'current';
            return 'pending';
        }

        if (step === 4) { // Clôture / Traité
            if (status === 'TRAITE') return 'completed';
            return 'pending';
        }

        return 'pending';
    }

    downloadPdf() {
        const etaCode = this.etat()?.eta_code;
        if (!etaCode) return;
        const url = `${this.etatService.baseUrl}/${etaCode}/pdf`;
        
        this.dialog.open(PdfPreviewDialogComponent, {
            width: '800px',
            maxWidth: '95vw',
            data: {
                pdfUrl: url,
                filename: `etat_remboursement_${etaCode}.pdf`,
                title: 'État de Remboursement'
            }
        });
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
