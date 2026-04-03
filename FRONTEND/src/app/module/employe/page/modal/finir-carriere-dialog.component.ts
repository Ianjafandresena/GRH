import { Component, Inject, OnInit } from '@angular/core';
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
import { MatSnackBarModule, MatSnackBar } from '@angular/material/snack-bar';
import { EmployeService, SortieType } from '../../service/employe.service';

export interface FinirCarriereDialogData {
    emp_code: number;
    emp_nom: string;
    emp_prenom: string;
}

@Component({
    selector: 'app-finir-carriere-dialog',
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
        MatSnackBarModule,
    ],
    template: `
        <h2 mat-dialog-title>
            <mat-icon>work_off</mat-icon>
            Finir la carrière
        </h2>

        <mat-dialog-content>
            <p class="info-text">
                Terminer la carrière de <strong>{{ data.emp_prenom }} {{ data.emp_nom }}</strong>
            </p>

            <form [formGroup]="form">
                <mat-form-field appearance="outline" class="full-width">
                    <mat-label>Date de sortie</mat-label>
                    <input matInput [matDatepicker]="picker" formControlName="date_sortie" (click)="picker.open()" (focus)="picker.open()" readonly />
                    <mat-datepicker-toggle matSuffix [for]="picker"></mat-datepicker-toggle>
                    <mat-datepicker #picker></mat-datepicker>
                    <mat-error *ngIf="form.get('date_sortie')?.hasError('required')">
                        La date de sortie est obligatoire
                    </mat-error>
                </mat-form-field>

                <mat-form-field appearance="outline" class="full-width">
                    <mat-label>Type de sortie</mat-label>
                    <mat-select formControlName="s_type_code">
                        <mat-option *ngFor="let type of typesSortie" [value]="type.s_type_code">
                            {{ type.s_type_motif }}
                        </mat-option>
                    </mat-select>
                    <mat-icon matPrefix>exit_to_app</mat-icon>
                    <mat-error *ngIf="form.get('s_type_code')?.hasError('required')">
                        Le type de sortie est obligatoire
                    </mat-error>
                </mat-form-field>

                <mat-form-field appearance="outline" class="full-width">
                    <mat-label>Commentaire</mat-label>
                    <textarea matInput formControlName="commentaire" rows="3" placeholder="Commentaire optionnel..."></textarea>
                    <mat-icon matPrefix>comment</mat-icon>
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
                <span *ngIf="!loading">Terminer la carrière</span>
            </button>
        </mat-dialog-actions>
    `,
    styles: [`
        :host { display: block; border-radius: 20px; overflow: hidden; }

        h2[mat-dialog-title] {
            display: flex; align-items: center; gap: 0.75rem; 
            margin: 0; padding: 1.5rem 2rem; 
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #991b1b; font-weight: 800; border-bottom: none;
            mat-icon { 
                background: #fff; padding: 8px; border-radius: 12px;
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
                color: #dc2626;
            }
        }

        mat-dialog-content { padding: 2rem !important; min-width: 500px; background: #fff; }

        .info-text { 
            margin-bottom: 1.5rem; color: #64748b; font-size: 1rem;
            padding: 1rem; background: #fff1f2; border-radius: 12px;
            border-left: 4px solid #ef4444;
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
export class FinirCarriereDialogComponent implements OnInit {
    form: FormGroup;
    loading = false;
    errorMessage = '';
    typesSortie: SortieType[] = [];

    constructor(
        private fb: FormBuilder,
        private employeService: EmployeService,
        private dialogRef: MatDialogRef<FinirCarriereDialogComponent>,
        private snackBar: MatSnackBar,
        @Inject(MAT_DIALOG_DATA) public data: FinirCarriereDialogData,
    ) {
        this.form = this.fb.group({
            date_sortie: [new Date(), Validators.required],
            s_type_code: ['', Validators.required],
            commentaire: ['']
        });
    }

    ngOnInit(): void {
        this.loadTypesSortie();
    }

    loadTypesSortie(): void {
        this.employeService.getTypesSortie().subscribe({
            next: (types: SortieType[]) => {
                this.typesSortie = types;
            },
            error: (err: any) => {
                console.error('Erreur lors du chargement des types de sortie:', err);
                this.errorMessage = 'Impossible de charger les types de sortie';
            }
        });
    }

    onCancel(): void {
        this.dialogRef.close(false);
    }

    onSubmit(): void {
        if (this.form.invalid || this.loading) return;

        this.loading = true;
        this.errorMessage = '';

        const dateSortie = this.formatDate(this.form.value.date_sortie);
        const sTypeCode = this.form.value.s_type_code;
        const commentaire = this.form.value.commentaire?.trim() || null;

        this.employeService.finirCarriere(this.data.emp_code, dateSortie, sTypeCode, commentaire).subscribe({
            next: () => {
                this.loading = false;
                this.snackBar.open('Carrière terminée avec succès', 'Fermer', {
                    duration: 3000,
                    horizontalPosition: 'center',
                    verticalPosition: 'top',
                    panelClass: ['success-snackbar']
                });
                this.dialogRef.close(true);
            },
            error: (err: any) => {
                this.loading = false;
                const errorMsg = err.error?.message || err.error?.errors?.position || err.error?.errors?.date_sortie || err.error?.errors?.s_type_code || 'Erreur lors de la finalisation de la carrière';
                this.errorMessage = errorMsg;
                this.snackBar.open(errorMsg, 'Fermer', {
                    duration: 5000,
                    horizontalPosition: 'center',
                    verticalPosition: 'top',
                    panelClass: ['error-snackbar']
                });
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

