import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { PrisEnChargeService } from '../../../service/prise-en-charge.service';
import { RemboursementService } from '../../../service/remboursement.service';
import { LayoutService } from '../../../../../shared/layout/service/layout.service';
import { AuthService } from '../../../../auth/service/auth-service';
import { EmployeeService } from '../../../../employee/service/employee.service';
import { Employee } from '../../../../employee/model/employee.model';

@Component({
    selector: 'app-ajout-prise-en-charge',
    standalone: true,
    imports: [CommonModule, FormsModule],
    templateUrl: './ajout.html',
    styleUrls: ['./ajout.scss']
})
export class AjoutPriseEnChargeComponent {
    private readonly router = inject(Router);
    private readonly pecService = inject(PrisEnChargeService);
    private readonly rembService = inject(RemboursementService);
    private readonly layoutService = inject(LayoutService);
    private readonly authService = inject(AuthService);
    private readonly employeeService = inject(EmployeeService);

    model: any = {
        emp_code: null,
        beneficiaire_type: 'agent',
        conj_code: null,
        enf_code: null,
        cen_code: null
    };

    employees: Employee[] = [];
    filteredEmployees: Employee[] = [];
    selectedEmployee: Employee | null = null;
    empSearchText = '';
    showDropdown = false;

    conjoints: any[] = [];
    enfants: any[] = [];
    errorMsg = '';
    loading = false;

    ngOnInit() {
        this.layoutService.setTitle('Nouvelle prise en charge');
        this.loadEmployees();
    }

    loadEmployees() {
        this.employeeService.getEmployees().subscribe((employees: Employee[]) => {
            this.employees = employees || [];
            this.filteredEmployees = this.employees;

            // Si admin connecté, pré-sélectionner
            const admin: any = this.authService.currentAdminValue;
            if (admin?.emp_code) {
                const found = this.employees.find(e => e.emp_code === admin.emp_code);
                if (found) {
                    this.selectEmployee(found);
                }
            }
        });
    }

    onFocus() {
        this.showDropdown = true;
        // Si le champ est vide, on s'assure d'avoir toute la liste
        if (!this.empSearchText) {
            this.filteredEmployees = this.employees;
        }
    }

    onBlur() {
        // Petit délai pour permettre le clic sur un item
        setTimeout(() => {
            this.showDropdown = false;
        }, 200);
    }

    filterEmployees() {
        const term = this.empSearchText.toLowerCase();
        this.filteredEmployees = this.employees.filter(e =>
            (e.emp_nom + ' ' + e.emp_prenom).toLowerCase().includes(term) ||
            e.emp_imarmp.toLowerCase().includes(term)
        );
        this.showDropdown = true;
    }

    selectEmployee(emp: Employee) {
        this.selectedEmployee = emp;
        this.model.emp_code = emp.emp_code;
        this.empSearchText = `${emp.emp_nom} ${emp.emp_prenom}`;
        this.filteredEmployees = []; // Cacher la liste dropdown

        // Clear error if it was related to employee
        if (this.errorField === 'emp') {
            this.errorField = '';
            this.errorMsg = '';
        }

        // Charger la famille
        this.loadBeneficiaires();
    }

    loadBeneficiaires() {
        if (!this.model.emp_code) return;
        this.rembService.getFamilyMembers(this.model.emp_code).subscribe({
            next: (res: any) => {
                this.conjoints = res.conjoints || [];
                this.enfants = res.enfants || [];

                // Auto-sélection de la conjointe (puisque affichage seul)
                if (this.conjoints.length > 0) {
                    this.model.conj_code = this.conjoints[0].conj_code;
                } else {
                    this.model.conj_code = null;
                }
            },
            error: (err: any) => console.error('Erreur chargement famille', err)
        });
    }

    errorField = '';

    submit() {
        this.errorField = ''; // Reset

        if (!this.model.emp_code) {
            this.errorMsg = 'Agent obligatoire';
            this.errorField = 'emp';
            return;
        }

        // Validation bénéficiaire
        if (this.model.beneficiaire_type === 'conjoint' && !this.model.conj_code) {
            this.errorMsg = 'Aucun conjoint valide associé à cet employé';
            this.errorField = 'beneficiaire'; // Generic or specific
            return;
        }
        if (this.model.beneficiaire_type === 'enfant' && !this.model.enf_code) {
            this.errorMsg = 'Veuillez sélectionner un enfant';
            this.errorField = 'beneficiaire';
            return;
        }

        this.loading = true;
        this.errorMsg = '';
        this.errorField = '';
        this.pecService.create(this.model).subscribe({
            next: () => {
                this.loading = false;
                this.router.navigate(['/remboursement/prises-en-charge']);
            },
            error: (err: any) => {
                this.loading = false;
                this.errorMsg = err.error?.messages?.error || err.error?.message || 'Erreur lors de la création';
                // Backend errors are usually global unless mapped, leaving errorField empty displays nothing or global? 
                // I'll leave errorField empty for backend errors to show in global area if I keep it, or handle it.
                // For now, focusing on the requested "Agent" field error.
            }
        });
    }

    cancel() {
        this.router.navigate(['/remboursement/prises-en-charge']);
    }
}

