import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { CongeService } from '../../service/conge.service';
import { LayoutService } from '../../../../shared/layout/service/layout.service';

@Component({
  selector: 'app-conge-index',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './index.html',
  styleUrls: ['./index.scss']
})
export class CongeIndexComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly congeService = inject(CongeService);
  private readonly layoutService = inject(LayoutService);

  conges: any[] = [];
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
  getStatusLabel(cng_status: any): string {
    if (cng_status === true || cng_status === 't' || cng_status === 1) return 'Validé';
    if (cng_status === false || cng_status === 'f' || cng_status === 0) return 'En cours';
    return 'Rejeté';
  }

  getStatusClass(cng_status: any): string {
    if (cng_status === true || cng_status === 't' || cng_status === 1) return 'validated';
    if (cng_status === false || cng_status === 'f' || cng_status === 0) return 'pending';
    return 'rejected';
  }

  ngOnInit() {
    this.layoutService.setTitle('Gestion des Congés');
    this.route.data.subscribe(data => {
      this.conges = data['conges'] || [];
      if (!this.conges.length) {
        this.applyFilter();
      }
    });

    // Charger les régions
    this.congeService.getRegions().subscribe((regions: any[]) => {
      this.regions = regions;
      this.filteredRegions = regions;
    });
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
    this.congeService.getConges(params).subscribe({
      next: list => { this.conges = list || []; },
      error: err => { this.errorMsg = err?.message || 'Erreur lors du chargement'; },
      complete: () => { this.loading = false; }
    });
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
