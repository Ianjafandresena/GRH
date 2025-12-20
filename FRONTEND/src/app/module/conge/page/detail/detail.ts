import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { MatIconModule } from '@angular/material/icon';
import { CongeService } from '../../service/conge.service';
import { InterruptionService, Interruption } from '../../service/interruption.service';
import { ValidationCongeService, ValidationStatus } from '../../service/validation-conge.service';
import { LayoutService } from '../../../../shared/layout/service/layout.service';

@Component({
  selector: 'app-detail-conge',
  standalone: true,
  imports: [CommonModule, FormsModule, MatIconModule],
  templateUrl: './detail.html',
  styleUrls: ['./detail.scss']
})
export class DetailCongeComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly service = inject(CongeService);
  private readonly interruptionService = inject(InterruptionService);
  private readonly validationService = inject(ValidationCongeService);
  private readonly layoutService = inject(LayoutService);

  data: any = null;
  interruptionData: Interruption | null = null;
  validationStatus: ValidationStatus | null = null;
  loading = false;
  errorMsg = '';
  congeId: number = 0;

  // For validation actions
  validating = false;
  validationMsg = '';
  validationError = false;
  showRejectModal = false;
  rejectObservation = '';

  // Map step name to sign_code
  private readonly stepToSignCode: Record<string, number> = {
    'CHEF': 1,
    'RRH': 2,
    'DAAF': 3,
    'DG': 4
  };

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
        // Load validation status
        this.loadValidationStatus(id);
        // Check for existing interruption
        this.loadInterruption(id);
      },
      error: err => {
        this.errorMsg = err?.message || 'Erreur de chargement';
      },
      complete: () => {
        this.loading = false;
      }
    });
  }

  loadValidationStatus(cngCode: number) {
    this.validationService.getStatus(cngCode).subscribe({
      next: status => {
        this.validationStatus = status;
      }
    });
  }

  loadInterruption(cngCode: number) {
    this.interruptionService.getByConge(cngCode).subscribe({
      next: interruption => {
        this.interruptionData = interruption;
      }
    });
  }

  reloadCongeData() {
    if (!this.congeId) return;
    this.service.getCongeDetail(this.congeId).subscribe({
      next: d => {
        this.data = d;
      }
    });
  }

  /**
   * Check if the leave is fully validated (cng_status = true)
   * Handles PostgreSQL boolean variations ('t', true, 1)
   */
  isValidated(): boolean {
    if (!this.data?.conge) return false;
    const status = this.data.conge.cng_status;
    return status === true || status === 't' || status === 1 || status === '1';
  }

  /**
   * Check if the leave can be interrupted
   * A leave can be interrupted if:
   * - It is fully validated (cng_status = true)
   * - It is not already interrupted
   */
  canInterrupt(): boolean {
    if (!this.data?.conge || this.interruptionData) return false;
    return this.isValidated();
  }

  goToInterruption() {
    if (!this.congeId) return;
    this.router.navigate(['/conge/interruption', this.congeId]);
  }

  openPdfViewer() {
    if (!this.congeId) return;
    this.router.navigate(['/conge/viewer', this.congeId]);
  }

  getCurrentSignCode(): number {
    if (!this.validationStatus?.current_step) return 1;
    return this.stepToSignCode[this.validationStatus.current_step] || 1;
  }

  approveStep() {
    if (!this.congeId || !this.validationStatus?.current_step) return;

    const signCode = this.getCurrentSignCode();
    this.validating = true;
    this.validationMsg = '';

    this.validationService.validate(this.congeId, signCode).subscribe({
      next: (res) => {
        this.validationMsg = res.message || 'Validation effectuée';
        this.validationError = false;
        this.validating = false;
        // Reload validation status AND conge data for dynamic button update
        this.loadValidationStatus(this.congeId);
        this.reloadCongeData();
      },
      error: (err) => {
        this.validationMsg = err?.error?.messages?.error || 'Erreur lors de la validation';
        this.validationError = true;
        this.validating = false;
      }
    });
  }

  openRejectModal() {
    this.rejectObservation = '';
    this.showRejectModal = true;
  }

  closeRejectModal() {
    this.showRejectModal = false;
    this.rejectObservation = '';
  }

  confirmReject() {
    if (!this.congeId || !this.rejectObservation?.trim()) return;

    const signCode = this.getCurrentSignCode();
    this.validating = true;
    this.validationMsg = '';

    this.validationService.reject(this.congeId, signCode, this.rejectObservation).subscribe({
      next: (res) => {
        this.validationMsg = res.message || 'Demande rejetée';
        this.validationError = false;
        this.validating = false;
        this.closeRejectModal();
        // Reload validation status
        this.loadValidationStatus(this.congeId);
      },
      error: (err) => {
        this.validationMsg = err?.error?.messages?.error || 'Erreur lors du rejet';
        this.validationError = true;
        this.validating = false;
      }
    });
  }
}
