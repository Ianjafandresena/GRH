import { Component, OnInit, OnDestroy } from '@angular/core';
import { trigger, transition, style, animate } from '@angular/animations';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule, FormControl } from '@angular/forms';
import { Router } from '@angular/router';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSelectModule } from '@angular/material/select';
import { MatAutocompleteModule, MatAutocompleteSelectedEvent } from '@angular/material/autocomplete';
import { MatOptionModule } from '@angular/material/core';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { PdfPreviewDialogComponent } from '../../component/pdf-preview/pdf-preview-dialog.component';
import { Observable, Subject } from 'rxjs';
import { map, startWith, takeUntil, debounceTime, distinctUntilChanged } from 'rxjs/operators';
import { DocumentService } from '../../service/document.service';
import { DocumentDemande } from '../../model/document.model';
import { EmployeService } from '../../../employe/service/employe.service';
import { EmployeWithAffectation } from '../../../employe/model/employe.model';
import { TypeDocumentService, TypeDocument } from '../../../referentiel/service/type-document.service';

@Component({
    selector: 'app-demande-document',
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
        MatAutocompleteModule,
        MatOptionModule,
        MatDialogModule
    ],
    templateUrl: './demande-document.component.html',
    styleUrls: ['./demande-document.component.scss'],
    animations: [
        trigger('fadeIn', [
            transition(':enter', [
                style({ opacity: 0, transform: 'translateY(10px)' }),
                animate('400ms ease-out', style({ opacity: 1, transform: 'translateY(0)' }))
            ])
        ])
    ]
})
export class DemandeDocumentComponent implements OnInit, OnDestroy {
    demandeForm!: FormGroup;
    employeControl = new FormControl<EmployeWithAffectation | string>('', [Validators.required]);

    loading = false;
    loadingTypes = false;
    errorMessage = '';
    successMessage = '';

    employes: EmployeWithAffectation[] = [];
    filteredEmployes$!: Observable<EmployeWithAffectation[]>;
    typeDocuments: TypeDocument[] = [];

    private destroy$ = new Subject<void>();

    constructor(
        private fb: FormBuilder,
        private documentService: DocumentService,
        private employeService: EmployeService,
        private typeDocumentService: TypeDocumentService,
        private router: Router,
        private dialog: MatDialog
    ) { }

    ngOnInit(): void {
        this.initForm();
        this.loadEmployes();
        this.loadTypeDocuments();
        this.setupAutocomplete();
    }

    ngOnDestroy(): void {
        this.destroy$.next();
        this.destroy$.complete();
    }

    private initForm(): void {
        this.demandeForm = this.fb.group({
            type_document: [null, [Validators.required]],
            usage: [null, [Validators.required]],
            commentaire: ['']
        });

        this.demandeForm.get('usage')?.valueChanges
            .pipe(takeUntil(this.destroy$))
            .subscribe((usage) => {
                const commentaireCtrl = this.demandeForm.get('commentaire');
                if (!commentaireCtrl) return;

                if (usage === 'Administrative') {
                    commentaireCtrl.setValidators([Validators.required]);
                } else {
                    commentaireCtrl.clearValidators();
                    commentaireCtrl.setValue('');
                }
                commentaireCtrl.updateValueAndValidity({ emitEvent: false });
            });
    }

    private loadEmployes(): void {
        this.loading = true;
        this.employeService.list({ statut: 'actif' }).pipe(
            takeUntil(this.destroy$)
        ).subscribe({
            next: (employes) => {
                this.employes = employes;
                this.loading = false;
            },
            error: (err) => {
                console.error('Erreur lors du chargement des employés:', err);
                this.errorMessage = 'Erreur lors du chargement des employés';
                this.loading = false;
            }
        });
    }

    private loadTypeDocuments(): void {
        this.loadingTypes = true;
        this.typeDocumentService.getTypesDocument().pipe(
            takeUntil(this.destroy$)
        ).subscribe({
            next: (types) => {
                this.typeDocuments = types;
                this.loadingTypes = false;
            },
            error: (err) => {
                console.error('Erreur lors du chargement des types de documents:', err);
                this.errorMessage = 'Erreur lors du chargement des types de documents';
                this.loadingTypes = false;
            }
        });
    }

    private setupAutocomplete(): void {
        this.filteredEmployes$ = this.employeControl.valueChanges.pipe(
            startWith(''),
            debounceTime(300),
            distinctUntilChanged(),
            map(value => {
                const searchTerm = typeof value === 'string' ? value : this.getDisplayValue(value);
                return searchTerm ? this.filterEmployes(searchTerm) : this.employes.slice();
            })
        );
    }

    private filterEmployes(searchTerm: string): EmployeWithAffectation[] {
        const normalizedSearch = this.normalizeString(searchTerm);
        return this.employes.filter(emp => {
            const nom = this.normalizeString(emp.emp_nom || '');
            const prenom = this.normalizeString(emp.emp_prenom || '');
            const matricule = this.normalizeString(emp.emp_matricule || '');
            const imArmp = this.normalizeString(emp.emp_im_armp || '');

            return nom.includes(normalizedSearch) ||
                prenom.includes(normalizedSearch) ||
                matricule.includes(normalizedSearch) ||
                imArmp.includes(normalizedSearch) ||
                `${nom} ${prenom}`.includes(normalizedSearch);
        });
    }

    private normalizeString(str: string): string {
        return str
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[’']/g, '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase();
    }

    getDisplayValue(employe: EmployeWithAffectation | null | string): string {
        if (!employe || typeof employe === 'string') {
            return typeof employe === 'string' ? employe : '';
        }
        const nom = employe.emp_nom || '';
        const prenom = employe.emp_prenom || '';
        return `${nom} ${prenom}`.trim();
    }

    onEmployeSelected(event: MatAutocompleteSelectedEvent): void {
        const employe = event.option.value as EmployeWithAffectation;
        this.employeControl.setValue(employe);
    }

    onSubmit(): void {
        if (this.demandeForm.invalid || this.employeControl.invalid) {
            this.markFormGroupTouched();
            return;
        }

        const employe = this.employeControl.value;
        if (!employe || typeof employe === 'string') {
            this.errorMessage = 'Veuillez sélectionner un employé';
            return;
        }

        const empCode = employe.emp_code;
        if (!empCode) {
            this.errorMessage = 'L\'employé sélectionné n\'a pas de code valide';
            return;
        }

        const tdocCode = this.demandeForm.value.type_document;
        if (!tdocCode) {
            this.errorMessage = 'Veuillez sélectionner un type de document';
            return;
        }

        const usage = this.demandeForm.value.usage;
        if (!usage) {
            this.errorMessage = 'Veuillez sélectionner un usage';
            return;
        }

        const commentaire = this.demandeForm.value.commentaire;

        const demande: DocumentDemande = {
            emp_code: empCode,
            tdoc_code: tdocCode,
            usage: usage,
            commentaire: usage === 'Administrative' ? commentaire : undefined,
            date_demande: new Date().toISOString().split('T')[0]
        };

        this.loading = true;
        this.errorMessage = '';
        this.successMessage = '';

        this.documentService.creerDemande(demande).pipe(
            takeUntil(this.destroy$)
        ).subscribe({
            next: (response) => {
                this.loading = false;
                this.successMessage = 'Document généré avec succès !';

                // Ouvrir l'aperçu PDF au lieu de télécharger directement
                if (response.pdf_base64 && response.filename) {
                    this.dialog.open(PdfPreviewDialogComponent, {
                        width: '800px',
                        maxWidth: '95vw',
                        data: {
                            pdfBase64: response.pdf_base64,
                            filename: response.filename,
                            title: 'Demande de Document Officiel'
                        }
                    });
                }

                this.demandeForm.reset();
                this.employeControl.reset();

                // Rediriger après un certain délai ou laisser l'utilisateur sur la page
                // setTimeout(() => {
                //     this.router.navigate(['/home']);
                // }, 5000);
            },
            error: (err) => {
                this.loading = false;
                this.errorMessage =
                    err?.error?.message ||
                    err?.error?.messages?.error ||
                    err?.message ||
                    'Erreur lors de la génération du document';
                console.error('Erreur:', err);
            }
        });
    }

    onCancel(): void {
        this.router.navigate(['/home']);
    }

    private markFormGroupTouched(): void {
        Object.keys(this.demandeForm.controls).forEach(key => {
            const control = this.demandeForm.get(key);
            control?.markAsTouched();
        });
        this.employeControl.markAsTouched();
    }
}

