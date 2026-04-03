import { Component, Inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatDialogModule, MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatDividerModule } from '@angular/material/divider';
import { MatCardModule } from '@angular/material/card';

export interface ResumeCreationDialogData {
    employe: any;
    affectation?: any;
    poste?: any;
    motif?: any;
    typeEntree?: any;
    typeContrat?: any;
}

@Component({
    selector: 'app-resume-creation-dialog',
    standalone: true,
    imports: [
        CommonModule,
        MatDialogModule,
        MatButtonModule,
        MatIconModule,
        MatDividerModule,
        MatCardModule,
    ],
    template: `
        <h2 mat-dialog-title>
            <mat-icon>preview</mat-icon>
            Résumé de la création
        </h2>

        <mat-dialog-content>
            <div class="resume-container">
                <!-- Informations employé -->
                <mat-card class="resume-section">
                    <mat-card-header>
                        <mat-card-title>
                            <mat-icon>person</mat-icon>
                            Informations de l'employé
                        </mat-card-title>
                    </mat-card-header>
                    <mat-card-content>
                        <div class="info-row">
                            <span class="label">Nom complet:</span>
                            <span class="value">{{ data.employe.emp_titre }} {{ data.employe.emp_prenom }} {{ data.employe.emp_nom }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Sexe:</span>
                            <span class="value">{{ (data.employe.emp_sexe === true || data.employe.emp_sexe === 1) ? 'Homme' : 'Femme' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Date de naissance:</span>
                            <span class="value">{{ formatDate(data.employe.emp_datenaissance) }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">IM ARMP:</span>
                            <span class="value">{{ data.employe.emp_im_armp }}</span>
                        </div>
                        <div class="info-row" *ngIf="data.employe.emp_im_etat">
                            <span class="label">IM État:</span>
                            <span class="value">{{ data.employe.emp_im_etat }}</span>
                        </div>
                        <div class="info-row" *ngIf="data.employe.emp_matricule">
                            <span class="label">Matricule:</span>
                            <span class="value">{{ data.employe.emp_matricule }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Email:</span>
                            <span class="value">{{ data.employe.emp_mail }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Contact:</span>
                            <span class="value">{{ data.employe.emp_contact }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Date d'entrée:</span>
                            <span class="value">{{ formatDate(data.employe.date_entree) }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Type d'entrée:</span>
                            <span class="value">{{ data.typeEntree?.e_type_motif || data.employe.e_type_code }}</span>
                        </div>
                    </mat-card-content>
                </mat-card>

                <!-- Affectation initiale -->
                <mat-card class="resume-section" *ngIf="data.affectation">
                    <mat-card-header>
                        <mat-card-title>
                            <mat-icon>work</mat-icon>
                            Affectation initiale
                        </mat-card-title>
                    </mat-card-header>
                    <mat-card-content>
                        <div class="info-row">
                            <span class="label">Poste:</span>
                            <span class="value">{{ data.poste?.pst_fonction || 'Poste #' + data.affectation.pst_code }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Motif:</span>
                            <span class="value">{{ data.motif?.m_aff_motif || 'Motif #' + data.affectation.m_aff_code }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Date de début:</span>
                            <span class="value">{{ formatDate(data.affectation.affec_date_debut) }}</span>
                        </div>
                        <div class="info-row" *ngIf="data.affectation.affec_date_fin">
                            <span class="label">Date de fin:</span>
                            <span class="value">{{ formatDate(data.affectation.affec_date_fin) }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Type de contrat:</span>
                            <span class="value">
                                <span class="badge" [ngClass]="'badge-' + data.typeContrat?.tcontrat_nom?.toLowerCase()">
                                    {{ data.typeContrat?.tcontrat_nom || 'N/A' }}
                                </span>
                            </span>
                        </div>
                        <div class="info-row" *ngIf="data.affectation.affec_commentaire">
                            <span class="label">Commentaire:</span>
                            <span class="value">{{ data.affectation.affec_commentaire }}</span>
                        </div>
                    </mat-card-content>
                </mat-card>
            </div>
        </mat-dialog-content>

        <mat-dialog-actions align="end">
            <button mat-button (click)="onCancel()">Annuler</button>
            <button mat-raised-button color="primary" (click)="onConfirm()">
                <mat-icon>check</mat-icon>
                Confirmer la création
            </button>
        </mat-dialog-actions>
    `,
    styles: [`
        :host { display: block; }

        h2[mat-dialog-title] {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;

            mat-icon { color: #6366f1; }
        }

        mat-dialog-content {
            padding-top: 1rem;
            min-width: 500px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .resume-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .resume-section {
            border-radius: 4px !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1) !important;
            border: 1px solid #e2e8f0;
            overflow: hidden;

            mat-card-header {
                background: #f8fafc;
                padding: 0.75rem 1rem;
                margin: 0;
                border-bottom: 1px solid #e2e8f0;

                mat-card-title {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    margin: 0;
                    font-size: 0.9rem;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.025em;

                    mat-icon {
                        color: #6366f1;
                        font-size: 18px;
                        width: 18px;
                        height: 18px;
                    }
                }
            }

            mat-card-content {
                padding: 0.5rem 1rem !important;
            }
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f1f5f9;

            &:last-child {
                border-bottom: none;
            }

            .label {
                font-weight: 600;
                color: #64748b;
                min-width: 150px;
            }

            .value {
                color: #1e293b;
                text-align: right;
                flex: 1;

                &.badge {
                    display: inline-block;
                    padding: 0.25rem 0.75rem;
                    border-radius: 9999px;
                    font-size: 0.75rem;
                    font-weight: 700;
                    text-transform: uppercase;

                    &.badge-fonctionnaire {
                        background-color: #0284c7;
                        color: #ffffff;
                    }

                    &.badge-efa {
                        background-color: #10b981;
                        color: #ffffff;
                    }

                    &.badge-eld {
                        background-color: #f59e0b;
                        color: #ffffff;
                    }
                }
            }
        }

        mat-dialog-actions {
            padding: 1rem 0 0 0;
            border-top: 1px solid #e2e8f0;
        }

        ::ng-deep .mat-mdc-dialog-container .mdc-dialog__surface {
            border-radius: 4px !important;
        }
    `]
})
export class ResumeCreationDialogComponent {
    constructor(
        private dialogRef: MatDialogRef<ResumeCreationDialogComponent>,
        @Inject(MAT_DIALOG_DATA) public data: ResumeCreationDialogData,
    ) { }

    formatDate(date: any): string {
        if (!date) return 'N/A';
        if (date instanceof Date) {
            return date.toLocaleDateString('fr-FR');
        }
        if (typeof date === 'string') {
            return new Date(date).toLocaleDateString('fr-FR');
        }
        return date;
    }

    onCancel(): void {
        this.dialogRef.close(false);
    }

    onConfirm(): void {
        this.dialogRef.close(true);
    }
}

