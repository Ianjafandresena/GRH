import { Component, OnInit, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { SoldeCongeService, SoldeDetail } from '../../service/solde-conge.service';

@Component({
    selector: 'app-solde-tracking',
    standalone: true,
    imports: [CommonModule],
    templateUrl: './solde-tracking.component.html',
    styleUrls: ['./solde-tracking.component.scss']
})
export class SoldeTrackingComponent implements OnInit {
    @Input() empCode!: number;
    @Input() empNom!: string;
    @Input() empPrenom!: string;

    soldes: SoldeDetail[] = [];
    totalDisponible = 0;
    loading = false;
    error = '';

    constructor(private soldeService: SoldeCongeService) { }

    ngOnInit() {
        if (this.empCode) {
            this.loadSoldes();
        }
    }

    loadSoldes() {
        this.loading = true;
        this.error = '';

        this.soldeService.getSoldesByEmployee(this.empCode).subscribe({
            next: (data) => {
                this.soldes = data;
                this.totalDisponible = this.soldeService.getTotalDisponible(data);
                this.loading = false;
            },
            error: (err) => {
                console.error('Erreur chargement soldes:', err);
                this.error = 'Impossible de charger les soldes';
                this.loading = false;
            }
        });
    }

    getProgressPercent(solde: SoldeDetail): number {
        if (!solde.sld_initial) return 0;
        return (solde.sld_restant / solde.sld_initial) * 100;
    }

    getProgressColor(percent: number): string {
        if (percent > 70) return '#10b981'; // Vert
        if (percent > 30) return '#f59e0b'; // Orange
        return '#ef4444'; // Rouge
    }

    isOldBalance(annee: number): boolean {
        const currentYear = new Date().getFullYear();
        return annee < currentYear - 1;
    }
}
