import { Component, Inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatTableModule } from '@angular/material/table';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { EmployeService } from '../../../employe/service/employe.service';
import { EmployeWithAffectation } from '../../../employe/model/employe.model';
import { finalize } from 'rxjs/operators';

export interface EmployeesByPosteDialogData {
    pst_code: number;
    pst_fonction: string;
}

@Component({
    selector: 'app-employees-by-poste-dialog',
    standalone: true,
    imports: [
        CommonModule,
        RouterModule,
        MatDialogModule,
        MatButtonModule,
        MatIconModule,
        MatTableModule,
        MatProgressSpinnerModule
    ],
    template: `
        <h2 mat-dialog-title>
            <mat-icon>people</mat-icon>
            Agents affectés : {{ data.pst_fonction }}
        </h2>
        <mat-dialog-content>
            <div *ngIf="loading" class="loading-container">
                <mat-spinner diameter="40"></mat-spinner>
                <p>Chargement des agents...</p>
            </div>

            <div *ngIf="!loading && employees.length === 0" class="no-data">
                <mat-icon>info_outline</mat-icon>
                <p>Aucun agent n'est actuellement affecté à ce poste.</p>
            </div>

            <table mat-table [dataSource]="employees" *ngIf="!loading && employees.length > 0">
                <ng-container matColumnDef="emp_im_armp">
                    <th mat-header-cell *matHeaderCellDef>IM ARMP</th>
                    <td mat-cell *matCellDef="let emp" class="code-highlight">{{ emp.emp_im_armp }}</td>
                </ng-container>

                <ng-container matColumnDef="nom_complet">
                    <th mat-header-cell *matHeaderCellDef>Nom & Prénom</th>
                    <td mat-cell *matCellDef="let emp" class="font-bold">{{ emp.emp_nom }} {{ emp.emp_prenom }}</td>
                </ng-container>

                <ng-container matColumnDef="actions">
                    <th mat-header-cell *matHeaderCellDef></th>
                    <td mat-cell *matCellDef="let emp" align="end">
                        <button mat-icon-button color="primary" [routerLink]="['/employes', emp.emp_code]" (click)="close()">
                            <mat-icon>visibility</mat-icon>
                        </button>
                    </td>
                </ng-container>

                <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
                <tr mat-row *matRowDef="let row; columns: displayedColumns;"></tr>
            </table>
        </mat-dialog-content>
        <mat-dialog-actions align="end">
            <button mat-button mat-dialog-close>Fermer</button>
        </mat-dialog-actions>
    `,
    styles: [`
        h2 {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #1e293b;
            font-weight: 700;
        }
        mat-dialog-content {
            min-height: 200px;
            max-height: 60vh;
            min-width: 500px;
        }
        .loading-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            gap: 1rem;
            color: #64748b;
        }
        .no-data {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            gap: 0.5rem;
            color: #64748b;
            text-align: center;
            mat-icon { font-size: 40px; width: 40px; height: 40px; }
        }
        table {
            width: 100%;
        }
        .code-highlight {
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.85rem;
            color: #475569;
        }
        .font-bold {
            font-weight: 600;
            color: #1e293b;
        }
    `]
})
export class EmployeesByPosteDialogComponent implements OnInit {
    employees: EmployeWithAffectation[] = [];
    loading = true;
    displayedColumns: string[] = ['emp_im_armp', 'nom_complet', 'actions'];

    constructor(
        private dialogRef: MatDialogRef<EmployeesByPosteDialogComponent>,
        @Inject(MAT_DIALOG_DATA) public data: EmployeesByPosteDialogData,
        private employeService: EmployeService
    ) { }

    ngOnInit(): void {
        this.loadEmployees();
    }

    private loadEmployees(): void {
        this.loading = true;
        this.employeService.list({ pst_code: this.data.pst_code }).pipe(
            finalize(() => this.loading = false)
        ).subscribe({
            next: (data) => this.employees = data,
            error: (err) => console.error('Erreur chargement agents du poste:', err)
        });
    }

    close(): void {
        this.dialogRef.close();
    }
}
