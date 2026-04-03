import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatChipsModule } from '@angular/material/chips';
import { MatDividerModule } from '@angular/material/divider';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { Subject } from 'rxjs';
import { map, catchError, finalize, takeUntil } from 'rxjs/operators';
import { trigger, transition, style, animate } from '@angular/animations';

import { EmployeService, CompetenceEmploye } from '../../service/employe.service';
import { EmployeWithAffectation } from '../../model/employe.model';
import { ModifierEmployeDialogComponent } from '../modal/modifier-employe-dialog.component';
import { CompetenceDialogComponent } from '../../component/competence-dialog/competence-dialog.component';
import { of } from 'rxjs';

@Component({
    selector: 'app-detail-employe',
    templateUrl: './detail-employe.component.html',
    styleUrls: ['./detail-employe.component.scss'],
    standalone: true,
    imports: [
        CommonModule,
        RouterModule,
        MatCardModule,
        MatIconModule,
        MatButtonModule,
        MatProgressSpinnerModule,
        MatChipsModule,
        MatDividerModule,
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
export class DetailEmployeComponent implements OnInit, OnDestroy {
    employe: EmployeWithAffectation | null = null;
    competences: CompetenceEmploye[] = [];
    loading = false;
    errorMessage = '';

    private destroy$ = new Subject<void>();

    constructor(
        private route: ActivatedRoute,
        private router: Router,
        private employeService: EmployeService,
        private dialog: MatDialog
    ) { }

    ngOnInit(): void {
        const id = this.route.snapshot.paramMap.get('id');
        if (id) {
            this.loadEmploye(+id);
        } else {
            this.errorMessage = 'ID de l\'employé manquant';
        }
    }

    ngOnDestroy(): void {
        this.destroy$.next();
        this.destroy$.complete();
    }

    private loadEmploye(id: number): void {
        this.loading = true;
        this.errorMessage = '';

        this.employeService.get(id).pipe(
            catchError((error: any) => {
                this.errorMessage = error.error?.messages?.error || 'Erreur lors du chargement de l\'employé.';
                console.error('Erreur API:', error);
                return of(null);
            }),
            finalize(() => {
                this.loading = false;
            }),
            takeUntil(this.destroy$)
        ).subscribe((employe: EmployeWithAffectation | null) => {
            if (employe) {
                this.employe = employe;
                if (employe.emp_code) {
                    this.loadCompetences(employe.emp_code);
                }
            }
        });
    }

    private loadCompetences(id: number): void {
        this.employeService.getCompetences(id).subscribe({
            next: (competences) => this.competences = competences,
            error: (err) => console.error('Erreur chargement compétences:', err)
        });
    }

    openModifierDialog(): void {
        if (!this.employe || !this.employe.emp_code) return;

        const dialogRef = this.dialog.open(ModifierEmployeDialogComponent, {
            width: '700px',
            maxWidth: '90vw',
            data: {
                employe: this.employe
            },
            disableClose: true
        });

        dialogRef.afterClosed().subscribe((result) => {
            if (result === true && this.employe?.emp_code) {
                this.loadEmploye(this.employe.emp_code);
            }
        });
    }

    openCompetenceDialog(): void {
        if (!this.employe || !this.employe.emp_code) return;

        const dialogRef = this.dialog.open(CompetenceDialogComponent, {
            width: '600px',
            data: { employe: this.employe },
            disableClose: true
        });

        dialogRef.afterClosed().subscribe(() => {
            if (this.employe?.emp_code) {
                this.loadCompetences(this.employe.emp_code);
            }
        });
    }


    goBack(): void {
        this.router.navigate(['/employes']);
    }

    goToParcours(): void {
        if (this.employe?.emp_code) {
            this.router.navigate(['/employes', this.employe.emp_code, 'parcours']);
        }
    }

    formatDate(date: string | undefined): string {
        if (!date) return 'Non renseigné';
        return new Date(date).toLocaleDateString('fr-FR');
    }
}

