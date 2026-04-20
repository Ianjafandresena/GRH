import { Component, OnInit, OnDestroy, inject } from '@angular/core';
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
export class DetailCongeComponent implements OnInit, OnDestroy {
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
  absenceType: string = '';

  // For validation actions
  validating = false;
  validationMsg = '';
  validationError = false;
  showRejectModal = false;
  showValidateModal = false;
  rejectObservation = '';

  // Permission reject
  showRejectPermModal = false;
  rejectPermMotif = '';

  // Auto-refresh interval
  private refreshInterval: any = null;

  ngOnInit() {
    this.layoutService.setTitle('Gestion des Absences');
    const id = Number(this.route.snapshot.paramMap.get('id'));
    if (!id) {
      this.errorMsg = 'Identifiant invalide';
      return;
    }

    this.congeId = id;
    this.absenceType = this.route.snapshot.queryParamMap.get('type') || '';
    this.loading = true;

    // Load leave/permission details
    this.service.getCongeDetail(id, this.absenceType).subscribe({
      next: d => {
        this.data = d;
        // Si c'est un conge, charger validations et interruptions
        if (this.data?.absence_type === 'conge') {
          this.loadValidationStatus(id);
          this.loadInterruption(id);
        }
      },
      error: err => {
        this.errorMsg = err?.message || 'Erreur de chargement';
      },
      complete: () => {
        this.loading = false;
        // Start auto-refresh every 10 seconds
        this.startAutoRefresh();
      }
    });
  }

  loadValidationStatus(cngCode: number) {
    this.validationService.getStatus(cngCode).subscribe({
      next: status => {
        this.validationStatus = status;
        this.validating = false;
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
    this.service.getCongeDetail(this.congeId, this.absenceType).subscribe({
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
   */
  canInterrupt(): boolean {
    if (!this.data?.conge || this.interruptionData) return false;
    if (!this.isValidated()) return false;

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const endDate = new Date(this.data.conge.cng_fin);
    if (endDate < today) return false;

    return true;
  }

  goToInterruption() {
    if (!this.congeId) return;
    this.router.navigate(['/conge/interruption', this.congeId]);
  }

  openPdfViewer() {
    if (!this.congeId || !this.data) return;
    this.router.navigate(['/conge/viewer', this.congeId], { 
      queryParams: { type: this.data.absence_type } 
    });
  }

  getCurrentSignCode(): number {
    if (!this.validationStatus?.current_step || !this.validationStatus?.steps) return 1;
    const currentStepObj = this.validationStatus.steps.find(s => s.step === this.validationStatus?.current_step);
    return currentStepObj?.sign_code || 1;
  }

  approveStep() {
    if (!this.congeId) return;

    // Si c'est une permission, on valide directement via le modal
    if (this.data?.absence_type === 'permission') {
      this.openValidateModal();
      return;
    }

    if (!this.validationStatus?.current_step) return;

    const signCode = this.getCurrentSignCode();
    this.validating = true;
    this.validationMsg = '';

    this.validationService.validate(this.congeId, signCode).subscribe({
      next: (res) => {
        this.validationMsg = res.message || 'Validation effectuée';
        this.validationError = false;
        this.validating = false;
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
        this.loadValidationStatus(this.congeId);
      },
      error: (err) => {
        this.validationMsg = err?.error?.messages?.error || 'Erreur lors du rejet';
        this.validationError = true;
        this.validating = false;
      }
    });
  }

  openValidateModal() {
    this.showValidateModal = true;
  }

  closeValidateModal() {
    this.showValidateModal = false;
  }

  openRejectPermModal() {
    this.rejectPermMotif = '';
    this.showRejectPermModal = true;
  }

  closeRejectPermModal() {
    this.showRejectPermModal = false;
    this.rejectPermMotif = '';
  }

  confirmRejectPermission() {
    if (!this.congeId || !this.rejectPermMotif?.trim()) return;
    this.validating = true;
    this.service.rejectPermission(this.congeId, this.rejectPermMotif).subscribe({
      next: () => {
        this.layoutService.showSuccessMessage('Permission refusée avec succès');
        this.closeRejectPermModal();
        this.reloadCongeData();
        this.validating = false;
      },
      error: (err) => {
        this.layoutService.showErrorMessage(err?.error?.messages?.error || 'Erreur lors du refus');
        this.validating = false;
      }
    });
  }

  confirmValidation() {
    this.validating = true;
    this.service.validatePermission(this.congeId).subscribe({
      next: () => {
        this.layoutService.showSuccessMessage('Permission validée avec succès');
        this.closeValidateModal();
        this.reloadCongeData();
        this.validating = false;
      },
      error: (err) => {
        this.layoutService.showErrorMessage(err?.error?.message || 'Erreur lors de la validation');
        this.validating = false;
      }
    });
  }

  private startAutoRefresh() {
    if (this.refreshInterval) clearInterval(this.refreshInterval);
    this.refreshInterval = setInterval(() => {
      if (this.congeId && !this.isValidated()) {
        this.loadValidationStatus(this.congeId);
        this.reloadCongeData();
      }
    }, 10000);
  }

  ngOnDestroy() {
    if (this.refreshInterval) clearInterval(this.refreshInterval);
  }
}
