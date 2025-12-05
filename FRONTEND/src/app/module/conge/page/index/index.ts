import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { CongeService } from '../../service/conge.service';
import { LayoutService } from '../../../layout/service/layout.service';

@Component({
  selector: 'app-conge-index',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './index.html',
  styleUrls: ['./index.css']
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

  ngOnInit() {
    this.layoutService.setTitle('Gestion des Congés');
    this.route.data.subscribe(data => {
      this.conges = data['conges'] || [];
      if (!this.conges.length) {
        this.applyFilter();
      }
    });
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

}
