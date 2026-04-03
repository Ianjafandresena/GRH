import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSelectModule } from '@angular/material/select';
import { StagiaireService } from '../../service/stagiaire.service';

@Component({
    selector: 'app-creer-stagiaire',
    standalone: true,
    imports: [
        CommonModule,
        ReactiveFormsModule,
        MatFormFieldModule,
        MatInputModule,
        MatButtonModule,
        MatCardModule,
        MatIconModule,
        MatProgressSpinnerModule,
        MatSelectModule
    ],
    templateUrl: './creer-stagiaire.component.html',
    styleUrls: ['./creer-stagiaire.component.scss']
})
export class CreerStagiaireComponent implements OnInit {
    stagiaireForm!: FormGroup;
    loading = false;
    errorMessage = '';
    successMessage = '';

    constructor(
        private fb: FormBuilder,
        private stagiaireService: StagiaireService,
        private router: Router
    ) { }

    ngOnInit(): void {
        this.initForm();
    }

    initForm(): void {
        this.stagiaireForm = this.fb.group({
            stgr_nom: ['', [Validators.required, Validators.minLength(2)]],
            stgr_prenom: ['', [Validators.required, Validators.minLength(2)]],
            stgr_contact: ['', [Validators.required]],
            stgr_filiere: [''],
            stgr_niveau: [''],
            stgr_sexe: [true, [Validators.required]],
            stgr_adresse: ['', [Validators.required]]
        });
    }

    onSubmit(): void {
        if (this.stagiaireForm.invalid) {
            this.stagiaireForm.markAllAsTouched();
            return;
        }

        this.loading = true;
        this.errorMessage = '';
        this.successMessage = '';

        this.stagiaireService.create(this.stagiaireForm.value).subscribe({
            next: (response) => {
                this.loading = false;
                const data: any = (response as any).data || response;
                this.successMessage = `Stagiaire ${data.stgr_prenom ?? ''} ${data.stgr_nom ?? ''} créé avec succès !`;
                setTimeout(() => this.router.navigate(['/stagiaires']), 1500);
            },
            error: (err) => {
                this.loading = false;
                this.errorMessage = err.error?.message || 'Erreur lors de la création du stagiaire';
            }
        });
    }

    onCancel(): void {
        this.router.navigate(['/stagiaires']);
    }

    getErrorMessage(fieldName: string): string {
        const control = this.stagiaireForm.get(fieldName);
        if (control?.hasError('required')) {
            return 'Ce champ est obligatoire';
        }
        if (control?.hasError('minlength')) {
            return 'Minimum 2 caractères';
        }
        return '';
    }
}
