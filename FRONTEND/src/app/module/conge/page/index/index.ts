import { Component, OnInit, inject, AfterViewInit, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { CongeService } from '../../service/conge.service';
import { LayoutService } from '../../../../shared/layout/service/layout.service';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatPaginator, MatPaginatorModule } from '@angular/material/paginator';
import { MatSort, MatSortModule } from '@angular/material/sort';

@Component({
  selector: 'app-conge-index',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    RouterLink,
    MatTableModule,
    MatPaginatorModule,
    MatSortModule
  ],
  templateUrl: './index.html',
  styleUrls: ['./index.scss']
})
export class CongeIndexComponent implements OnInit, AfterViewInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly congeService = inject(CongeService);
  private readonly layoutService = inject(LayoutService);

  conges: any[] = [];
  absences: any[] = [];
  dataSource = new MatTableDataSource<any>([]);
  displayedColumns: string[] = ['employee', 'type', 'start', 'end', 'duration', 'reason', 'status', 'actions'];

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

  displayMode: 'unified' | 'conge-only' = 'unified';
  absenceTypeFilter: string = '';

  start: string | null = null;
  end: string | null = null;
  typ_code: number | null = null;
  lieu: string | null = null;
  loading = false;
  errorMsg = '';

  regions: any[] = [];
  filteredRegions: any[] = [];
  showRegionDropdown = false;

  // Helper for status display
  getStatusLabel(status: any): string {
    if (status === true || status === 't' || status === 1) return 'Validé';
    if (status === false || status === 'f' || status === 0) return 'Rejeté';
    return 'En attente';
  }

  getStatusClass(status: any): string {
    if (status === true || status === 't' || status === 1) return 'validated';
    if (status === false || status === 'f' || status === 0) return 'rejected';
    return 'pending';
  }

  ngOnInit() {
    this.layoutService.setTitle('Gestion des Absences');
    this.route.data.subscribe(data => {
      this.conges = data['conges'] || [];
      if (!this.conges.length) {
        this.applyFilter();
      } else {
        this.absences = this.conges.map(c => ({ ...c, absence_type: 'conge' }));
        this.dataSource.data = this.absences;
      }
    });

    this.congeService.getRegions().subscribe((regions: any[]) => {
      this.regions = regions;
      this.filteredRegions = regions;
    });
  }

  ngAfterViewInit() {
  }

  // Region Filter Logic
  onRegionFocus() {
    this.showRegionDropdown = true;
    if (!this.lieu) {
      this.filteredRegions = this.regions;
    }
  }

  onRegionBlur() {
    setTimeout(() => { this.showRegionDropdown = false; }, 200);
  }

  filterRegions() {
    const filterVal = this.lieu ? this.lieu.toLowerCase() : '';
    this.filteredRegions = this.regions.filter(r =>
      r.reg_nom.toLowerCase().includes(filterVal)
    );
    this.showRegionDropdown = true;
  }

  selectRegion(region: any) {
    this.lieu = region.reg_nom;
    this.showRegionDropdown = false;
  }

  exportCsv() {
    this.congeService.exportCongesCsv().subscribe(blob => {
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'conges.csv';
      a.click();
      window.URL.revokeObjectURL(url);
    });
  }

  importCsv(file: File) {
    if (!file) return;
    const form = new FormData();
    form.append('file', file);
    this.congeService.importCongesCsv(form).subscribe(() => {
      this.congeService.getConges().subscribe(list => { this.conges = list || []; });
    });
  }

  applyFilter() {
    const params: any = {};
    if (this.start) params.start = this.start;
    if (this.end) params.end = this.end;
    if (this.typ_code) params.typ_code = this.typ_code;
    if (this.lieu) params.lieu = this.lieu;

    this.loading = true;
    this.errorMsg = '';

    this.congeService.getAbsences(params).subscribe({
      next: (absences) => {
        this.absences = absences || [];
        this.conges = this.absences.filter(a => a.absence_type === 'conge');
        this.applyClientSideFilter();
      },
      error: (err) => {
        this.errorMsg = err?.message || 'Erreur lors du chargement';
      },
      complete: () => {
        this.loading = false;
      }
    });
  }

  applyClientSideFilter() {
    let result = [...this.absences];
    if (this.absenceTypeFilter) {
      result = result.filter(a => a.absence_type === this.absenceTypeFilter);
    }
    this.dataSource.data = result;
  }

  getAbsenceType(item: any): string {
    return item.absence_type === 'permission' ? 'Permission' : 'Congé';
  }

  getAbsenceTypeClass(item: any): string {
    return item.absence_type === 'permission' ? 'badge-permission' : 'badge-conge';
  }

  reload() {
    this.applyFilter();
  }

  exportExcel() {
    this.congeService.exportCongesExcel().subscribe(blob => {
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'conges.xls';
      a.click();
      window.URL.revokeObjectURL(url);
    });
  }

  openDetail(id: number) {
    this.router.navigate(['/conge/detail', id]);
  }

  create() {
    this.router.navigate(['/conge/create']);
  }
}
