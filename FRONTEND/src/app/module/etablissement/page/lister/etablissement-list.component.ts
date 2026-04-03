import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute } from '@angular/router';
import { MatTableModule } from '@angular/material/table';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';

import { EtablissementService, Etablissement } from '../../service/etablissement.service';
import { EtablissementDialogComponent, EtablissementDialogData } from '../modal/etablissement-dialog.component';

@Component({
    selector: 'app-etablissement-list',
    standalone: true,
    imports: [
        CommonModule,
        MatTableModule,
        MatCardModule,
        MatIconModule,
        MatButtonModule,
        MatProgressSpinnerModule,
        MatDialogModule
    ],
    templateUrl: './etablissement-list.component.html',
    styleUrls: ['./etablissement-list.component.scss']
})
export class EtablissementListComponent implements OnInit {
    etablissements: Etablissement[] = [];
    loading = false;
    errorMessage = '';

    displayedColumns: string[] = ['etab_code', 'etab_nom', 'etab_adresse', 'actions'];

    private etablissementService = inject(EtablissementService);
    private dialog = inject(MatDialog);
    private route = inject(ActivatedRoute);

    ngOnInit(): void {
        // Utiliser les données préchargées par le resolver
        const resolvedData = this.route.snapshot.data;
        if (resolvedData['etablissements']) {
            this.etablissements = resolvedData['etablissements'];
        } else {
            // Fallback si le resolver n'a pas fonctionné
            this.load();
        }
    }

    load(): void {
        this.loading = true;
        this.errorMessage = '';

        this.etablissementService.getAll().subscribe({
            next: (data) => {
                this.etablissements = data;
                this.loading = false;
            },
            error: () => {
                this.loading = false;
                this.errorMessage = 'Erreur lors du chargement des établissements';
            }
        });
    }

    openCreate(): void {
        const dialogRef = this.dialog.open<EtablissementDialogComponent, EtablissementDialogData, Etablissement | null>(
            EtablissementDialogComponent,
            {
                width: '520px',
                data: { mode: 'create' }
            }
        );

        dialogRef.afterClosed().subscribe(result => {
            if (!result) return;
            this.etablissementService.create(result).subscribe({
                next: () => this.load(),
                error: () => this.errorMessage = 'Erreur lors de la création de l\'établissement'
            });
        });
    }

    openEdit(etab: Etablissement): void {
        const dialogRef = this.dialog.open<EtablissementDialogComponent, EtablissementDialogData, Etablissement | null>(
            EtablissementDialogComponent,
            {
                width: '520px',
                data: { mode: 'edit', etablissement: etab }
            }
        );

        dialogRef.afterClosed().subscribe(result => {
            if (!result || !etab.etab_code) return;
            this.etablissementService.update(etab.etab_code, result).subscribe({
                next: () => this.load(),
                error: () => this.errorMessage = 'Erreur lors de la modification de l\'établissement'
            });
        });
    }

    onDelete(etab: Etablissement): void {
        if (!etab.etab_code) return;
        const ok = window.confirm(`Supprimer l'établissement "${etab.etab_nom}" ?`);
        if (!ok) return;

        this.etablissementService.delete(etab.etab_code).subscribe({
            next: () => this.load(),
            error: () => this.errorMessage = 'Erreur lors de la suppression de l\'établissement'
        });
    }
}
