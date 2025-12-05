import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { MatIconModule } from '@angular/material/icon';
import { CongeService } from '../../service/conge.service';
import { LayoutService } from '../../../layout/service/layout.service';

@Component({
  selector: 'app-detail-conge',
  standalone: true,
  imports: [CommonModule, MatIconModule],
  templateUrl: './detail.html',
  styleUrls: ['./detail.css']
})
export class DetailCongeComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly service = inject(CongeService);
  private readonly layoutService = inject(LayoutService);

  data: any = null;
  loading = false;
  errorMsg = '';
  congeId: number = 0;

  ngOnInit() {
    this.layoutService.setTitle('Gestion des Congés');
    const id = Number(this.route.snapshot.paramMap.get('id'));
    if (!id) {
      this.errorMsg = 'Identifiant invalide';
      return;
    }

    this.congeId = id;
    this.loading = true;

    // Load leave details
    this.service.getCongeDetail(id).subscribe({
      next: d => {
        this.data = d;
      },
      error: err => {
        this.errorMsg = err?.message || 'Erreur de chargement';
      },
      complete: () => {
        this.loading = false;
      }
    });
  }

  openPdfViewer() {
    if (!this.congeId) return;

    // Navigate to viewer page in same tab
    this.router.navigate(['/conge/viewer', this.congeId]);
  }
}
