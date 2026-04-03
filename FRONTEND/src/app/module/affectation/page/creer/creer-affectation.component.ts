import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule, FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatSelectModule } from '@angular/material/select';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule } from '@angular/material/core';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatTableModule } from '@angular/material/table';
import { trigger, transition, style, animate, query, stagger } from '@angular/animations';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { combineLatest, Subject, Observable } from 'rxjs';
import { takeUntil, map, startWith, debounceTime } from 'rxjs/operators';

import { AffectationService } from '../../service/affectation.service';
import { EmployeService, CompetenceEmploye } from '../../../employe/service/employe.service';
import { PosteService, CompetenceRequise } from '../../../poste/service/poste.service';
import { ServiceService } from '../../../referentiel/service/service.service';
import { MotifAffectation } from '../../model/affectation.model';
import { Employe } from '../../../employe/model/employe.model';
import { Service } from '../../../referentiel/model/referentiel.model';
import { Poste } from '../../../poste/service/poste.service';
import { TypeContratService, TypeContrat } from '../../../referentiel/service/type-contrat.service'; // Poste interface exportée depuis le service pour l'instant

@Component({
    selector: 'app-creer-affectation',
    standalone: true,
    imports: [
        CommonModule,
        ReactiveFormsModule,
        FormsModule,
        MatFormFieldModule,
        MatInputModule,
        MatButtonModule,
        MatSelectModule,
        MatDatepickerModule,
        MatNativeDateModule,
        MatCardModule,
        MatIconModule,
        MatProgressSpinnerModule,
        MatCheckboxModule,
        MatTableModule,
        MatProgressBarModule,
        MatTooltipModule,
        MatAutocompleteModule
    ],
    templateUrl: './creer-affectation.component.html',
    styleUrls: ['./creer-affectation.component.scss'],
    animations: [
        trigger('fadeIn', [
            transition(':enter', [
                style({ opacity: 0, transform: 'translateY(10px)' }),
                animate('400ms ease-out', style({ opacity: 1, transform: 'translateY(0)' }))
            ])
        ]),
        trigger('listAnimation', [
            transition('* => *', [
                query(':enter', [
                    style({ opacity: 0, transform: 'translateX(-10px)' }),
                    stagger(50, [
                        animate('300ms ease-out', style({ opacity: 1, transform: 'translateX(0)' }))
                    ])
                ], { optional: true })
            ])
        ])
    ]
})
export class CreerAffectationComponent implements OnInit {
    affectationForm!: FormGroup;
    loading = false;
    errorMessage = '';
    successMessage = '';
    private destroy$ = new Subject<void>();

    employeId: number | null = null;
    employe: Employe | null = null;
    employes: Employe[] = []; // Liste de tous les employés

    motifs: MotifAffectation[] = [];
    services: Service[] = []; // Liste des services pour filtrer
    postes: any[] = []; // Postes (tous ou filtrés par service)
    allPostes: any[] = []; // Sauvegarde de tous les postes
    typesContrat: TypeContrat[] = []; // Liste des types de contrat dynamiques

    // Observables pour le filtrage
    filteredEmployes$!: Observable<Employe[]>;
    filteredPostes$!: Observable<any[]>;
    analysisDone = false;
    analysisResult: {
        competence: string;
        domaine: string;
        niveauRequis: number;
        niveauAcquis: number;
        status: 'OK' | 'A_RENFORCER' | 'MANQUANT';
    }[] = [];
    confirmationChecked = false;
    contratType: string | null = null;
    lastAffectation: any | null = null;

    constructor(
        private fb: FormBuilder,
        private route: ActivatedRoute,
        private router: Router,
        private affectationService: AffectationService,
        private employeService: EmployeService,
        private posteService: PosteService,
        private serviceService: ServiceService,
        private typeContratService: TypeContratService
    ) { }

    ngOnInit(): void {
        this.initForm();
        this.loadInitialData();

        // Analyse automatique facultative quand employé et poste sont sélectionnés
        this.affectationForm.valueChanges.pipe(
            debounceTime(800),
            takeUntil(this.destroy$)
        ).subscribe((val: any) => {
            if (val.emp_code && val.pst_code && typeof val.emp_code === 'object' && typeof val.pst_code === 'object') {
                this.runAnalysisSilent();
            } else {
                this.analysisResult = [];
            }
        });

        // Récupérer l'ID de l'employé depuis l'URL si présent
        this.route.queryParams.pipe(takeUntil(this.destroy$)).subscribe(params => {
            if (params['emp_code']) {
                const empId = +params['emp_code'];
                this.loadSelectedEmploye(empId);
            }
        });
    }

    // Force analysis calculation manually if needed
    onTriggerAnalysis(): void {
        this.runAnalysis();
    }

    private setupContractListeners(): void {
        const typeCtrl = this.affectationForm.get('tcontrat_code');
        const debutCtrl = this.affectationForm.get('affec_date_debut');
        const finCtrl = this.affectationForm.get('affec_date_fin');

        typeCtrl?.valueChanges.pipe(takeUntil(this.destroy$)).subscribe(code => {
            const contract = this.typesContrat.find(tc => tc.tcontrat_code === code);
            const name = contract ? contract.tcontrat_nom : null;
            this.contratType = name;

            if (name === 'ELD') {
                finCtrl?.setValidators([Validators.required]);
            } else if (name === 'Fonctionnaire' || name === 'CDI') {
                finCtrl?.clearValidators();
                finCtrl?.setValue(null);
            } else if (name === 'EFA') {
                this.calculateEfaDateFin();
            } else {
                finCtrl?.clearValidators();
            }
            finCtrl?.updateValueAndValidity();
        });

        debutCtrl?.valueChanges.pipe(takeUntil(this.destroy$)).subscribe(date => {
            if (this.contratType === 'EFA' && date) {
                this.calculateEfaDateFin();
            }
        });
    }

    private calculateEfaDateFin(): void {
        const dateDebut = this.affectationForm.get('affec_date_debut')?.value;
        if (!dateDebut) return;

        const dateFinControl = this.affectationForm.get('affec_date_fin');
        const dateDebutObj = new Date(dateDebut);
        if (isNaN(dateDebutObj.getTime())) return;

        dateDebutObj.setFullYear(dateDebutObj.getFullYear() + 6);

        const year = dateDebutObj.getFullYear();
        const month = String(dateDebutObj.getMonth() + 1).padStart(2, '0');
        const day = String(dateDebutObj.getDate()).padStart(2, '0');

        dateFinControl?.setValue(`${year}-${month}-${day}`);
        dateFinControl?.clearValidators();
        dateFinControl?.updateValueAndValidity();
    }

    private setupFilterListeners(): void {
        // Filtrage des employés avec optimisation
        this.filteredEmployes$ = this.affectationForm.get('emp_code')!.valueChanges.pipe(
            startWith(''),
            debounceTime(300), // Réduire la latence de frappe
            map(value => {
                // Si c'est un objet (sélectionné), on ne filtre pas, on retourne tout ou rien
                if (typeof value === 'object' && value !== null) return '';
                return typeof value === 'string' ? value : '';
            }),
            map(name => name ? this._filterEmployes(name) : this.employes.slice(0, 50)) // Limiter les résultats pour la perf
        );

        // Filtrage des postes
        this.filteredPostes$ = this.affectationForm.get('pst_code')!.valueChanges.pipe(
            startWith(''),
            debounceTime(300),
            map(value => {
                if (typeof value === 'object' && value !== null) return '';
                return typeof value === 'string' ? value : '';
            }),
            map(name => name ? this._filterPostes(name) : this.postes.slice(0, 50))
        );
    }

    // Gestionnaire explicite lors de la sélection d'une option
    onEmployeSelected(event: any): void {
        const selectedEmp = event.option.value;
        if (selectedEmp && selectedEmp.emp_code) {
            this.loadLastAffectation(selectedEmp.emp_code);
        }
    }

    private _filterEmployes(value: string): Employe[] {
        const filterValue = value.toLowerCase();
        return this.employes.filter(emp =>
            emp.emp_nom.toLowerCase().includes(filterValue) ||
            emp.emp_prenom.toLowerCase().includes(filterValue) ||
            (emp.emp_matricule && emp.emp_matricule.toLowerCase().includes(filterValue))
        );
    }

    private _filterPostes(value: string): any[] {
        const filterValue = value.toLowerCase();
        return this.postes.filter(poste =>
            poste.pst_fonction.toLowerCase().includes(filterValue)
        );
    }

    displayEmploye(emp: Employe): string {
        return emp ? `${emp.emp_nom} ${emp.emp_prenom}` : '';
    }

    displayPoste(poste: any): string {
        return poste ? poste.pst_fonction : '';
    }

    private loadSelectedEmploye(id: number): void {
        this.employeService.get(id).subscribe(emp => {
            this.employe = emp;
            this.affectationForm.patchValue({ emp_code: emp });
            this.loadLastAffectation(id);
        });
    }

    private loadLastAffectation(empCode: number): void {
        this.affectationService.getAll({ emp_code: empCode }).subscribe(data => {
            if (data && data.length > 0) {
                // Trouver la dernière clôturée
                this.lastAffectation = data
                    .filter((a: any) => a.affec_etat === 'cloture')
                    .sort((a: any, b: any) => new Date(b.affec_date_fin!).getTime() - new Date(a.affec_date_fin!).getTime())[0];

                // Par défaut, la date de prise de fonction est la date de fin de la précédente
                if (this.lastAffectation && this.lastAffectation.affec_date_fin) {
                    this.affectationForm.patchValue({
                        affec_date_debut: this.formatDate(this.lastAffectation.affec_date_fin)
                    });
                }
            } else {
                this.lastAffectation = null;
            }
        });
    }

    private loadPostesByService(): void {
        const srvcCode = this.affectationForm.get('srvc_code')?.value;
        if (srvcCode) {
            this.postes = this.allPostes.filter(p => p.srvc_code === srvcCode);
        } else {
            this.postes = [...this.allPostes];
        }
    }

    ngOnDestroy(): void {
        this.destroy$.next();
        this.destroy$.complete();
    }

    initForm(): void {
        this.affectationForm = this.fb.group({
            emp_code: [null, Validators.required],
            srvc_code: [''], // Champ optionnel pour filtre
            pst_code: [null, Validators.required],
            m_aff_code: ['', Validators.required],
            affec_date_debut: [new Date().toISOString().split('T')[0], Validators.required],
            affec_date_fin: [''],
            tcontrat_code: [null, Validators.required],
            affec_commentaire: ['']
        });

        this.setupContractListeners();
        this.setupFilterListeners();

        // Listener pour le service
        this.affectationForm.get('srvc_code')?.valueChanges.pipe(takeUntil(this.destroy$)).subscribe(srvcCode => {
            if (srvcCode) {
                this.postes = this.allPostes.filter(p => p.srvc_code == srvcCode);
            } else {
                this.postes = [...this.allPostes];
            }
            this.affectationForm.get('pst_code')?.setValue(null);
        });
    }

    loadInitialData(): void {
        this.employeService.getEmployes({ pos_code: 2 }).subscribe(data => this.employes = data);
        this.affectationService.getMotifs().subscribe(data => this.motifs = data);
        this.serviceService.getServices().subscribe(data => this.services = data);
        this.typeContratService.getTypesContrat().subscribe(data => this.typesContrat = data);
        this.posteService.list({ disponibles_only: 'true' }).subscribe((res: any) => {
            this.allPostes = res?.data || res || [];
            this.postes = [...this.allPostes];
        });
    }

    runAnalysisSilent(): void {
        const empVal = this.affectationForm.get('emp_code')?.value;
        const pstVal = this.affectationForm.get('pst_code')?.value;

        const empCode = typeof empVal === 'object' ? empVal?.emp_code : null;
        const pstCode = typeof pstVal === 'object' ? pstVal?.pst_code : null;

        if (!empCode || !pstCode) return;

        combineLatest([
            this.employeService.getCompetences(empCode),
            this.posteService.get(pstCode)
        ]).subscribe({
            next: ([empComp, pstDetail]) => {
                this.performGapAnalysis(empComp, pstDetail.competences || []);
                this.analysisDone = true; // IMPORTANT : Marquer l'analyse comme faite pour afficher la section
            },
            error: (err) => console.error('Erreur silent analysis:', err)
        });
    }

    loadEmployes(): void {
        this.employeService.getEmployes({ pos_code: 2 }).subscribe((data: Employe[]) => {
            this.employes = data;
        });
    }

    loadMotifs(): void {
        this.affectationService.getMotifs().subscribe((data: MotifAffectation[]) => this.motifs = data);
    }

    loadServices(): void {
        // Utilisation du service existant ou à créer
        this.serviceService.getServices().subscribe((data: Service[]) => this.services = data);
    }

    loadTypesContrat(): void {
        this.typeContratService.getTypesContrat().subscribe({
            next: (data) => this.typesContrat = data,
            error: (err) => console.error('Erreur chargement types contrat:', err)
        });
    }

    loadAllPostes(): void {
        // Charger les postes via l'API existante (filtrés par disponibilité)
        this.posteService.list({ disponibles_only: 'true' }).subscribe({
            next: (response: any) => {
                this.allPostes = response?.data || response || [];
                this.postes = [...this.allPostes];
                console.log('Postes chargés:', this.postes.length);
            },
            error: (err) => {
                console.error('Erreur chargement postes:', err);
                this.allPostes = [];
                this.postes = [];
            }
        });
    }

    onSubmit(): void {
        if (this.affectationForm.invalid || this.loading) return;
        this.saveAffectation();
    }

    runAnalysis(): void {
        const empVal = this.affectationForm.get('emp_code')?.value;
        const pstVal = this.affectationForm.get('pst_code')?.value;

        const empCode = typeof empVal === 'object' ? empVal?.emp_code : +empVal;
        const pstCode = typeof pstVal === 'object' ? pstVal?.pst_code : +pstVal;

        if (!empCode || !pstCode || isNaN(empCode) || isNaN(pstCode)) {
            this.errorMessage = 'Veuillez sélectionner un employé et un poste valides.';
            return;
        }

        this.loading = true;
        this.errorMessage = '';
        this.analysisResult = [];

        combineLatest([
            this.employeService.getCompetences(empCode),
            this.posteService.get(pstCode)
        ]).subscribe({
            next: ([empCompetences, posteDetail]) => {
                this.performGapAnalysis(empCompetences, posteDetail.competences || []);
                this.analysisDone = true;
                this.loading = false;
            },
            error: (err) => {
                console.error('Erreur analyse:', err);
                this.errorMessage = 'Erreur lors de l\'analyse des compétences.';
                this.loading = false;
            }
        });
    }

    performGapAnalysis(empCompetences: CompetenceEmploye[], posteCompetences: CompetenceRequise[]): void {
        this.analysisResult = posteCompetences.map(req => {
            // Utilisation de == pour permettre la comparaison string/number si nécessaire
            const empComp = empCompetences.find(ec => ec.comp_code == req.comp_code);
            const niveauAcquis = empComp ? empComp.niveau_acquis : 0;

            let status: 'OK' | 'A_RENFORCER' | 'MANQUANT' = 'OK';
            if (niveauAcquis === 0) {
                status = 'MANQUANT';
            } else if (niveauAcquis < req.niveau_requis) {
                status = 'A_RENFORCER';
            }

            return {
                competence: req.comp_intitule,
                domaine: req.comp_domaine,
                niveauRequis: req.niveau_requis,
                niveauAcquis: niveauAcquis,
                status: status
            };
        });
    }

    saveAffectation(): void {
        this.loading = true;
        const formValue = this.affectationForm.getRawValue();

        const empVal = formValue.emp_code;
        const pstVal = formValue.pst_code;

        const empCode = empVal?.emp_code || empVal;
        const pstCode = pstVal?.pst_code || pstVal;

        // Nettoyage des champs virtuels et extraction des IDs
        const { srvc_code, emp_code, pst_code, ...payload } = formValue;
        const finalPayload = {
            ...payload,
            emp_code: empCode,
            pst_code: pstCode
        };

        // Formatage dates
        finalPayload.affec_date_debut = this.formatDate(finalPayload.affec_date_debut);
        if (finalPayload.affec_date_fin) finalPayload.affec_date_fin = this.formatDate(finalPayload.affec_date_fin);

        this.affectationService.create(finalPayload).subscribe({
            next: () => {
                this.loading = false;
                this.successMessage = 'Affectation enregistrée avec succès !';
                setTimeout(() => {
                    this.router.navigate(['/employes/lister']); // Ou liste des affectations
                }, 1500);
            },
            error: (err) => {
                this.loading = false;
                this.errorMessage = err.error?.message || 'Erreur lors de l\'enregistrement';
            }
        });
    }

    // Reset analysis if critical fields change
    onFormChange(): void {
        if (this.analysisDone) {
            this.analysisDone = false;
            this.analysisResult = [];
            this.confirmationChecked = false;
        }
    }

    formatDate(date: any): string {
        if (!date) return '';
        const d = new Date(date);
        if (isNaN(d.getTime())) return date;
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    shouldShowDateFin(): boolean {
        return this.contratType === 'ELD' || this.contratType === 'EFA';
    }

    isDateFinRequired(): boolean {
        return this.contratType === 'ELD';
    }

    isDateFinReadonly(): boolean {
        return this.contratType === 'EFA';
    }
}
