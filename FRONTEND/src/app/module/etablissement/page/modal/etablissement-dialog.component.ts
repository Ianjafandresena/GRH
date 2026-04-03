import { Component, Inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';

import { Etablissement } from '../../service/etablissement.service';

export type EtablissementDialogMode = 'create' | 'edit';

export interface EtablissementDialogData {
    mode: EtablissementDialogMode;
    etablissement?: Etablissement;
}

@Component({
    selector: 'app-etablissement-dialog',
    standalone: true,
    imports: [
        CommonModule,
        ReactiveFormsModule,
        MatDialogModule,
        MatFormFieldModule,
        MatInputModule,
        MatButtonModule,
        MatIconModule
    ],
    templateUrl: './etablissement-dialog.component.html',
    styleUrls: ['./etablissement-dialog.component.scss']
})
export class EtablissementDialogComponent {
    form: FormGroup;

    constructor(
        private fb: FormBuilder,
        private dialogRef: MatDialogRef<EtablissementDialogComponent>,
        @Inject(MAT_DIALOG_DATA) public data: EtablissementDialogData
    ) {
        this.form = this.fb.group({
            etab_nom: [data.etablissement?.etab_nom ?? '', [Validators.required, Validators.minLength(2)]],
            etab_adresse: [data.etablissement?.etab_adresse ?? '']
        });
    }

    save(): void {
        if (this.form.invalid) {
            this.form.markAllAsTouched();
            return;
        }
        this.dialogRef.close(this.form.value);
    }

    close(): void {
        this.dialogRef.close(null);
    }

    getTitle(): string {
        return this.data.mode === 'create' ? 'Nouvel établissement' : 'Modifier établissement';
    }
}
