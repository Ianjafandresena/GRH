import { Component, Inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule, MatDialog } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatIconModule } from '@angular/material/icon';
import { MatListModule } from '@angular/material/list';
import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { EmployeService, CompetenceEmploye } from '../../service/employe.service';
import { CompetenceService, Competence } from '../../../competence/service/competence.service';
import { Employe } from '../../model/employe.model';
import { BehaviorSubject, Observable, combineLatest } from 'rxjs';
import { map, startWith } from 'rxjs/operators';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { ConfirmDialogComponent } from '../confirm-dialog/confirm-dialog.component';

@Component({
    selector: 'app-competence-dialog',
    standalone: true,
    imports: [
        CommonModule,
        ReactiveFormsModule,
        MatDialogModule,
        MatButtonModule,
        MatFormFieldModule,
        MatInputModule,
        MatSelectModule,
        MatIconModule,
        MatListModule,
        MatAutocompleteModule,
        MatProgressSpinnerModule,
        MatSnackBarModule
    ],
    templateUrl: './competence-dialog.component.html',
    styleUrls: ['./competence-dialog.component.scss']
})
export class CompetenceDialogComponent implements OnInit {
    competencesEmploye: CompetenceEmploye[] = [];
    allCompetences: Competence[] = [];
    filteredCompetences$: Observable<Competence[]>;

    addForm: FormGroup;
    loading = false;

    niveaux = [
        { value: 1, label: '1 - Débutant' },
        { value: 2, label: '2 - Intermédiaire' },
        { value: 3, label: '3 - Confirmé' },
        { value: 4, label: '4 - Avancé' },
        { value: 5, label: '5 - Expert' }
    ];

    private dataLoaded$ = new BehaviorSubject<boolean>(false);

    constructor(
        public dialogRef: MatDialogRef<CompetenceDialogComponent>,
        @Inject(MAT_DIALOG_DATA) public data: { employe: Employe },
        private employeService: EmployeService,
        private competenceService: CompetenceService,
        private fb: FormBuilder,
        private snackBar: MatSnackBar,
        private dialog: MatDialog
    ) {
        this.addForm = this.fb.group({
            competence: ['', Validators.required],
            niveau: [1, [Validators.required, Validators.min(1), Validators.max(5)]]
        });

        this.filteredCompetences$ = combineLatest([
            this.addForm.get('competence')!.valueChanges.pipe(startWith('')),
            this.dataLoaded$
        ]).pipe(
            map(([value, loaded]) => {
                if (!loaded) return [];
                const name = typeof value === 'string' ? value : value?.comp_intitule;
                return name ? this._filter(name as string) : this.allCompetences.slice();
            })
        );
    }

    ngOnInit(): void {
        this.loadData();
    }

    loadData(): void {
        this.loading = true;
        combineLatest([
            this.employeService.getCompetences(this.data.employe.emp_code!),
            this.competenceService.getAll()
        ]).subscribe({
            next: ([empComps, allComps]) => {
                this.competencesEmploye = empComps;
                this.allCompetences = allComps;
                this.loading = false;
                this.dataLoaded$.next(true);

                // Force update validity for autocomplete to re-run filter with data
                this.addForm.get('competence')?.updateValueAndValidity({ emitEvent: true });
            },
            error: (err) => {
                console.error('Erreur chargement données', err);
                this.loading = false;
                this.showError('Erreur lors du chargement des données');
            }
        });
    }

    displayFn(competence: Competence): string {
        return competence && competence.comp_intitule ? competence.comp_intitule : '';
    }

    private _filter(name: string): Competence[] {
        const filterValue = name.toLowerCase();
        return this.allCompetences.filter(option =>
            option.comp_intitule.toLowerCase().includes(filterValue) ||
            (option.comp_domaine && option.comp_domaine.toLowerCase().includes(filterValue))
        );
    }

    addCompetence(): void {
        if (this.addForm.invalid) return;

        const competence = this.addForm.get('competence')?.value as Competence;
        const niveau = this.addForm.get('niveau')?.value as number;

        if (!competence || !competence.comp_code) {
            this.showError('Veuillez sélectionner une compétence valide');
            return;
        }

        this.loading = true;
        this.employeService.addCompetence(this.data.employe.emp_code!, competence.comp_code, niveau)
            .subscribe({
                next: () => {
                    this.showSuccess('Compétence ajoutée avec succès');
                    this.addForm.reset({ niveau: 1 });
                    this.loadData(); // Reload to refresh list and potentially filter out added one if we wanted to
                },
                error: (err) => {
                    console.error('Erreur ajout', err);
                    this.loading = false;
                    this.showError('Erreur lors de l\'ajout de la compétence');
                }
            });
    }

    removeCompetence(comp: CompetenceEmploye): void {
        const dialogRef = this.dialog.open(ConfirmDialogComponent, {
            width: '400px',
            data: {
                title: 'Supprimer la compétence',
                message: `Voulez-vous vraiment retirer la compétence "${comp.comp_intitule}" ?`,
                confirmText: 'Supprimer',
                color: 'warn'
            }
        });

        dialogRef.afterClosed().subscribe(result => {
            if (result) {
                this.loading = true;
                this.employeService.removeCompetence(this.data.employe.emp_code!, comp.comp_code)
                    .subscribe({
                        next: () => {
                            this.showSuccess('Compétence retirée avec succès');
                            this.competencesEmploye = this.competencesEmploye.filter(c => c.comp_code !== comp.comp_code);
                            this.loading = false;
                        },
                        error: (err) => {
                            console.error('Erreur suppression', err);
                            this.loading = false;
                            this.showError('Erreur lors de la suppression de la compétence');
                        }
                    });
            }
        });
    }

    private showSuccess(message: string) {
        this.snackBar.open(message, 'Fermer', { duration: 3000, panelClass: ['success-snackbar'] });
    }

    private showError(message: string) {
        this.snackBar.open(message, 'Fermer', { duration: 5000, panelClass: ['error-snackbar'] });
    }

    close(): void {
        this.dialogRef.close();
    }
}
