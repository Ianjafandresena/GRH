import { Component, Inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { MatDialogModule, MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule } from '@angular/material/core';
import { MatSelectModule } from '@angular/material/select';
import { AffectationService } from '../../service/affectation.service';

export interface CloturerAffectationDialogData {
    affec_code: number;
    emp_nom?: string;
    emp_prenom?: string;
}

@Component({
    selector: 'app-cloturer-affectation-dialog',
    standalone: true,
    imports: [
        CommonModule,
        ReactiveFormsModule,
        MatDialogModule,
        MatFormFieldModule,
        MatInputModule,
        MatButtonModule,
        MatIconModule,
        MatProgressSpinnerModule,
        MatDatepickerModule,
        MatNativeDateModule,
        MatSelectModule,
    ],
    template: `
        <h2 mat-dialog-title>
            <mat-icon>event_available</mat-icon>
            Clôturer l'affectation
        </h2>

        <mat-dialog-content>
            <p class="info-text">
                Clôturer l'affectation de <strong>{{ data.emp_prenom }} {{ data.emp_nom }}</strong>
            </p>

            <form [formGroup]="form">
                <mat-form-field appearance="outline" class="full-width">
                    <mat-label>Date de fin</mat-label>
                    <input matInput [matDatepicker]="picker" formControlName="affec_date_fin" (click)="picker.open()" (focus)="picker.open()" readonly />
                    <mat-datepicker-toggle matSuffix [for]="picker"></mat-datepicker-toggle>
                    <mat-datepicker #picker></mat-datepicker>
                    <mat-error *ngIf="form.get('affec_date_fin')?.hasError('required')">
                        La date de fin est obligatoire
                    </mat-error>
                </mat-form-field>

                <mat-form-field appearance="outline" class="full-width">
                    <mat-label>Statut du poste après clôture</mat-label>
                    <mat-select formControlName="statut_poste">
                        <mat-option value="vacant">
                            Vacant
                        </mat-option>
                        <mat-option value="cessation">
                            En cessation
                        </mat-option>
                    </mat-select>
                    <mat-icon matPrefix>info</mat-icon>
                    <mat-hint>Indiquez si le poste devient vacant ou en cessation</mat-hint>
                </mat-form-field>
            </form>

            <div *ngIf="errorMessage" class="error-message">
                <mat-icon>error</mat-icon>
                {{ errorMessage }}
            </div>
        </mat-dialog-content>

        <mat-dialog-actions align="end">
            <button mat-stroked-button type="button" (click)="onCancel()" [disabled]="loading">
                Annuler
            </button>
            <button mat-raised-button color="warn" type="button" (click)="onSubmit()" [disabled]="loading || form.invalid">
                <mat-spinner *ngIf="loading" diameter="20"></mat-spinner>
                <span *ngIf="!loading">Clôturer</span>
            </button>
        </mat-dialog-actions>
    `,
    styles: [`
        :host { display: block; border-radius: 20px; overflow: hidden; }

        h2[mat-dialog-title] {
            display: flex; align-items: center; gap: 0.75rem; 
            margin: 0; padding: 1.5rem 2rem; 
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            color: #92400e; font-weight: 800; border-bottom: none;
            mat-icon { 
                background: #fff; padding: 8px; border-radius: 12px;
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
                color: #d97706;
            }
        }

        mat-dialog-content { padding: 2rem !important; min-width: 450px; background: #fff; }

        .info-text { 
            margin-bottom: 1.5rem; color: #64748b; font-size: 1rem;
            padding: 1rem; background: #fffbeb; border-radius: 12px;
            border-left: 4px solid #f59e0b;
            strong { color: #1e293b; }
        }

        .full-width { width: 100%; margin-bottom: 1rem; }
        
        mat-form-field {
            ::ng-deep .mat-mdc-text-field-wrapper {
                background-color: #fafafa !important;
                border-radius: 12px !important;
            }
        }

        .error-message {
            display: flex; align-items: center; gap: 0.75rem; padding: 1rem;
            background: #fff1f2; color: #be185d; border-radius: 12px; 
            margin-top: 1rem; font-weight: 600;
        }

        mat-dialog-actions { 
            padding: 1.25rem 2rem; background: #fafafa;
            button { border-radius: 10px; font-weight: 600; height: 44px; }
            button[color="warn"] {
                background: #ef4444; color: #fff;
                box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
                &:hover { background: #dc2626; }
            }
        }
    `]
})
export class CloturerAffectationDialogComponent {
    form: FormGroup;
    loading = false;
    errorMessage = '';

    constructor(
        private fb: FormBuilder,
        private affectationService: AffectationService,
        private dialogRef: MatDialogRef<CloturerAffectationDialogComponent>,
        @Inject(MAT_DIALOG_DATA) public data: CloturerAffectationDialogData,
    ) {
        this.form = this.fb.group({
            affec_date_fin: [new Date(), Validators.required],
            statut_poste: ['vacant', Validators.required],
        });
    }

    onCancel(): void {
        this.dialogRef.close(false);
    }

    onSubmit(): void {
        if (this.form.invalid || this.loading) return;

        this.loading = true;
        this.errorMessage = '';

        const dateFin = this.formatDate(this.form.value.affec_date_fin);
        const statutPoste = this.form.value.statut_poste;

        this.affectationService.cloturer(this.data.affec_code, dateFin, statutPoste).subscribe({
            next: () => {
                this.loading = false;
                this.dialogRef.close(true);
            },
            error: (err) => {
                this.loading = false;
                this.errorMessage = err.error?.message || 'Erreur lors de la clôture';
            }
        });
    }

    private formatDate(date: any): string {
        if (date instanceof Date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
        return date;
    }
}
