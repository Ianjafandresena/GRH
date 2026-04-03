import { Component, OnInit, inject, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { MatTabsModule } from '@angular/material/tabs';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTooltipModule } from '@angular/material/tooltip';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { EmployeeService } from '../../service/employee.service';
import { CongeService } from '../../../conge/service/conge.service';
import { SoldeCongeService } from '../../../conge/service/solde-conge.service';
import { PermissionService } from '../../../permission/service/permission.service';
import { Subject, forkJoin, of } from 'rxjs';
import { takeUntil, catchError, map } from 'rxjs/operators';
import { trigger, transition, style, animate } from '@angular/animations';

@Component({
    selector: 'app-employee-detail',
    standalone: true,
    imports: [
        CommonModule,
        MatIconModule,
        MatButtonModule,
        MatFormFieldModule,
        MatInputModule,
        MatSelectModule,
        MatSnackBarModule,
        MatProgressSpinnerModule,
        MatTooltipModule,
        ReactiveFormsModule,
        RouterModule
    ],
    templateUrl: './detail.html',
    styleUrls: ['./detail.scss'],
    animations: [
        trigger('fadeInUp', [
            transition(':enter', [
                style({ opacity: 0, transform: 'translateY(20px)' }),
                animate('400ms ease-out', style({ opacity: 1, transform: 'translateY(0)' }))
            ])
        ])
    ]
})
export class EmployeeDetailComponent implements OnInit, OnDestroy {
    private readonly route = inject(ActivatedRoute);
    private readonly router = inject(Router);
    private readonly fb = inject(FormBuilder);
    private readonly snackBar = inject(MatSnackBar);
    private readonly employeeService = inject(EmployeeService);
    private readonly congeService = inject(CongeService);
    private readonly soldeCongeService = inject(SoldeCongeService);
    private readonly permissionService = inject(PermissionService);

    private destroy$ = new Subject<void>();

    employee: any = null;
    soldesConge: any[] = [];
    soldesPermission: any[] = [];
    absences: any[] = [];
    spouses: any[] = [];
    children: any[] = [];
    loading = true;
    empCode!: number;
    showAttributionForm = false;

    // Career stats
    careerStats = {
        anciennete: '',
        anneeRetraite: 0,
        dejaRetraite: false
    };

    attributionForm: FormGroup = this.fb.group({
        type: ['conge', Validators.required],
        jours: [30, [Validators.required, Validators.min(0.5)]],
        annee: [new Date().getFullYear() - 1, [Validators.required, Validators.min(2020)]]
    });

    ngOnInit() {
        const idParam = this.route.snapshot.paramMap.get('id');
        if (idParam) {
            this.empCode = +idParam;
            this.loadAllData();
        }

        // Auto-adjust max days based on type
        this.attributionForm.get('type')?.valueChanges.subscribe(type => {
            const joursControl = this.attributionForm.get('jours');
            if (joursControl) {
                if (type === 'permission') {
                    joursControl.setValidators([Validators.required, Validators.min(0.5), Validators.max(10)]);
                    if (joursControl.value > 10) joursControl.setValue(10);
                } else {
                    joursControl.setValidators([Validators.required, Validators.min(0.5), Validators.max(30)]);
                    if (joursControl.value > 30) joursControl.setValue(30);
                }
                joursControl.updateValueAndValidity();
            }
        });
    }

    ngOnDestroy() {
        this.destroy$.next();
        this.destroy$.complete();
    }

    loadAllData() {
        this.loading = true;
        this.employee = null; // Clear previous

        forkJoin({
            employee: this.employeeService.getEmployeeDetail(this.empCode).pipe(
                catchError(err => {
                    console.error('Erreur employe detail:', err);
                    return of(null);
                })
            ),
            soldesConge: this.soldeCongeService.getSoldesByEmployee(this.empCode).pipe(
                catchError(err => {
                    console.error('Erreur soldes conge:', err);
                    return of([]);
                })
            ),
            soldesPermission: this.permissionService.getSoldesPermission(this.empCode).pipe(
                catchError(err => {
                    console.error('Erreur soldes permission:', err);
                    return of([]);
                })
            ),
            absences: this.congeService.getAbsences({ emp_code: this.empCode }).pipe(
                catchError(err => {
                    console.error('Erreur absences:', err);
                    return of([]);
                })
            ),
            spouses: this.employeeService.getSpouses(this.empCode).pipe(
                catchError(err => {
                    console.error('Erreur conjoints:', err);
                    return of([]);
                })
            ),
            children: this.employeeService.getChildren(this.empCode).pipe(
                catchError(err => {
                    console.error('Erreur enfants:', err);
                    return of([]);
                })
            )
        }).pipe(takeUntil(this.destroy$)).subscribe({
            next: (data) => {
                this.employee = data.employee;
                this.soldesConge = data.soldesConge;
                this.soldesPermission = data.soldesPermission;
                this.absences = data.absences;
                this.spouses = data.spouses;
                this.children = data.children;

                this.calculateAvailability();
                this.calculateCareerStats();

                this.loading = false;

                if (!this.employee) {
                    this.snackBar.open('Employé introuvable', 'D\'accord', { duration: 4000 });
                }
            },
            error: (err) => {
                // This shouldn't be reached now due to catchError inside forkJoin
                console.error('Erreur globale fatale:', err);
                this.snackBar.open('Erreur globale lors du chargement', 'OK', { duration: 3000 });
                this.loading = false;
            }
        });
    }

    attribuerSolde() {
        if (this.attributionForm.invalid) return;

        const payload = {
            emp_code: this.empCode,
            ...this.attributionForm.value
        };

        this.soldeCongeService.attribuerManuellement(payload).subscribe({
            next: (res) => {
                this.snackBar.open('Solde attribué avec succès', 'OK', { duration: 3000 });
                this.loadAllData(); // Refresh lists
            },
            error: (err) => {
                console.error('Erreur attribution:', err);
                this.snackBar.open('Erreur : ' + (err.error?.message || 'Impossible d\'attribuer le solde'), 'D\'accord');
            }
        });
    }

    goBack() {
        this.router.navigate(['/employee']);
    }

    formatDate(dateStr: string | Date): string {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    }

    calculateCareerStats() {
        if (!this.employee) return;

        // 1. Ancienneté
        const start = new Date(this.employee.affec_date_debut || this.employee.date_entree || new Date());
        const now = new Date();
        const diffTime = Math.abs(now.getTime() - start.getTime());
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays < 30) {
            this.careerStats.anciennete = `${diffDays} jour${diffDays > 1 ? 's' : ''}`;
        } else if (diffDays < 365) {
            const months = Math.floor(diffDays / 30);
            this.careerStats.anciennete = `${months} mois`;
        } else {
            const years = Math.floor(diffDays / 365);
            const remainingMonths = Math.floor((diffDays % 365) / 30);
            this.careerStats.anciennete = `${years} an${years > 1 ? 's' : ''}${remainingMonths > 0 ? ' ' + remainingMonths + ' mois' : ''}`;
        }

        // 2. Retraite (Hypothèse 60 ans)
        if (this.employee.emp_datenaissance) {
            const birth = new Date(this.employee.emp_datenaissance);
            const retirementYear = birth.getFullYear() + 60;
            this.careerStats.anneeRetraite = retirementYear;
            this.careerStats.dejaRetraite = now.getFullYear() >= retirementYear;
        }
    }

    goToParcours() {
        // Redirect to Career module's parcours if it exists, or just a toast
        this.router.navigate(['/employe/parcours', this.empCode]).catch(() => {
            this.snackBar.open('Module Parcours non accessible ici', 'OK', { duration: 2000 });
        });
    }

    openModifierDialog() {
        this.router.navigate(['/employe/modif', this.empCode]).catch(() => {
            this.snackBar.open('Utilisez le module carrière pour modifier', 'OK', { duration: 2000 });
        });
    }

    calculateAvailability() {
        if (!this.employee) return;

        const now = new Date();
        const activeAbsence = this.absences.find(a => {
            // Only consider validated absences
            const isValidated = (a.cng_status === true || a.cng_status === 't' || a.cng_status === 1 ||
                a.prm_status === true || a.prm_status === 't' || a.prm_status === 1 ||
                a.absence_type === 'permission');

            if (!isValidated && a.absence_type === 'conge') return false;

            const start = new Date(a.cng_debut || a.prm_debut);
            const end = new Date(a.cng_fin || a.prm_fin);

            if (a.absence_type === 'conge') {
                end.setHours(23, 59, 59, 999);
            }

            return now >= start && now <= end;
        });

        if (activeAbsence) {
            this.employee.is_available = false;
            this.employee.active_absence = activeAbsence;
        } else {
            this.employee.is_available = true;
        }
    }

    toggleAttributionForm() {
        this.showAttributionForm = !this.showAttributionForm;
    }

    goToFamilyMgmt() {
        this.router.navigate(['/employee', this.empCode, 'family']);
    }

    getMarriedSpouse() {
        // Use == to handle both string and number from DB
        return this.spouses.find(s => s.cjs_id == 1);
    }

    getStatusClass(): string {
        return this.employee?.is_available ? 'status-available' : 'status-unavailable';
    }

    getStatusText(): string {
        if (!this.employee) return '';
        if (this.employee.is_available) return 'Présent';

        const a = this.employee.active_absence;
        if (a) {
            const date = new Date(a.cng_fin || a.prm_fin).toLocaleDateString();
            return `Absent (jusqu'au ${date})`;
        }
        return 'Absent / En congé';
    }
}
