import { Component, inject, signal, computed, OnInit, ViewChild, AfterViewInit, effect } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { EtatRemb, EtatRembService } from '../../../service/etat-remb.service';
import { LayoutService } from '../../../../../shared/layout/service/layout.service';
import { Employee } from '../../../../employee/model/employee.model';
import { CentreSanteService } from '../../../service/centre-sante.service';
import { CentreSante } from '../../../model/centre-sante.model';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatPaginator, MatPaginatorModule } from '@angular/material/paginator';
import { MatSort, MatSortModule } from '@angular/material/sort';

@Component({
    selector: 'app-etats-index',
    standalone: true,
    imports: [
        CommonModule,
        FormsModule,
        MatTableModule,
        MatPaginatorModule,
        MatSortModule
    ],
    templateUrl: './index.html',
    styleUrls: ['./index.scss']
})
export class EtatsIndexComponent implements OnInit, AfterViewInit {
    private readonly router = inject(Router);
    private readonly route = inject(ActivatedRoute);
    private readonly etatService = inject(EtatRembService);
    private readonly layoutService = inject(LayoutService);
    private readonly centreService = inject(CentreSanteService);

    // Signals
    etats = signal<EtatRemb[]>([]);
    loading = signal(false);

    // MatTable integration
    dataSource = new MatTableDataSource<EtatRemb>([]);
    displayedColumns: string[] = ['etat_num', 'eta_date', 'employee', 'matricule', 'nb_demandes', 'total', 'actions'];

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

    filter = signal({
        emp_code: null as number | null,
        cen_code: null as number | null
    });

    constructor() {
        // Sync signal updates to MatTableDataSource
        effect(() => {
            const data = this.filteredEtats();
            this.dataSource.data = data;

            if (this._paginator) {
                this._paginator.firstPage();
            }
        });
    }

    eligibleEmployees = computed(() => {
        const etatsData = this.etats();
        const uniqueEntries = new Map<number, Employee>();
        etatsData.forEach(e => {
            if (e.emp_code && !uniqueEntries.has(e.emp_code)) {
                uniqueEntries.set(e.emp_code, {
                    emp_code: e.emp_code,
                    emp_nom: e.nom_emp || '',
                    emp_prenom: e.prenom_emp || '',
                    emp_im_armp: e.matricule || ''
                } as Employee);
            }
        });
        return Array.from(uniqueEntries.values());
    });

    filteredEmployeesList: Employee[] = [];
    employeeSearch = '';
    employeeDropdownOpen = false;
    selectedEmployee: Employee | null = null;

    centres = signal<CentreSante[]>([]);
    filteredCentresList: CentreSante[] = [];
    centreSearch = '';
    centreDropdownOpen = false;
    selectedCentre: CentreSante | null = null;

    filteredEtats = computed(() => {
        const etatsData = this.etats();
        const filterData = this.filter();
        return etatsData.filter(e => {
            const matchEmp = !filterData.emp_code || Number(e.emp_code) === Number(filterData.emp_code);
            const matchCen = !filterData.cen_code || Number(e.cen_code) === Number(filterData.cen_code);
            return matchEmp && matchCen;
        });
    });

    totalAmount = computed(() => this.filteredEtats().reduce((sum, e) => sum + Number(e.eta_total || 0), 0));
    uniqueEmployees = computed(() => new Set(this.filteredEtats().map(e => Number(e.emp_code))).size);
    totalDemandes = computed(() => this.filteredEtats().reduce((sum, e) => sum + Number(e.nb_demandes || 0), 0));

    ngOnInit() {
        this.layoutService.setTitle('États de Remboursement');
        const resolvedData = this.route.snapshot.data['etats'] as EtatRemb[];
        if (resolvedData) {
            this.etats.set(resolvedData);
        }
        this.loadCentres();
    }

    ngAfterViewInit() {
    }

    loadCentres() {
        this.centreService.getCentres().subscribe({
            next: (data) => this.centres.set(data),
            error: (err) => console.error('Erreur chargement centres:', err)
        });
    }

    filterEmployees() {
        const employees = this.eligibleEmployees();
        if (!this.employeeSearch) {
            this.filteredEmployeesList = employees.slice(0, 10);
            return;
        }
        const search = this.employeeSearch.toLowerCase();
        this.filteredEmployeesList = employees.filter(emp =>
            emp.emp_nom?.toLowerCase().includes(search) ||
            emp.emp_prenom?.toLowerCase().includes(search) ||
            emp.emp_im_armp?.toLowerCase().includes(search)
        ).slice(0, 10);
    }

    onFocus() {
        this.employeeDropdownOpen = true;
        if (!this.employeeSearch) {
            this.filteredEmployeesList = this.eligibleEmployees().slice(0, 10);
        }
    }

    selectEmployee(emp: Employee) {
        this.selectedEmployee = emp;
        this.employeeSearch = `${emp.emp_nom} ${emp.emp_prenom}`;
        this.employeeDropdownOpen = false;
        this.filteredEmployeesList = [];
        this.filter.update(f => ({ ...f, emp_code: emp.emp_code }));
    }

    clearEmployee() {
        this.selectedEmployee = null;
        this.employeeSearch = '';
        this.filter.update(f => ({ ...f, emp_code: null }));
    }

    closeDropdownDelayed() {
        setTimeout(() => this.employeeDropdownOpen = false, 200);
    }

    filterCentres() {
        const allCentres = this.centres();
        if (!this.centreSearch) {
            this.filteredCentresList = allCentres.slice(0, 10);
            return;
        }
        const search = this.centreSearch.toLowerCase();
        this.filteredCentresList = allCentres.filter(cen =>
            cen.cen_nom?.toLowerCase().includes(search)
        ).slice(0, 10);
    }

    onCentreFocus() {
        this.centreDropdownOpen = true;
        if (!this.centreSearch) {
            this.filteredCentresList = this.centres().slice(0, 10);
        }
    }

    selectCentre(cen: CentreSante) {
        this.selectedCentre = cen;
        this.centreSearch = cen.cen_nom || '';
        this.centreDropdownOpen = false;
        this.filteredCentresList = [];
        this.filter.update(f => ({ ...f, cen_code: cen.cen_code ?? null }));
    }

    clearCentre() {
        this.selectedCentre = null;
        this.centreSearch = '';
        this.filter.update(f => ({ ...f, cen_code: null }));
    }

    closeCentreDropdownDelayed() {
        setTimeout(() => this.centreDropdownOpen = false, 200);
    }

    loadEtats() {
        this.loading.set(true);
        this.etatService.getAll().subscribe({
            next: (data) => {
                this.etats.set(data);
                this.loading.set(false);
            },
            error: () => this.loading.set(false)
        });
    }

    viewDetails(etaCode: number) {
        this.router.navigate(['/remboursement/etats', etaCode]);
    }

    downloadPdf(etaCode: number) {
        window.open(`${this.etatService.baseUrl}/${etaCode}/pdf`, '_blank');
    }
}
