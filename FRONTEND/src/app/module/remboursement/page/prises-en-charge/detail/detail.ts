import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { PrisEnChargeService } from '../../../service/prise-en-charge.service';
import { PrisEnCharge } from '../../../model/prise-en-charge.model';

@Component({
    selector: 'app-detail-prise-en-charge',
    standalone: true,
    imports: [CommonModule, RouterModule],
    templateUrl: './detail.html',
    styleUrls: ['./detail.scss']
})
export class DetailPriseEnChargeComponent implements OnInit {
    private readonly route = inject(ActivatedRoute);
    private readonly router = inject(Router);
    private readonly service = inject(PrisEnChargeService);

    pec: PrisEnCharge | null = null;
    loading = false;
    errorMsg = '';

    ngOnInit() {
        const id = Number(this.route.snapshot.paramMap.get('id'));
        if (!id) {
            this.errorMsg = 'Identifiant invalide';
            return;
        }

        this.loading = true;
        this.service.get(id).subscribe({
            next: (data) => {
                this.pec = data;
                this.loading = false;
            },
            error: (err) => {
                this.errorMsg = "Erreur lors du chargement des détails.";
                this.loading = false;
            }
        });
    }

    downloadBulletin() {
        if (!this.pec?.pec_code) return;

        this.service.downloadBulletin(this.pec.pec_code).subscribe(blob => {
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `Bulletin_PEC_${this.pec?.pec_num || 'Inconnu'}.pdf`;
            link.click();
            window.URL.revokeObjectURL(url);
        });
    }

    goBack() {
        this.router.navigate(['/remboursement/prises-en-charge']);
    }
}
