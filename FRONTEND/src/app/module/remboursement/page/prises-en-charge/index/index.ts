import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { MatIconModule } from '@angular/material/icon';
import { PrisEnChargeService } from '../../../service/prise-en-charge.service';
import { LayoutService } from '../../../../../shared/layout/service/layout.service';
import { PrisEnCharge } from '../../../model/prise-en-charge.model';

@Component({
    selector: 'app-prises-en-charge-index',
    standalone: true,
    imports: [CommonModule, MatIconModule],
    templateUrl: './index.html',
    styleUrls: ['./index.scss']
})
export class PrisesEnChargeIndexComponent {
    private readonly router = inject(Router);
    private readonly pecService = inject(PrisEnChargeService);
    private readonly layoutService = inject(LayoutService);

    prises: PrisEnCharge[] = [];
    loading = false;
    errorMsg = '';

    ngOnInit() {
        this.layoutService.setTitle('Prises en Charge');
        this.loadData();
    }

    loadData() {
        this.loading = true;
        this.pecService.getAll().subscribe({
            next: (list) => {
                this.prises = list || [];
                this.loading = false;
            },
            error: () => {
                this.errorMsg = 'Erreur chargement';
                this.loading = false;
            }
        });
    }

    goToCreate() {
        this.router.navigate(['/remboursement/prises-en-charge/create']);
    }

    goToDetail(id: number) {
        this.router.navigate(['/remboursement/prises-en-charge', id]);
    }

    downloadBulletin(id: number) {
        this.pecService.downloadBulletin(id).subscribe(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `prise_en_charge_${id}.pdf`;
            a.click();
        });
    }
}
