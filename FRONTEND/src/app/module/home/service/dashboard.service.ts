import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, forkJoin, of } from 'rxjs';
import { map, catchError } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';

@Injectable({
    providedIn: 'root'
})
export class DashboardService {
    private readonly http = inject(HttpClient);
    private readonly apiUrl = environment.apiUrl;

    getDashboardStats(startDate?: string, endDate?: string): Observable<any> {
        const params: any = {};
        if (startDate) {
            params.start_date = startDate;
            params.start = startDate;
        }
        if (endDate) {
            params.end_date = endDate;
            params.end = endDate;
        }

        const conges$ = this.http.get<any[]>(`${this.apiUrl}/conge`, { params, withCredentials: true });
        const employees$ = this.http.get<any[]>(`${this.apiUrl}/employee`, { params, withCredentials: true });

        return forkJoin([conges$, employees$]).pipe(
            map(([conges, employees]) => {
                return this.processStats(conges, employees, startDate, endDate);
            })
        );
    }

    private processStats(conges: any[], employees: any[], startDate?: string, endDate?: string): any {
        const now = new Date();
        const currentMonth = now.getMonth();
        const currentYear = now.getFullYear();
        const lastMonth = currentMonth === 0 ? 11 : currentMonth - 1;
        const lastMonthYear = currentMonth === 0 ? currentYear - 1 : currentYear;

        // --- CONGES STATS ---
        const validatedConges = conges.filter(c => c.cng_status === true || c.cng_status === 't');

        const activeLeaves = validatedConges.filter(c => {
            if (!c.cng_debut || !c.cng_fin) return false;
            const start = new Date(c.cng_debut);
            const end = new Date(c.cng_fin);
            return now >= start && now <= end;
        }).length;

        let currentPeriodLeaves = 0;
        let comparisonPeriodLeaves = 0;

        if (startDate && endDate) {
            // If custom period is provided, we just count leaves in that period
            currentPeriodLeaves = validatedConges.length;
            comparisonPeriodLeaves = 0;
        } else {
            validatedConges.forEach(c => {
                if (c.cng_debut) {
                    const date = new Date(c.cng_debut);
                    if (date.getMonth() === currentMonth && date.getFullYear() === currentYear) {
                        currentPeriodLeaves++;
                    } else if (date.getMonth() === lastMonth && date.getFullYear() === lastMonthYear) {
                        comparisonPeriodLeaves++;
                    }
                }
            });
        }

        let congesEvolution = 0;
        if (comparisonPeriodLeaves > 0) {
            congesEvolution = ((currentPeriodLeaves - comparisonPeriodLeaves) / comparisonPeriodLeaves) * 100;
        } else if (currentPeriodLeaves > 0 && !startDate) {
            congesEvolution = 100;
        }

        // --- EMPLOYEES STATS ---
        const totalEmployees = employees.length;
        const activeEmployees = employees.filter(e => e.is_actif == 1 || e.is_actif === true || e.is_actif === '1').length;

        return {
            congesEnCours: activeLeaves,
            congesEvolution: Math.round(congesEvolution),
            totalEmployees,
            activeEmployees,
            employeesEvolution: 0 // Simplified for consistency
        };
    }

    getEvolutionStats(startDate?: string, endDate?: string): Observable<any> {
        const params: any = {};
        if (startDate) {
            params.start_date = startDate;
            params.start = startDate;
        }
        if (endDate) {
            params.end_date = endDate;
            params.end = endDate;
        }

        const conges$ = this.http.get<any[]>(`${this.apiUrl}/conge`, { params, withCredentials: true }).pipe(catchError(() => of([])));
        const permissions$ = this.http.get<any[]>(`${this.apiUrl}/permission`, { params, withCredentials: true }).pipe(catchError(() => of([])));

        return forkJoin([conges$, permissions$]).pipe(
            map(([conges, permissions]) => {
                return this.processData(conges, permissions, startDate, endDate);
            })
        );
    }

    private processData(conges: any[], permissions: any[], startDate?: string, endDate?: string): any {
        const start = startDate ? new Date(startDate) : new Date(new Date().getFullYear(), 0, 1);
        const end = endDate ? new Date(endDate) : new Date(new Date().getFullYear(), 11, 31);

        // Ensure start is beginning of month and end is end of day
        start.setDate(1);
        start.setHours(0, 0, 0, 0);
        end.setHours(23, 59, 59, 999);

        const monthNames = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
        const labels: string[] = [];
        const congeCounts: number[] = [];
        const permissionCounts: number[] = [];

        // Generate chronological buckets between start and end
        const temp = new Date(start);
        const buckets: { month: number, year: number, key: string }[] = [];

        // Limit to 24 months to avoid infinite loops or memory issues
        let maxBuckets = 24;
        while (temp <= end && maxBuckets > 0) {
            const m = temp.getMonth();
            const y = temp.getFullYear();
            const yearShort = y.toString().slice(-2);

            buckets.push({
                month: m,
                year: y,
                key: `${y}-${m}`
            });
            labels.push(`${monthNames[m]} '${yearShort}`);

            congeCounts.push(0);
            permissionCounts.push(0);

            temp.setMonth(temp.getMonth() + 1);
            maxBuckets--;
        }

        // Map data to buckets
        const safeConges = Array.isArray(conges) ? conges : [];
        const safePermissions = Array.isArray(permissions) ? permissions : [];

        safeConges.forEach(c => {
            if ((c.cng_status === true || c.cng_status === 't') && c.cng_debut) {
                const date = new Date(c.cng_debut);
                const idx = buckets.findIndex(b => b.month === date.getMonth() && b.year === date.getFullYear());
                if (idx !== -1) {
                    congeCounts[idx]++;
                }
            }
        });

        safePermissions.forEach(p => {
            const isValidated = p.prm_status === true || p.prm_status === 't' || p.prm_status === 1 || p.prm_status === '1';
            if (isValidated && p.prm_debut) {
                const date = new Date(p.prm_debut);
                const idx = buckets.findIndex(b => b.month === date.getMonth() && b.year === date.getFullYear());
                if (idx !== -1) {
                    permissionCounts[idx]++;
                }
            }
        });

        // Fallback if no buckets generated (should not happen with default year)
        if (labels.length === 0) {
            return {
                labels: monthNames,
                conges: new Array(12).fill(0),
                permissions: new Array(12).fill(0)
            };
        }

        return {
            labels,
            conges: congeCounts,
            permissions: permissionCounts
        };
    }

    getEmployeesOnLeave(): Observable<any[]> {
        return this.http.get<any[]>(`${this.apiUrl}/dashboard/employees-on-leave`, { withCredentials: true });
    }

    getPendingReimbursements(startDate?: string, endDate?: string): Observable<any> {
        const params: any = {};
        if (startDate) params.start_date = startDate;
        if (endDate) params.end_date = endDate;
        return this.http.get<any>(`${this.apiUrl}/dashboard/pending-reimbursements`, { params, withCredentials: true });
    }

    getRecentActivity(startDate?: string, endDate?: string): Observable<any[]> {
        const params: any = {};
        if (startDate) params.start_date = startDate;
        if (endDate) params.end_date = endDate;
        return this.http.get<any[]>(`${this.apiUrl}/dashboard/recent-activity`, { params, withCredentials: true });
    }

    getReimbursementDistribution(startDate?: string, endDate?: string): Observable<any> {
        const params: any = {};
        if (startDate) params.start_date = startDate;
        if (endDate) params.end_date = endDate;
        return this.http.get<any>(`${this.apiUrl}/dashboard/reimbursement-distribution`, { params, withCredentials: true });
    }

    getTopAbsent(startDate?: string, endDate?: string): Observable<any[]> {
        const params: any = {};
        if (startDate) params.start_date = startDate;
        if (endDate) params.end_date = endDate;
        return this.http.get<any[]>(`${this.apiUrl}/dashboard/top-absent`, { params, withCredentials: true });
    }

    getTopReimbursements(startDate?: string, endDate?: string): Observable<any[]> {
        const params: any = {};
        if (startDate) params.start_date = startDate;
        if (endDate) params.end_date = endDate;
        return this.http.get<any[]>(`${this.apiUrl}/dashboard/top-reimbursements`, { params, withCredentials: true });
    }

    getDashboardCarriereStats(filters?: any): Observable<any> {
        return this.http.get<any>(`${this.apiUrl}/dashboard-carriere`, { params: filters, withCredentials: true });
    }
}
