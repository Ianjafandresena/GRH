import { Component, OnInit } from '@angular/core';
import { trigger, transition, style, animate } from '@angular/animations';
import { CommonModule } from '@angular/common';
import { RouterModule, ActivatedRoute } from '@angular/router';
import { FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { MatTableModule } from '@angular/material/table';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatTooltipModule } from '@angular/material/tooltip';
import { CompetenceService, Competence, CompetenceStats } from '../../service/competence.service';

@Component({
    selector: 'app-lister-competences',
    standalone: true,
    imports: [
        CommonModule,
        ReactiveFormsModule,
        RouterModule,
        MatTableModule,
        MatCardModule,
        MatIconModule,
        MatButtonModule,
        MatProgressSpinnerModule,
        MatFormFieldModule,
        MatInputModule,
        MatTooltipModule
    ],
    templateUrl: './lister-competences.component.html',
    styleUrls: ['./lister-competences.component.scss'],
    animations: [
        trigger('fadeIn', [
            transition(':enter', [
                style({ opacity: 0, transform: 'translateY(10px)' }),
                animate('400ms ease-out', style({ opacity: 1, transform: 'translateY(0)' }))
            ])
        ])
    ]
})
export class ListerCompetencesComponent implements OnInit {
    competences: Competence[] = [];
    loading = false;
    stats: CompetenceStats | null = null;
    errorMessage = '';
    displayedColumns: string[] = ['intitule', 'domaine', 'description', 'actions'];

    filterForm!: FormGroup;

    constructor(
        private competenceService: CompetenceService,
        private fb: FormBuilder,
        private route: ActivatedRoute,
    ) { }

    ngOnInit(): void {
        this.filterForm = this.fb.group({
            q: [''],
            domaine: [''],
        });

        // Utiliser les données préchargées par le resolver
        const resolvedData = this.route.snapshot.data;
        if (resolvedData['competences']) {
            const data = resolvedData['competences'];
            this.competences = data.competences || [];
            this.stats = data.stats || null;
        } else {
            // Fallback si le resolver n'a pas fonctionné
            this.load();
            this.loadStats();
        }
    }

    load(): void {
        this.loading = true;
        this.errorMessage = '';

        const raw = this.filterForm?.getRawValue?.() || {};
        const filters: Record<string, any> = {
            q: raw.q,
            domaine: raw.domaine,
        };

        this.competenceService.getAll(filters).subscribe({
            next: (data) => {
                this.competences = data;
                this.loading = false;
            },
            error: () => {
                this.loading = false;
                this.errorMessage = 'Erreur lors du chargement des compétences';
            }
        });
    }

    loadStats(): void {
        this.competenceService.getStats().subscribe({
            next: (data) => this.stats = data,
            error: (err) => console.error('Erreur stats competences:', err)
        });
    }


    resetFilters(): void {
        this.filterForm.reset({
            q: '',
            domaine: '',
        });
        this.load();
    }

    deleteCompetence(comp: Competence): void {
        if (!comp?.comp_code) return;
        if (!confirm(`Supprimer la compétence "${comp.comp_intitule}" ?`)) return;

        this.competenceService.delete(comp.comp_code).subscribe({
            next: () => {
                this.load();
            },
            error: () => {
                this.errorMessage = 'Erreur lors de la suppression';
            }
        });
    }
}
