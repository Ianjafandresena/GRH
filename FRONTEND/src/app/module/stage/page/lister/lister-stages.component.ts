import { Component, OnInit } from '@angular/core';
import { trigger, transition, style, animate } from '@angular/animations';
import { CommonModule } from '@angular/common';
import { RouterModule, ActivatedRoute } from '@angular/router';
import { FormBuilder, FormGroup, ReactiveFormsModule, FormControl } from '@angular/forms';
import { Observable, forkJoin } from 'rxjs';
import { map, startWith } from 'rxjs/operators';
import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { StagiaireService } from '../../../stagiaire/service/stagiaire.service';
import { EmployeService } from '../../../employe/service/employe.service';
import { MatTableModule } from '@angular/material/table';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule } from '@angular/material/core';
import { MatTooltipModule } from '@angular/material/tooltip';
import { StageService, Stage, StageStats } from '../../service/stage.service';
import { StageEvalDialogComponent } from '../modal/stage-eval-dialog.component';
import { StageDetailDialogComponent } from '../modal/stage-detail-dialog.component';

@Component({
    selector: 'app-lister-stages',
    standalone: true,
    imports: [
        CommonModule,
        ReactiveFormsModule,
        RouterModule,
        MatTableModule,
        MatCardModule,
        MatIconModule,
        MatButtonModule,
        MatProgressSpinnerModule,
        MatDialogModule,
        MatFormFieldModule,
        MatInputModule,
        MatDatepickerModule,
        MatNativeDateModule,
        MatTooltipModule,
        MatAutocompleteModule
    ],
    templateUrl: './lister-stages.component.html',
    styleUrls: ['./lister-stages.component.scss'],
    animations: [
        trigger('fadeIn', [
            transition(':enter', [
                style({ opacity: 0, transform: 'translateY(10px)' }),
                animate('400ms ease-out', style({ opacity: 1, transform: 'translateY(0)' }))
            ])
        ])
    ]
})
export class ListerStagesComponent implements OnInit {
    stages: Stage[] = [];
    loading = false;
    stats: StageStats | null = null;
    errorMessage = '';
    displayedColumns: string[] = ['stagiaire', 'encadreur', 'theme', 'etablissement', 'date_debut', 'date_fin', 'duree', 'actions'];

    filterForm!: FormGroup;
    viewMode: 'table' | 'gantt' = 'table';

    // Gantt / Calendar logic
    currentDate: Date = new Date();
    ganttDays: any[] = [];
    ganttStages: any[] = [];
    monthNames = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

    // Autocompletes
    stagiaires: any[] = [];
    encadreurs: any[] = [];
    stagiaireCtrl = new FormControl<any>(null);
    encadreurCtrl = new FormControl<any>(null);
    filteredStagiaires$!: Observable<any[]>;
    filteredEncadreurs$!: Observable<any[]>;

    constructor(
        private stageService: StageService,
        private stagiaireService: StagiaireService,
        private employeService: EmployeService,
        private dialog: MatDialog,
        private fb: FormBuilder,
        private route: ActivatedRoute,
    ) { }

    ngOnInit(): void {
        this.filterForm = this.fb.group({
            stagiaire: [''],
            encadreur: [''],
            theme: [''],
            date_debut_from: [''],
            date_debut_to: [''],
        });

        this.loadInitialData();

        const resolvedData = this.route.snapshot.data;
        if (resolvedData['stages']) {
            const data = resolvedData['stages'];
            this.stages = data.stages || [];
            this.stats = data.stats || null;
        } else {
            this.loadStages();
            this.loadStats();
        }
    }

    loadInitialData(): void {
        if (this.stages && this.stages.length > 0) {
            this.extractEntitiesFromStages(this.stages);
        } else {
            this.stageService.getAll().subscribe(data => {
                this.stages = data;
                this.extractEntitiesFromStages(data);
            });
        }
    }

    private extractEntitiesFromStages(stages: any[]): void {
        const stgrMap = new Map();
        const encMap = new Map();

        stages.forEach(s => {
            if (s.stgr_code) {
                stgrMap.set(s.stgr_code, {
                    stgr_code: s.stgr_code,
                    stgr_nom: s.stgr_nom,
                    stgr_prenom: s.stgr_prenom,
                    stgr_nom_prenom: s.stgr_nom_prenom
                });
            }
            if (s.encadreur_emp_code) {
                encMap.set(s.encadreur_emp_code, {
                    emp_code: s.encadreur_emp_code,
                    emp_nom: s.encadreur_nom,
                    emp_prenom: s.encadreur_prenom
                });
            }
        });

        this.stagiaires = Array.from(stgrMap.values());
        this.encadreurs = Array.from(encMap.values());
        this.setupAutocompletes();
    }

    private setupAutocompletes(): void {
        this.filteredStagiaires$ = this.stagiaireCtrl.valueChanges.pipe(
            startWith(''),
            map(val => {
                const search = typeof val === 'string' ? val : '';
                return search ? this._filterStagiaires(search) : this.stagiaires.slice(0, 20);
            })
        );

        this.filteredEncadreurs$ = this.encadreurCtrl.valueChanges.pipe(
            startWith(''),
            map(val => {
                const search = typeof val === 'string' ? val : '';
                return search ? this._filterEncadreurs(search) : this.encadreurs.slice(0, 20);
            })
        );
    }

    private _filterStagiaires(val: string): any[] {
        const f = val.toLowerCase();
        return this.stagiaires.filter(s => (s.stgr_nom_prenom || `${s.stgr_nom} ${s.stgr_prenom}`).toLowerCase().includes(f));
    }

    private _filterEncadreurs(val: string): any[] {
        const f = val.toLowerCase();
        return this.encadreurs.filter(e => (`${e.emp_nom} ${e.emp_prenom}`).toLowerCase().includes(f) || (e.emp_im_armp || '').toLowerCase().includes(f));
    }

    displayStagiaire = (s: any) => s ? (s.stgr_nom_prenom || `${s.stgr_nom} ${s.stgr_prenom}`) : '';
    displayEncadreur = (e: any) => e ? `${e.emp_nom} ${e.emp_prenom}` : '';

    onStagiaireSelected(e: any): void {
        this.filterForm.patchValue({ stagiaire: e.option.value.stgr_code });
    }

    onEncadreurSelected(e: any): void {
        this.filterForm.patchValue({ encadreur: e.option.value.emp_code });
    }

    loadStages(): void {
        this.loading = true;
        this.errorMessage = '';

        const raw = this.filterForm.getRawValue();

        let stgrFilter = raw.stagiaire;
        if (typeof this.stagiaireCtrl.value === 'string' && this.stagiaireCtrl.value.trim() !== '') {
            stgrFilter = this.stagiaireCtrl.value;
        }

        let encFilter = raw.encadreur;
        if (typeof this.encadreurCtrl.value === 'string' && this.encadreurCtrl.value.trim() !== '') {
            encFilter = this.encadreurCtrl.value;
        }

        const filters: Record<string, any> = {
            stagiaire: stgrFilter,
            encadreur: encFilter,
            theme: raw.theme,
            date_debut_from: this.formatDate(raw.date_debut_from),
            date_debut_to: this.formatDate(raw.date_debut_to),
        };

        this.stageService.getAll(filters).subscribe({
            next: (data) => {
                this.stages = data;
                this.loading = false;
                if (this.viewMode === 'gantt') {
                    this.generateGantt();
                }
            },
            error: () => {
                this.loading = false;
                this.errorMessage = 'Erreur lors du chargement des stages';
            }
        });
    }

    toggleView(): void {
        this.viewMode = this.viewMode === 'table' ? 'gantt' : 'table';
        if (this.viewMode === 'gantt') {
            this.generateGantt();
        }
    }

    generateGantt(): void {
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();

        const firstDayOfMonth = new Date(year, month, 1);
        const lastDayOfMonth = new Date(year, month + 1, 0);
        const daysInMonth = lastDayOfMonth.getDate();

        this.ganttDays = [];
        for (let i = 1; i <= daysInMonth; i++) {
            this.ganttDays.push(i);
        }

        this.ganttStages = this.stages.filter(s => {
            const sStart = new Date(s.stg_date_debut);
            const sEnd = s.stg_date_fin ? new Date(s.stg_date_fin) : sStart;
            return sStart <= lastDayOfMonth && sEnd >= firstDayOfMonth;
        }).map(s => {
            const sStart = new Date(s.stg_date_debut);
            const sEnd = s.stg_date_fin ? new Date(s.stg_date_fin) : sStart;

            const startVisible = sStart < firstDayOfMonth ? firstDayOfMonth : sStart;
            const endVisible = sEnd > lastDayOfMonth ? lastDayOfMonth : sEnd;

            const startDay = startVisible.getDate();
            const endDay = endVisible.getDate();
            const duration = (endDay - startDay) + 1;

            const left = ((startDay - 1) / daysInMonth) * 100;
            const width = (duration / daysInMonth) * 100;

            // Determine status and colors
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            let color = '#00bcd4'; // Cyan (En cours)
            let shadow = 'rgba(0, 188, 212, 0.3)';

            if (sEnd < today) {
                color = '#22c55e'; // Green (Terminé)
                shadow = 'rgba(34, 197, 94, 0.3)';
            } else if (sStart > today) {
                color = '#f59e0b'; // Orange (À venir)
                shadow = 'rgba(245, 158, 11, 0.3)';
            }

            return {
                ...s,
                ganttStyle: {
                    'left': left + '%',
                    'width': width + '%',
                    'background': color,
                    'box-shadow': `0 4px 12px ${shadow}`
                },
                isStartCut: sStart < firstDayOfMonth,
                isEndCut: sEnd > lastDayOfMonth
            };
        });
    }

    prevMonth(): void {
        this.currentDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() - 1, 1);
        this.generateGantt();
    }

    nextMonth(): void {
        this.currentDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 1);
        this.generateGantt();
    }

    loadStats(): void {
        this.stageService.getStats().subscribe({
            next: (data) => this.stats = data,
            error: (err) => console.error('Erreur stats stages:', err)
        });
    }

    resetFilters(): void {
        this.filterForm.reset({
            stagiaire: '',
            encadreur: '',
            theme: '',
            date_debut_from: '',
            date_debut_to: '',
        });
        this.stagiaireCtrl.setValue(null);
        this.encadreurCtrl.setValue(null);
        this.loadStages();
    }

    private formatDate(date: any): string {
        if (!date) return '';
        if (date instanceof Date) {
            return date.toISOString().split('T')[0];
        }
        return date;
    }

    telechargerConvention(stage: Stage): void {
        if (!stage?.stg_code) return;

        this.stageService.telechargerConvention(stage.stg_code).subscribe({
            next: (res) => {
                if (res.pdf_base64) {
                    const blob = this.base64ToBlob(res.pdf_base64, 'application/pdf');
                    const url = window.URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = res.filename || 'convention_stage.pdf';
                    link.click();
                    window.URL.revokeObjectURL(url);
                }
            },
            error: (err) => {
                console.error('Erreur téléchargement convention:', err);
                alert('Erreur lors du téléchargement de la convention.');
            }
        });
    }

    private base64ToBlob(base64: string, type: string): Blob {
        const byteCharacters = atob(base64);
        const byteNumbers = new Array(byteCharacters.length);
        for (let i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
        }
        const byteArray = new Uint8Array(byteNumbers);
        return new Blob([byteArray], { type: type });
    }

    openEvaluation(stage: Stage): void {
        if (!stage?.stg_code) return;

        const dialogRef = this.dialog.open(StageEvalDialogComponent, {
            width: '560px',
            data: { stg_code: stage.stg_code, evstg_code: stage.evstg_code || null }
        });

        dialogRef.afterClosed().subscribe((ok: boolean) => {
            if (ok) {
                this.loadStages();
            }
        });
    }

    openDetails(stage: Stage): void {
        if (!stage?.stg_code) return;

        this.dialog.open(StageDetailDialogComponent, {
            width: '620px',
            data: { stage }
        });
    }
}
