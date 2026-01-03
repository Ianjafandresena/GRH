import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { CongeService } from '../../service/conge.service';
import { LayoutService } from '../../../../shared/layout/service/layout.service';

interface EmployeeSoldeState {
    emp_code: number;
    emp_nom: string;
    emp_prenom: string;
    emp_imarmp: string;
    direction: string;
    fonction: string;
    soldes: {
        annee: number;
        decision: string;
        initial: number;
        reste: number;
    }[];
}

@Component({
    selector: 'app-etat-conge',
    standalone: true,
    imports: [CommonModule, FormsModule],
    templateUrl: './etat-conge.component.html',
    styleUrls: ['./etat-conge.component.scss']
})
export class EtatCongeComponent implements OnInit {
    private readonly congeService = inject(CongeService);
    private readonly layoutService = inject(LayoutService);

    employees: EmployeeSoldeState[] = [];
    filteredEmployees: EmployeeSoldeState[] = [];

    // Filtres
    searchText = '';
    selectedYear: number | null = null;
    availableYears: number[] = [];

    loading = false;
    error = '';

    ngOnInit() {
        this.layoutService.setTitle('État de Congé');
        this.loadData();
    }

    loadData() {
        this.loading = true;
        this.error = '';

        // Charger années disponibles
        this.congeService.getAvailableYears().subscribe({
            next: (years) => {
                this.availableYears = years;
                this.selectedYear = years.length > 0 ? years[years.length - 1] : null;

                // Charger données employés
                this.loadEmployees();
            },
            error: (err) => {
                console.error('Erreur chargement années:', err);
                this.error = 'Impossible de charger les années disponibles';
                this.loading = false;
            }
        });
    }

    loadEmployees() {
        const params: any = {};
        if (this.selectedYear) params.year = this.selectedYear;
        if (this.searchText) params.search = this.searchText;

        this.congeService.getEtatConge(params).subscribe({
            next: (data) => {
                this.employees = data;
                this.filteredEmployees = data;
                this.loading = false;
            },
            error: (err) => {
                console.error('Erreur chargement employés:', err);
                this.error = 'Impossible de charger les données des employés';
                this.loading = false;
            }
        });
    }

    onYearChange() {
        this.loadEmployees();
    }

    onSearchChange() {
        this.loadEmployees();
    }

    getTotalInitial(employee: EmployeeSoldeState): number {
        return employee.soldes.reduce((sum, s) => sum + s.initial, 0);
    }

    getTotalReste(employee: EmployeeSoldeState): number {
        return employee.soldes.reduce((sum, s) => sum + s.reste, 0);
    }

    getTotalPris(employee: EmployeeSoldeState): number {
        return this.getTotalInitial(employee) - this.getTotalReste(employee);
    }

    exportPDF(employee: EmployeeSoldeState) {
        // TODO: Générer PDF
        console.log('Export PDF pour', employee.emp_nom);
    }
}
