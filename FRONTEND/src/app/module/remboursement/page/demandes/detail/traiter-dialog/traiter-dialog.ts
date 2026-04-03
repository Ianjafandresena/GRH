import { Component, Inject, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../../../../environments/environment';

export interface TraiterDialogData {
  rem_code: number;
  emp_code: number;
  emp_nom: string;
}

@Component({
  selector: 'app-traiter-dialog',
  standalone: true,
  imports: [CommonModule, FormsModule, MatDialogModule],
  template: `
    <h2 mat-dialog-title>Traiter la demande</h2>
    <mat-dialog-content>
      <p>Agent: <strong>{{ data.emp_nom }}</strong></p>

      <div class="form-group">
        <label>État de Remboursement</label>
        <select [(ngModel)]="selectedEtatCode" class="form-control">
          <option [value]="null">-- Choisir un état existant --</option>
          <option *ngFor="let etat of etatsAgent" [value]="etat.eta_code">
            {{ etat.etat_num }} ({{ etat.eta_total | number:'1.2-2' }} Ar)
          </option>
        </select>
      </div>

      <div class="separator">
        <span>OU</span>
      </div>

      <button class="btn-create-new" (click)="createNewEtat()" type="button">
        <span class="icon">+</span>
        Créer un Nouvel État
      </button>

      <p *ngIf="willCreateNew" class="info-text">
        ℹ️ Un numéro d'état sera généré automatiquement (format: NNN/ARMP/DG/DAAF/SERVICE/MOIS-YY)
      </p>

      <div class="error" *ngIf="errorMsg">{{ errorMsg }}</div>
    </mat-dialog-content>
    <mat-dialog-actions align="end">
      <button class="btn btn-secondary" (click)="onCancel()">Annuler</button>
      <button class="btn btn-primary" (click)="onConfirm()" [disabled]="loading || (!selectedEtatCode && !willCreateNew)">
        {{ loading ? 'Traitement...' : 'Confirmer' }}
      </button>
    </mat-dialog-actions>
  `,
  styles: [`
    .form-group { margin-bottom: 16px; }
    .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
    label { display: block; margin-bottom: 6px; font-weight: 600; color: #333; }
    
    .separator {
      text-align: center;
      margin: 20px 0;
      position: relative;
      
      &::before, &::after {
        content: '';
        position: absolute;
        top: 50%;
        width: 40%;
        height: 1px;
        background: #ddd;
      }
      
      &::before { left: 0; }
      &::after { right: 0; }
      
      span {
        background: white;
        padding: 0 12px;
        color: #999;
        font-size: 13px;
        font-weight: 500;
      }
    }
    
    .btn-create-new {
      width: 100%;
      padding: 14px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      font-size: 15px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: all 0.3s;
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
      
      &:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
      }
      
      &:active {
        transform: translateY(0);
      }
      
      .icon {
        font-size: 20px;
        font-weight: 700;
      }
    }
    
    .info-text { 
      color: #17a2b8; 
      font-size: 0.875rem; 
      margin-top: 12px; 
      padding: 10px; 
      background: #e7f6f8; 
      border-radius: 6px;
      border-left: 3px solid #17a2b8;
    }
    
    .error { 
      color: #dc3545; 
      margin-top: 8px; 
      padding: 10px;
      background: #f8d7da;
      border-radius: 6px;
      border-left: 3px solid #dc3545;
    }
    
    .btn { 
      padding: 10px 20px; 
      border: none; 
      border-radius: 6px; 
      cursor: pointer; 
      font-weight: 600;
      transition: all 0.2s;
    }
    
    .btn-primary { 
      background: #007bff; 
      color: white; 
      
      &:hover:not(:disabled) {
        background: #0056b3;
      }
      
      &:disabled {
        background: #ccc;
        cursor: not-allowed;
      }
    }
    
    .btn-secondary { 
      background: #6c757d; 
      color: white; 
      margin-right: 8px;
      
      &:hover {
        background: #5a6268;
      }
    }
  `]
})
export class TraiterDialogComponent implements OnInit {
  private readonly http = inject(HttpClient);
  private readonly dialogRef = inject(MatDialogRef<TraiterDialogComponent>);

  etatsAgent: any[] = [];
  selectedEtatCode: number | null = null;
  willCreateNew = false;
  loading = false;
  errorMsg = '';

  constructor(@Inject(MAT_DIALOG_DATA) public data: TraiterDialogData) { }

  ngOnInit() {
    this.loadEtatsAgent();
  }

  loadEtatsAgent() {
    this.http.get<any[]>(`${environment.apiUrl}/etat_remb/agent/${this.data.emp_code}`)
      .subscribe({
        next: (etats) => this.etatsAgent = etats,
        error: () => this.etatsAgent = []
      });
  }

  createNewEtat() {
    this.willCreateNew = true;
    this.selectedEtatCode = null; // Désélectionner l'état existant
  }

  onCancel() {
    this.dialogRef.close(false);
  }

  onConfirm() {
    if (!this.selectedEtatCode && !this.willCreateNew) {
      this.errorMsg = 'Veuillez sélectionner un état ou créer un nouvel état';
      return;
    }

    this.loading = true;
    this.errorMsg = '';

    let payload: any = {};

    if (this.willCreateNew) {
      payload = { create_new: true };
    } else {
      payload = { eta_code: this.selectedEtatCode };
    }

    this.http.post(`${environment.apiUrl}/remboursement/${this.data.rem_code}/traiter`, payload)
      .subscribe({
        next: (res: any) => {
          this.loading = false;
          this.dialogRef.close({ success: true, ...res });
        },
        error: (err) => {
          this.loading = false;
          this.errorMsg = err.error?.messages?.error || err.error?.message || 'Erreur lors du traitement';
        }
      });
  }
}
