import { Component, Inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatDialogModule, MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDividerModule } from '@angular/material/divider';
import { StageService, Stage } from '../../service/stage.service';

export interface StageDetailDialogData {
    stage: Stage;
}

export interface EvalStageDetail {
    evstg_code?: number;
    evstg_lieu?: string;
    evstg_note?: number;
    evstg_aptitude?: string;
    evstg_date_eval?: string;
    asdt_remarque?: string;
    asdt_nb_abscence?: number;
    asdt_nb_retard?: number;
}

@Component({
    selector: 'app-stage-detail-dialog',
    standalone: true,
    imports: [
        CommonModule,
        MatDialogModule,
        MatButtonModule,
        MatIconModule,
        MatProgressSpinnerModule,
        MatDividerModule,
    ],
    templateUrl: './stage-detail-dialog.component.html',
    styleUrls: ['./stage-detail-dialog.component.scss']
})
export class StageDetailDialogComponent implements OnInit {
    stage: Stage;
    evaluation: EvalStageDetail | null = null;
    loadingEval = false;

    constructor(
        private stageService: StageService,
        private dialogRef: MatDialogRef<StageDetailDialogComponent>,
        @Inject(MAT_DIALOG_DATA) public data: StageDetailDialogData,
    ) {
        this.stage = data.stage;
    }

    ngOnInit(): void {
        this.loadEvaluation();
    }

    private loadEvaluation(): void {
        if (!this.stage?.stg_code) return;

        this.loadingEval = true;
        this.stageService.getEvaluation(this.stage.stg_code).subscribe({
            next: (eval_data) => {
                this.loadingEval = false;
                if (eval_data && eval_data.evstg_code) {
                    this.evaluation = eval_data;
                }
            },
            error: () => {
                this.loadingEval = false;
            }
        });
    }

    onClose(): void {
        this.dialogRef.close();
    }

    formatDate(date: string | undefined): string {
        if (!date) return '-';
        try {
            return new Date(date).toLocaleDateString('fr-FR');
        } catch {
            return date;
        }
    }

    telechargerDemandeAttestation(): void {
        if (!this.stage?.stg_code) return;

        this.stageService.telechargerDemandeAttestation(this.stage.stg_code).subscribe({
            next: (res) => {
                if (res.pdf_base64) {
                    const blob = this.base64ToBlob(res.pdf_base64, 'application/pdf');
                    const url = window.URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = res.filename || `Demande_Attestation_${this.stage.stg_code}.pdf`;
                    link.click();
                    window.URL.revokeObjectURL(url);
                }
            },
            error: (err) => {
                console.error('Erreur lors du téléchargement de la demande:', err);
            }
        });
    }

    private base64ToBlob(base64: string, type: string): Blob {
        const binStr = atob(base64);
        const len = binStr.length;
        const arr = new Uint8Array(len);
        for (let i = 0; i < len; i++) {
            arr[i] = binStr.charCodeAt(i);
        }
        return new Blob([arr], { type });
    }
}
