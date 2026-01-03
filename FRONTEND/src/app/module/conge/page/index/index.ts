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

  conges: any[] = [];  // Conservé pour compatibilité
  absences: any[] = [];  // ➕ NOUVEAU: Liste unifiée congés + permissions
  displayMode: 'unified' | 'conge-only' = 'unified';  // ➕ Mode d'affichage
  absenceTypeFilter: string = '';  // ➕ '' = tous, 'conge' = congés, 'permission' = permissions

  start: string | null = null;
  end: string | null = null;
  typ_code: number | null = null;
  lieu: string | null = null;
  loading = false;
  errorMsg = '';
  successMsg: string | null = null;  // ➕ AJOUTÉ pour notification

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
    this.layoutService.setTitle('Gestion des Absences');  // 🔄 MODIFIÉ: Titre unifié
    this.route.data.subscribe(data => {
      // Charger données initiales (resolver peut retourner congés)
      this.conges = data['conges'] || [];
      if (!this.conges.length) {
        this.applyFilter();
      } else {
        // Si données du resolver, mapper en absences
        this.absences = this.conges.map(c => ({ ...c, absence_type: 'conge' }));
      }
    });

    // Charger les régions
    this.congeService.getRegions().subscribe((regions: any[]) => {
      this.regions = regions;
      this.filteredRegions = regions;
    });

    // ➕ NOUVEAU: Écouter les messages de succès
    this.layoutService.successMessage$.subscribe(msg => {
      this.successMsg = msg;
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

    // ➕ NOUVEAU: Charger absences unifiées (congés + permissions)
    this.congeService.getAbsences(params).subscribe({
      next: (absences) => {
        this.absences = absences || [];
        // Conserver aussi dans conges pour compatibilité
        this.conges = this.absences.filter(a => a.absence_type === 'conge');
        this.applyClientSideFilter();  // Appliquer filtre type côté client
      },
      error: (err) => {
        this.errorMsg = err?.message || 'Erreur lors du chargement';
      },
      complete: () => {
        this.loading = false;
      }
    });
  }

  /**
   * ➕ NOUVEAU: Filtrage côté client par type d'absence
   */
  applyClientSideFilter() {
    if (!this.absenceTypeFilter) {
      // Aucun filtre type → afficher tout
      return;
    }

    // Filtrer par type
    this.absences = this.absences.filter(a => a.absence_type === this.absenceTypeFilter);
  }

  /**
   * ➕ NOUVEAU: Helper pour déterminer le type d'absence
   */
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
