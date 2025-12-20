import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, forkJoin } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';

@Injectable({
    providedIn: 'root'
})
export class DashboardService {
    private readonly http = inject(HttpClient);
    private readonly apiUrl = environment.apiUrl;

    getDashboardStats(): Observable<any> {
        const conges$ = this.http.get<any[]>(`${this.apiUrl}/conge`, { withCredentials: true });
        const employees$ = this.http.get<any[]>(`${this.apiUrl}/employee`, { withCredentials: true });

        return forkJoin([conges$, employees$]).pipe(
            map(([conges, employees]) => {
                return this.processStats(conges, employees);
            })
        );
    }

    private processStats(conges: any[], employees: any[]): any {
        const now = new Date();
        const currentMonth = now.getMonth();
        const currentYear = now.getFullYear();
        const lastMonth = currentMonth === 0 ? 11 : currentMonth - 1;
        const lastMonthYear = currentMonth === 0 ? currentYear - 1 : currentYear;

        // --- CONGES STATS ---
        // Filter only validated leaves (cng_status = true)
        const validatedConges = conges.filter(c => c.cng_status === true || c.cng_status === 't');

        // 1. Congés en cours (Active leaves)
        const activeLeaves = validatedConges.filter(c => {
            if (!c.cng_debut || !c.cng_fin) return false;
            const start = new Date(c.cng_debut);
            const end = new Date(c.cng_fin);
            return now >= start && now <= end;
        }).length;

        // 2. Congés stats for evolution
        let currentMonthLeaves = 0;
        let lastMonthLeaves = 0;

        validatedConges.forEach(c => {
            if (c.cng_debut) {
                const date = new Date(c.cng_debut);
                if (date.getMonth() === currentMonth && date.getFullYear() === currentYear) {
                    currentMonthLeaves++;
                } else if (date.getMonth() === lastMonth && date.getFullYear() === lastMonthYear) {
                    lastMonthLeaves++;
                }
            }
        });

        let congesEvolution = 0;
        if (lastMonthLeaves > 0) {
            congesEvolution = ((currentMonthLeaves - lastMonthLeaves) / lastMonthLeaves) * 100;
        } else if (currentMonthLeaves > 0) {
            congesEvolution = 100;
        }

        // --- EMPLOYEES STATS ---
        const totalEmployees = employees.length;

        // Active employees (assuming is_actif is 1 or true)
        const activeEmployees = employees.filter(e => e.is_actif == 1 || e.is_actif === true || e.is_actif === '1').length;

        // Employee evolution (based on date_embauche)
        let currentMonthHires = 0;
        let lastMonthHires = 0;

        employees.forEach(e => {
            if (e.date_embauche) {
                const date = new Date(e.date_embauche);
                if (date.getMonth() === currentMonth && date.getFullYear() === currentYear) {
                    currentMonthHires++;
                } else if (date.getMonth() === lastMonth && date.getFullYear() === lastMonthYear) {
                    lastMonthHires++;
                }
            }
        });

        let employeesEvolution = 0;
        if (lastMonthHires > 0) {
            employeesEvolution = ((currentMonthHires - lastMonthHires) / lastMonthHires) * 100;
        } else if (currentMonthHires > 0) {
            employeesEvolution = 100;
        }

        return {
            congesEnCours: activeLeaves,
            congesEvolution: Math.round(congesEvolution),
            totalEmployees,
            activeEmployees,
            employeesEvolution: Math.round(employeesEvolution)
        };
    }

    getEvolutionStats(): Observable<any> {
        // Utilisation des APIs existantes
        const conges$ = this.http.get<any[]>(`${this.apiUrl}/conge`, { withCredentials: true });
        const permissions$ = this.http.get<any[]>(`${this.apiUrl}/permission`, { withCredentials: true });

        return forkJoin([conges$, permissions$]).pipe(
            map(([conges, permissions]) => {
                return this.processData(conges, permissions);
            })
        );
    }

    private processData(conges: any[], permissions: any[]): any {
        const currentYear = new Date().getFullYear();
        const months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
        const congeCounts = new Array(12).fill(0);
        const permissionCounts = new Array(12).fill(0);

        // Traitement des Congés (only validated ones - cng_status = true)
        if (Array.isArray(conges)) {
            conges.forEach(c => {
                // Only count validated leaves
                if ((c.cng_status === true || c.cng_status === 't') && c.cng_debut) {
                    const date = new Date(c.cng_debut);
                    if (date.getFullYear() === currentYear) {
                        congeCounts[date.getMonth()]++;
                    }
                }
            });
        }

        // Traitement des Permissions
        if (Array.isArray(permissions)) {
            permissions.forEach(p => {
                if (p.prm_debut) {
                    const date = new Date(p.prm_debut);
                    if (date.getFullYear() === currentYear) {
                        permissionCounts[date.getMonth()]++;
                    }
                }
            });
        }

        return {
            labels: months,
            conges: congeCounts,
            permissions: permissionCounts
        };
    }
}
