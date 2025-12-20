import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { ValidationCongeService, ValidationStatus } from '../../service/validation-conge.service';
import { LayoutService } from '../../../../shared/layout/service/layout.service';

interface PendingLeave {
    cng_code: number;
    emp_nom: string;
    emp_prenom: string;
    emp_imarmp: string;
    cng_debut: string;
    cng_fin: string;
    cng_nb_jour: number;
    typ_appelation: string;
    cng_demande: string;
    currentStep?: string;
}

@Component({
    selector: 'app-validation',
    standalone: true,
    imports: [CommonModule],
    templateUrl: './validation.html',
    styleUrls: ['./validation.scss']
})
export class ValidationComponent implements OnInit {
    private readonly router = inject(Router);
    private readonly validationService = inject(ValidationCongeService);
    private readonly layoutService = inject(LayoutService);

    pendingLeaves: PendingLeave[] = [];
    loading = false;
    errorMsg = '';
    successMsg = '';

    // Current user's sign_code (should come from auth service)
    currentSignCode: number = 1; // Default to CHEF for now

    ngOnInit() {
        this.layoutService.setTitle('Validation des Congés');
        this.loadPendingLeaves();
    }

    loadPendingLeaves() {
        this.loading = true;
        this.validationService.getPendingForSigner(this.currentSignCode).subscribe({
            next: (leaves) => {
                this.pendingLeaves = leaves;
                this.loading = false;
            },
            error: (err) => {
                this.errorMsg = err?.message || 'Erreur lors du chargement';
                this.loading = false;
            }
        });
    }

    openDetail(cngCode: number) {
        this.router.navigate(['/conge/detail', cngCode]);
    }

    approve(cngCode: number) {
        if (!confirm('Confirmer la validation de ce congé ?')) return;

        this.loading = true;
        this.validationService.validate(cngCode, this.currentSignCode).subscribe({
            next: (res) => {
                this.successMsg = res.message || 'Congé validé';
                this.loadPendingLeaves();
            },
            error: (err) => {
                this.errorMsg = err?.error?.messages?.error || 'Erreur lors de la validation';
                this.loading = false;
            }
        });
    }

    reject(cngCode: number) {
        const observation = prompt('Motif du rejet (optionnel):');
        if (observation === null) return;

        this.loading = true;
        this.validationService.reject(cngCode, this.currentSignCode, observation).subscribe({
            next: (res) => {
                this.successMsg = res.message || 'Congé rejeté';
                this.loadPendingLeaves();
            },
            error: (err) => {
                this.errorMsg = err?.error?.messages?.error || 'Erreur lors du rejet';
                this.loading = false;
            }
        });
    }

    clearMessages() {
        this.errorMsg = '';
        this.successMsg = '';
    }
}
