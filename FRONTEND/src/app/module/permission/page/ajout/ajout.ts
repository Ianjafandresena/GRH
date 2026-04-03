import { Component, OnInit, inject } from '@angular/core';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { EmployeeService } from '../../../employee/service/employee.service';
import { Employee } from '../../../employee/model/employee.model';
import { PermissionService } from '../../service/permission.service';
import { LayoutService } from '../../../../shared/layout/service/layout.service';
import { MatIconModule } from '@angular/material/icon';

@Component({
  selector: 'app-ajout-permission',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, MatIconModule, RouterLink],
  templateUrl: './ajout.html',
  styleUrls: ['./ajout.scss']
})
export class AjoutPermissionComponent implements OnInit {
  private readonly layoutService = inject(LayoutService);
  private readonly fb = inject(FormBuilder);
  private readonly employeeService = inject(EmployeeService);
  private readonly permissionService = inject(PermissionService);

  form: FormGroup;
  employees: Employee[] = [];
  filteredEmployees: Employee[] = [];
  selectedEmployee: Employee | null = null;

  soldeRestant: number | null = null;
  calculatedHours: number | null = null;

  loading = false;
  errorMsg = '';

  constructor() {
    this.form = this.fb.group({
      emp_search: ['', Validators.required],
      prm_date: [null, Validators.required],
      periode: ['matin', Validators.required],
      motif: ['']
    });
  }

  ngOnInit() {
    this.layoutService.setTitle('Nouvelle Permission');

    // Charger les employés
    this.employeeService.getEmployees().subscribe((employees: Employee[]) => {
      this.employees = employees ?? [];
      this.filteredEmployees = [];
    });

    // Filtrer les employés lors de la saisie
    this.form.get('emp_search')?.valueChanges.subscribe((val: string) => {
      const v = (val || '').toLowerCase();
      if (v.length >= 2) {
        this.filteredEmployees = this.employees.filter(e =>
          `${e.emp_nom} ${e.emp_prenom}`.toLowerCase().includes(v)
        ).slice(0, 10);
      } else {
        this.filteredEmployees = [];
      }
      if (!val) this.selectedEmployee = null;
    });

    // Recalculer la durée quand la période change
    this.form.get('periode')?.valueChanges.subscribe(() => {
      this.updateDuration();
    });
  }

  selectEmployee(emp: Employee) {
    this.selectedEmployee = emp;
    this.form.patchValue({ emp_search: `${emp.emp_nom} ${emp.emp_prenom}` });
    this.filteredEmployees = [];

    // Charger le solde de permission
    this.loadSolde(emp.emp_code);
  }

  loadSolde(empCode: number) {
    this.soldeRestant = null;
    this.permissionService.getLastSoldeDispo(empCode).subscribe({
      next: (data: any) => {
        this.soldeRestant = data?.sld_prm_dispo ?? 0;
      },
      error: () => {
        this.soldeRestant = 0;
      }
    });
  }

  updateDuration() {
    const periode = this.form.get('periode')?.value;
    switch (periode) {
      case 'matin':
      case 'apres-midi':
        this.calculatedHours = 4;
        break;
      case 'journee':
        this.calculatedHours = 8;
        break;
      default:
        this.calculatedHours = null;
    }
  }

  submit() {
    this.errorMsg = '';

    if (this.form.invalid || !this.selectedEmployee) {
      this.errorMsg = 'Veuillez remplir tous les champs obligatoires';
      return;
    }

    this.updateDuration();

    // Vérifier le solde
    if (this.soldeRestant !== null && this.calculatedHours && this.calculatedHours > this.soldeRestant) {
      this.errorMsg = `Solde insuffisant. Disponible: ${this.soldeRestant}h, Demandé: ${this.calculatedHours}h`;
      return;
    }

    const date = this.form.value.prm_date;
    const periode = this.form.value.periode;

    // Calculer les heures de début et fin selon la période
    let debut: string, fin: string;
    switch (periode) {
      case 'matin':
        debut = `${date}T08:00:00`;
        fin = `${date}T12:00:00`;
        break;
      case 'apres-midi':
        debut = `${date}T14:00:00`;
        fin = `${date}T18:00:00`;
        break;
      case 'journee':
        debut = `${date}T08:00:00`;
        fin = `${date}T18:00:00`;
        break;
      default:
        debut = `${date}T08:00:00`;
        fin = `${date}T12:00:00`;
    }

    const payload = {
      emp_code: this.selectedEmployee.emp_code,
      prm_debut: debut,
      prm_fin: fin,
      prm_duree: this.calculatedHours
    };

    this.loading = true;
    this.permissionService.createPermission(payload as any).subscribe({
      next: () => {
        this.loading = false;
        this.layoutService.showSuccessMessage('Permission enregistrée avec succès');
        this.reset();
      },
      error: (err: any) => {
        this.loading = false;
        this.errorMsg = err?.error?.messages?.error || err?.message || 'Erreur lors de l\'enregistrement';
      }
    });
  }

  reset() {
    this.form.reset({ periode: 'matin' });
    this.selectedEmployee = null;
    this.soldeRestant = null;
    this.calculatedHours = null;
    this.errorMsg = '';
  }
}
