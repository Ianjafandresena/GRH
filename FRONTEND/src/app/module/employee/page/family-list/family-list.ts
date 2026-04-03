import { Component, OnInit, inject, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatIconModule } from '@angular/material/icon';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatPaginator, MatPaginatorModule } from '@angular/material/paginator';
import { MatSort, MatSortModule } from '@angular/material/sort';
import { Router } from '@angular/router';
import { EmployeeService } from '../../service/employee.service';
import { LayoutService } from '../../../../shared/layout/service/layout.service';

@Component({
    selector: 'app-family-list',
    standalone: true,
    imports: [
        CommonModule,
        FormsModule,
        MatIconModule,
        MatTableModule,
        MatPaginatorModule,
        MatSortModule
    ],
    templateUrl: './family-list.html',
    styleUrls: ['./family-list.scss']
})
export class FamilyListComponent implements OnInit {
    private readonly employeeService = inject(EmployeeService);
    private readonly layoutService = inject(LayoutService);
    private readonly router = inject(Router);

    dataSource = new MatTableDataSource<any>([]);
    displayedColumns: string[] = ['family_name', 'employee', 'nb_enfants', 'actions'];

    @ViewChild(MatPaginator) paginator!: MatPaginator;
    @ViewChild(MatSort) sort!: MatSort;

    loading = false;
    searchQuery = '';
    directionFilter = '';
    availableDirections: string[] = [];

    ngOnInit() {
        this.layoutService.setTitle('Gestion des Familles');
        this.loadFamilies();

        this.dataSource.filterPredicate = (data: any, filter: string): boolean => {
            const filters = JSON.parse(filter);
            const searchMatch = !filters.search ||
                `${data.emp_nom} ${data.emp_prenom}`.toLowerCase().includes(filters.search.toLowerCase()) ||
                (data.conj_nom_prenom && data.conj_nom_prenom.toLowerCase().includes(filters.search.toLowerCase()));

            const dirMatch = !filters.direction || data.dir_nom === filters.direction;
            return !!(searchMatch && dirMatch);
        };
    }

    loadFamilies() {
        this.loading = true;
        this.employeeService.getFamilyList().subscribe({
            next: (data) => {
                this.dataSource.data = data;
                this.dataSource.paginator = this.paginator;
                this.dataSource.sort = this.sort;

                // Extract unique directions
                this.availableDirections = [...new Set(data.map(f => f.dir_nom).filter(Boolean) as string[])];
                this.loading = false;
            },
            error: () => {
                this.loading = false;
            }
        });
    }

    applyFilters() {
        const filters = {
            search: this.searchQuery,
            direction: this.directionFilter
        };
        this.dataSource.filter = JSON.stringify(filters);
        if (this.dataSource.paginator) {
            this.dataSource.paginator.firstPage();
        }
    }

    resetFilters() {
        this.searchQuery = '';
        this.directionFilter = '';
        this.applyFilters();
    }

    getGenderDetails(gender: any, nbEnfants: number = 0): string {
        const isMale = gender === true || gender === 't' || gender === 1;
        if (nbEnfants > 0) {
            return isMale ? 'Père' : 'Mère';
        }
        return isMale ? 'Marié' : 'Épouse';
    }

    viewEmployee(empCode: number) {
        this.router.navigate(['/employee', empCode]);
    }

    openFamilyMgmt(empCode: number) {
        this.router.navigate(['/employee', empCode, 'family']);
    }

    addNewFamily() {
        // Navigate to employee list so user can choose an employee to add a family to
        this.router.navigate(['/employee']);
    }
}
