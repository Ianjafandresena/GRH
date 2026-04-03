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
import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { MatSnackBarModule, MatSnackBar } from '@angular/material/snack-bar';
import { Observable } from 'rxjs';
import { map, startWith } from 'rxjs/operators';

import { EmployeService } from '../../service/employe.service';
import { PosteService, Poste } from '../../../poste/service/poste.service';
import { AffectationService } from '../../../affectation/service/affectation.service';
import { TypeContratService, TypeContrat } from '../../../referentiel/service/type-contrat.service';
import { MotifAffectation } from '../../../affectation/model/affectation.model';

export interface ReintegrationDialogData {
    emp_code: number;
    emp_nom: string;
    emp_prenom: string;
}

@Component({
    selector: 'app-reintegration-dialog',
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
        MatAutocompleteModule,
        MatSnackBarModule,
    ],
    template: `
        <h2 mat-dialog-title>
            <mat-icon>restore_page</mat-icon>
            Réintégrer un employé
        </h2>

        <mat-dialog-content>
            <p class="info-text">
                Réintégration de <strong>{{ data.emp_prenom }} {{ data.emp_nom }}</strong>
            </p>

            <form [formGroup]="form">
                <!-- Date Réintégration -->
                <mat-form-field appearance="outline" class="full-width">
                    <mat-label>Date de réintégration</mat-label>
                    <input matInput [matDatepicker]="picker" formControlName="date_reintegration" (click)="picker.open()" (focus)="picker.open()" readonly />
                    <mat-datepicker-toggle matSuffix [for]="picker"></mat-datepicker-toggle>
                    <mat-datepicker #picker></mat-datepicker>
                    <mat-error *ngIf="form.get('date_reintegration')?.hasError('required')">
                        La date est obligatoire
                    </mat-error>
                </mat-form-field>

                <!-- Poste (Autocomplete) -->
                <mat-form-field appearance="outline" class="full-width">
                    <mat-label>Nouveau Poste</mat-label>
                    <input type="text" matInput formControlName="poste_input" [matAutocomplete]="autoPoste">
                    <mat-autocomplete #autoPoste="matAutocomplete" [displayWith]="displayPoste" (optionSelected)="onPosteSelected($event)">
                        <mat-option *ngFor="let poste of filteredPostes | async" [value]="poste">
                            <span>{{ poste.pst_fonction }}</span>
                            <small class="service-name" *ngIf="poste.srvc_nom"> ({{ poste.srvc_nom }})</small>
                        </mat-option>
                    </mat-autocomplete>
                    <mat-icon matPrefix>work</mat-icon>
                    <mat-error *ngIf="form.get('pst_code')?.hasError('required')">
                        Le poste est obligatoire
                    </mat-error>
                </mat-form-field>

                <div class="row">
                    <!-- Type Contrat -->
                    <mat-form-field appearance="outline" class="half-width">
                        <mat-label>Type de Contrat</mat-label>
                        <mat-select formControlName="tcontrat_code">
                            <mat-option *ngFor="let type of typesContrat" [value]="type.tcontrat_code">
                                {{ type.tcontrat_nom }}
                            </mat-option>
                        </mat-select>
                        <mat-error *ngIf="form.get('tcontrat_code')?.hasError('required')">
                            Requis
                        </mat-error>
                    </mat-form-field>

                    <!-- Motif -->
                    <mat-form-field appearance="outline" class="half-width">
                        <mat-label>Motif Affectation</mat-label>
                        <mat-select formControlName="m_aff_code">
                            <mat-option *ngFor="let motif of motifs" [value]="motif.m_aff_code">
                                {{ motif.m_aff_motif }}
                            </mat-option>
                        </mat-select>
                        <mat-error *ngIf="form.get('m_aff_code')?.hasError('required')">
                            Requis
                        </mat-error>
                    </mat-form-field>
                </div>

                <!-- Commentaire -->
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
            <button mat-raised-button color="primary" type="button" (click)="onSubmit()" [disabled]="loading || form.invalid">
                <mat-spinner *ngIf="loading" diameter="20"></mat-spinner>
                <span *ngIf="!loading">Réintégrer</span>
            </button>
        </mat-dialog-actions>
    `,
    styles: [`
        :host { display: block; border-radius: 20px; overflow: hidden; }
        
        h2[mat-dialog-title] {
            display: flex; align-items: center; gap: 0.75rem; 
            margin: 0; padding: 1.5rem; 
            background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%);
            color: #1e293b;
            font-weight: 800;
            letter-spacing: -0.5px;
            border-bottom: 1px solid rgba(0,0,0,0.05);

            mat-icon { 
                color: #3b82f6; 
                background: #fff;
                padding: 8px;
                border-radius: 12px;
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
                width: 40px; height: 40px; font-size: 24px;
                display: flex; align-items: center; justify-content: center;
            }
        }

        mat-dialog-content { 
            padding: 1.5rem 2rem; 
            min-width: 550px;
            background: #fff;
        }

        .info-text { 
            margin-bottom: 1.5rem; color: #64748b; font-size: 1rem;
            padding: 1rem; background: #f8fafc; border-radius: 12px;
            border-left: 4px solid #3b82f6;
            strong { color: #1e293b; }
        }

        .full-width { width: 100%; margin-bottom: 1rem; }
        .row { display: flex; gap: 1rem; }
        .half-width { flex: 1; }
        
        mat-form-field {
            ::ng-deep .mat-mdc-text-field-wrapper {
                background-color: #f8fafc !important;
                border-radius: 12px !important;
            }
        }

        .service-name { color: #94a3b8; font-size: 0.8rem; font-weight: 500; }

        .error-message {
            display: flex; align-items: center; gap: 0.75rem; padding: 1rem;
            background: #fff1f2; color: #be185d; border-radius: 12px; 
            margin-top: 1rem; font-weight: 600; font-size: 0.9rem;
            border: 1px solid #fecdd3;
        }

        mat-dialog-actions { 
            padding: 1.25rem 2rem; 
            background: #f8fafc;
            border-top: 1px solid rgba(0,0,0,0.05);
            
            button {
                border-radius: 10px;
                padding: 0 1.5rem;
                font-weight: 600;
                height: 44px;
            }

            button[color="primary"] {
                background: #3b82f6;
                box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
                &:hover {
                    background: #2563eb;
                    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
                }
            }
        }
    `]
})
export class ReintegrationDialogComponent implements OnInit {
    form: FormGroup;
    loading = false;
    errorMessage = '';

    postes: Poste[] = [];
    filteredPostes!: Observable<Poste[]>;
    typesContrat: TypeContrat[] = [];
    motifs: MotifAffectation[] = [];

    constructor(
        private fb: FormBuilder,
        private employeService: EmployeService,
        private posteService: PosteService,
        private affectationService: AffectationService,
        private typeContratService: TypeContratService,
        private dialogRef: MatDialogRef<ReintegrationDialogComponent>,
        private snackBar: MatSnackBar,
        @Inject(MAT_DIALOG_DATA) public data: ReintegrationDialogData,
    ) {
        this.form = this.fb.group({
            date_reintegration: [new Date(), Validators.required],
            poste_input: ['', Validators.required],
            pst_code: ['', Validators.required],
            tcontrat_code: ['', Validators.required],
            m_aff_code: ['', Validators.required],
            commentaire: ['Réintégration']
        });
    }

    ngOnInit(): void {
        this.loadPostes();
        this.loadTypesContrat();
        this.loadMotifs();

        this.filteredPostes = this.form.get('poste_input')!.valueChanges.pipe(
            startWith(''),
            map(value => this._filterPostes(value || ''))
        );
    }

    private _filterPostes(value: string | Poste): Poste[] {
        const filterValue = typeof value === 'string' ? value.toLowerCase() : value.pst_fonction.toLowerCase();
        return this.postes.filter(poste =>
            poste.pst_fonction.toLowerCase().includes(filterValue) ||
            (poste.srvc_nom && poste.srvc_nom.toLowerCase().includes(filterValue))
        );
    }

    displayPoste(poste: Poste): string {
        return poste && poste.pst_fonction ? poste.pst_fonction : '';
    }

    onPosteSelected(event: any): void {
        const poste = event.option.value as Poste;
        this.form.patchValue({ pst_code: poste.pst_code });
    }

    loadPostes(): void {
        // Obtenir seulement les postes vacants (disponibles)
        this.posteService.list({ disponibles_only: 'true' }).subscribe(postes => {
            this.postes = postes;
        });
    }

    loadTypesContrat(): void {
        this.typeContratService.getTypesContrat().subscribe(types => {
            this.typesContrat = types;
        });
    }

    loadMotifs(): void {
        this.affectationService.getMotifs().subscribe(motifs => {
            // Filtrer éventuellement pour trouver un motif adapté à la réintégration
            this.motifs = motifs;

            // Essayer de pré-sélectionner "Réintégration" si existe
            const reintegrationMotif = motifs.find(m => m.m_aff_motif.toLowerCase().includes('réintégration') || m.m_aff_motif.toLowerCase().includes('reintegration'));
            if (reintegrationMotif) {
                this.form.patchValue({ m_aff_code: reintegrationMotif.m_aff_code });
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

        const payload = {
            pst_code: this.form.value.pst_code,
            date_reintegration: this.formatDate(this.form.value.date_reintegration),
            tcontrat_code: this.form.value.tcontrat_code,
            m_aff_code: this.form.value.m_aff_code,
            commentaire: this.form.value.commentaire
        };

        this.employeService.reintegration(this.data.emp_code, payload).subscribe({
            next: () => {
                this.loading = false;
                this.snackBar.open('Employé réintégré avec succès', 'Fermer', {
                    duration: 3000,
                    panelClass: ['success-snackbar']
                });
                this.dialogRef.close(true);
            },
            error: (err) => {
                this.loading = false;
                this.errorMessage = err.error?.message || 'Erreur lors de la réintégration';
                this.snackBar.open(this.errorMessage, 'Fermer', {
                    duration: 5000,
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
