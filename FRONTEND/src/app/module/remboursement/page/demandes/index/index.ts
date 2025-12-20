import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { RemboursementService } from '../../../service/remboursement.service';
import { LayoutService } from '../../../../../shared/layout/service/layout.service';
import { DemandeRemb } from '../../../model/demande-remb.model';

@Component({
    selector: 'app-demandes-index',
    standalone: true,
    imports: [CommonModule, FormsModule],
    templateUrl: './index.html',
    styleUrls: ['./index.scss']
})
export class DemandesIndexComponent implements OnInit {
    private readonly route = inject(ActivatedRoute);
    private readonly router = inject(Router);
    private readonly rembService = inject(RemboursementService);
    private readonly layoutService = inject(LayoutService);

    demandes: DemandeRemb[] = [];
    loading = false;
    errorMsg = '';

    // Filtres
    filter = {
        emp_code: '',
        mois: new Date().getMonth() + 1,
        annee: new Date().getFullYear()
    };

    ngOnInit() {
        this.layoutService.setTitle('Demandes de Remboursement');
        this.route.data.subscribe(data => {
            this.demandes = data['demandes'] || [];
            if (!this.demandes.length) {
                this.loadDemandes();
            }
        });
    }

    loadDemandes() {
        this.loading = true;
        const params: any = {};
        if (this.filter.emp_code) {
            params.emp_code = this.filter.emp_code;
        }
        this.rembService.getDemandes(params).subscribe({
            next: (list) => {
                this.demandes = list || [];
                this.loading = false;
            },
            error: () => {
                this.errorMsg = 'Erreur lors du chargement';
                this.loading = false;
            }
        });
    }

    goToCreate() {
        this.router.navigate(['/remboursement/demandes/create']);
    }

    viewDetail(id: number) {
        this.router.navigate(['/remboursement/demandes', id]);
    }

    downloadPdf(id: number) {
        this.rembService.downloadPdf(id).subscribe(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `remboursement_${id}.pdf`;
            a.click();
            window.URL.revokeObjectURL(url);
        });
    }

    downloadEtatAgent() {
        const emp = parseInt(this.filter.emp_code, 10);
        if (!emp) {
            alert('Veuillez saisir le code employé (emp_code) pour générer l’état.');
            return;
        }
        this.rembService.downloadEtatAgentPdf(emp, this.filter.annee, this.filter.mois).subscribe(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `etat_remboursement_agent_${emp}_${this.filter.annee}_${this.filter.mois}.pdf`;
            a.click();
            window.URL.revokeObjectURL(url);
        }, () => {
            this.errorMsg = 'Erreur lors du téléchargement de l’état';
        });
    }

    getStatutClass(etat: string): string {
        switch (etat) {
            case 'EN_ATTENTE': return 'badge-warning';
            case 'VALIDE_RRH':
            case 'VALIDE_DAAF': return 'badge-info';
            case 'ENGAGE': return 'badge-primary';
            case 'PAYE': return 'badge-success';
            case 'REFUSE': return 'badge-danger';
            default: return 'badge-secondary';
        }
    }
}
