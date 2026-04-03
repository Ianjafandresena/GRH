import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatSelectModule } from '@angular/material/select';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatChipsModule } from '@angular/material/chips';
import { MatDividerModule } from '@angular/material/divider';
import { MatTableModule } from '@angular/material/table';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { trigger, transition, style, animate } from '@angular/animations';

import { PosteService, Poste, CompetenceRequise } from '../../service/poste.service';
import { CompetenceService, Competence } from '../../../competence/service/competence.service';
import { EmployeesByPosteDialogComponent } from '../modal/employees-by-poste-dialog.component';
import { Subject, of } from 'rxjs';
import { map, catchError, finalize, takeUntil } from 'rxjs/operators';

@Component({
    selector: 'app-poste-detail',
    templateUrl: './detail.component.html',
    styleUrls: ['./detail.component.scss'],
    standalone: true,
    imports: [
        CommonModule,
        RouterModule,
        ReactiveFormsModule,
        MatCardModule,
        MatIconModule,
        MatButtonModule,
        MatFormFieldModule,
        MatSelectModule,
        MatProgressSpinnerModule,
        MatChipsModule,
        MatDividerModule,
        MatTableModule,
        MatTooltipModule,
        MatDialogModule
    ],
    animations: [
        trigger('fadeIn', [
            transition(':enter', [
                style({ opacity: 0, transform: 'translateY(10px)' }),
                animate('400ms ease-in', style({ opacity: 1, transform: 'translateY(0)' }))
            ])
        ])
    ]
})
export class PosteDetailComponent implements OnInit, OnDestroy {
    poste: Poste | null = null;
    loading = false;
    errorMessage = '';

    competences: Competence[] = [];
    competenceForm: FormGroup;

    displayedColumns: string[] = ['comp_intitule', 'comp_domaine', 'niveau_requis', 'actions'];

    private destroy$ = new Subject<void>();

    constructor(
        private route: ActivatedRoute,
        private router: Router,
        private posteService: PosteService,
        private competenceService: CompetenceService,
        private fb: FormBuilder,
        private dialog: MatDialog
    ) {
        this.competenceForm = this.fb.group({
            comp_code: [null, Validators.required],
            niveau_requis: [3, [Validators.required, Validators.min(1), Validators.max(5)]],
        });
    }

    ngOnInit(): void {
        const id = this.route.snapshot.paramMap.get('id');
        if (id) {
            this.loadPoste(+id);
            this.loadCompetences();
        } else {
            this.errorMessage = 'ID du poste manquant';
        }
    }

    private loadCompetences(): void {
        this.competenceService.getAll().pipe(
            takeUntil(this.destroy$)
        ).subscribe({
            next: (data: Competence[]) => {
                this.competences = (data || []).map((c) => ({
                    ...c,
                    comp_code: c.comp_code !== undefined && c.comp_code !== null ? Number(c.comp_code) : c.comp_code,
                }));
            },
            error: () => {
                this.competences = [];
            }
        });
    }

    private loadPoste(id: number): void {
        this.loading = true;
        this.errorMessage = '';

        this.posteService.get(id).pipe(
            map((poste: Poste) => {
                // Normaliser les directions
                if (poste.directions && !Array.isArray(poste.directions)) {
                    try {
                        const arr = JSON.parse(poste.directions as unknown as string);
                        poste.directions = Array.isArray(arr) ? arr : [arr];
                    } catch {
                        poste.directions = [poste.directions as unknown as string];
                    }
                }
                return poste;
            }),
            catchError((error: any) => {
                this.errorMessage = error.error?.messages?.error || 'Erreur lors du chargement du poste.';
                console.error('Erreur API:', error);
                return of(null);
            }),
            finalize(() => {
                this.loading = false;
            }),
            takeUntil(this.destroy$)
        ).subscribe((poste: Poste | null) => {
            if (poste) {
                this.poste = poste;
            }
        });
    }

    ngOnDestroy(): void {
        this.destroy$.next();
        this.destroy$.complete();
    }

    goBack(): void {
        this.router.navigate(['/postes']);
    }

    openEmployeesDialog(): void {
        if (!this.poste) return;

        this.dialog.open(EmployeesByPosteDialogComponent, {
            width: '600px',
            data: {
                pst_code: this.poste.pst_code,
                pst_fonction: this.poste.pst_fonction
            },
            autoFocus: false
        });
    }

    hasCompetencesWithDescription(): boolean {
        if (!this.poste || !this.poste.competences) return false;
        return this.poste.competences.some(c => c.comp_description);
    }

    getCompetencesWithDescription(): CompetenceRequise[] {
        if (!this.poste || !this.poste.competences) return [];
        return this.poste.competences.filter(c => c.comp_description);
    }

    addCompetence(): void {
        if (!this.poste?.pst_code) return;
        if (this.competenceForm.invalid) return;

        const { comp_code, niveau_requis } = this.competenceForm.getRawValue();

        this.loading = true;
        this.posteService.addCompetence(this.poste.pst_code, Number(comp_code), Number(niveau_requis)).pipe(
            finalize(() => (this.loading = false)),
            takeUntil(this.destroy$)
        ).subscribe({
            next: () => {
                this.competenceForm.patchValue({ comp_code: null, niveau_requis: 3 });
                this.loadPoste(this.poste!.pst_code);
            },
            error: (err) => {
                this.errorMessage = err?.error?.message || "Erreur lors de l'ajout de la compétence";
            }
        });
    }

    removeCompetence(comp: CompetenceRequise): void {
        if (!this.poste?.pst_code) return;
        if (!comp?.comp_code) return;

        this.loading = true;
        this.posteService.removeCompetence(this.poste.pst_code, Number(comp.comp_code)).pipe(
            finalize(() => (this.loading = false)),
            takeUntil(this.destroy$)
        ).subscribe({
            next: () => {
                this.loadPoste(this.poste!.pst_code);
            },
            error: (err) => {
                this.errorMessage = err?.error?.message || 'Erreur lors de la suppression de la compétence';
            }
        });
    }
}

