import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { RemboursementService } from '../../../service/remboursement.service';
import { LayoutService } from '../../../../../shared/layout/service/layout.service';
import { DemandeRemb } from '../../../model/demande-remb.model';
import { EmployeeService } from '../../../../employee/service/employee.service';
import { Employee } from '../../../../employee/model/employee.model';

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
    private readonly employeeService = inject(EmployeeService);
    private readonly layoutService = inject(LayoutService);

    demandes: DemandeRemb[] = [];
    employees: Employee[] = [];
    loading = false;
    errorMsg = '';

    // Filtres
    filter = {
        emp_code: null as number | null,
        traitement: 'all' as 'all' | 'traite' | 'non_traite',
        type: 'all' as 'all' | 'agent' | 'centre',
        mois: new Date().getMonth() + 1,
        annee: new Date().getFullYear()
    };

    // Employee dropdown control
    employeeSearch = '';
    employeeDropdownOpen = false;
    selectedEmployee: Employee | null = null;

    get filteredEmployees(): Employee[] {
        if (!this.employeeSearch) return this.employees.slice(0, 50);
        const query = this.employeeSearch.toLowerCase();
        return this.employees.filter(e =>
            e.emp_nom?.toLowerCase().includes(query) ||
            e.emp_prenom?.toLowerCase().includes(query) ||
            e.emp_imarmp?.toLowerCase().includes(query)
        ).slice(0, 50);
    }

    get filteredDemandes(): DemandeRemb[] {
        let result = this.demandes;

        // Filtre type
        if (this.filter.type !== 'all') {
            result = result.filter(d =>
                this.filter.type === 'centre' ? d.rem_is_centre === true : d.rem_is_centre !== true
            );
        }

        // Filtre traitement
        if (this.filter.traitement !== 'all') {
            result = result.filter(d =>
                this.filter.traitement === 'traite' ? d.rem_status === true : d.rem_status !== true
            );
        }

        return result;
    }

    ngOnInit() {
        this.layoutService.setTitle('Demandes de Remboursement');
        this.loadEmployees();
        this.route.data.subscribe(data => {
            this.demandes = data['demandes'] || [];
            if (!this.demandes.length) {
                this.loadDemandes();
            }
        });
    }

    loadEmployees() {
        this.employeeService.getEmployees().subscribe({
            next: (list: Employee[]) => this.employees = list || [],
            error: () => console.error('Erreur chargement employes')
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

    selectEmployee(emp: Employee) {
        this.selectedEmployee = emp;
        this.filter.emp_code = emp.emp_code;
        this.employeeSearch = `${emp.emp_nom} ${emp.emp_prenom} (${emp.emp_imarmp})`;
        this.employeeDropdownOpen = false;
    }

    clearEmployee() {
        this.selectedEmployee = null;
        this.filter.emp_code = null;
        this.employeeSearch = '';
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
        const emp = this.filter.emp_code;
        if (!emp) {
            alert('Veuillez selectionner un employe pour generer etat.');
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
            this.errorMsg = 'Erreur lors du telechargement';
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
