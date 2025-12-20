import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { RemboursementService } from '../../../service/remboursement.service';
import { LayoutService } from '../../../../../shared/layout/service/layout.service';

@Component({
    selector: 'app-detail-demande',
    standalone: true,
    imports: [CommonModule],
    templateUrl: './detail.html',
    styleUrls: ['./detail.scss']
})
export class DetailDemandeComponent {
    private readonly route = inject(ActivatedRoute);
    private readonly router = inject(Router);
    private readonly rembService = inject(RemboursementService);
    private readonly layoutService = inject(LayoutService);

    demande: any = null;
    historique: any[] = [];
    loading = true;
    errorMsg = '';

    ngOnInit() {
        this.layoutService.setTitle('Détail Demande');
        const id = this.route.snapshot.params['id'];
        this.loadDetail(+id);
    }

    loadDetail(id: number) {
        this.rembService.getDemande(id).subscribe({
            next: (data: any) => {
                this.demande = data.demande;
                this.historique = data.historique || [];
                this.loading = false;
            },
            error: () => {
                this.errorMsg = 'Erreur chargement';
                this.loading = false;
            }
        });
    }

    // Actions selon l'état actuel
    validerRRH() {
        if (!this.demande) return;
        this.rembService.validerRRH(this.demande.rem_code, 'APPROUVE').subscribe({
            next: (res: any) => {
                this.demande = res.demande;
                alert(res.message);
                this.loadDetail(this.demande.rem_code);
            },
            error: (err: any) => this.errorMsg = err.error?.message || 'Erreur validation RRH'
        });
    }

    validerDAAF() {
        if (!this.demande) return;
        this.rembService.validerDAAF(this.demande.rem_code, 'APPROUVE').subscribe({
            next: (res: any) => {
                this.demande = res.demande;
                alert(res.message);
                this.loadDetail(this.demande.rem_code);
            },
            error: (err: any) => this.errorMsg = err.error?.message || 'Erreur validation DAAF'
        });
    }

    engager() {
        if (!this.demande) return;
        this.rembService.engager(this.demande.rem_code).subscribe({
            next: (res: any) => {
                this.demande = res.demande;
                alert(`Engagement créé: ${res.num_engagement}`);
                this.loadDetail(this.demande.rem_code);
            },
            error: (err: any) => this.errorMsg = err.error?.message || 'Erreur engagement'
        });
    }

    payer() {
        if (!this.demande) return;
        this.rembService.payer(this.demande.rem_code).subscribe({
            next: (res: any) => {
                this.demande = res.demande;
                alert(res.message);
                this.loadDetail(this.demande.rem_code);
            },
            error: (err: any) => this.errorMsg = err.error?.message || 'Erreur paiement'
        });
    }

    rejeter() {
        if (!this.demande) return;
        const motif = prompt('Motif du rejet:');
        if (!motif) return;

        this.rembService.rejeter(this.demande.rem_code, motif).subscribe({
            next: (res: any) => {
                this.demande = res.demande;
                alert(res.message);
                this.loadDetail(this.demande.rem_code);
            },
            error: (err: any) => this.errorMsg = err.error?.message || 'Erreur rejet'
        });
    }

    downloadPdf() {
        if (!this.demande) return;
        this.rembService.downloadPdf(this.demande.rem_code).subscribe((blob: Blob) => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `demande_remboursement_${this.demande.rem_code}.pdf`;
            a.click();
        });
    }

    goBack() {
        this.router.navigate(['/remboursement/demandes']);
    }
}
