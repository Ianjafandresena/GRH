import { Component, OnInit, Inject, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { EmployeeService } from '../../service/employee.service';

@Component({
    selector: 'app-employee-detail',
    standalone: true,
    imports: [CommonModule, MatIconModule, MatDialogModule],
    templateUrl: './detail.html',
    styleUrls: ['./detail.scss']
})
export class EmployeeDetailComponent implements OnInit {
    private readonly employeeService = inject(EmployeeService);
    private readonly dialogRef = inject(MatDialogRef<EmployeeDetailComponent>);

    employee: any = null;
    loading = true;

    constructor(@Inject(MAT_DIALOG_DATA) public data: { emp_code: number }) { }

    ngOnInit() {
        this.loadEmployeeDetail();
    }

    loadEmployeeDetail() {
        this.loading = true;
        this.employeeService.getEmployeeDetail(this.data.emp_code).subscribe({
            next: (data) => {
                this.employee = data;
                this.loading = false;
            },
            error: () => {
                this.loading = false;
            }
        });
    }

    close() {
        this.dialogRef.close();
    }

    formatDate(dateStr: string): string {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('fr-FR');
    }

    getStatusClass(): string {
        return this.employee?.is_available ? 'status-available' : 'status-unavailable';
    }

    getStatusText(): string {
        if (!this.employee) return '';

        if (this.employee.is_available) {
            return 'Présent';
        }

        if (this.employee.absence_end) {
            const endDate = new Date(this.employee.absence_end);
            return `Non disponible (jusqu'au ${endDate.toLocaleDateString('fr-FR')})`;
        }

        return 'Non disponible';
    }
}
