import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterModule, ActivatedRoute } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatSelectModule } from '@angular/material/select';
import { MatTableModule } from '@angular/material/table';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDividerModule } from '@angular/material/divider';
import { MatChipsModule } from '@angular/material/chips';
import { MatFormFieldModule } from '@angular/material/form-field';

import { DashboardService } from '../../service/dashboard.service';
import { LayoutService } from '../../../../shared/layout/service/layout.service';
import { ServiceService } from '../../../referentiel/service/service.service';

@Component({
    selector: 'app-home-carriere',
    standalone: true,
    imports: [
        CommonModule,
        RouterModule,
        FormsModule,
        MatCardModule,
        MatIconModule,
        MatButtonModule,
        MatSelectModule,
        MatTableModule,
        MatProgressSpinnerModule,
        MatDividerModule,
        MatChipsModule,
        MatFormFieldModule
    ],
    templateUrl: './index.html',
    styleUrls: ['./index.scss']
})
export class HomeCarriereComponent implements OnInit {
    private readonly router = inject(Router);
    private readonly route = inject(ActivatedRoute);
    private readonly dashboardService = inject(DashboardService);
    private readonly layoutService = inject(LayoutService);
    private readonly serviceService = inject(ServiceService);

    stats: any = null;
    services: any[] = [];
    loading = false;
    errorMessage = '';

    // Filtres
    selectedService: number | null = null;
    selectedPeriod = 'year';

    periods = [
        { value: 'month', label: 'Ce mois' },
        { value: 'quarter', label: 'Ce trimestre' },
        { value: 'year', label: 'Cette année' }
    ];

    ngOnInit() {
        this.layoutService.setTitle('Tableau de Bord Carrière');
        this.loadServices();

        // Récupérer les données du resolver
        const resolvedData = this.route.snapshot.data['dashboardData'];
        if (resolvedData) {
            this.stats = resolvedData;
        } else {
            this.loadStats();
        }
    }

    loadServices() {
        this.serviceService.getServices().subscribe({
            next: (data) => this.services = data,
            error: (err) => console.error('Erreur services:', err)
        });
    }

    loadStats() {
        this.loading = true;
        this.errorMessage = '';
        const filters: any = { period: this.selectedPeriod };
        if (this.selectedService) {
            filters.srvc_code = this.selectedService;
        }

        this.dashboardService.getDashboardCarriereStats(filters).subscribe({
            next: (data) => {
                this.stats = data;
                this.loading = false;
                this.errorMessage = '';
            },
            error: (err) => {
                console.error('Erreur chargement dashboard', err);
                this.loading = false;
                this.errorMessage = 'Erreur lors du chargement du dashboard.';
            }
        });
    }

    onFilterChange() {
        this.loadStats();
    }

    // --- Graph Helpers ---

    getDonutStyle(data: { name: string; value: number }[] | undefined): string {
        if (!data || data.length === 0) return 'conic-gradient(#f1f5f9 0 100%)';

        const total = data.reduce((sum, item) => sum + item.value, 0);
        if (total === 0) return 'conic-gradient(#f1f5f9 0 100%)';

        let currentPercentage = 0;
        const colors = ['#6366f1', '#a855f7', '#3b82f6', '#2dd4bf', '#f59e0b', '#ef4444'];

        const segments = data.map((item, index) => {
            const start = currentPercentage;
            const percent = (item.value / total) * 100;
            currentPercentage += percent;
            return `${colors[index % colors.length]} ${start}% ${currentPercentage}%`;
        });

        return `conic-gradient(${segments.join(', ')})`;
    }

    getBarHeight(value: number): number {
        if (!this.stats || !this.stats.charts || !this.stats.charts.evolution || this.stats.charts.evolution.length === 0) return 0;
        const max = Math.max(...this.stats.charts.evolution.map((d: any) => d.count), 1);
        return (value / max) * 100;
    }

    getGenderRatio(): { male: number; female: number } {
        if (!this.stats || !this.stats.charts || !this.stats.charts.sexe) return { male: 50, female: 50 };
        const male = this.getGenderCounts().male;
        const female = this.getGenderCounts().female;
        const total = male + female || 1;
        return {
            male: (male / total) * 100,
            female: (female / total) * 100
        };
    }

    getGenderCounts(): { male: number; female: number } {
        if (!this.stats || !this.stats.charts || !this.stats.charts.sexe) return { male: 0, female: 0 };
        return {
            male: this.stats.charts.sexe.find((s: any) => s.name === 'Homme')?.value || 0,
            female: this.stats.charts.sexe.find((s: any) => s.name === 'Femme')?.value || 0
        };
    }
}
