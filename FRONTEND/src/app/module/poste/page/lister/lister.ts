import { Component, OnInit, OnDestroy } from '@angular/core';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterModule } from '@angular/router';

import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTableModule } from '@angular/material/table';
import { MatExpansionModule } from '@angular/material/expansion';
import { MatChipsModule } from '@angular/material/chips';
import { MatTooltipModule } from '@angular/material/tooltip';

import { Observable, Subject, forkJoin } from 'rxjs';
import { map, startWith, switchMap, catchError, finalize, debounceTime, distinctUntilChanged, takeUntil } from 'rxjs/operators';
import { trigger, transition, style, animate } from '@angular/animations';

import { PosteService, PosteStats } from '../../service/poste.service';
import { Poste } from '../../model/poste.model';
import { MatDialog } from '@angular/material/dialog';
import { QuotaDialogComponent } from '../modal/quota-dialog.component';

@Component({
    selector: 'app-lister-postes',
    templateUrl: './lister.html',
    styleUrls: ['./lister.scss'],
    standalone: true,
    imports: [
        CommonModule,
        ReactiveFormsModule,
        RouterModule,
        MatAutocompleteModule,
        MatFormFieldModule,
        MatInputModule,
        MatButtonModule,
        MatCardModule,
        MatIconModule,
        MatProgressSpinnerModule,
        MatTableModule,
        MatExpansionModule,
        MatChipsModule,
        MatTooltipModule
    ],
    animations: [
        trigger('fadeIn', [
            transition(':enter', [
                style({ opacity: 0, transform: 'translateY(10px)' }),
                animate('400ms ease-out', style({ opacity: 1, transform: 'translateY(0)' }))
            ])
        ])
    ]
})
export class ListerPostesComponent implements OnInit, OnDestroy {
    postes: Poste[] = [];
    filters: Record<string, string> = {};
    loading = false;
    stats: PosteStats | null = null;
    errorMessage = '';
    displayedColumns: string[] = ['pst_fonction', 'directions', 'srvc_nom', 'rhq_rang', 'actions'];

    posteControl = new FormControl<string>('');
    filteredPostes!: Observable<string[]>;
    directionControl = new FormControl<string>('');
    filteredDirections!: Observable<string[]>;
    serviceControl = new FormControl<string>('');
    filteredServices!: Observable<string[]>;
    statutControl = new FormControl<string>('');
    filteredStatuts!: Observable<string[]>;
    rangControl = new FormControl<string>('');
    filteredRangs!: Observable<string[]>;

    // Reference data
    allFonctions: string[] = [];
    allDirections: string[] = [];
    allServices: string[] = [];
    allRangs: string[] = [];

    private destroy$ = new Subject<void>();

    constructor(
        private posteService: PosteService,
        private route: ActivatedRoute,
        private dialog: MatDialog
    ) { }

    ngOnInit(): void {
        // Chargement des données de référence en parallèle
        forkJoin({
            fonctions: this.posteService.getFonctions().pipe(catchError(() => [])),
            directions: this.posteService.getDirections().pipe(catchError(() => [])),
            services: this.posteService.getServices().pipe(catchError(() => [])),
            rangs: this.posteService.getRangs().pipe(catchError(() => []))
        }).pipe(
            takeUntil(this.destroy$)
        ).subscribe(data => {
            this.allFonctions = data.fonctions;
            this.allDirections = data.directions;
            this.allServices = data.services;
            this.allRangs = data.rangs;

            // Forcer la mise à jour des filtres une fois les données chargées
            this.posteControl.updateValueAndValidity({ emitEvent: true });
            this.directionControl.updateValueAndValidity({ emitEvent: true });
            this.serviceControl.updateValueAndValidity({ emitEvent: true });
            this.rangControl.updateValueAndValidity({ emitEvent: true });
        });

        // Utiliser les données préchargées par le resolver pour la liste principale
        const resolvedData = this.route.snapshot.data;
        if (resolvedData['postes']) {
            const data = resolvedData['postes'];
            this.postes = data.postes || [];
            this.stats = data.stats || null;
            this.groupPostes();
        } else {
            this.loadPostes({});
            this.loadStats();
        }

        // Configuration des filtres (filtrage local)
        this.filteredPostes = this.posteControl.valueChanges.pipe(
            startWith(''),
            map(value => this.filterArray(value, this.allFonctions))
        );

        this.filteredDirections = this.directionControl.valueChanges.pipe(
            startWith(''),
            map(value => this.filterArray(value, this.allDirections))
        );

        this.filteredServices = this.serviceControl.valueChanges.pipe(
            startWith(''),
            map(value => this.filterArray(value, this.allServices))
        );

        this.filteredStatuts = this.statutControl.valueChanges.pipe(
            startWith(''),
            map(v => {
                const options = ['Occupé', 'Vacant', 'En cessation'];
                return this.filterArray(v, options);
            })
        );

        this.filteredRangs = this.rangControl.valueChanges.pipe(
            startWith(''),
            map(value => this.filterArray(value, this.allRangs))
        );
    }

    filterArray(val: string | null, arr: string[]): string[] {
        const filterValue = (val ?? '').toLowerCase();
        return arr.filter(option => option.toLowerCase().includes(filterValue));
    }

    onFilterChange(): void {
        const rawFilters = {
            pst_fonction: this.posteControl.value?.trim() || undefined,
            dir_nom: this.directionControl.value?.trim() || undefined,
            srvc_nom: this.serviceControl.value?.trim() || undefined,
            statut: this.statutControl.value?.trim() || undefined,
            rhq_rang: this.rangControl.value?.trim() || undefined
        };

        // Nettoyer les valeurs undefined et vides
        this.filters = {};
        Object.keys(rawFilters).forEach(key => {
            const value = rawFilters[key as keyof typeof rawFilters];
            if (value && value.length > 0) {
                this.filters[key] = value;
            }
        });

        console.log('Filtres appliqués:', this.filters);
        this.loadPostes(this.filters);
    }

    resetFilters(): void {
        this.posteControl.setValue('');
        this.directionControl.setValue('');
        this.serviceControl.setValue('');
        this.statutControl.setValue('');
        this.rangControl.setValue('');
        this.filters = {};
        this.loadPostes({});
        this.loadStats();
    }

    getStatutClass(statut?: string): string {
        if (!statut) return '';
        return statut.toLowerCase() === 'vacant' ? 'badge-success' : 'badge-warning';
    }

    trackByPosteCode(index: number, poste: Poste): number {
        return poste.pst_code;
    }


    groupedPostes: { direction: string, services: { serviceName: string, postes: Poste[] }[], directPostes: Poste[], totalPostes: number }[] = [];

    /**
     * Regroupe les postes par direction puis par service
     */
    private groupPostes() {
        if (!this.postes || this.postes.length === 0) {
            this.groupedPostes = [];
            return;
        }

        const groups: {
            [dirName: string]: {
                services: { [srvcName: string]: Poste[] },
                directPostes: Poste[]
            }
        } = {};

        this.postes.forEach(poste => {
            const dirName = poste.dir_nom || 'Sans direction';

            if (!groups[dirName]) {
                groups[dirName] = { services: {}, directPostes: [] };
            }

            if (poste.srvc_nom) {
                if (!groups[dirName].services[poste.srvc_nom]) {
                    groups[dirName].services[poste.srvc_nom] = [];
                }
                groups[dirName].services[poste.srvc_nom].push(poste);
            } else {
                groups[dirName].directPostes.push(poste);
            }
        });

        // Convertir en tableau trié
        this.groupedPostes = Object.keys(groups).sort().map(dir => {
            const group = groups[dir];
            // Trier les services
            const sortedServices = Object.keys(group.services).sort().map(srvc => ({
                serviceName: srvc,
                postes: group.services[srvc]
            }));

            return {
                direction: dir,
                services: sortedServices,
                directPostes: group.directPostes,
                totalPostes: group.directPostes.length + sortedServices.reduce((acc, s) => acc + s.postes.length, 0)
            };
        });
    }

    loadPostes(filters: Record<string, string>): void {
        this.loading = true;
        this.errorMessage = '';

        this.posteService.getPostes(filters).pipe(
            map((response: any) => {
                const postes = Array.isArray(response) ? response : (response?.data || []);
                return postes.map((poste: Poste) => {
                    if (Array.isArray(poste.directions)) return poste;
                    try {
                        const arr = JSON.parse(poste.directions as unknown as string);
                        poste.directions = Array.isArray(arr) ? arr : [arr];
                    } catch {
                        poste.directions = [poste.directions as unknown as string];
                    }
                    return poste;
                });
            }),
            finalize(() => this.loading = false),
            takeUntil(this.destroy$)
        ).subscribe({
            next: (postes: Poste[]) => {
                this.postes = postes;
                this.groupPostes(); // Calculer le groupement une seule fois
            }
        });
    }

    loadStats(): void {
        this.posteService.getStats().subscribe({
            next: (data) => this.stats = data,
            error: (err) => console.error('Erreur stats postes:', err)
        });
    }

    /**
     * Ouvrir le dialog de gestion du quota
     */
    openQuotaDialog(poste: Poste): void {
        const dialogRef = this.dialog.open(QuotaDialogComponent, {
            width: '550px',
            data: {
                pst_code: poste.pst_code,
                pst_fonction: poste.pst_fonction,
                quota: poste.quota,
                nb_occupe: poste.nb_occupe,
                nb_vacant: poste.nb_vacant,
                nb_encessation: poste.nb_encessation
            }
        });

        dialogRef.afterClosed().subscribe(result => {
            if (result) {
                // Recharger la liste des postes après modification
                this.loadPostes(this.filters);
                this.loadStats();
            }
        });
    }

    ngOnDestroy(): void {
        this.destroy$.next();
        this.destroy$.complete();
    }
}