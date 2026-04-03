import { Component, computed, inject, signal, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule, ActivatedRoute } from '@angular/router';
import { CongeService } from '../../service/conge.service';
import { EmployeeService } from '../../../employee/service/employee.service';
import { Subject, Subscription } from 'rxjs';
import { debounceTime, distinctUntilChanged } from 'rxjs/operators';

interface CalendarDay {
    date: Date;
    isCurrentMonth: boolean;
    day: number;
}

interface CongeCalendar {
    cng_code: number;
    cng_debut: string;
    cng_fin: string;
    cng_nb_jour: number;
    cng_status: boolean | null;
    emp_code?: number;
    emp_nom?: string;
    emp_prenom?: string;
    nom_emp?: string;  // Alias API
    prenom_emp?: string; // Alias API
    typ_code?: string;
    typ_appelation?: string;
    typ_couleur?: string;
    dir_nom?: string;
    dir_code?: string;
    nom_region?: string;
    tp_cng_nom?: string;
    tp_cng_couleur?: string;
}

@Component({
    selector: 'app-conge-calendar',
    standalone: true,
    imports: [CommonModule, FormsModule, RouterModule],
    templateUrl: './calendar.component.html',
    styleUrls: ['./calendar.component.scss']
})
export class CongeCalendarComponent implements OnDestroy {
    private congeService = inject(CongeService);
    private employeeService = inject(EmployeeService);
    private router = inject(Router);
    private route = inject(ActivatedRoute);

    // Signals
    currentMonth = signal(new Date());
    conges = signal<CongeCalendar[]>([]);
    allConges = signal<CongeCalendar[]>([]);
    loading = signal(false);

    // Filtres
    filterEmployee = '';
    filterType = '';
    selectedMonth = new Date().getMonth();
    selectedYear = new Date().getFullYear();

    // Employee dropdown
    employees: any[] = [];
    filteredEmployees: any[] = [];
    showEmployeeDropdown = false;
    selectedEmployeeCode: number | null = null;

    typesConge: any[] = [];
    months = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
        'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    years: number[] = [];

    private filterSubject = new Subject<void>();
    private filterSubscription?: Subscription;

    // Computed
    monthName = computed(() => this.months[this.currentMonth().getMonth()]);
    year = computed(() => this.currentMonth().getFullYear());
    today = computed(() => this.formatDate(new Date()));

    calendarDays = computed(() => {
        const current = this.currentMonth();
        const year = current.getFullYear();
        const month = current.getMonth();

        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);

        let startingDayOfWeek = firstDay.getDay();
        if (startingDayOfWeek === 0) startingDayOfWeek = 7;

        const days: CalendarDay[] = [];

        const prevMonthLastDay = new Date(year, month, 0).getDate();
        for (let i = startingDayOfWeek - 2; i >= 0; i--) {
            days.push({
                date: new Date(year, month - 1, prevMonthLastDay - i),
                isCurrentMonth: false,
                day: prevMonthLastDay - i
            });
        }

        for (let day = 1; day <= lastDay.getDate(); day++) {
            days.push({
                date: new Date(year, month, day),
                isCurrentMonth: true,
                day
            });
        }

        const remainingDays = 42 - days.length;
        for (let day = 1; day <= remainingDays; day++) {
            days.push({
                date: new Date(year, month + 1, day),
                isCurrentMonth: false,
                day
            });
        }

        return days;
    });

    weekDays = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

    ngOnInit() {
        this.initializeYears();
        this.loadEmployees();
        this.loadTypesConge();
        // Plus besoin de loadPermissions séparé si on gère bien les types

        this.filterSubscription = this.filterSubject.pipe(
            debounceTime(400),
            distinctUntilChanged()
        ).subscribe(() => {
            this.applyFilters();
        });

        // Utiliser les données du resolver pour le chargement initial
        const initialData = this.route.snapshot.data['initialConges'] as any;
        if (initialData) {
            this.allConges.set(initialData as CongeCalendar[]);
            this.applyFilters();
        } else {
            this.loadConges();
        }
    }

    ngOnDestroy() {
        this.filterSubscription?.unsubscribe();
    }

    onFilterChange() {
        this.filterSubject.next();
    }

    initializeYears() {
        const currentYear = new Date().getFullYear();
        for (let i = currentYear - 5; i <= currentYear + 5; i++) {
            this.years.push(i);
        }
    }

    loadTypesConge() {
        this.congeService.getTypesConge().subscribe({
            next: (data: any) => {
                this.typesConge = data;
            },
            error: (err: any) => console.error('Erreur types congés:', err)
        });
    }

    loadPermissions() {
        this.congeService.getConges({}).subscribe({
            next: (data: any) => {
                // Extraire les types de permission uniques
                const permTypes = data
                    .filter((c: any) => c.typ_ref && c.typ_ref.startsWith('PERM'))
                    .map((c: any) => ({
                        typ_code: c.typ_code,
                        typ_appelation: c.typ_appelation
                    }));

                // Dédupliquer
                const uniquePerms = permTypes.filter((perm: any, index: number, self: any[]) =>
                    index === self.findIndex((p: any) => p.typ_code === perm.typ_code)
                );

                this.typesConge = [...this.typesConge, ...uniquePerms];
                console.log('[CALENDAR] Types chargés:', this.typesConge.length, 'dont', uniquePerms.length, 'permissions');
            },
            error: (err: any) => console.error('Erreur permissions:', err)
        });
    }

    loadEmployees() {
        this.employeeService.getEmployees().subscribe({
            next: (data) => {
                this.employees = data;
                this.filteredEmployees = data;
            },
            error: (err) => console.error('Erreur employés:', err)
        });
    }

    onEmployeeFocus() {
        this.showEmployeeDropdown = true;
        if (!this.filterEmployee) {
            this.filteredEmployees = this.employees;
        }
    }

    onEmployeeBlur() {
        setTimeout(() => { this.showEmployeeDropdown = false; }, 200);
    }

    onEmployeeInput() {
        const search = this.filterEmployee.toLowerCase();
        this.filteredEmployees = this.employees.filter(e =>
            e.emp_nom?.toLowerCase().includes(search) ||
            e.emp_prenom?.toLowerCase().includes(search) ||
            e.emp_im_armp?.includes(search)
        );
        this.showEmployeeDropdown = true;
        this.onFilterChange();
    }

    selectEmployee(emp: any) {
        this.filterEmployee = `${emp.emp_prenom} ${emp.emp_nom}`;
        this.selectedEmployeeCode = emp.emp_code;
        this.showEmployeeDropdown = false;
        this.onFilterChange();
    }

    onMonthChange() {
        this.currentMonth.set(new Date(this.selectedYear, this.selectedMonth, 1));
        this.loadConges();
    }

    onYearChange() {
        this.currentMonth.set(new Date(this.selectedYear, this.selectedMonth, 1));
        this.loadConges();
    }

    applyFilters() {
        let filtered = this.allConges();

        if (this.selectedEmployeeCode) {
            filtered = filtered.filter(c => c.emp_code == this.selectedEmployeeCode);
        } else if (this.filterEmployee) {
            const search = this.filterEmployee.toLowerCase();
            filtered = filtered.filter(c =>
                c.emp_nom?.toLowerCase().includes(search) ||
                c.emp_prenom?.toLowerCase().includes(search)
            );
        }

        if (this.filterType) {
            filtered = filtered.filter(c => c.typ_code == this.filterType);
        }

        this.conges.set(filtered);
    }

    resetFilters() {
        this.filterEmployee = '';
        this.filterType = '';
        this.selectedEmployeeCode = null;
        const now = new Date();
        this.selectedMonth = now.getMonth();
        this.selectedYear = now.getFullYear();
        this.currentMonth.set(now);
        this.loadConges();
    }

    loadConges() {
        this.loading.set(true);
        const current = this.currentMonth();
        const year = current.getFullYear();
        const month = current.getMonth();

        // On prend une marge pour voir les jours des mois adjacents dans le calendrier (vue 42 jours)
        const startDate = new Date(year, month - 1, 20); // 20 du mois précédent
        const endDate = new Date(year, month + 1, 10);  // 10 du mois suivant

        const params = {
            start: this.formatDate(startDate),
            end: this.formatDate(endDate)
        };

        this.congeService.getConges(params).subscribe({
            next: (data: any[]) => {
                // On garde tous les congés retournés par le serveur (déjà filtrés par date)
                this.allConges.set(data as CongeCalendar[]);

                // Si des types "permission" existent, on les ajoute à la liste des types si pas déjà présents
                const permTypes = data
                    .filter(c => c.typ_ref && c.typ_ref.startsWith('PERM'))
                    .map(c => ({ typ_code: c.typ_code, typ_appelation: c.typ_appelation }));

                if (permTypes.length > 0) {
                    const existingCodes = this.typesConge.map(t => t.typ_code);
                    const newTypes = permTypes.filter((p, index, self) =>
                        !existingCodes.includes(p.typ_code) &&
                        index === self.findIndex(t => t.typ_code === p.typ_code)
                    );
                    if (newTypes.length > 0) {
                        this.typesConge = [...this.typesConge, ...newTypes];
                    }
                }

                this.applyFilters();
                this.loading.set(false);
            },
            error: (err) => {
                console.error('Erreur:', err);
                this.loading.set(false);
            }
        });
    }

    previousMonth() {
        const current = this.currentMonth();
        const newMonth = new Date(current.getFullYear(), current.getMonth() - 1, 1);
        this.currentMonth.set(newMonth);
        this.selectedMonth = newMonth.getMonth();
        this.selectedYear = newMonth.getFullYear();
        this.loadConges();
    }

    nextMonth() {
        const current = this.currentMonth();
        const newMonth = new Date(current.getFullYear(), current.getMonth() + 1, 1);
        this.currentMonth.set(newMonth);
        this.selectedMonth = newMonth.getMonth();
        this.selectedYear = newMonth.getFullYear();
        this.loadConges();
    }

    goToday() {
        const now = new Date();
        this.currentMonth.set(now);
        this.selectedMonth = now.getMonth();
        this.selectedYear = now.getFullYear();
        this.loadConges();
    }

    getCongeColor(conge: CongeCalendar): string {
        const now = new Date();
        now.setHours(0, 0, 0, 0);

        // On convertit les chaînes YYYY-MM-DD en Date à midi pour éviter les problèmes de timezone
        const debut = new Date(conge.cng_debut + 'T12:00:00');
        const fin = new Date(conge.cng_fin + 'T12:00:00');

        if (fin < now) {
            return '#10b981'; // Vert (déjà fini)
        } else if (debut <= now && fin >= now) {
            return '#3b82f6'; // Bleu (en cours)
        } else {
            return '#f59e0b'; // Jaune (futur)
        }
    }

    // Map des congés par date (indexé pour performance)
    congesByDate = computed(() => {
        const rawConges = this.conges();
        const map = new Map<string, CongeCalendar[]>();

        rawConges.forEach(conge => {
            const start = new Date(conge.cng_debut);
            const end = new Date(conge.cng_fin);

            // Parcourir chaque jour du congé pour l'ajouter à la map
            let current = new Date(start);
            while (current <= end) {
                const dateStr = this.formatDate(current);
                if (!map.has(dateStr)) map.set(dateStr, []);
                map.get(dateStr)!.push(conge);
                current.setDate(current.getDate() + 1);
            }
        });
        return map;
    });

    getCongesForDay(day: Date): CongeCalendar[] {
        const dayStr = this.formatDate(day);
        return this.congesByDate().get(dayStr) || [];
    }

    formatDate(date: Date): string {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    navigateToDetail(cngCode: number) {
        this.router.navigate(['/conge/detail', cngCode]);
    }

    getTooltip(conge: CongeCalendar): string {
        const typeName = conge.tp_cng_nom || conge.typ_appelation || 'Congé';
        const prenom = conge.prenom_emp || conge.emp_prenom || '';
        const nom = conge.nom_emp || conge.emp_nom || '';
        return `${prenom} ${nom}\n${typeName}\n${conge.cng_debut} → ${conge.cng_fin}\n${conge.cng_nb_jour} jour(s)`;
    }

    switchToTableView() {
        this.router.navigate(['/conge']);
    }
}
