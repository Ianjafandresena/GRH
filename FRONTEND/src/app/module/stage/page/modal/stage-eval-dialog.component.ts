import { Component, Inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { MatDialogModule, MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule } from '@angular/material/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../../environments/environment';

export interface StageEvalDialogData {
    stg_code: number;
    evstg_code?: number | null;
}

export interface EvalStage {
    evstg_code?: number;
    evstg_lieu?: string;
    evstg_note?: number;
    evstg_aptitude?: string;
    evstg_date_eval?: string;
    asdt_code?: number;
    asdt_remarque?: string;
    asdt_nb_abscence?: number;
    asdt_nb_retard?: number;
}

@Component({
    selector: 'app-stage-eval-dialog',
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
    ],
    templateUrl: './stage-eval-dialog.component.html',
    styleUrls: ['./stage-eval-dialog.component.scss']
})
export class StageEvalDialogComponent implements OnInit {
    form!: FormGroup;
    loading = false;
    loadingData = false;
    errorMessage = '';
    isEdit = false;
    existingEval: EvalStage | null = null;

    private apiUrl = environment.apiUrl;

    constructor(
        private fb: FormBuilder,
        private http: HttpClient,
        private dialogRef: MatDialogRef<StageEvalDialogComponent>,
        @Inject(MAT_DIALOG_DATA) public data: StageEvalDialogData,
    ) {}

    ngOnInit(): void {
        this.form = this.fb.group({
            asdt_remarque: [''],
            asdt_nb_abscence: [null],
            asdt_nb_retard: [null],
            evstg_lieu: [''],
            evstg_note: [null],
            evstg_aptitude: [''],
            evstg_date_eval: [''],
        });

        this.loadExistingEval();
    }

    private loadExistingEval(): void {
        this.loadingData = true;
        this.http.get<EvalStage>(`${this.apiUrl}/stages/${this.data.stg_code}/eval`).subscribe({
            next: (eval_data) => {
                this.loadingData = false;
                if (eval_data && eval_data.evstg_code) {
                    this.isEdit = true;
                    this.existingEval = eval_data;
                    this.form.patchValue({
                        asdt_remarque: eval_data.asdt_remarque || '',
                        asdt_nb_abscence: eval_data.asdt_nb_abscence || null,
                        asdt_nb_retard: eval_data.asdt_nb_retard || null,
                        evstg_lieu: eval_data.evstg_lieu || '',
                        evstg_note: eval_data.evstg_note || null,
                        evstg_aptitude: eval_data.evstg_aptitude || '',
                        evstg_date_eval: eval_data.evstg_date_eval || '',
                    });
                }
            },
            error: () => {
                this.loadingData = false;
            }
        });
    }

    onCancel(): void {
        this.dialogRef.close(false);
    }

    onSubmit(): void {
        if (this.loading) return;

        this.loading = true;
        this.errorMessage = '';

        const raw = this.form.getRawValue();
        const payload = {
            stg_code: this.data.stg_code,
            asdt_remarque: raw.asdt_remarque,
            asdt_nb_abscence: raw.asdt_nb_abscence,
            asdt_nb_retard: raw.asdt_nb_retard,
            evstg_lieu: raw.evstg_lieu,
            evstg_note: raw.evstg_note,
            evstg_aptitude: raw.evstg_aptitude,
            evstg_date_eval: this.formatDate(raw.evstg_date_eval),
        };

        if (this.isEdit && this.existingEval?.evstg_code) {
            this.http.put(`${this.apiUrl}/eval-stages/${this.existingEval.evstg_code}`, payload).subscribe({
                next: () => {
                    this.loading = false;
                    this.dialogRef.close(true);
                },
                error: (err) => {
                    this.loading = false;
                    this.errorMessage = err.error?.message || "Erreur lors de la mise à jour";
                }
            });
        } else {
            this.http.post(`${this.apiUrl}/eval-stages`, payload).subscribe({
                next: () => {
                    this.loading = false;
                    this.dialogRef.close(true);
                },
                error: (err) => {
                    this.loading = false;
                    this.errorMessage = err.error?.message || "Erreur lors de la création";
                }
            });
        }
    }

    private formatDate(date: any): string {
        if (!date) return '';
        if (date instanceof Date) {
            return date.toISOString().split('T')[0];
        }
        return date;
    }
}
