import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { trigger, transition, style, animate } from '@angular/animations';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { EmployeService } from '../../service/employe.service';
import { EmployeWithAffectation } from '../../model/employe.model';
import { MatTableModule } from '@angular/material/table';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { FormControl, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatDividerModule } from '@angular/material/divider';
import { MatAutocompleteModule, MatAutocompleteSelectedEvent } from '@angular/material/autocomplete';
import { MatOptionModule } from '@angular/material/core';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatButtonToggleModule } from '@angular/material/button-toggle';
import { BehaviorSubject, combineLatest, Observable, Subject } from 'rxjs';
import { debounceTime, distinctUntilChanged, map, startWith, takeUntil } from 'rxjs/operators';
import { Position, EmployeStats } from '../../service/employe.service';
import { ServiceService, Service } from '../../../referentiel/service/service.service';
import { PosteService, Poste } from '../../../poste/service/poste.service';
import { CompetenceDialogComponent } from '../../component/competence-dialog/competence-dialog.component';
import { FinirCarriereDialogComponent } from '../modal/finir-carriere-dialog.component';
import { ModifierEmployeDialogComponent } from '../modal/modifier-employe-dialog.component';
import { ReintegrationDialogComponent } from '../modal/reintegration-dialog.component';
import { ConfirmDialogComponent } from '../../component/confirm-dialog/confirm-dialog.component';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';

@Component({
    selector: 'app-lister-employes',
    standalone: true,
    imports: [
        CommonModule,
        RouterModule,
        ReactiveFormsModule,
        MatTableModule,
        MatCardModule,
        MatIconModule,
        MatButtonModule,
        MatProgressSpinnerModule,
        MatFormFieldModule,
        MatInputModule,
        MatDividerModule,
        MatAutocompleteModule,
        MatOptionModule,
        MatDialogModule,
        MatTooltipModule,
        MatButtonToggleModule,
        MatSnackBarModule
    ],
    templateUrl: './lister.html',
    styleUrls: ['./lister.scss'],
    animations: [
        trigger('fadeIn', [
            transition(':enter', [
                style({ opacity: 0, transform: 'translateY(10px)' }),
                animate('400ms ease-out', style({ opacity: 1, transform: 'translateY(0)' }))
            ])
        ])
    ]
})
export class ListerEmployesComponent implements OnInit, OnDestroy {
    employes: EmployeWithAffectation[] = [];
    positions: Position[] = [];
    services: Service[] = [];
    postes: Poste[] = [];
    stats: EmployeStats | null = null;

    statuts = [
        { label: 'Actif', value: 'actif' as const },
        { label: 'Inactif', value: 'inactif' as const },
    ];
    loading = false;
    exporting = false;
    errorMessage = '';
    displayedColumns: string[] = ['emp_matricule', 'emp_nom', 'emp_prenom', 'pos_type', 'emp_im_armp', 'date_entree', 'actions'];

    // Tri par nom
    sortOrder: 'asc' | 'desc' | null = null;

    filterForm = new FormGroup({
        q: new FormControl<string>('', { nonNullable: true }),
        statut: new FormControl<'actif' | 'inactif' | null>(null),
        srvc_code: new FormControl<number | null>(null),
        pst_code: new FormControl<number | null>(null),
        pos_code: new FormControl<number | null>(null),
    });

    serviceControl = new FormControl<Service | string>('', { nonNullable: true });
    posteControl = new FormControl<Poste | string>('', { nonNullable: true });
    positionControl = new FormControl<Position | string>('', { nonNullable: true });
    statutControl = new FormControl<{ label: string; value: 'actif' | 'inactif' } | string>('', { nonNullable: true });

    filteredServices$!: Observable<Service[]>;
    filteredPostes$!: Observable<Poste[]>;
    filteredPositions$!: Observable<Position[]>;
    filteredStatuts$!: Observable<{ label: string; value: 'actif' | 'inactif' }[]>;

    private services$ = new BehaviorSubject<Service[]>([]);
    private postes$ = new BehaviorSubject<Poste[]>([]);
    private positions$ = new BehaviorSubject<Position[]>([]);

    private destroy$ = new Subject<void>();

    constructor(
        private employeService: EmployeService,
        private serviceService: ServiceService,
        private posteService: PosteService,
        private dialog: MatDialog,
        private route: ActivatedRoute,
        private cdr: ChangeDetectorRef,
        private snackBar: MatSnackBar
    ) { }

    ngOnInit(): void {
        this.filteredServices$ = combineLatest([
            this.serviceControl.valueChanges.pipe(startWith('')),
            this.services$
        ]).pipe(
            map(([value, services]) => this.filterServices(value, services))
        );

        this.filteredPostes$ = combineLatest([
            this.posteControl.valueChanges.pipe(startWith('')),
            this.postes$
        ]).pipe(
            map(([value, postes]) => this.filterPostes(value, postes))
        );

        this.filteredPositions$ = combineLatest([
            this.positionControl.valueChanges.pipe(startWith('')),
            this.positions$
        ]).pipe(
            map(([value, positions]) => this.filterPositions(value, positions))
        );

        this.filteredStatuts$ = this.statutControl.valueChanges.pipe(
            startWith(''),
            map((value) => this.filterStatuts(value))
        );

        this.serviceControl.valueChanges.pipe(
            takeUntil(this.destroy$)
        ).subscribe((value) => {
            if (typeof value === 'string') {
                const shouldEmit = value.trim() === '';
                if (this.filterForm.controls.srvc_code.value !== null) {
                    this.filterForm.controls.srvc_code.setValue(null, { emitEvent: shouldEmit });
                }
            }
        });

        this.posteControl.valueChanges.pipe(
            takeUntil(this.destroy$)
        ).subscribe((value) => {
            if (typeof value === 'string') {
                const shouldEmit = value.trim() === '';
                if (this.filterForm.controls.pst_code.value !== null) {
                    this.filterForm.controls.pst_code.setValue(null, { emitEvent: shouldEmit });
                }
            }
        });

        this.positionControl.valueChanges.pipe(
            takeUntil(this.destroy$)
        ).subscribe((value) => {
            if (typeof value === 'string') {
                const shouldEmit = value.trim() === '';
                if (this.filterForm.controls.pos_code.value !== null) {
                    this.filterForm.controls.pos_code.setValue(null, { emitEvent: shouldEmit });
                }
            }
        });

        this.statutControl.valueChanges.pipe(
            takeUntil(this.destroy$)
        ).subscribe((value) => {
            if (typeof value === 'string') {
                const shouldEmit = value.trim() === '';
                if (this.filterForm.controls.statut.value !== null) {
                    this.filterForm.controls.statut.setValue(null, { emitEvent: shouldEmit });
                }
            }
        });

        this.loadPositions();
        this.loadServices();
        this.loadPostes();

        // Utiliser les données préchargées par le resolver (employés et stats)
        const resolvedData = this.route.snapshot.data;
        if (resolvedData['employes']) {
            const data = resolvedData['employes'];
            this.employes = data.employes || [];
            this.stats = data.stats || null;

            // Appliquer le tri si défini
            if (this.sortOrder) {
                this.sortEmployesByName();
            }
        } else {
            // Fallback si le resolver n'a pas fonctionné
            this.loadEmployes();
            this.loadStats();
        }

        this.filterForm.controls.srvc_code.valueChanges.pipe(
            takeUntil(this.destroy$)
        ).subscribe((srvcCode) => {
            // Quand on change de service: reload postes + reset poste
            this.filterForm.controls.pst_code.setValue(null, { emitEvent: false });
            this.posteControl.setValue('', { emitEvent: false });
            if (srvcCode) {
                this.loadPostes(srvcCode);
            } else {
                this.loadPostes();
            }
        });
    }

    ngOnDestroy(): void {
        this.destroy$.next();
        this.destroy$.complete();
    }

    clearFilters(): void {
        this.filterForm.reset({
            q: '',
            statut: null,
            srvc_code: null,
            pst_code: null,
            pos_code: null
        }, { emitEvent: false });

        this.statutControl.setValue('', { emitEvent: false });
        this.serviceControl.setValue('', { emitEvent: false });
        this.posteControl.setValue('', { emitEvent: false });
        this.positionControl.setValue('', { emitEvent: false });

        this.loadEmployes();
    }

    onFilterChange(): void {
        const prevSrvcCode = this.filterForm.controls.srvc_code.value;
        const nextSrvcCode = this.resolveServiceCode();

        this.filterForm.controls.srvc_code.setValue(nextSrvcCode, { emitEvent: false });
        if (prevSrvcCode !== nextSrvcCode) {
            this.filterForm.controls.pst_code.setValue(null, { emitEvent: false });
            this.posteControl.setValue('', { emitEvent: false });
            if (nextSrvcCode) {
                this.loadPostes(nextSrvcCode);
            } else {
                this.loadPostes();
            }
        }

        this.filterForm.controls.pst_code.setValue(this.resolvePosteCode(), { emitEvent: false });
        this.filterForm.controls.pos_code.setValue(this.resolvePositionCode(), { emitEvent: false });
        this.filterForm.controls.statut.setValue(this.resolveStatutValue(), { emitEvent: false });

        console.log('[Employes] filtres résolus', {
            q: (this.filterForm.value.q || '').trim(),
            statut: this.filterForm.value.statut,
            srvc_code: this.filterForm.value.srvc_code,
            pst_code: this.filterForm.value.pst_code,
            pos_code: this.filterForm.value.pos_code,
        });

        this.loadEmployes();
    }

    private resolveServiceCode(): number | null {
        const v = this.serviceControl.value;
        if (!v) return null;
        if (typeof v !== 'string') {
            const code = Number((v as any)?.srvc_code);
            return Number.isFinite(code) ? code : null;
        }

        const q = this.normalizeText(v);
        if (!q) return null;
        const exact = this.services.find((s) => this.normalizeText(s.srvc_nom || '') === q);
        if (exact) return Number(exact.srvc_code);

        const partial = this.services.filter((s) => this.normalizeText(s.srvc_nom || '').includes(q));
        return partial.length === 1 ? Number(partial[0].srvc_code) : null;
    }

    private resolvePosteCode(): number | null {
        const v = this.posteControl.value;
        if (!v) return null;
        if (typeof v !== 'string') {
            const code = Number((v as any)?.pst_code);
            return Number.isFinite(code) ? code : null;
        }

        const q = this.normalizeText(v);
        if (!q) return null;
        const exact = this.postes.find((p) => this.normalizeText(p.pst_fonction || '') === q);
        if (exact) return Number(exact.pst_code);

        const partial = this.postes.filter((p) => this.normalizeText(p.pst_fonction || '').includes(q));
        return partial.length === 1 ? Number(partial[0].pst_code) : null;
    }

    private resolvePositionCode(): number | null {
        const v = this.positionControl.value;
        if (!v) return null;
        if (typeof v !== 'string') {
            const code = Number((v as any)?.pos_code);
            return Number.isFinite(code) ? code : null;
        }

        const q = this.normalizeText(v);
        if (!q) return null;
        const exact = this.positions.find((p) => this.normalizeText(p.pos_type || '') === q);
        if (exact) return Number(exact.pos_code);

        const partial = this.positions.filter((p) => this.normalizeText(p.pos_type || '').includes(q));
        return partial.length === 1 ? Number(partial[0].pos_code) : null;
    }

    private resolveStatutValue(): 'actif' | 'inactif' | null {
        const v = this.statutControl.value;
        if (!v) return null;
        if (typeof v !== 'string') return v.value;

        const q = this.normalizeText(v);
        if (!q) return null;
        const match = this.statuts.find((s) => this.normalizeText(s.label) === q || s.value === q);
        return match ? match.value : null;
    }

    private normalizeText(value: string): string {
        return (value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[’']/g, '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase();
    }

    private loadPositions(): void {
        this.employeService.getPositions().subscribe({
            next: (rows) => {
                this.positions = rows;
                this.positions$.next(rows);
            },
            error: () => {
                this.positions = [];
                this.positions$.next([]);
            }
        });
    }

    private loadServices(): void {
        this.serviceService.getServices().subscribe({
            next: (rows) => {
                this.services = rows;
                this.services$.next(rows);
            },
            error: () => {
                this.services = [];
                this.services$.next([]);
            }
        });
    }

    private loadPostes(srvcCode?: number): void {
        const filters: any = {};
        if (srvcCode) {
            filters.srvc_code = srvcCode;
        }

        this.posteService.list(filters).subscribe({
            next: (rows) => {
                this.postes = rows;
                this.postes$.next(rows);
            },
            error: () => {
                this.postes = [];
                this.postes$.next([]);
            }
        });
    }

    displayService = (service: Service | string): string => {
        return typeof service === 'string' ? service : (service?.srvc_nom || '');
    };

    displayPoste = (poste: Poste | string): string => {
        return typeof poste === 'string' ? poste : (poste?.pst_fonction || '');
    };

    displayPosition = (position: Position | string): string => {
        return typeof position === 'string' ? position : (position?.pos_type || '');
    };

    displayStatut = (statut: { label: string; value: 'actif' | 'inactif' } | string): string => {
        return typeof statut === 'string' ? statut : (statut?.label || '');
    };

    private filterServices(value: Service | string, services: Service[]): Service[] {
        const q = (typeof value === 'string' ? value : value?.srvc_nom || '').toLowerCase();
        return services.filter((s) => (s.srvc_nom || '').toLowerCase().includes(q));
    }

    private filterPostes(value: Poste | string, postes: Poste[]): Poste[] {
        const q = (typeof value === 'string' ? value : value?.pst_fonction || '').toLowerCase();
        return postes.filter((p) => (p.pst_fonction || '').toLowerCase().includes(q));
    }

    private filterPositions(value: Position | string, positions: Position[]): Position[] {
        const q = (typeof value === 'string' ? value : value?.pos_type || '').toLowerCase();
        return positions.filter((p) => (p.pos_type || '').toLowerCase().includes(q));
    }

    private filterStatuts(value: { label: string; value: 'actif' | 'inactif' } | string): { label: string; value: 'actif' | 'inactif' }[] {
        const q = (typeof value === 'string' ? value : value?.label || '').toLowerCase();
        return this.statuts.filter((s) => s.label.toLowerCase().includes(q));
    }

    loadStats(): void {
        this.employeService.getStats().subscribe({
            next: (data) => this.stats = data,
            error: (err) => console.error('Erreur stats:', err)
        });
    }

    loadEmployes(): void {
        this.loading = true;
        this.errorMessage = '';

        const q = (this.filterForm.value.q || '').trim();
        const pos_code = this.filterForm.value.pos_code || undefined;
        const srvc_code = this.filterForm.value.srvc_code || undefined;
        const pst_code = this.filterForm.value.pst_code || undefined;
        const statut = this.filterForm.value.statut || undefined;

        const filters = {
            q: q || undefined,
            pos_code: pos_code || undefined,
            srvc_code: srvc_code || undefined,
            pst_code: pst_code || undefined,
            statut: statut || undefined,
        };

        console.log('[Employes] requête /api/employes params', filters);

        this.employeService.list(filters).subscribe({
            next: (response: any) => {
                console.log('[Employes] réponse (count)', response?.length || response?.count);
                this.employes = Array.isArray(response) ? response : (response?.data || []);
                // Appliquer le tri si défini
                if (this.sortOrder) {
                    this.sortEmployesByName();
                }
                this.loading = false;
            },
            error: (err) => {
                this.loading = false;
                this.errorMessage = 'Erreur lors du chargement des employés';
                console.error('Erreur:', err);
            }
        });
    }

    toggleSortByName(event: Event): void {
        event.stopPropagation(); // Empêcher la propagation de l'événement

        if (this.sortOrder === null || this.sortOrder === 'desc') {
            this.sortOrder = 'asc';
        } else {
            this.sortOrder = 'desc';
        }
        this.sortEmployesByName();
    }

    private sortEmployesByName(): void {
        if (!this.sortOrder || this.employes.length === 0) return;

        // Créer une copie du tableau pour forcer la détection des changements
        const sorted = [...this.employes].sort((a, b) => {
            const nomA = (a.emp_nom || '').toLowerCase().trim();
            const nomB = (b.emp_nom || '').toLowerCase().trim();

            if (this.sortOrder === 'asc') {
                return nomA.localeCompare(nomB, 'fr', { sensitivity: 'base' });
            } else {
                return nomB.localeCompare(nomA, 'fr', { sensitivity: 'base' });
            }
        });

        this.employes = sorted;
        this.cdr.detectChanges(); // Forcer la détection des changements
    }

    onStatutSelected(event: MatAutocompleteSelectedEvent): void {
        const val = event.option.value;
        if (val && val.value) {
            this.filterForm.controls.statut.setValue(val.value);
        }
    }

    onServiceSelected(event: MatAutocompleteSelectedEvent): void {
        const val = event.option.value as Service;
        if (val && val.srvc_code) {
            this.filterForm.controls.srvc_code.setValue(val.srvc_code);
        }
    }

    onPosteSelected(event: MatAutocompleteSelectedEvent): void {
        const val = event.option.value as Poste;
        if (val && val.pst_code) {
            this.filterForm.controls.pst_code.setValue(val.pst_code);
        }
    }

    onPositionSelected(event: MatAutocompleteSelectedEvent): void {
        const val = event.option.value as Position;
        if (val && val.pos_code) {
            this.filterForm.controls.pos_code.setValue(val.pos_code);
        }
    }

    openModifierDialog(employe: EmployeWithAffectation): void {
        if (!employe.emp_code) return;

        const dialogRef = this.dialog.open(ModifierEmployeDialogComponent, {
            width: '700px',
            maxWidth: '90vw',
            data: {
                employe: employe
            },
            disableClose: true
        });

        dialogRef.afterClosed().subscribe((result) => {
            if (result === true) {
                // Recharger la liste des employés après la modification
                this.loadEmployes();
            }
        });
    }

    openCompetenceDialog(employe: EmployeWithAffectation): void {
        const dialogRef = this.dialog.open(CompetenceDialogComponent, {
            width: '600px',
            data: { employe },
            disableClose: true
        });

        dialogRef.afterClosed().subscribe(() => {
            this.loadEmployes();
        });
    }

    isEnCessation(employe: EmployeWithAffectation): boolean {
        // Vérifier si l'employé est en cessation (pos_code = 2 ou pos_type = "en cessation")
        // ET qu'il n'a pas encore de date de sortie
        if (!employe) return false;

        // Si l'employé a déjà une date de sortie, ne pas afficher le bouton
        if (employe.date_sortie) {
            return false;
        }

        // Vérifier pos_code (peut être number or undefined)
        if (employe.pos_code === 2 || employe.pos_code === Number('2')) {
            return true;
        }

        // Vérifier pos_type (peut être string or undefined)
        if (employe.pos_type && typeof employe.pos_type === 'string') {
            return employe.pos_type.toLowerCase().includes('cessation');
        }

        return false;
    }

    hasSortie(employe: EmployeWithAffectation): boolean {
        // Vérifier si l'employé a une date de sortie
        return !!(employe.date_sortie);
    }

    openFinirCarriereDialog(employe: EmployeWithAffectation): void {
        if (!employe.emp_code) return;

        const dialogRef = this.dialog.open(FinirCarriereDialogComponent, {
            width: '500px',
            data: {
                emp_code: employe.emp_code,
                emp_nom: employe.emp_nom,
                emp_prenom: employe.emp_prenom
            },
            disableClose: true
        });

        dialogRef.afterClosed().subscribe((result) => {
            if (result === true) {
                // Recharger la liste des employés après la finalisation
                this.loadEmployes();
            }
        });
    }

    isPosteSortie(employe: EmployeWithAffectation): boolean {
        // Vérifier si l'employé est en position "Sortie" (pos_code = 3)
        if (!employe) return false;
        // Vérifier pos_code (peut être number or undefined)
        if (employe.pos_code === 3 || employe.pos_code === Number('3')) {
            return true;
        }
        // Fallback sur le texte
        if (employe.pos_type && typeof employe.pos_type === 'string') {
            return employe.pos_type.toLowerCase().includes('sortie');
        }
        return false;
    }

    openReintegrationDialog(employe: EmployeWithAffectation): void {
        if (!employe.emp_code) return;

        const dialogRef = this.dialog.open(ReintegrationDialogComponent, {
            width: '600px',
            data: {
                emp_code: employe.emp_code,
                emp_nom: employe.emp_nom,
                emp_prenom: employe.emp_prenom
            },
            disableClose: true
        });

        dialogRef.afterClosed().subscribe((result) => {
            if (result === true) {
                // Recharger la liste des employés après la réintégration
                this.loadEmployes();
            }
        });
    }

    exportExcel(): void {
        if (this.exporting) return;

        this.exporting = true;
        const q = (this.filterForm.value.q || '').trim();
        const pos_code = this.filterForm.value.pos_code || undefined;
        const srvc_code = this.filterForm.value.srvc_code || undefined;
        const pst_code = this.filterForm.value.pst_code || undefined;
        const statut = this.filterForm.value.statut || undefined;

        const filters = {
            q: q || undefined,
            pos_code: pos_code || undefined,
            srvc_code: srvc_code || undefined,
            pst_code: pst_code || undefined,
            statut: statut || undefined,
        };

        this.employeService.exportXlsx(filters).subscribe({
            next: (blob) => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `BDD_Personnel_${new Date().toISOString().split('T')[0]}.xlsx`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                this.exporting = false;
            },
            error: (err) => {
                console.error('Erreur:', err);
                this.exporting = false;
                this.errorMessage = 'Erreur lors de l\'exportation Excel';
            }
        });
    }
}
