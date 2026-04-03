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
import { MatRadioModule } from '@angular/material/radio';
import { MatSnackBarModule, MatSnackBar } from '@angular/material/snack-bar';
import { EmployeService } from '../../service/employe.service';
import { Employe, TypeEntree } from '../../model/employe.model';
import { StatutArmpService, StatutArmp } from '../../../referentiel/service/statut-armp.service';

export interface ModifierEmployeDialogData {
    employe: Employe;
}

@Component({
    selector: 'app-modifier-employe-dialog',
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
        MatRadioModule,
        MatSnackBarModule,
    ],
    templateUrl: './modifier-employe-dialog.component.html',
    styleUrl: './modifier-employe-dialog.component.scss'
})
export class ModifierEmployeDialogComponent implements OnInit {
    form: FormGroup;
    loading = false;
    errorMessage = '';
    typesEntree: TypeEntree[] = [];
    statutsArmp: StatutArmp[] = [];

    constructor(
        private fb: FormBuilder,
        private employeService: EmployeService,
        private statutArmpService: StatutArmpService,
        private dialogRef: MatDialogRef<ModifierEmployeDialogComponent>,
        private snackBar: MatSnackBar,
        @Inject(MAT_DIALOG_DATA) public data: ModifierEmployeDialogData,
    ) {
        this.form = this.fb.group({
            emp_nom: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(50)]],
            emp_prenom: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(50)]],
            emp_titre: ['M.', [Validators.required]],
            emp_sexe: [true, [Validators.required]],
            emp_datenaissance: ['', [Validators.required]],
            emp_im_armp: ['', [Validators.required, Validators.maxLength(50)]],
            emp_cin: ['', [Validators.required, Validators.pattern(/^[0-9]{3} [0-9]{3} [0-9]{3} [0-9]{3}$/)]],
            emp_im_etat: [''],
            emp_matricule: [''],
            emp_mail: ['', [Validators.required, Validators.email, Validators.maxLength(100)]],
            emp_contact: ['', [Validators.required]],
            date_entree: ['', [Validators.required]],
            e_type_code: ['', [Validators.required]],
            stt_armp_code: ['']
        });
    }

    ngOnInit(): void {
        // Charger les statuts ARMP
        this.statutArmpService.getStatutsArmp().subscribe({
            next: (data) => this.statutsArmp = data
        });

        // Charger les types d'entrée puis pré-remplir le formulaire
        this.employeService.getTypesEntree().subscribe({
            next: (types: TypeEntree[]) => {
                this.typesEntree = types;
                // Pré-remplir le formulaire après le chargement des types d'entrée
                this.populateForm();
            },
            error: (err: any) => {
                console.error('Erreur chargement types entrée:', err);
                // Pré-remplir quand même le formulaire même en cas d'erreur
                this.populateForm();
            }
        });
    }

    private populateForm(): void {
        if (!this.data.employe) return;

        const emp = this.data.employe;

        // Convertir emp_sexe en boolean
        let empSexeValue: boolean = true; // Valeur par défaut
        if (emp.emp_sexe !== undefined && emp.emp_sexe !== null) {
            if (typeof emp.emp_sexe === 'number') {
                // Si c'est un nombre (0 ou 1)
                empSexeValue = emp.emp_sexe === 1;
            } else if (typeof emp.emp_sexe === 'boolean') {
                // Si c'est déjà un boolean
                empSexeValue = emp.emp_sexe;
            } else if (typeof emp.emp_sexe === 'string') {
                // Si c'est une chaîne ('t', 'f', 'true', 'false', etc.)
                empSexeValue = emp.emp_sexe === 't' || emp.emp_sexe === 'true' || emp.emp_sexe === '1';
            }
        }

        this.form.patchValue({
            emp_nom: emp.emp_nom || '',
            emp_prenom: emp.emp_prenom || '',
            emp_titre: emp.emp_titre || 'M.',
            emp_sexe: empSexeValue,
            emp_datenaissance: emp.emp_datenaissance ? new Date(emp.emp_datenaissance) : null,
            emp_im_armp: emp.emp_im_armp || '',
            emp_cin: emp.emp_cin || '',
            emp_im_etat: emp.emp_im_etat || '',
            emp_matricule: emp.emp_matricule || '',
            emp_mail: emp.emp_mail || '',
            emp_contact: emp.emp_contact || '', // Le contact vient maintenant de la table contact via le backend
            date_entree: emp.date_entree ? new Date(emp.date_entree) : null,
            e_type_code: emp.e_type_code || '',
            stt_armp_code: emp.stt_armp_code || ''
        });
    }

    onCancel(): void {
        this.dialogRef.close(false);
    }

    onSubmit(): void {
        if (this.form.invalid) {
            this.form.markAllAsTouched();
            return;
        }

        this.loading = true;
        this.errorMessage = '';

        const formValue = this.form.value;

        if (!this.data.employe.emp_code) {
            this.errorMessage = 'ID employé manquant';
            this.loading = false;
            return;
        }

        // Préparer les données pour l'API (convertir emp_sexe en 0/1 pour le backend)
        const apiData: any = {
            emp_nom: formValue.emp_nom,
            emp_prenom: formValue.emp_prenom,
            emp_titre: formValue.emp_titre,
            emp_sexe: formValue.emp_sexe ? 1 : 0, // Convertir boolean en 0/1 pour le backend
            emp_datenaissance: this.formatDate(formValue.emp_datenaissance),
            emp_im_armp: formValue.emp_im_armp,
            emp_cin: formValue.emp_cin,
            emp_im_etat: formValue.emp_im_etat || null,
            emp_matricule: formValue.emp_matricule || null,
            emp_mail: formValue.emp_mail,
            emp_contact: formValue.emp_contact,
            date_entree: this.formatDate(formValue.date_entree),
            e_type_code: formValue.e_type_code,
            stt_armp_code: formValue.stt_armp_code
        };

        this.employeService.update(this.data.employe.emp_code, apiData).subscribe({
            next: () => {
                this.loading = false;
                this.snackBar.open('Employé modifié avec succès', 'Fermer', {
                    duration: 3000,
                    horizontalPosition: 'center',
                    verticalPosition: 'top',
                    panelClass: ['success-snackbar']
                });
                this.dialogRef.close(true);
            },
            error: (err: any) => {
                this.loading = false;
                this.errorMessage = err.error?.message || err.error?.errors || 'Erreur lors de la modification de l\'employé';
                this.snackBar.open(this.errorMessage, 'Fermer', {
                    duration: 5000,
                    horizontalPosition: 'center',
                    verticalPosition: 'top',
                    panelClass: ['error-snackbar']
                });
            }
        });
    }

    private formatDate(date: any): string {
        if (!date) return '';
        if (date instanceof Date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
        return date;
    }

    formatCin(event: any): void {
        const input = event.target as HTMLInputElement;
        const initialValue = input.value;
        let value = input.value.replace(/\D/g, ''); // Enlever tout sauf les chiffres

        if (value.length > 12) {
            value = value.substring(0, 12);
        }

        // Formater XXX XXX XXX XXX
        let formattedValue = value;
        if (value.length > 0) {
            formattedValue = value.match(/.{1,3}/g)?.join(' ') || value;
        }

        // Uniquement mettre à jour si nécessaire pour éviter de faire sauter le curseur
        if (initialValue !== formattedValue) {
            input.value = formattedValue;
        }

        this.form.get('emp_cin')?.setValue(formattedValue, { emitEvent: false });
    }
}
