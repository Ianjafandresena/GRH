import { Component, OnInit } from '@angular/core';
import { trigger, transition, style, animate } from '@angular/animations';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { CompetenceService, Competence } from '../../service/competence.service';

@Component({
    selector: 'app-creer-competence',
    standalone: true,
    imports: [
        CommonModule,
        ReactiveFormsModule,
        MatFormFieldModule,
        MatInputModule,
        MatButtonModule,
        MatCardModule,
        MatIconModule,
        MatProgressSpinnerModule
    ],
    templateUrl: './creer-competence.component.html',
    styleUrls: ['./creer-competence.component.scss'],
    animations: [
        trigger('fadeIn', [
            transition(':enter', [
                style({ opacity: 0, transform: 'translateY(10px)' }),
                animate('400ms ease-out', style({ opacity: 1, transform: 'translateY(0)' }))
            ])
        ])
    ]
})
export class CreerCompetenceComponent implements OnInit {
    competenceForm!: FormGroup;
    loading = false;
    loadingData = false;
    errorMessage = '';
    successMessage = '';
    isEdit = false;
    competenceId: number | null = null;

    constructor(
        private fb: FormBuilder,
        private competenceService: CompetenceService,
        private router: Router,
        private route: ActivatedRoute
    ) { }

    ngOnInit(): void {
        this.initForm();

        const idParam = this.route.snapshot.paramMap.get('id');
        if (idParam) {
            this.isEdit = true;
            this.competenceId = +idParam;
            this.loadCompetence(this.competenceId);
        }
    }

    initForm(): void {
        this.competenceForm = this.fb.group({
            comp_intitule: ['', [Validators.required, Validators.maxLength(50)]],
            comp_domaine: ['', [Validators.maxLength(50)]],
            comp_description: ['', [Validators.maxLength(50)]],
        });
    }

    loadCompetence(id: number): void {
        this.loadingData = true;
        this.competenceService.getById(id).subscribe({
            next: (data: Competence) => {
                this.loadingData = false;
                this.competenceForm.patchValue({
                    comp_intitule: data.comp_intitule || '',
                    comp_domaine: data.comp_domaine || '',
                    comp_description: data.comp_description || '',
                });
            },
            error: () => {
                this.loadingData = false;
                this.errorMessage = 'Erreur lors du chargement de la compétence';
            }
        });
    }

    onSubmit(): void {
        if (this.competenceForm.invalid) {
            this.competenceForm.markAllAsTouched();
            return;
        }

        this.loading = true;
        this.errorMessage = '';
        this.successMessage = '';

        const payload: Competence = this.competenceForm.value;

        if (this.isEdit && this.competenceId) {
            this.competenceService.update(this.competenceId, payload).subscribe({
                next: () => {
                    this.loading = false;
                    this.successMessage = 'Compétence mise à jour avec succès';
                    setTimeout(() => this.router.navigate(['/competences']), 1500);
                },
                error: (err) => {
                    this.loading = false;
                    this.errorMessage = err.error?.message || 'Erreur lors de la mise à jour';
                }
            });
        } else {
            this.competenceService.create(payload).subscribe({
                next: () => {
                    this.loading = false;
                    this.successMessage = 'Compétence créée avec succès';
                    setTimeout(() => this.router.navigate(['/competences']), 1500);
                },
                error: (err) => {
                    this.loading = false;
                    this.errorMessage = err.error?.message || 'Erreur lors de la création';
                }
            });
        }
    }

    onCancel(): void {
        this.router.navigate(['/competences']);
    }
}
