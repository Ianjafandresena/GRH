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
import { StagiaireService, Stagiaire, StagiaireStats } from '../../service/stagiaire.service';

@Component({
    selector: 'app-lister-stagiaires',
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
        MatInputModule
    ],
    templateUrl: './lister-stagiaires.component.html',
    styleUrls: ['./lister-stagiaires.component.scss'],
    animations: [
        trigger('fadeIn', [
            transition(':enter', [
                style({ opacity: 0, transform: 'translateY(10px)' }),
                animate('400ms ease-out', style({ opacity: 1, transform: 'translateY(0)' }))
            ])
        ])
    ]
})
export class ListerStagiairesComponent implements OnInit {
    stagiaires: Stagiaire[] = [];
    loading = false;
    stats: StagiaireStats | null = null;
    errorMessage = '';
    displayedColumns: string[] = ['nom', 'sexe', 'contact', 'adresse', 'filiere', 'niveau'];

    filterForm!: FormGroup;

    constructor(
        private stagiaireService: StagiaireService,
        private fb: FormBuilder,
        private route: ActivatedRoute,
    ) { }

    ngOnInit(): void {
        this.filterForm = this.fb.group({
            q: [''],
            contact: [''],
            filiere: [''],
            niveau: [''],
        });

        // Utiliser les données préchargées par le resolver
        const resolvedData = this.route.snapshot.data;
        if (resolvedData['stagiaires']) {
            const data = resolvedData['stagiaires'];
            this.stagiaires = data.stagiaires || [];
            this.stats = data.stats || null;
        } else {
            // Fallback si le resolver n'a pas fonctionné
            this.loadStagiaires();
            this.loadStats();
        }
    }

    loadStagiaires(): void {
        this.loading = true;
        this.errorMessage = '';

        const raw = this.filterForm?.getRawValue?.() || {};
        const filters: Record<string, any> = {
            q: raw.q,
            contact: raw.contact,
            filiere: raw.filiere,
            niveau: raw.niveau,
        };

        this.stagiaireService.getAll(filters).subscribe({
            next: (data) => {
                this.stagiaires = data;
                this.loading = false;
            },
            error: () => {
                this.loading = false;
                this.errorMessage = 'Erreur lors du chargement des stagiaires';
            }
        });
    }

    loadStats(): void {
        this.stagiaireService.getStats().subscribe({
            next: (data) => this.stats = data,
            error: (err) => console.error('Erreur stats stagiaires:', err)
        });
    }

    resetFilters(): void {
        this.filterForm.reset({
            q: '',
            contact: '',
            filiere: '',
            niveau: '',
        });
        this.loadStagiaires();
    }
}
