import { Component, OnInit, inject } from '@angular/core';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { EmployeeService } from '../../../employee/service/employee.service';
import { Employee } from '../../../employee/model/employee.model';
import { PermissionService } from '../../service/permission.service';
import { Permission } from '../../model/permission.model';
import { LayoutService } from '../../../layout/service/layout.service';

@Component({
  selector: 'app-ajout-permission',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './ajout.html',
  styleUrls: ['./ajout.css']
})
export class AjoutPermissionComponent implements OnInit {
  private readonly layoutService = inject(LayoutService);

  form: FormGroup;
  employees: Employee[] = [];
  filteredEmployees: Employee[] = [];
  selectedEmployee: Employee | null = null;
  calculatedHours: number | null = null;
  calculatedDays: number | null = null;
  loading = false;
  errorMsg = '';

  constructor(
    private fb: FormBuilder,
    private employeeService: EmployeeService,
    private permissionService: PermissionService
  ) {
    this.form = this.fb.group({
      emp_search: ['', Validators.required],
      prm_debut: [null, Validators.required],
      prm_fin: [null, Validators.required]
    });
  }

  ngOnInit() {
    this.layoutService.setTitle('Permissions');
    this.employeeService.getEmployees().subscribe((employees: Employee[]) => {
      this.employees = employees ?? [];
      this.filteredEmployees = employees ?? [];
    });

    this.form.get('emp_search')?.valueChanges.subscribe((val: string) => {
      const v = (val || '').toLowerCase();
      this.filteredEmployees = this.employees.filter(e => (`${e.nom} ${e.prenom}`.toLowerCase().includes(v)));
      if (!val) this.selectedEmployee = null;
    });
  }

  updateDurations() {
    const debut = this.form.get('prm_debut')?.value;
    const fin = this.form.get('prm_fin')?.value;
    if (!debut || !fin) { this.calculatedHours = null; this.calculatedDays = null; return; }
    const d1 = new Date(debut).getTime();
    const d2 = new Date(fin).getTime();
    const diffMs = Math.max(0, d2 - d1);
    const diffH = diffMs / (1000 * 60 * 60);
    this.calculatedHours = parseFloat(diffH.toFixed(2));
    this.calculatedDays = parseFloat((diffH / 8).toFixed(2));
  }

  selectEmployee(emp: Employee) {
    this.selectedEmployee = emp;
    this.form.patchValue({ emp_search: `${emp.nom} ${emp.prenom}` });
    this.filteredEmployees = [];
  }

  submit() {
    this.errorMsg = '';
    if (this.form.invalid || !this.selectedEmployee) {
      this.errorMsg = 'Veuillez remplir tous les champs';
      return;
    }
    this.updateDurations();
    const payload: any = {
      emp_code: this.selectedEmployee.emp_code,
      prm_debut: this.form.value.prm_debut,
      prm_fin: this.form.value.prm_fin
    };
    this.loading = true;
    this.permissionService.createPermission(payload).subscribe({
      next: () => { this.loading = false; alert('Permission enregistrée'); this.reset(); },
      error: (err: any) => { this.loading = false; this.errorMsg = err?.message || 'Erreur API'; }
    });
  }

  reset() {
    this.form.reset();
    this.selectedEmployee = null;
    this.calculatedHours = null;
    this.calculatedDays = null;
  }
}
