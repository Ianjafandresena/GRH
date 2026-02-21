import { Component, inject, OnInit, ViewChild, AfterViewInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { MatIconModule } from '@angular/material/icon';
import { PrisEnChargeService } from '../../../service/prise-en-charge.service';
import { LayoutService } from '../../../../../shared/layout/service/layout.service';
import { PrisEnCharge } from '../../../model/prise-en-charge.model';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatPaginator, MatPaginatorModule } from '@angular/material/paginator';
import { MatSort, MatSortModule } from '@angular/material/sort';

@Component({
    selector: 'app-prises-en-charge-index',
    standalone: true,
    imports: [
        CommonModule,
        MatIconModule,
        MatTableModule,
        MatPaginatorModule,
        MatSortModule
    ],
    templateUrl: './index.html',
    styleUrls: ['./index.scss']
})
export class PrisesEnChargeIndexComponent implements OnInit, AfterViewInit {
    private readonly router = inject(Router);
    private readonly pecService = inject(PrisEnChargeService);
    private readonly layoutService = inject(LayoutService);

    prises: PrisEnCharge[] = [];
    dataSource = new MatTableDataSource<PrisEnCharge>([]);
    displayedColumns: string[] = ['pec_num', 'employee', 'centre', 'status', 'actions'];

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
    errorMsg = '';

    ngOnInit() {
        this.layoutService.setTitle('Prises en Charge');
        this.loadData();
    }

    ngAfterViewInit() {
    }

    loadData() {
        this.loading = true;
        this.pecService.getAll().subscribe({
            next: (list) => {
                this.prises = list || [];
                this.dataSource.data = this.prises;
                this.loading = false;
            },
            error: () => {
                this.errorMsg = 'Erreur chargement';
                this.loading = false;
            }
        });
    }

    goToCreate() {
        this.router.navigate(['/remboursement/prises-en-charge/create']);
    }

    goToDetail(id: number) {
        this.router.navigate(['/remboursement/prises-en-charge', id]);
    }

    downloadBulletin(id: number) {
        this.pecService.downloadBulletin(id).subscribe(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `prise_en_charge_${id}.pdf`;
            a.click();
        });
    }
}
