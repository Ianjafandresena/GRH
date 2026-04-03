import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ReactiveFormsModule, FormBuilder, FormGroup, FormControl } from '@angular/forms';

import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatAutocompleteModule, MatAutocompleteSelectedEvent } from '@angular/material/autocomplete';
import { MatNativeDateModule, MatOptionModule } from '@angular/material/core';
import { MatTableDataSource, MatTableModule } from '@angular/material/table';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatPaginator, MatPaginatorModule } from '@angular/material/paginator';
import { ViewChild, AfterViewInit } from '@angular/core';
import { trigger, transition, style, animate } from '@angular/animations';

import { AffectationService, AffectationStats } from '../../service/affectation.service';
import { CloturerAffectationDialogComponent } from '../modal/cloturer-affectation-dialog.component';
import { Affectation, MotifAffectation } from '../../model/affectation.model';
import { PosteService, Poste } from '../../../poste/service/poste.service';
import { TypeContratService, TypeContrat } from '../../../referentiel/service/type-contrat.service';

import { BehaviorSubject, combineLatest, Observable, debounceTime, map, startWith, switchMap, catchError, of } from 'rxjs';
import { EmployeService } from '../../../employe/service/employe.service';

@Component({
    selector: 'app-lister-affectations',
    standalone: true,
    imports: [
        CommonModule,
        ReactiveFormsModule,
        MatCardModule,
        MatIconModule,
        MatButtonModule,
        MatFormFieldModule,
        MatInputModule,
        MatSelectModule,
        MatDatepickerModule,
        MatAutocompleteModule,
        MatNativeDateModule,
        MatOptionModule,
        MatTableModule,
        MatProgressSpinnerModule,
        MatDialogModule,
        MatTooltipModule,
        MatPaginatorModule,
        RouterModule
    ],
    templateUrl: './lister-affectations.component.html',
    styleUrls: ['./lister-affectations.component.scss'],
    animations: [
        trigger('fadeIn', [
            transition(':enter', [
                style({ opacity: 0, transform: 'translateY(10px)' }),
                animate('400ms ease-out', style({ opacity: 1, transform: 'translateY(0)' }))
            ])
        ])
    ]
})
export class ListerAffectationsComponent implements OnInit, AfterViewInit {
    affectations: Affectation[] = [];
    dataSource = new MatTableDataSource<Affectation>([]);

    @ViewChild(MatPaginator) set matPaginator(paginator: MatPaginator) {
        if (paginator) {
            this.dataSource.paginator = paginator;
        }
    }

    loading = false;
    stats: AffectationStats | null = null;
    errorMessage = '';

    employes: any[] = [];
    postes: Poste[] = [];
    motifs: MotifAffectation[] = [];
    typesContrat: TypeContrat[] = [];

    displayedColumns: string[] = [
        'employe',
        'poste',
        'motif',
        'type_contrat',
        'date_debut',
        'date_fin',
        'commentaire',
        'actions'
    ];

    filterForm: FormGroup;

    employeControl = new FormControl<any | string>('', { nonNullable: true });
    posteControl = new FormControl<Poste | string>('', { nonNullable: true });
    motifControl = new FormControl<MotifAffectation | string>('', { nonNullable: true });

    filteredEmployes$!: Observable<any[]>;
    filteredPostes$!: Observable<Poste[]>;
    filteredMotifs$!: Observable<MotifAffectation[]>;

    private postes$ = new BehaviorSubject<Poste[]>([]);
    private motifs$ = new BehaviorSubject<MotifAffectation[]>([]);

    constructor(
        private fb: FormBuilder,
        private affectationService: AffectationService,
        private posteService: PosteService,
        private dialog: MatDialog,
        private route: ActivatedRoute,
        private router: Router,
        private typeContratService: TypeContratService,
        private employeService: EmployeService,
    ) {
        this.filterForm = this.fb.group({
            emp: [''],
            poste: [''],
            motif: [''],
            tcontrat_code: [''],
            date_debut: [''],
            date_fin: [''],
        });
    }

    ngAfterViewInit() {
        // Le paginateur est géré par le setter @ViewChild
    }

    ngOnInit(): void {
        this.filteredEmployes$ = this.employeControl.valueChanges.pipe(
            startWith(''),
            debounceTime(300),
            switchMap(value => {
                const q = typeof value === 'string' ? value : '';
                if (q.length < 2) return of([]);
                return this.employeService.list({ q }).pipe(
                    catchError(() => of([]))
                );
            })
        );

        this.filteredPostes$ = combineLatest([
            this.posteControl.valueChanges.pipe(
                startWith(''),
                debounceTime(300)
            ),
            this.postes$
        ]).pipe(
            map(([value, postes]) => this.filterPostes(value as string | Poste, postes))
        );

        this.filteredMotifs$ = combineLatest([
            this.motifControl.valueChanges.pipe(
                startWith(''),
                debounceTime(300)
            ),
            this.motifs$
        ]).pipe(
            map(([value, motifs]) => this.filterMotifs(value as string | MotifAffectation, motifs))
        );

        this.employeControl.valueChanges.subscribe((value) => {
            if (typeof value === 'string') {
                this.filterForm.patchValue({ emp: value }, { emitEvent: false });
            }
        });

        this.posteControl.valueChanges.subscribe((value) => {
            if (typeof value === 'string') {
                this.filterForm.patchValue({ poste: value }, { emitEvent: false });
            }
        });

        this.motifControl.valueChanges.subscribe((value) => {
            if (typeof value === 'string') {
                this.filterForm.patchValue({ motif: value }, { emitEvent: false });
            }
        });

        this.loadInitialData();

        const resolvedData = this.route.snapshot.data;
        if (resolvedData['affectations']) {
            const data = resolvedData['affectations'];
            const list = data.affectations || [];
            list.sort((a: any, b: any) => (b.affec_code || 0) - (a.affec_code || 0));
            this.affectations = list;
            this.dataSource.data = this.affectations;
            this.stats = data.stats || null;
        }
        else {
            this.load();
            this.loadStats();
        }
    }

    private loadInitialData(): void {
        this.loadMotifs();
        this.loadPostes();
        this.loadTypesContrat();
        this.loadEmployes();
    }

    private loadEmployes(): void {
        // Optionnel : précharger une petite liste. On utilise le service pour filtrage réactif sinon.
        this.affectationService.getAll({}).subscribe(data => {
            // On peut extraire les employés uniques si besoin, 
            // mais il vaut mieux utiliser employeService.list()
        });
    }

    private loadMotifs(): void {
        this.affectationService.getMotifs().subscribe({
            next: (data: MotifAffectation[]) => {
                this.motifs = data || [];
                this.motifs$.next(this.motifs);
            },
            error: () => {
                this.motifs$.next([]);
            }
        });
    }

    private loadPostes(): void {
        this.posteService.getPostes({}).subscribe({
            next: (response: any) => {
                this.postes = (response?.data || response || []) as Poste[];
                this.postes$.next(this.postes);
            },
            error: () => {
                this.postes$.next([]);
            }
        });
    }

    private loadTypesContrat(): void {
        this.typeContratService.getTypesContrat().subscribe(data => this.typesContrat = data);
    }

    load(): void {
        this.loading = true;
        this.errorMessage = '';

        const raw = this.filterForm.getRawValue();
        const filters: Record<string, any> = {
            emp: raw.emp,
            poste: raw.poste,
            motif: raw.motif,
            tcontrat_code: raw.tcontrat_code,
            date_debut: this.formatDate(raw.date_debut),
            date_fin: this.formatDate(raw.date_fin),
        };

        this.affectationService.getAll(filters).subscribe({
            next: (data) => {
                const results = data || [];
                // Trier par affec_code décroissant pour avoir les plus récentes en tête
                results.sort((a, b) => (b.affec_code || 0) - (a.affec_code || 0));
                this.affectations = results;
                this.dataSource.data = this.affectations;
                this.loading = false;
            },
            error: () => {
                this.loading = false;
                this.errorMessage = "Erreur lors du chargement de l'historique des affectations";
            }
        });
    }

    loadStats(): void {
        this.affectationService.getStats().subscribe({
            next: (data) => this.stats = data,
            error: (err) => console.error('Erreur stats affectations:', err)
        });
    }

    reset(): void {
        this.filterForm.reset({
            emp: '',
            poste: '',
            motif: '',
            tcontrat_code: '',
            date_debut: '',
            date_fin: '',
        });

        this.employeControl.setValue('', { emitEvent: false });
        this.posteControl.setValue('', { emitEvent: false });
        this.motifControl.setValue('', { emitEvent: false });

        this.load();
    }

    displayPoste = (poste: Poste | string): string => {
        return typeof poste === 'string' ? poste : (poste?.pst_fonction || '');
    };

    displayMotif = (motif: MotifAffectation | string): string => {
        return typeof motif === 'string' ? motif : (motif?.m_aff_motif || '');
    };

    displayEmploye = (emp: any): string => {
        return emp ? (typeof emp === 'string' ? emp : `${emp.emp_nom} ${emp.emp_prenom}`) : '';
    };

    onEmployeSelected(event: MatAutocompleteSelectedEvent): void {
        const emp = event.option.value;
        this.filterForm.patchValue({ emp: emp.emp_nom + ' ' + emp.emp_prenom });
        this.load(); // Déclencher la recherche immédiatement à la sélection
    }

    onPosteSelected(event: MatAutocompleteSelectedEvent): void {
        const poste = event.option.value as Poste;
        this.filterForm.patchValue({ poste: poste.pst_fonction });
        this.load();
    }

    onMotifSelected(event: MatAutocompleteSelectedEvent): void {
        const motif = event.option.value as MotifAffectation;
        this.filterForm.patchValue({ motif: motif.m_aff_motif });
        this.load();
    }

    private filterPostes(value: Poste | string, postes: Poste[]): Poste[] {
        const q = (typeof value === 'string' ? value : value?.pst_fonction || '').toLowerCase();
        return postes.filter((p) => (p.pst_fonction || '').toLowerCase().includes(q));
    }

    private filterMotifs(value: MotifAffectation | string, motifs: MotifAffectation[]): MotifAffectation[] {
        const q = (typeof value === 'string' ? value : value?.m_aff_motif || '').toLowerCase();
        return motifs.filter((m) => (m.m_aff_motif || '').toLowerCase().includes(q));
    }

    private formatDate(date: any): string {
        if (!date) return '';
        const d = new Date(date);
        if (isNaN(d.getTime())) return date;
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    openCloturer(affectation: Affectation): void {
        if (!affectation?.affec_code) return;

        const dialogRef = this.dialog.open(CloturerAffectationDialogComponent, {
            width: '450px',
            data: {
                affec_code: affectation.affec_code,
                emp_nom: affectation.emp_nom,
                emp_prenom: affectation.emp_prenom
            }
        });

        dialogRef.afterClosed().subscribe((ok: boolean) => {
            if (ok) {
                this.load();
                this.loadStats();
            }
        });
    }

    isOpen(affectation: Affectation): boolean {
        // Une affectation est considérée comme ouverte si son état est 'active'
        // On garde la vérification de date_fin en fallback pour la compatibilité
        return affectation.affec_etat === 'active' || (!affectation.affec_etat && !affectation.affec_date_fin);
    }
}
