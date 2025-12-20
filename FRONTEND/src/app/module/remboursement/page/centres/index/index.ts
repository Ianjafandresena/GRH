import { Component, OnInit, AfterViewInit, ViewChild, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatPaginator, MatPaginatorModule } from '@angular/material/paginator';
import { MatSort, MatSortModule } from '@angular/material/sort';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatFormFieldModule } from '@angular/material/form-field';

import { CentreSanteService } from '../../../service/centre-sante.service';
import { LayoutService } from '../../../../../shared/layout/service/layout.service';
import { CentreSante } from '../../../model/centre-sante.model';

@Component({
    selector: 'app-centres-index',
    standalone: true,
    imports: [
        CommonModule,
        FormsModule,
        MatTableModule,
        MatPaginatorModule,
        MatSortModule,
        MatButtonModule,
        MatIconModule,
        MatInputModule,
        MatFormFieldModule
    ],
    templateUrl: './index.html',
    styleUrls: ['./index.scss']
})
export class CentresIndexComponent implements OnInit, AfterViewInit {
    private readonly route = inject(ActivatedRoute);
    private readonly router = inject(Router);
    private readonly centreService = inject(CentreSanteService);
    private readonly layoutService = inject(LayoutService);

    dataSource = new MatTableDataSource<CentreSante>([]);
    displayedColumns: string[] = ['cen_code', 'cen_nom', 'cen_adresse', 'convention', 'actions'];

    @ViewChild(MatPaginator) paginator!: MatPaginator;
    @ViewChild(MatSort) sort!: MatSort;

    loading = false;
    errorMsg = '';

    ngOnInit() {
        this.layoutService.setTitle('Centres de Santé');
        this.loadCentres();
    }

    ngAfterViewInit() {
        this.dataSource.paginator = this.paginator;
        this.dataSource.sort = this.sort;
    }

    loadCentres() {
        this.loading = true;
        this.centreService.getCentres().subscribe({
            next: (list) => {
                this.dataSource.data = list || [];
                this.loading = false;
            },
            error: (err) => {
                this.errorMsg = 'Erreur lors du chargement des centres';
                this.loading = false;
            }
        });
    }

    applyFilter(event: Event) {
        const filterValue = (event.target as HTMLInputElement).value;
        this.dataSource.filter = filterValue.trim().toLowerCase();
        if (this.dataSource.paginator) {
            this.dataSource.paginator.firstPage();
        }
    }

    goToCreate() {
        this.router.navigate(['/remboursement/centres/create']);
    }

    editCentre(id: number) {
        this.router.navigate(['/remboursement/centres', id, 'edit']);
    }

    deleteCentre(id: number) {
        if (confirm('Voulez-vous vraiment supprimer ce centre ?')) {
            this.centreService.deleteCentre(id).subscribe({
                next: () => this.loadCentres(),
                error: (err) => this.errorMsg = 'Erreur lors de la suppression'
            });
        }
    }
}
