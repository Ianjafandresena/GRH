import { Component, OnInit } from '@angular/core';
import { trigger, transition, style, animate } from '@angular/animations';
import { forkJoin } from 'rxjs';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule, FormControl } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSelectModule } from '@angular/material/select';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule } from '@angular/material/core';
import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { Observable } from 'rxjs';
import { map, startWith } from 'rxjs/operators';

import { StageService } from '../../service/stage.service';
import { StagiaireService, Stagiaire } from '../../../stagiaire/service/stagiaire.service';
import { EtablissementService, Etablissement } from '../../../etablissement/service/etablissement.service';
import { EmployeService } from '../../../employe/service/employe.service';
import { Employe } from '../../../employe/model/employe.model';

@Component({
    selector: 'app-creer-stage',
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
        MatSelectModule,
        MatDatepickerModule,
        MatNativeDateModule,
        MatAutocompleteModule
    ],
    templateUrl: './creer-stage.component.html',
    styleUrls: ['./creer-stage.component.scss'],
    animations: [
        trigger('fadeIn', [
            transition(':enter', [
                style({ opacity: 0, transform: 'translateY(10px)' }),
                animate('400ms ease-out', style({ opacity: 1, transform: 'translateY(0)' }))
            ])
        ])
    ]
})
export class CreerStageComponent implements OnInit {
    stageForm!: FormGroup;

    // Data lists
    stagiaires: Stagiaire[] = [];
    etablissements: Etablissement[] = [];
    encadreurs: Employe[] = [];

    // Form Controls for Autocompletes
    stagiaireCtrl = new FormControl<any>(null);
    etablissementCtrl = new FormControl<any>(null);
    encadreurCtrl = new FormControl<any>(null);

    // Filtered Observables
    filteredStagiaires$!: Observable<Stagiaire[]>;
    filteredEtablissements$!: Observable<Etablissement[]>;
    filteredEncadreurs$!: Observable<Employe[]>;

    loading = false;
    loadingData = false;
    errorMessage = '';
    successMessage = '';
    isEdit = false;
    stageId: number | null = null;

    constructor(
        private fb: FormBuilder,
        private stageService: StageService,
        private stagiaireService: StagiaireService,
        private etablissementService: EtablissementService,
        private employeService: EmployeService,
        private router: Router,
        private route: ActivatedRoute
    ) { }

    ngOnInit(): void {
        this.initForm();
        this.loadInitialData();

        const idParam = this.route.snapshot.paramMap.get('id');
        if (idParam) {
            this.isEdit = true;
            this.stageId = +idParam;
        }
    }

    initForm(): void {
        this.stageForm = this.fb.group({
            stgr_code: [null, [Validators.required]],
            stg_theme: ['', [Validators.required]],
            stg_date_debut: [new Date(), [Validators.required]],
            stg_duree: [1, [Validators.required, Validators.min(0.5)]],
            stg_date_fin: [{ value: '', disabled: true }],
            etab_code: [null, [Validators.required]],
            encadreur_emp_code: [null]
        });

        // Auto calculate end date
        this.stageForm.get('stg_date_debut')?.valueChanges.subscribe(() => this.calculateDateFin());
        this.stageForm.get('stg_duree')?.valueChanges.subscribe(() => this.calculateDateFin());
    }

    loadInitialData(): void {
        this.loadingData = true;
        forkJoin({
            stagiaires: this.stagiaireService.getAll(),
            etablissements: this.etablissementService.getAll(),
            encadreurs: this.employeService.getEncadreurs()
        }).subscribe({
            next: (res: any) => {
                this.stagiaires = res.stagiaires;
                this.etablissements = res.etablissements;
                this.encadreurs = res.encadreurs;

                this.setupAutocompletes();

                if (this.isEdit && this.stageId) {
                    this.loadStageDetails(this.stageId);
                } else {
                    this.loadingData = false;
                }
            },
            error: (err) => {
                this.loadingData = false;
                this.errorMessage = 'Erreur lors du chargement des données initiales';
                console.error(err);
            }
        });
    }

    private setupAutocompletes(): void {
        // Stagiaires
        this.filteredStagiaires$ = this.stagiaireCtrl.valueChanges.pipe(
            startWith(''),
            map(value => {
                const search = typeof value === 'string' ? value : '';
                return search ? this._filterStagiaires(search) : this.stagiaires.slice(0, 50);
            })
        );

        // Etablissements
        this.filteredEtablissements$ = this.etablissementCtrl.valueChanges.pipe(
            startWith(''),
            map(value => {
                const search = typeof value === 'string' ? value : '';
                return search ? this._filterEtablissements(search) : this.etablissements.slice(0, 50);
            })
        );

        // Encadreurs
        this.filteredEncadreurs$ = this.encadreurCtrl.valueChanges.pipe(
            startWith(''),
            map(value => {
                const search = typeof value === 'string' ? value : '';
                return search ? this._filterEncadreurs(search) : this.encadreurs.slice(0, 50);
            })
        );
    }

    private _filterStagiaires(value: string): Stagiaire[] {
        const filterValue = value.toLowerCase();
        return this.stagiaires.filter(s =>
            (s.stgr_nom || '').toLowerCase().includes(filterValue) ||
            (s.stgr_prenom || '').toLowerCase().includes(filterValue)
        );
    }

    private _filterEtablissements(value: string): Etablissement[] {
        const filterValue = value.toLowerCase();
        return this.etablissements.filter(e => (e.etab_nom || '').toLowerCase().includes(filterValue));
    }

    private _filterEncadreurs(value: string): Employe[] {
        const filterValue = value.toLowerCase();
        return this.encadreurs.filter(emp =>
            (emp.emp_nom || '').toLowerCase().includes(filterValue) ||
            (emp.emp_prenom || '').toLowerCase().includes(filterValue) ||
            (emp.emp_im_armp || '').toLowerCase().includes(filterValue)
        );
    }

    // Display functions
    displayStagiaire = (s?: Stagiaire): string => s ? (s.stgr_nom_prenom || `${s.stgr_nom} ${s.stgr_prenom}`) : '';
    displayEtablissement = (e?: Etablissement): string => e ? e.etab_nom : '';
    displayEncadreur = (emp?: Employe): string => emp ? `${emp.emp_nom} ${emp.emp_prenom}` : '';

    // Selection handlers
    onStagiaireSelected(s: any): void {
        this.stageForm.patchValue({ stgr_code: s.option.value.stgr_code });
    }
    onEtablissementSelected(e: any): void {
        this.stageForm.patchValue({ etab_code: e.option.value.etab_code });
    }
    onEncadreurSelected(emp: any): void {
        this.stageForm.patchValue({ encadreur_emp_code: emp.option.value.emp_code });
    }

    loadStageDetails(id: number): void {
        this.stageService.getById(id).subscribe({
            next: (data: any) => {
                this.stageForm.patchValue({
                    stgr_code: data.stgr_code,
                    stg_theme: data.stg_theme,
                    stg_date_debut: data.stg_date_debut ? new Date(data.stg_date_debut) : null,
                    etab_code: data.etab_code,
                    encadreur_emp_code: data.encadreur_emp_code
                });

                // Set autocomplete controls
                if (data.stgr_code) {
                    const stgr = this.stagiaires.find(s => s.stgr_code === data.stgr_code);
                    if (stgr) this.stagiaireCtrl.setValue(stgr);
                }
                if (data.etab_code) {
                    const etab = this.etablissements.find(e => e.etab_code === data.etab_code);
                    if (etab) this.etablissementCtrl.setValue(etab);
                }
                if (data.encadreur_emp_code) {
                    const emp = this.encadreurs.find(e => e.emp_code === data.encadreur_emp_code);
                    if (emp) this.encadreurCtrl.setValue(emp);
                }

                this.calculateDateFin();
                this.loadingData = false;
            },
            error: () => {
                this.loadingData = false;
                this.errorMessage = 'Erreur lors du chargement des détails du stage';
            }
        });
    }

    calculateDateFin(): void {
        const debut = this.stageForm.get('stg_date_debut')?.value;
        const duree = this.stageForm.get('stg_duree')?.value;

        if (debut && duree) {
            const dateDebut = new Date(debut);
            if (isNaN(dateDebut.getTime())) return;

            const dateFin = new Date(dateDebut);
            const monthsToAdd = Math.floor(duree);
            const extraDays = Math.round((duree - monthsToAdd) * 30);

            dateFin.setMonth(dateFin.getMonth() + monthsToAdd);
            dateFin.setDate(dateFin.getDate() + extraDays);

            this.stageForm.get('stg_date_fin')?.setValue(dateFin, { emitEvent: false });
        }
    }

    onSubmit(): void {
        if (this.stageForm.invalid) {
            this.stageForm.markAllAsTouched();
            return;
        }

        this.loading = true;
        const rawValue = this.stageForm.getRawValue();
        const payload = { ...rawValue };

        payload.stg_date_debut = this.formatDate(payload.stg_date_debut);
        if (payload.stg_date_fin) payload.stg_date_fin = this.formatDate(payload.stg_date_fin);

        delete payload.stg_duree;

        const request = this.isEdit && this.stageId
            ? this.stageService.update(this.stageId, payload)
            : this.stageService.create(payload);

        request.subscribe({
            next: () => {
                this.loading = false;
                this.successMessage = `Stage ${this.isEdit ? 'mis à jour' : 'créé'} avec succès`;
                setTimeout(() => this.router.navigate(['/stages']), 1500);
            },
            error: (err) => {
                this.loading = false;
                this.errorMessage = err.error?.message || 'Une erreur est survenue';
            }
        });
    }

    formatDate(date: any): string {
        if (!date) return '';
        const d = new Date(date);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    onCancel(): void {
        this.router.navigate(['/stages']);
    }
}
