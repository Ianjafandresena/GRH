import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDividerModule } from '@angular/material/divider';
import { MatChipsModule } from '@angular/material/chips';
import { MatTooltipModule } from '@angular/material/tooltip';
import { trigger, transition, style, animate } from '@angular/animations';
import { Subject, of } from 'rxjs';
import { catchError, finalize, takeUntil } from 'rxjs/operators';

import { EmployeService, ParcoursAffectation } from '../../service/employe.service';

@Component({
    selector: 'app-parcours-employe',
    standalone: true,
    templateUrl: './parcours-employe.component.html',
    styleUrls: ['./parcours-employe.component.scss'],
    imports: [
        CommonModule,
        RouterModule,
        MatCardModule,
        MatIconModule,
        MatButtonModule,
        MatProgressSpinnerModule,
        MatDividerModule,
        MatChipsModule,
        MatTooltipModule,
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
export class ParcoursEmployeComponent implements OnInit, OnDestroy {
    empId: number | null = null;
    parcours: ParcoursAffectation[] = [];
    loading = false;
    errorMessage = '';

    private destroy$ = new Subject<void>();

    constructor(
        private route: ActivatedRoute,
        private router: Router,
        private employeService: EmployeService,
    ) { }

    ngOnInit(): void {
        const id = this.route.snapshot.paramMap.get('id');
        this.empId = id ? Number(id) : null;

        if (!this.empId) {
            this.errorMessage = 'ID de l\'employé manquant';
            return;
        }

        this.loadParcours(this.empId);
    }

    ngOnDestroy(): void {
        this.destroy$.next();
        this.destroy$.complete();
    }

    goBack(): void {
        if (this.empId) {
            this.router.navigate(['/employes', this.empId]);
        } else {
            this.router.navigate(['/employes']);
        }
    }

    private loadParcours(empId: number): void {
        this.loading = true;
        this.errorMessage = '';

        this.employeService.getParcours(empId).pipe(
            catchError((error: any) => {
                this.errorMessage = error?.error?.messages?.error || error?.error?.message || 'Erreur lors du chargement du parcours.';
                return of([] as ParcoursAffectation[]);
            }),
            finalize(() => (this.loading = false)),
            takeUntil(this.destroy$)
        ).subscribe((rows: ParcoursAffectation[]) => {
            this.parcours = rows || [];
        });
    }

    formatDate(date?: string | null): string {
        if (!date) return 'En cours';
        return new Date(date).toLocaleDateString('fr-FR');
    }

    trackByItem(_: number, item: ParcoursAffectation): number | string {
        if (item.type === 'sortie') {
            return `sortie-${item.date_sortie}-${item.s_type_code}`;
        }
        return item.affec_code || 0;
    }

    getContratClass(type?: string | null): string {
        if (!type) return 'contrat-na';
        const t = type.toLowerCase();
        if (t.includes('fonctionnaire')) return 'contrat-fonctionnaire';
        if (t.includes('efa')) return 'contrat-efa';
        if (t.includes('eld')) return 'contrat-eld';
        return 'contrat-na';
    }

    getPeriode(aff: ParcoursAffectation): string {
        if (aff.type === 'sortie') {
            return this.formatDate(aff.date_sortie);
        }
        const debut = this.formatDate(aff.affec_date_debut);
        const fin = aff.affec_date_fin ? this.formatDate(aff.affec_date_fin) : 'En cours';
        return `${debut} → ${fin}`;
    }
}
