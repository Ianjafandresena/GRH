import { Component, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { EtatRemb, EtatRembService } from '../../../service/etat-remb.service';
import { LayoutService } from '../../../../../shared/layout/service/layout.service';

@Component({
    selector: 'app-etats-index',
    standalone: true,
    imports: [CommonModule, FormsModule],
    templateUrl: './index.html',
    styleUrls: ['./index.scss']
})
export class EtatsIndexComponent {
    private readonly router = inject(Router);
    private readonly route = inject(ActivatedRoute);
    private readonly etatService = inject(EtatRembService);
    private readonly layoutService = inject(LayoutService);

    // Signals for reactive state management
    etats = signal<EtatRemb[]>([]);
    loading = signal(false);
    filter = signal({ emp_code: null as number | null });

    // Computed values
    filteredEtats = computed(() => {
        const etatsData = this.etats();
        const filterData = this.filter();

        if (!filterData.emp_code) return etatsData;

        return etatsData.filter(e => e.emp_code === filterData.emp_code);
    });

    totalAmount = computed(() => {
        return this.filteredEtats().reduce((sum, e) => sum + (e.eta_total || 0), 0);
    });

    ngOnInit() {
        this.layoutService.setTitle('États de Remboursement');

        // Load initial data from resolver
        const resolvedData = this.route.snapshot.data['etats'] as EtatRemb[];
        if (resolvedData) {
            this.etats.set(resolvedData);
        }
    }

    loadEtats() {
        this.loading.set(true);
        this.etatService.getAll().subscribe({
            next: (data) => {
                this.etats.set(data);
                this.loading.set(false);
            },
            error: () => {
                this.loading.set(false);
            }
        });
    }

    viewDetails(etaCode: number) {
        // Navigate to detailed view (to be implemented)
        this.router.navigate(['/remboursement/etats', etaCode]);
    }

    downloadPdf(etaCode: number) {
        window.open(`${this.etatService.baseUrl}/${etaCode}/pdf`, '_blank');
    }
}
