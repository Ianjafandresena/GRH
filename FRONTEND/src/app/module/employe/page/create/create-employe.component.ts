import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule, AbstractControl, ValidationErrors } from '@angular/forms';
import { Router } from '@angular/router';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatSelectModule } from '@angular/material/select';
import { MatRadioModule } from '@angular/material/radio';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule } from '@angular/material/core';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatExpansionModule } from '@angular/material/expansion';
import { MatDividerModule } from '@angular/material/divider';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { MatStepperModule } from '@angular/material/stepper';
import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { Subject, Observable } from 'rxjs';
import { takeUntil, debounceTime, distinctUntilChanged, map, startWith } from 'rxjs/operators';
import { trigger, transition, style, animate } from '@angular/animations';

import { EmployeService } from '../../service/employe.service';
import { AffectationService } from '../../../affectation/service/affectation.service';
import { PosteService } from '../../../poste/service/poste.service';
import { TypeContratService, TypeContrat } from '../../../referentiel/service/type-contrat.service';
import { StatutArmpService, StatutArmp } from '../../../referentiel/service/statut-armp.service';
import { TypeEntree, AffectationInitiale } from '../../model/employe.model';
import { Poste } from '../../../poste/service/poste.service';
import { MotifAffectation } from '../../../affectation/model/affectation.model';
import { ResumeCreationDialogComponent } from '../modal/resume-creation-dialog.component';

@Component({
    selector: 'app-create-employe',
    standalone: true,
    imports: [
        CommonModule,
        ReactiveFormsModule,
        MatFormFieldModule,
        MatInputModule,
        MatButtonModule,
        MatSelectModule,
        MatRadioModule,
        MatDatepickerModule,
        MatNativeDateModule,
        MatCardModule,
        MatIconModule,
        MatProgressSpinnerModule,
        MatCheckboxModule,
        MatExpansionModule,
        MatDividerModule,
        MatDialogModule,
        MatStepperModule,
        MatAutocompleteModule
    ],
    templateUrl: './create-employe.component.html',
    styleUrls: ['./create-employe.component.scss'],
    animations: [
        trigger('fadeIn', [
            transition(':enter', [
                style({ opacity: 0, transform: 'translateY(10px)' }),
                animate('400ms ease-out', style({ opacity: 1, transform: 'translateY(0)' }))
            ])
        ]),
        trigger('stepAnimation', [
            transition(':enter', [
                style({ opacity: 0, transform: 'translateX(20px)' }),
                animate('400ms ease-out', style({ opacity: 1, transform: 'translateX(0)' }))
            ])
        ])
    ]
})
export class CreateEmployeComponent implements OnInit, OnDestroy {
    personalForm!: FormGroup;
    adminForm!: FormGroup;
    entryForm!: FormGroup;
    affectationForm!: FormGroup;

    typesEntree: TypeEntree[] = [];
    postes: Poste[] = [];
    motifs: MotifAffectation[] = [];
    typesContrat: TypeContrat[] = [];
    statutsArmp: StatutArmp[] = [];

    filteredPostes$!: Observable<Poste[]>;

    loading = false;
    loadingData = false;
    errorMessage = '';
    successMessage = '';

    contratType: string | null = null;

    private destroy$ = new Subject<void>();

    constructor(
        private fb: FormBuilder,
        private employeService: EmployeService,
        private affectationService: AffectationService,
        private posteService: PosteService,
        private typeContratService: TypeContratService,
        private statutArmpService: StatutArmpService,
        private router: Router,
        private dialog: MatDialog
    ) { }

    ngOnInit(): void {
        this.initForms();
        this.loadReferenceData();
        this.setupFormListeners();
        this.setupFilterListeners();
    }

    ngOnDestroy(): void {
        this.destroy$.next();
        this.destroy$.complete();
    }

    private initForms(): void {
        // Étape 1 : Informations Personnelles
        this.personalForm = this.fb.group({
            emp_nom: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(50)]],
            emp_prenom: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(50)]],
            emp_titre: ['M.', [Validators.required]],
            emp_sexe: [true, [Validators.required]],
            emp_datenaissance: ['', [Validators.required]]
        });

        // Étape 2 : Informations Administratives
        this.adminForm = this.fb.group({
            emp_im_armp: ['', [Validators.required, Validators.maxLength(50)]],
            emp_im_etat: [''],
            emp_mail: ['', [Validators.required, Validators.email, Validators.maxLength(100)]],
            emp_contact: ['', [Validators.required]],
            emp_cin: ['', [Validators.required, Validators.pattern(/^[0-9]{3} [0-9]{3} [0-9]{3} [0-9]{3}$/)]]
        });

        // Étape 3 : Informations d'Entrée
        this.entryForm = this.fb.group({
            date_entree: ['', [Validators.required]],
            e_type_code: ['', [Validators.required]],
            stt_armp_code: ['', [Validators.required]]
        });

        // Étape 4 : Affectation initiale
        this.affectationForm = this.fb.group({
            pst_code: ['', [Validators.required]],
            m_aff_code: ['', [Validators.required]],
            affec_date_debut: ['', [Validators.required]],
            affec_date_fin: [null],
            tcontrat_code: [null, [Validators.required]],
            affec_commentaire: ['']
        }, { validators: this.typeContratValidator });
    }

    private setupFormListeners(): void {
        // Écouter les changements de la date d'entrée pour la copier vers l'affectation
        this.entryForm.get('date_entree')?.valueChanges
            .pipe(takeUntil(this.destroy$))
            .subscribe(dateEntree => {
                if (dateEntree) {
                    const dateDebutControl = this.affectationForm.get('affec_date_debut');
                    if (dateDebutControl) {
                        dateDebutControl.setValue(dateEntree, { emitEvent: false });
                    }
                }
            });

        // Écouter les changements du type de contrat
        this.affectationForm.get('tcontrat_code')?.valueChanges
            .pipe(takeUntil(this.destroy$))
            .subscribe(code => {
                const contract = this.typesContrat.find(tc => tc.tcontrat_code === code);
                const type = contract ? contract.tcontrat_nom : null;
                this.contratType = type;
                const dateFinControl = this.affectationForm.get('affec_date_fin');

                if (type === 'ELD') {
                    // ELD : date de fin obligatoire
                    dateFinControl?.setValidators([Validators.required]);
                    dateFinControl?.updateValueAndValidity();
                } else if (type === 'Fonctionnaire') {
                    // Fonctionnaire : pas de date de fin
                    dateFinControl?.clearValidators();
                    dateFinControl?.setValue(null);
                    dateFinControl?.updateValueAndValidity();
                } else if (type === 'EFA') {
                    // EFA : date de fin générée automatiquement (date entrée + 6 ans)
                    const dateDebut = this.affectationForm.get('affec_date_debut')?.value;
                    const dateFinControl = this.affectationForm.get('affec_date_fin');
                    const dateDebutObj = new Date(dateDebut);
                    dateDebutObj.setFullYear(dateDebutObj.getFullYear() + 6);

                    // Format YYYY-MM-DD manuellement pour éviter les décalages de timezone
                    const year = dateDebutObj.getFullYear();
                    const month = String(dateDebutObj.getMonth() + 1).padStart(2, '0');
                    const day = String(dateDebutObj.getDate()).padStart(2, '0');
                    dateFinControl?.setValue(`${year}-${month}-${day}`);
                    dateFinControl?.clearValidators();
                    dateFinControl?.updateValueAndValidity();
                }
            });

        // Écouter les changements de la date de début pour EFA
        this.affectationForm.get('affec_date_debut')?.valueChanges
            .pipe(takeUntil(this.destroy$))
            .subscribe(dateDebut => {
                if (this.contratType === 'EFA' && dateDebut) {
                    const dateDebutObj = new Date(dateDebut);
                    dateDebutObj.setFullYear(dateDebutObj.getFullYear() + 6);
                    const dateFinControl = this.affectationForm.get('affec_date_fin');

                    const year = dateDebutObj.getFullYear();
                    const month = String(dateDebutObj.getMonth() + 1).padStart(2, '0');
                    const day = String(dateDebutObj.getDate()).padStart(2, '0');
                    dateFinControl?.setValue(`${year}-${month}-${day}`);
                }
            });
    }

    private setupFilterListeners(): void {
        this.filteredPostes$ = this.affectationForm.get('pst_code')!.valueChanges.pipe(
            startWith(''),
            map(value => {
                const name = typeof value === 'string' ? value : '';
                return name ? this._filterPostes(name) : this.postes.slice();
            })
        );
    }

    private _filterPostes(value: string): Poste[] {
        const filterValue = value.toLowerCase();
        return this.postes.filter(poste =>
            poste.pst_fonction.toLowerCase().includes(filterValue) ||
            (poste.srvc_nom && poste.srvc_nom.toLowerCase().includes(filterValue)) ||
            (poste.dir_nom && poste.dir_nom.toLowerCase().includes(filterValue))
        );
    }

    displayPoste(poste: Poste): string {
        if (!poste) return '';
        let display = poste.pst_fonction;
        if (poste.srvc_nom) {
            display += ` (${poste.srvc_nom})`;
        } else if (poste.dir_nom) {
            display += ` (${poste.dir_nom})`;
        }
        return display;
    }

    private typeContratValidator = (control: AbstractControl): ValidationErrors | null => {
        const typeContratNom = this.contratType;
        const dateFin = control.get('affec_date_fin')?.value;

        if (typeContratNom === 'ELD' && !dateFin) {
            return { eldDateFinRequired: true };
        }

        if (typeContratNom === 'Fonctionnaire' && dateFin) {
            return { fonctionnaireDateFinMustBeNull: true };
        }

        return null;
    };

    private loadReferenceData(): void {
        this.loadingData = true;

        // Charger les types d'entrée (exclure "Promotion")
        this.employeService.getTypesEntree()
            .pipe(takeUntil(this.destroy$))
            .subscribe({
                next: (types) => {
                    // Filtrer "Promotion" du type d'entrée
                    this.typesEntree = types.filter(t =>
                        t.e_type_motif && !t.e_type_motif.toLowerCase().includes('promotion')
                    );
                },
                error: (err) => {
                    console.error('Erreur chargement types entrée:', err);
                }
            });

        // Charger les types de contrat
        this.typeContratService.getTypesContrat()
            .pipe(takeUntil(this.destroy$))
            .subscribe({
                next: (types) => {
                    this.typesContrat = types;
                },
                error: (err) => {
                    console.error('Erreur chargement types contrat:', err);
                }
            });

        // Charger les postes (seulement valides/vacants ou avec cessation)
        this.posteService.list({ disponibles_only: 'true' })
            .pipe(takeUntil(this.destroy$))
            .subscribe({
                next: (postes) => {
                    this.postes = postes;
                },
                error: (err) => {
                    console.error('Erreur chargement postes:', err);
                }
            });

        // Charger les statuts ARMP
        this.statutArmpService.getStatutsArmp()
            .pipe(takeUntil(this.destroy$))
            .subscribe({
                next: (statuts) => {
                    this.statutsArmp = statuts;
                },
                error: (err) => {
                    console.error('Erreur chargement statuts ARMP:', err);
                }
            });

        // Charger les motifs d'affectation
        this.affectationService.getMotifs()
            .pipe(takeUntil(this.destroy$))
            .subscribe({
                next: (motifs) => {
                    this.motifs = motifs;
                    this.loadingData = false;
                },
                error: (err) => {
                    console.error('Erreur chargement motifs:', err);
                    this.loadingData = false;
                }
            });
    }

    // Formatage du numéro de téléphone au format XXX XX XXX XX (10 chiffres: 3-2-3-2)
    formatPhoneNumber(value: string): string {
        // Supprimer tous les caractères non numériques
        const numbers = value.replace(/\D/g, '');

        // Limiter à 10 chiffres
        const limited = numbers.slice(0, 10);

        // Formater : XXX XX XXX XX (3-2-3-2 pour 10 chiffres)
        if (limited.length === 0) {
            return '';
        } else if (limited.length <= 3) {
            return limited;
        } else if (limited.length <= 5) {
            return `${limited.slice(0, 3)} ${limited.slice(3)}`;
        } else if (limited.length <= 8) {
            return `${limited.slice(0, 3)} ${limited.slice(3, 5)} ${limited.slice(5)}`;
        } else if (limited.length <= 9) {
            // 9 chiffres: XXX XX XXX X
            return `${limited.slice(0, 3)} ${limited.slice(3, 5)} ${limited.slice(5, 8)} ${limited.slice(8)}`;
        } else {
            // 10 chiffres: XXX XX XXX XX (3-2-3-2)
            return `${limited.slice(0, 3)} ${limited.slice(3, 5)} ${limited.slice(5, 8)} ${limited.slice(8, 10)}`;
        }
    }

    onPhoneInput(event: any): void {
        const input = event.target;
        const formatted = this.formatPhoneNumber(input.value);
        this.adminForm.patchValue({ emp_contact: formatted }, { emitEvent: false });
        if (input.value !== formatted) {
            input.value = formatted;
        }
    }

    // Nettoyer le numéro de téléphone avant l'envoi (supprimer les espaces)
    cleanPhoneNumber(phone: string): string {
        return phone ? phone.replace(/\s/g, '') : '';
    }

    onSubmit(): void {
        // Valider tous les formulaires
        if (this.personalForm.invalid || this.adminForm.invalid || this.entryForm.invalid || this.affectationForm.invalid) {
            this.personalForm.markAllAsTouched();
            this.adminForm.markAllAsTouched();
            this.entryForm.markAllAsTouched();
            this.affectationForm.markAllAsTouched();
            this.errorMessage = 'Veuillez remplir correctement tous les champs obligatoires dans chaque étape.';
            return;
        }

        this.loading = true;
        this.errorMessage = '';
        this.successMessage = '';

        // Préparer les données employé en combinant les 3 formulaires
        const employeData = {
            ...this.personalForm.value,
            ...this.adminForm.value,
            ...this.entryForm.value,
            emp_sexe: this.personalForm.value.emp_sexe ? 1 : 0,
            emp_datenaissance: this.formatDate(this.personalForm.value.emp_datenaissance),
            date_entree: this.formatDate(this.entryForm.value.date_entree),
            emp_contact: this.cleanPhoneNumber(this.adminForm.value.emp_contact)
        };

        // S'assurer que la date d'entrée est transmise à l'affectation
        const dateEntreeEmploye = this.formatDate(this.entryForm.value.date_entree);
        if (!this.affectationForm.value.affec_date_debut && dateEntreeEmploye) {
            this.affectationForm.patchValue({
                affec_date_debut: this.entryForm.value.date_entree
            });
        }

        // Préparer l'affectation initiale (obligatoire)
        // Utiliser la date d'entrée de l'employé si la date de début de l'affectation n'est pas définie
        const pstVal = this.affectationForm.value.pst_code;
        const pstCode = pstVal?.pst_code || pstVal;

        const affectationInitiale: AffectationInitiale = {
            pst_code: pstCode,
            m_aff_code: this.affectationForm.value.m_aff_code,
            affec_date_debut: this.formatDate(this.affectationForm.value.affec_date_debut || this.entryForm.value.date_entree),
            affec_date_fin: this.affectationForm.value.affec_date_fin
                ? this.formatDate(this.affectationForm.value.affec_date_fin)
                : undefined,
            tcontrat_code: this.affectationForm.value.tcontrat_code,
            affec_commentaire: this.affectationForm.value.affec_commentaire || undefined
        };

        // Récupérer les informations pour le résumé
        const poste = this.postes.find(p => p.pst_code === pstCode);
        const motif = this.motifs.find(m => m.m_aff_code === this.affectationForm.value.m_aff_code);
        const typeEntree = this.typesEntree.find(t => t.e_type_code === this.entryForm.value.e_type_code);
        const typeContrat = this.typesContrat.find(t => t.tcontrat_code === this.affectationForm.value.tcontrat_code);

        // Afficher le dialog de résumé avant la création
        const dialogRef = this.dialog.open(ResumeCreationDialogComponent, {
            width: '600px',
            maxWidth: '90vw',
            data: {
                employe: employeData,
                affectation: affectationInitiale,
                poste: poste,
                motif: motif,
                typeEntree: typeEntree,
                typeContrat: typeContrat
            },
            disableClose: true
        });

        dialogRef.afterClosed().subscribe((confirmed) => {
            if (!confirmed) {
                this.loading = false;
                return;
            }

            // Créer l'employé après confirmation
            this.createEmploye(employeData, affectationInitiale);
        });
    }

    private createEmploye(employeData: any, affectationInitiale?: AffectationInitiale): void {
        // Créer l'employé
        this.employeService.create(employeData, affectationInitiale)
            .pipe(takeUntil(this.destroy$))
            .subscribe({
                next: (employe) => {
                    this.loading = false;
                    this.successMessage = `Employé ${employe.emp_prenom} ${employe.emp_nom} créé avec succès et affectation initiale créée !`;

                    setTimeout(() => {
                        this.router.navigate(['/employes', employe.emp_code]);
                    }, 1500);
                },
                error: (err) => {
                    this.loading = false;
                    console.error('Erreur création employé:', err);

                    if (err.error?.messages) {
                        const messages = Object.values(err.error.messages).flat();
                        this.errorMessage = messages.join('. ');
                    } else if (err.error?.error) {
                        this.errorMessage = `${err.error.error} (${err.error.status})`;
                    } else {
                        this.errorMessage = err.error?.message || 'Erreur lors de la création de l\'employé';
                    }
                }
            });
    }

    onCancel(): void {
        this.router.navigate(['/employes']);
    }

    formatDate(date: any): string {
        if (!date) return '';
        if (date instanceof Date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
        if (typeof date === 'string') {
            return date.split('T')[0];
        }
        return date;
    }

    getErrorMessage(controlName: string, step: number = 1): string {
        let form: FormGroup;
        switch (step) {
            case 1: form = this.personalForm; break;
            case 2: form = this.adminForm; break;
            case 3: form = this.entryForm; break;
            default: form = this.personalForm;
        }

        const control = form.get(controlName);
        if (!control || !control.errors || !control.touched) {
            return '';
        }

        if (control.hasError('required')) return 'Ce champ est obligatoire';
        if (control.hasError('minlength')) return `Min ${control.errors['minlength'].requiredLength} caractères`;
        if (control.hasError('maxlength')) return `Max ${control.errors['maxlength'].requiredLength} caractères`;
        if (control.hasError('email')) return 'Email invalide';
        if (control.hasError('pattern')) return 'Format invalide';

        return '';
    }

    getAffectationErrorMessage(controlName: string): string {
        const control = this.affectationForm.get(controlName);
        if (!control || !control.errors || !control.touched) {
            return '';
        }

        if (control.hasError('required')) {
            return 'Ce champ est obligatoire';
        }

        return '';
    }

    getAffectationFormError(): string {
        if (this.affectationForm.hasError('eldDateFinRequired')) {
            return 'La date de fin est obligatoire pour un contrat ELD';
        }
        if (this.affectationForm.hasError('fonctionnaireDateFinMustBeNull')) {
            return 'La date de fin doit être vide pour un Fonctionnaire';
        }
        return '';
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

    formatCin(event: any): void {
        const input = event.target as HTMLInputElement;
        const initialValue = input.value;
        let value = input.value.replace(/\D/g, '');

        if (value.length > 12) value = value.substring(0, 12);

        let formattedValue = value;
        if (value.length > 0) {
            formattedValue = value.match(/.{1,3}/g)?.join(' ') || value;
        }

        if (initialValue !== formattedValue) {
            input.value = formattedValue;
        }

        this.adminForm.get('emp_cin')?.setValue(formattedValue, { emitEvent: false });
    }
}
