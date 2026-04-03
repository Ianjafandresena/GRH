import { Component, Inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatDialogModule, MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatSelectModule } from '@angular/material/select';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';

import { EmployeService } from '../../../employe/service/employe.service';
import { Employe } from '../../../employe/model/employe.model';
import { StageService } from '../../service/stage.service';

export interface StageCarriereDialogData {
    stg_code: number;
    emp_code?: number | null;
}

@Component({
    selector: 'app-stage-carriere-dialog',
    standalone: true,
    imports: [
        CommonModule,
        ReactiveFormsModule,
        MatDialogModule,
        MatFormFieldModule,
        MatSelectModule,
        MatInputModule,
        MatButtonModule,
        MatIconModule,
        MatProgressSpinnerModule
    ],
    templateUrl: './stage-carriere-dialog.component.html',
    styleUrls: ['./stage-carriere-dialog.component.scss']
})
export class StageCarriereDialogComponent {
    form: FormGroup;

    employes: Employe[] = [];

    loading = false;
    errorMessage = '';

    constructor(
        private fb: FormBuilder,
        private employeService: EmployeService,
        private stageService: StageService,
        private dialogRef: MatDialogRef<StageCarriereDialogComponent>,
        @Inject(MAT_DIALOG_DATA) public data: StageCarriereDialogData,
    ) {
        this.form = this.fb.group({
            emp_code: [this.data?.emp_code ?? null, Validators.required],
        });

        this.loadEmployes();
    }

    private loadEmployes(): void {
        this.employeService.getEmployes().subscribe({
            next: (data: Employe[]) => {
                const list = data || [];
                const selectedEmpCode = this.form.value.emp_code;

                if (selectedEmpCode) {
                    const selected = list.find(e => e.emp_code === selectedEmpCode);
                    const others = list.filter(e => e.emp_code !== selectedEmpCode);
                    this.employes = selected ? [selected, ...others] : list;
                } else {
                    this.employes = list;
                }
            },
            error: () => {
                this.errorMessage = "Erreur lors du chargement des employés";
            }
        });
    }

    displayEmploye(e?: Employe): string {
        if (!e) return '';
        return `${e.emp_nom} ${e.emp_prenom}`;
    }

    onCancel(): void {
        this.dialogRef.close(false);
    }

    onSubmit(): void {
        if (this.form.invalid || this.loading) return;

        this.loading = true;
        this.errorMessage = '';

        const payload = {
            emp_code: this.form.value.emp_code,
        };

        this.stageService.assignCarriere(this.data.stg_code, payload).subscribe({
            next: () => {
                this.loading = false;
                this.dialogRef.close(true);
            },
            error: () => {
                this.loading = false;
                this.errorMessage = "Erreur lors de l'assignation de la carrière";
            }
        });
    }
}
