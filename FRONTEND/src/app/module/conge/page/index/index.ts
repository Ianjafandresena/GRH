import { Component, OnInit, inject, AfterViewInit, ViewChild, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { CongeService } from '../../service/conge.service';
import { LayoutService } from '../../../../shared/layout/service/layout.service';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatPaginator, MatPaginatorModule } from '@angular/material/paginator';
import { MatSort, MatSortModule } from '@angular/material/sort';
import { Subject, Subscription } from 'rxjs';
import { debounceTime, distinctUntilChanged } from 'rxjs/operators';

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
export class CongeIndexComponent implements OnInit, AfterViewInit, OnDestroy {
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

  private filterSubject = new Subject<void>();
  private filterSubscription?: Subscription;

  // Helper for status display
  getStatusLabel(status: any): string {
    if (status === true || status === 't' || status === 1) return 'Validé';
    if (status === false || status === 'f' || status === 0) return 'En attente';
    return 'En attente';
  }

  getStatusClass(status: any): string {
    if (status === true || status === 't' || status === 1) return 'validated';
    if (status === false || status === 'f' || status === 0) return 'pending';
    return 'pending';
  }

  validatePermission(id: number) {
    if (!confirm('Voulez-vous vraiment valider cette permission ? Le solde de l\'employé sera débité.')) return;
    
    this.congeService.validatePermission(id).subscribe({
      next: () => {
        this.layoutService.showSuccessMessage('Permission validée avec succès');
        this.loadData();
      },
      error: (err: any) => {
        this.layoutService.showErrorMessage(err?.error?.message || 'Erreur lors de la validation');
      }
    });
  }

  ngOnInit() {
    this.layoutService.setTitle('Gestion des Absences');

    // Setup dynamic filtering with debounce
    this.filterSubscription = this.filterSubject.pipe(
      debounceTime(400),
      distinctUntilChanged()
    ).subscribe(() => {
      this.loadData();
    });

    this.route.data.subscribe(data => {
      const resolvedAbsences = data['conges'] as any[];
      if (resolvedAbsences && resolvedAbsences.length) {
        this.absences = resolvedAbsences;
        this.conges = this.absences.filter(a => a.absence_type === 'conge');
        this.applyClientSideFilter();
      } else {
        this.loadData();
      }
    });

    this.congeService.getRegions().subscribe((regions: any[]) => {
      this.regions = regions;
      this.filteredRegions = regions;
    });
  }

  ngOnDestroy() {
    this.filterSubscription?.unsubscribe();
  }

  ngAfterViewInit() {
  }

  // Trigger dynamic filter
  onFilterChange() {
    this.filterSubject.next();
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
    this.onFilterChange();
  }

  selectRegion(region: any) {
    this.lieu = region.reg_nom;
    this.showRegionDropdown = false;
    this.onFilterChange();
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
      this.loadData();
    });
  }

  loadData() {
    const params: any = {};
    if (this.start) params.start = this.start;
    if (this.end) params.end = this.end;
    if (this.typ_code) params.typ_code = this.typ_code;
    if (this.lieu) params.lieu = this.lieu;
    params.all = true; // IMPORTANT: On montre tout pour que l'admin puisse valider

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
        this.loading = false;
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
    this.loadData();
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

  openDetail(item: any) {
    const id = item.cng_code || item.prm_code;
    const type = item.absence_type;
    this.router.navigate(['/conge/detail', id], { queryParams: { type } });
  }

  create() {
    this.router.navigate(['/conge/create']);
  }
}
