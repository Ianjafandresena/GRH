import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { MatDividerModule } from '@angular/material/divider';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule, MAT_DATE_LOCALE } from '@angular/material/core';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { EmployeeService } from '../../service/employee.service';

@Component({
    selector: 'app-family-mgmt',
    standalone: true,
    imports: [
        CommonModule,
        FormsModule,
        ReactiveFormsModule,
        MatIconModule,
        MatButtonModule,
        MatFormFieldModule,
        MatInputModule,
        MatSelectModule,
        MatSnackBarModule,
        MatProgressSpinnerModule,
        MatDividerModule,
        MatTooltipModule,
        MatDatepickerModule,
        MatNativeDateModule,
        RouterModule
    ],
    providers: [
        { provide: MAT_DATE_LOCALE, useValue: 'fr-FR' }
    ],
    templateUrl: './family-mgmt.html',
    styleUrls: ['./family-mgmt.scss']
})
export class FamilyMgmtComponent implements OnInit {
    private readonly route = inject(ActivatedRoute);
    public readonly router = inject(Router);
    private readonly fb = inject(FormBuilder);
    private readonly snackBar = inject(MatSnackBar);
    private readonly employeeService = inject(EmployeeService);

    empCode!: number;
    employee: any = null;
    spouses: any[] = [];
    children: any[] = [];
    loading = true;

    spouseForm: FormGroup = this.fb.group({
        conj_nom: ['', Validators.required],
        conj_prenom: ['']
    });

    childForm: FormGroup = this.fb.group({
        enf_nom: ['', Validators.required],
        enf_num: [''],
        date_naissance: ['']
    });

    ngOnInit() {
        const id = this.route.snapshot.paramMap.get('id');
        if (id) {
            this.empCode = +id;
            this.loadData();
        }
    }

    loadData() {
        this.loading = true;
        const employee$ = this.employeeService.getEmployeeDetail(this.empCode);
        const spouses$ = this.employeeService.getSpouses(this.empCode);
        const children$ = this.employeeService.getChildren(this.empCode);

        import('rxjs').then(({ forkJoin }) => {
            forkJoin({
                employee: employee$,
                spouses: spouses$,
                children: children$
            }).subscribe({
                next: (res) => {
                    this.employee = res.employee;
                    this.spouses = res.spouses;
                    this.children = res.children;
                    this.loading = false;
                },
                error: () => {
                    this.loading = false;
                    this.snackBar.open('Erreur de chargement', 'OK');
                }
            });
        });
    }

    get activeSpouse() {
        return this.spouses.find(s => s.cjs_id === 1);
    }

    addSpouse() {
        if (this.spouseForm.invalid) return;
        if (this.activeSpouse) {
            this.snackBar.open('L\'employé a déjà un(e) conjoint(e) marié(e).', 'OK', { duration: 3000 });
            return;
        }

        this.employeeService.addSpouse(this.empCode, this.spouseForm.value).subscribe({
            next: () => {
                this.snackBar.open('Conjoint(e) ajouté(e)', 'OK', { duration: 2000 });
                this.spouseForm.reset();
                this.loadData();
            },
            error: (err) => this.snackBar.open(err.error?.message || 'Erreur', 'OK')
        });
    }

    updateSpouseStatus(spouse: any, statusType: 'DIVORCÉ' | 'DÉCÉDÉ') {
        const statusId = statusType === 'DIVORCÉ' ? 2 : 3;
        this.employeeService.updateSpouseStatus(spouse.conj_code, statusId).subscribe({
            next: () => {
                this.snackBar.open('Statut mis à jour', 'OK', { duration: 2000 });
                this.loadData();
            }
        });
    }

    addChild() {
        if (this.childForm.invalid) return;
        this.employeeService.addChild(this.empCode, this.childForm.value).subscribe({
            next: () => {
                this.snackBar.open('Enfant ajouté', 'OK', { duration: 2000 });
                this.childForm.reset();
                this.loadData();
            }
        });
    }

    removeChild(child: any) {
        if (confirm(`Supprimer l'enfant ${child.enf_nom} ?`)) {
            this.employeeService.removeChild(child.enf_code).subscribe({
                next: () => {
                    this.snackBar.open('Enfant supprimé', 'OK', { duration: 2000 });
                    this.loadData();
                }
            });
        }
    }

    goBack() {
        this.router.navigate(['/employee', this.empCode]);
    }

    getGenderIcon() {
        if (!this.employee) return 'person';
        // Sexe du conjoint est l'opposé de l'employé
        return (this.employee.emp_sexe === true || this.employee.emp_sexe === 't' || this.employee.emp_sexe === 1)
            ? 'female' : 'male';
    }
}
