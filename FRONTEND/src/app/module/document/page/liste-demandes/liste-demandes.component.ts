import { Component, OnDestroy, OnInit } from '@angular/core';
import { trigger, transition, style, animate } from '@angular/animations';
import { CommonModule } from '@angular/common';
import { RouterModule, ActivatedRoute } from '@angular/router';
import { MatTableModule } from '@angular/material/table';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDividerModule } from '@angular/material/divider';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { PdfPreviewDialogComponent } from '../../component/pdf-preview/pdf-preview-dialog.component';
import { Subject, takeUntil, Observable } from 'rxjs';
import { DocumentService, DocumentStats } from '../../service/document.service';
import { DocumentDemande } from '../../model/document.model';

type DocumentDemandeRow = DocumentDemande & {
    doc_emp_code?: number;
    tdoc_matricule?: string;
    emp_matricule?: string;
    emp_nom?: string;
    emp_prenom?: string;
    tdoc_nom?: string;
};

@Component({
    selector: 'app-liste-demandes',
    standalone: true,
    imports: [
        CommonModule,
        RouterModule,
        MatTableModule,
        MatCardModule,
        MatIconModule,
        MatButtonModule,
        MatProgressSpinnerModule,
        MatDividerModule,
        MatDialogModule
    ],
    templateUrl: './liste-demandes.component.html',
    styleUrls: ['./liste-demandes.component.scss'],
    animations: [
        trigger('fadeIn', [
            transition(':enter', [
                style({ opacity: 0, transform: 'translateY(10px)' }),
                animate('400ms ease-out', style({ opacity: 1, transform: 'translateY(0)' }))
            ])
        ])
    ]
})
export class ListeDemandesComponent implements OnInit, OnDestroy {
    demandes: any[] = [];
    loading = false;
    stats: DocumentStats | null = null;
    errorMessage = '';
    mode: 'employe' | 'stage' = 'employe';

    displayedColumns: string[] = ['employe', 'type_document', 'date_demande', 'statut', 'actions'];

    private destroy$ = new Subject<void>();

    constructor(
        private documentService: DocumentService,
        private route: ActivatedRoute,
        private dialog: MatDialog
    ) { }

    ngOnInit(): void {
        const resolvedData = this.route.snapshot.data['data'];
        if (resolvedData) {
            this.demandes = resolvedData.demandes || [];
            this.stats = resolvedData.stats || null;
        } else {
            this.loadDemandes();
            this.loadStats();
        }
    }

    canValidate(row: any): boolean {
        if (this.mode === 'employe') {
            return !!row?.doc_emp_code && row?.statut !== 'traite';
        } else {
            return !!row?.doc_stg_code && row?.doc_stage_statut === 'en attente';
        }
    }

    canDownload(row: any): boolean {
        if (this.mode === 'employe') {
            return !!row?.doc_emp_code && row?.statut === 'traite';
        }
        return !!row?.doc_stg_code && row?.doc_stage_statut === 'valider';
    }

    setMode(newMode: 'employe' | 'stage'): void {
        if (this.mode === newMode) return;
        this.mode = newMode;
        if (this.mode === 'employe') {
            this.displayedColumns = ['employe', 'type_document', 'date_demande', 'statut', 'actions'];
            this.loadDemandes();
            this.loadStats();
        } else {
            this.displayedColumns = ['stagiaire', 'type_document', 'date_demande', 'statut', 'actions'];
            this.loadDemandesStage();
            this.loadStats();
        }
    }

    valider(row: any): void {
        if (this.mode === 'employe') {
            const id = Number(row?.doc_emp_code);
            if (!Number.isFinite(id)) return;

            this.documentService.validerDemande(id).pipe(
                takeUntil(this.destroy$)
            ).subscribe({
                next: () => {
                    row.statut = 'traite';
                    this.loadStats();
                },
                error: (err: any) => {
                    console.error('Erreur validation:', err);
                    this.errorMessage = err?.error?.message || err?.error?.messages?.error || 'Erreur lors de la validation';
                }
            });
        } else {
            const id = Number(row?.doc_stg_code);
            if (!Number.isFinite(id)) return;

            this.documentService.validerDemandeStage(id).pipe(
                takeUntil(this.destroy$)
            ).subscribe({
                next: () => {
                    row.doc_stage_statut = 'valider';
                    this.loadDemandesStage(); // Recharger pour enlever de la liste "en attente" si nécessaire
                },
                error: (err: any) => {
                    console.error('Erreur validation stage:', err);
                    this.errorMessage = err?.error?.message || 'Erreur lors de la validation du document de stage';
                }
            });
        }
    }

    telecharger(row: any): void {
        if (this.mode === 'employe') {
            const id = Number(row?.doc_emp_code);
            if (!Number.isFinite(id)) return;

            this.documentService.telechargerPdfDemande(id).pipe(
                takeUntil(this.destroy$)
            ).subscribe({
                next: (res: any) => {
                    const pdf = res?.pdf_base64;
                    const filename = res?.filename || 'document.pdf';
                    if (pdf) {
                        this.dialog.open(PdfPreviewDialogComponent, {
                            width: '800px',
                            maxWidth: '95vw',
                            data: {
                                pdfBase64: pdf,
                                filename: filename,
                                title: row.tdoc_nom || 'Aperçu du Document'
                            }
                        });
                    } else {
                        this.errorMessage = 'PDF introuvable dans la réponse';
                    }
                },
                error: (err: any) => {
                    console.error('Erreur téléchargement employe:', err);
                    this.errorMessage = err?.error?.message || 'Erreur lors du téléchargement';
                }
            });
        } else {
            const stgId = Number(row?.stg_code);
            if (!stgId) return;

            const tdocNom = (row.tdoc_nom || '').toLowerCase();
            let obs: Observable<any>;

            if (tdocNom.includes('convention')) {
                obs = this.documentService.telechargerConvention(stgId);
            } else {
                obs = this.documentService.telechargerDemandeAttestation(stgId);
            }

            obs.pipe(takeUntil(this.destroy$)).subscribe({
                next: (res: any) => {
                    if (res?.pdf_base64) {
                        this.dialog.open(PdfPreviewDialogComponent, {
                            width: '800px',
                            maxWidth: '95vw',
                            data: {
                                pdfBase64: res.pdf_base64,
                                filename: res.filename || 'document_stage.pdf',
                                title: row.tdoc_nom || 'Convention de Stage'
                            }
                        });
                    }
                },
                error: (err: any) => {
                    console.error('Erreur téléchargement stage:', err);
                    this.errorMessage = 'Erreur lors du téléchargement du document de stage';
                }
            });
        }
    }

    ngOnDestroy(): void {
        this.destroy$.next();
        this.destroy$.complete();
    }

    loadDemandes(): void {
        this.loading = true;
        this.errorMessage = '';

        this.documentService.listerDemandes().pipe(
            takeUntil(this.destroy$)
        ).subscribe({
            next: (rows: any[]) => {
                this.demandes = rows;
                this.loading = false;
            },
            error: (err: any) => {
                console.error('Erreur lors du chargement des demandes:', err);
                this.errorMessage = 'Erreur lors du chargement des demandes';
                this.loading = false;
            }
        });
    }

    loadDemandesStage(): void {
        this.loading = true;
        this.errorMessage = '';

        this.documentService.listerDemandesStage().pipe(
            takeUntil(this.destroy$)
        ).subscribe({
            next: (rows: any[]) => {
                this.demandes = rows;
                this.loading = false;
            },
            error: (err: any) => {
                console.error('Erreur lors du chargement des demandes stage:', err);
                this.errorMessage = 'Erreur lors du chargement des demandes de stage';
                this.loading = false;
            }
        });
    }

    loadStats(): void {
        const obs = this.mode === 'employe' ?
            this.documentService.getStats() :
            this.documentService.getStatsStage();

        obs.subscribe({
            next: (data: any) => this.stats = data,
            error: (err: any) => console.error('Erreur stats docs:', err)
        });
    }

    formatEmploye(row: any): string {
        const nom = (row.emp_nom || '').trim();
        const prenom = (row.emp_prenom || '').trim();
        const matricule = (row.emp_matricule || '').trim();

        const fullName = `${nom} ${prenom}`.trim();
        if (fullName && matricule) return `${fullName} (${matricule})`;
        if (fullName) return fullName;
        if (matricule) return matricule;
        return row.emp_code ? `Employé #${row.emp_code}` : 'N/A';
    }

    formatStagiaire(row: any): string {
        const nom = (row.stgr_nom || '').trim();
        const prenom = (row.stgr_prenom || '').trim();
        return `${nom} ${prenom}`.trim() || 'N/A';
    }

    formatTypeDocument(row: DocumentDemandeRow): string {
        const nom = (row.tdoc_nom || '').trim();
        if (nom) return nom;
        return row.tdoc_code ? `Type #${row.tdoc_code}` : 'N/A';
    }

    formatStatut(statut?: string): string {
        if (!statut) return 'N/A';
        const s = statut.toLowerCase();
        if (s === 'en_attente' || s === 'en attente') return 'En attente';
        if (s === 'traite' || s === 'valider') return 'Traité / Validé';
        if (s === 'refuse') return 'Refusé';
        return statut;
    }
}
