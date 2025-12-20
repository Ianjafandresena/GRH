import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { RemboursementService } from '../../../service/remboursement.service';
import { LayoutService } from '../../../../../shared/layout/service/layout.service';
import { AuthService } from '../../../../auth/service/auth-service';
import { PrisEnChargeService } from '../../../service/prise-en-charge.service';
import { CentreSanteService } from '../../../service/centre-sante.service';
import { EmployeeService } from '../../../../employee/service/employee.service';
import { Conjointe, Enfant } from '../../../model/prise-en-charge.model';
import { CentreSante } from '../../../model/centre-sante.model';
import { Employee } from '../../../../employee/model/employee.model';

@Component({
    selector: 'app-ajout-demande',
    standalone: true,
    imports: [CommonModule, FormsModule],
    templateUrl: './ajout.html',
    styleUrls: ['./ajout.scss']
})
export class AjoutDemandeComponent {
    private readonly router = inject(Router);
    private readonly rembService = inject(RemboursementService);
    private readonly layoutService = inject(LayoutService);
    private readonly authService = inject(AuthService);
    private readonly pecService = inject(PrisEnChargeService);
    private readonly centreService = inject(CentreSanteService);
    private readonly employeeService = inject(EmployeeService);

    demande: any = {
        rem_objet: '',
        rem_montant: 0,
        rem_montant_lettre: '',
        beneficiaire_type: 'agent',
        conj_code: null,
        enf_code: null,
        cen_code: null,
        has_ordonnance: false,
        has_facture: false,
        has_prise_en_charge: false,
        pec_reference: '',
        date_consultation: '',
        emp_code: null,
        pieces: [] as string[]
    };

    employees: Employee[] = [];
    filteredEmployees: Employee[] = [];
    selectedEmployee: Employee | null = null;
    empSearchText = '';
    showEmpDropdown = false;

    conjoints: Conjointe[] = [];
    enfants: Enfant[] = [];
    pecs: any[] = [];
    centres: CentreSante[] = [];
    selectedPieces: { [key: string]: boolean } = {
        Ordonnance: false,
        Facture: false,
        'Prise en charge': false
    };
    loading = false;
    errorMsg = '';

    ngOnInit() {
        this.layoutService.setTitle('Nouvelle Demande de Remboursement');
        this.loadEmployees();
        this.loadCentres();

        // Pré-remplir emp_code depuis l'utilisateur connecté si disponible
        const admin: any = this.authService.currentAdminValue;
        if (admin?.emp_code) {
            this.employeeService.getEmployees().subscribe({
                next: (emps) => {
                    const found = emps.find(e => e.emp_code === admin.emp_code);
                    if (found) {
                        this.selectEmployee(found);
                    }
                }
            });
        }
    }

    loadEmployees() {
        this.employeeService.getEmployees().subscribe({
            next: (emps) => {
                this.employees = emps || [];
                this.filteredEmployees = this.employees;
            },
            error: (err) => console.error('Erreur chargement employés', err)
        });
    }

    onEmpFocus() {
        this.showEmpDropdown = true;
        if (!this.empSearchText) {
            this.filteredEmployees = this.employees;
        }
    }

    onEmpBlur() {
        setTimeout(() => {
            this.showEmpDropdown = false;
        }, 200);
    }

    filterEmployees() {
        const term = this.empSearchText.toLowerCase();
        this.filteredEmployees = this.employees.filter(e =>
            (e.emp_nom + ' ' + e.emp_prenom).toLowerCase().includes(term) ||
            e.emp_imarmp?.toLowerCase().includes(term)
        );
        this.showEmpDropdown = true;
    }

    selectEmployee(emp: Employee) {
        this.selectedEmployee = emp;
        this.demande.emp_code = emp.emp_code;
        this.empSearchText = `${emp.emp_nom} ${emp.emp_prenom}`;
        this.showEmpDropdown = false;
        this.loadBeneficiaires();
        this.loadPecs();
    }

    loadBeneficiaires() {
        const empCode = this.demande.emp_code;
        if (!empCode) return;

        this.rembService.getFamilyMembers(empCode).subscribe({
            next: (res: any) => {
                this.conjoints = res.conjoints || [];
                this.enfants = res.enfants || [];
            },
            error: (err) => console.error('Erreur chargement famille', err)
        });
    }

    loadPecs() {
        if (!this.demande.emp_code) return;
        this.rembService.getMyPecs(this.demande.emp_code).subscribe({
            next: (res) => this.pecs = res || [],
            error: (err) => console.error('Erreur chargement PECs', err)
        });
    }

    loadCentres() {
        this.centreService.getCentres().subscribe({
            next: (res) => this.centres = res || [],
            error: (err) => console.error('Erreur chargement centres', err)
        });
    }

    onPecCheckChange() {
        this.demande.has_prise_en_charge = this.selectedPieces['Prise en charge'];
        if (!this.demande.has_prise_en_charge) {
            this.demande.pec_reference = '';
            this.demande.pec_code = null;
        }
    }

    onMontantChange() {
        // Conversion automatique du montant en lettres
        if (this.demande.rem_montant > 0) {
            this.demande.rem_montant_lettre = this.numberToWords(this.demande.rem_montant);
        } else {
            this.demande.rem_montant_lettre = '';
        }
    }

    /**
     * Convertit un nombre en lettres (français)
     */
    numberToWords(num: number): string {
        const ones = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
            'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        const tens = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'];
        const scales = ['', 'mille', 'million', 'milliard'];

        if (num === 0) return 'zéro';

        function convertHundreds(n: number): string {
            if (n === 0) return '';
            if (n < 20) return ones[n];
            if (n < 100) {
                const ten = Math.floor(n / 10);
                const one = n % 10;
                if (ten === 7 || ten === 9) {
                    const base = ten === 7 ? 60 : 80;
                    const remainder = n - base;
                    if (remainder === 0) return tens[ten];
                    if (remainder < 20) return tens[ten] + '-' + ones[remainder];
                    return tens[ten] + '-' + convertHundreds(remainder);
                }
                if (one === 0) return tens[ten];
                if (one === 1) return tens[ten] + '-et-un';
                return tens[ten] + '-' + ones[one];
            }
            const hundred = Math.floor(n / 100);
            const remainder = n % 100;
            let result = '';
            if (hundred === 1) {
                result = 'cent';
            } else {
                result = ones[hundred] + '-cent';
            }
            if (remainder > 0) {
                result += '-' + convertHundreds(remainder);
            }
            return result;
        }

        function convert(n: number, scaleIndex: number): string {
            if (n === 0) return '';
            const scale = scales[scaleIndex];
            const remainder = n % 1000;
            const quotient = Math.floor(n / 1000);

            let result = '';
            if (remainder > 0) {
                result = convertHundreds(remainder);
                if (scale && scaleIndex > 0) {
                    result += ' ' + scale;
                    if (remainder > 1 && scaleIndex === 1) result += 's';
                }
            }

            if (quotient > 0) {
                const prefix = convert(quotient, scaleIndex + 1);
                if (prefix) {
                    result = prefix + (result ? ' ' + result : '');
                }
            }

            return result;
        }

        const words = convert(num, 0);
        return words.charAt(0).toUpperCase() + words.slice(1) + ' ariary';
    }

    submit() {
        // Validations
        if (!this.demande.emp_code) {
            this.errorMsg = 'Veuillez sélectionner un employé';
            return;
        }
        if (this.demande.rem_montant <= 0) {
            this.errorMsg = 'Le montant doit être positif';
            return;
        }
        if (!this.demande.rem_objet) {
            this.errorMsg = 'L\'objet du remboursement (type de soin) est obligatoire';
            return;
        }
        if (this.demande.has_prise_en_charge && !this.demande.pec_reference) {
            this.errorMsg = 'Veuillez sélectionner une prise en charge';
            return;
        }

        // Récupérer les pièces cochées
        this.demande.pieces = Object.keys(this.selectedPieces).filter(k => this.selectedPieces[k]);

        this.loading = true;
        this.errorMsg = '';

        this.rembService.createIndirect(this.demande).subscribe({
            next: (res: any) => {
                alert(`Demande créée avec succès!\nN°: ${res.num_demande}`);
                this.router.navigate(['/remboursement/demandes']);
            },
            error: (err: any) => {
                this.errorMsg = err.error?.messages?.error || err.error?.message || 'Erreur lors de la création';
                this.loading = false;
            }
        });
    }

    cancel() {
        this.router.navigate(['/remboursement/demandes']);
    }
}
