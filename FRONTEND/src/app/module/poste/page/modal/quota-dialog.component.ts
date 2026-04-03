import { Component, Inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { PosteService } from '../../service/poste.service';

export interface QuotaDialogData {
    pst_code: number;
    pst_fonction: string;
    quota: number;
    nb_occupe: number;
    nb_vacant: number;
    nb_encessation: number;
}

@Component({
    selector: 'app-quota-dialog',
    standalone: true,
    imports: [
        CommonModule,
        FormsModule,
        MatDialogModule,
        MatFormFieldModule,
        MatInputModule,
        MatButtonModule,
        MatIconModule
    ],
    templateUrl: './quota-dialog.component.html',
    styleUrls: ['./quota-dialog.component.scss']
})
export class QuotaDialogComponent {
    nouveauQuota: number;
    minQuota: number;
    loading = false;
    errorMessage = '';

    constructor(
        public dialogRef: MatDialogRef<QuotaDialogComponent>,
        @Inject(MAT_DIALOG_DATA) public data: QuotaDialogData,
        private posteService: PosteService
    ) {
        this.nouveauQuota = Number(data.quota);
        this.minQuota = Number(data.nb_occupe) + Number(data.nb_encessation);
    }

    onCancel(): void {
        this.dialogRef.close(false);
    }

    onSave(): void {
        if (this.nouveauQuota < this.minQuota) {
            this.errorMessage = `Le quota ne peut pas être inférieur à ${this.minQuota}`;
            return;
        }

        this.loading = true;
        this.errorMessage = '';

        this.posteService.updateQuota(this.data.pst_code, this.nouveauQuota).subscribe({
            next: () => {
                this.loading = false;
                this.dialogRef.close(true);
            },
            error: (err) => {
                this.loading = false;
                console.error('Erreur mise à jour quota:', err);

                if (err.error?.messages?.quota) {
                    this.errorMessage = err.error.messages.quota;
                } else if (err.error?.message) {
                    this.errorMessage = err.error.message;
                } else {
                    this.errorMessage = 'Erreur lors de la mise à jour du quota';
                }
            }
        });
    }

    get isValid(): boolean {
        return this.nouveauQuota >= this.minQuota && Number(this.nouveauQuota) !== Number(this.data.quota);
    }
}
