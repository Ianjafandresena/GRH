import { Component, OnInit, inject, ViewChild, AfterViewInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatIconModule } from '@angular/material/icon';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatPaginator, MatPaginatorModule } from '@angular/material/paginator';
import { MatSort, MatSortModule } from '@angular/material/sort';
import { Router, ActivatedRoute } from '@angular/router';
import { EmployeeService } from '../../service/employee.service';
import { CongeService } from '../../../conge/service/conge.service';
import { LayoutService } from '../../../../shared/layout/service/layout.service';
import { Employee } from '../../model/employee.model';
import { EmployeeDetailComponent } from '../detail/detail';
import { forkJoin, of } from 'rxjs';
import { catchError } from 'rxjs/operators';

@Component({
  selector: 'app-employee',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatIconModule,
    MatDialogModule,
    MatTableModule,
    MatPaginatorModule,
    MatSortModule
  ],
  templateUrl: './employee.html',
  styleUrls: ['./employee.scss']
})
export class EmployeeComponent implements OnInit, AfterViewInit {
  private readonly employeeService = inject(EmployeeService);
  private readonly congeService = inject(CongeService);
  private readonly layoutService = inject(LayoutService);
  private readonly dialog = inject(MatDialog);
  private readonly router = inject(Router);

  dataSource = new MatTableDataSource<Employee>([]);
  displayedColumns: string[] = ['emp_nom', 'emp_prenom', 'emp_im_armp', 'pst_fonction', 'dir_abbreviation', 'status', 'actions'];

  private _paginator!: MatPaginator;
  private _sort!: MatSort;

  @ViewChild(MatPaginator) set matPaginator(mp: MatPaginator) {
    this._paginator = mp;
    this.updateDataSourceLinks();
  }

  @ViewChild(MatSort) set matSort(ms: MatSort) {
    this._sort = ms;
    this.updateDataSourceLinks();
  }

  private updateDataSourceLinks() {
    this.dataSource.paginator = this._paginator;
    this.dataSource.sort = this._sort;
  }

  loading = false;

  // Filter fields
  searchQuery = '';
  directionFilter = '';
  statusFilter = ''; // all, available, unavailable
  posteFilter = '';

  // For direction dropdown
  availableDirections: string[] = [];
  availablePostes: string[] = [];

  private readonly route = inject(ActivatedRoute);

  ngOnInit() {
    this.layoutService.setTitle('Gestion des Employés');

    // Récupérer les données du resolver
    const resolvedData = this.route.snapshot.data['employees'] as { employees: any[], absences: any[] };
    if (resolvedData && resolvedData.employees) {
      this.processEmployeeResults(resolvedData);
    } else {
      this.loadEmployees();
    }

    // Setup custom filter predicate
    this.dataSource.filterPredicate = (data: Employee, filter: string): boolean => {
      const filters = JSON.parse(filter);

      const searchMatch = !filters.search ||
        `${data.emp_nom} ${data.emp_prenom}`.toLowerCase().includes(filters.search.toLowerCase()) ||
        !!(data.emp_im_armp && data.emp_im_armp.toLowerCase().includes(filters.search.toLowerCase()));

      const dirMatch = !filters.direction || data.dir_nom === filters.direction;
      const posteMatch = !filters.poste || data.pst_fonction === filters.poste;

      const statusMatch = !filters.status ||
        (filters.status === 'available' && data.is_available) ||
        (filters.status === 'unavailable' && !data.is_available);

      return !!(searchMatch && dirMatch && posteMatch && statusMatch);
    };
  }

  ngAfterViewInit() {
  }

  loadEmployees() {
    this.loading = true;

    // Fetch employees and current absences in parallel to calculate status
    forkJoin({
      employees: this.employeeService.getEmployees().pipe(catchError(() => of([]))),
      absences: this.congeService.getAbsences().pipe(catchError(() => of([])))
    }).subscribe({
      next: (result) => {
        this.processEmployeeResults(result);
        this.loading = false;
      },
      error: () => {
        this.loading = false;
      }
    });
  }

  private processEmployeeResults(result: { employees: any[], absences: any[] }) {
    const now = new Date();
    const employees = result.employees as Employee[];
    const absences = result.absences as any[];

    // Process availability status client-side
    employees.forEach(emp => {
      // Find if employee has a currently active validated absence
      const activeAbsence = absences.find(a => {
        if (a.emp_code !== emp.emp_code) return false;

        // Only consider validated absences
        const isValidated = (a.cng_status === true || a.cng_status === 't' || a.cng_status === 1 ||
          a.prm_status === true || a.prm_status === 't' || a.prm_status === 1 ||
          a.absence_type === 'permission'); // Permissions are often assumed auto-validated

        if (!isValidated && a.absence_type === 'conge') return false;

        const start = new Date(a.cng_debut || a.prm_debut);
        const end = new Date(a.cng_fin || a.prm_fin);

        // Extend end date for leaves to cover the full day
        if (a.absence_type === 'conge') {
          end.setHours(23, 59, 59, 999);
        }

        return now >= start && now <= end;
      });

      if (activeAbsence) {
        emp.is_available = false;
        emp.absence_type = activeAbsence.absence_type;
        emp.absence_end = activeAbsence.cng_fin || activeAbsence.prm_fin;
      } else {
        emp.is_available = true;
      }
    });

    this.dataSource.data = employees;

    // Extract unique directions and postes for filters
    this.availableDirections = [...new Set(employees.map(e => e.dir_nom).filter(Boolean) as string[])];
    this.availablePostes = [...new Set(employees.map(e => e.pst_fonction).filter(Boolean) as string[])];

    this.applyFilters();
  }

  applyFilters() {
    const filters = {
      search: this.searchQuery,
      direction: this.directionFilter,
      poste: this.posteFilter,
      status: this.statusFilter
    };
    this.dataSource.filter = JSON.stringify(filters);

    if (this.dataSource.paginator) {
      this.dataSource.paginator.firstPage();
    }
  }

  resetFilters() {
    this.searchQuery = '';
    this.directionFilter = '';
    this.statusFilter = '';
    this.posteFilter = '';
    this.applyFilters();
  }

  openDetail(employee: Employee) {
    this.router.navigate(['/employee', employee.emp_code]);
  }

  getStatusClass(employee: Employee): string {
    return employee.is_available ? 'status-available' : 'status-unavailable';
  }

  getStatusText(employee: Employee): string {
    if (employee.is_available) {
      return 'Présent';
    }

    if (employee.absence_end) {
      const endDate = new Date(employee.absence_end);
      return `Non disponible (jusqu'au ${endDate.toLocaleDateString('fr-FR')})`;
    }

    return 'Non disponible';
  }

  getAbsenceIcon(employee: Employee): string {
    if (employee.is_available) return 'fas fa-check-circle';
    return employee.absence_type === 'conge' ? 'fas fa-calendar-times' : 'fas fa-clock';
  }
}
