import { Injectable, inject, signal, computed } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { interval, Subject, takeUntil } from 'rxjs';

export interface Notification {
    id: string;
    type: string;
    titre: string;
    message: string;
    date: string;
    icon: string;
    color: 'info' | 'warning' | 'default';
    link: string;
}

@Injectable({
    providedIn: 'root'
})
export class NotificationService {
    private readonly http = inject(HttpClient);
    private readonly baseUrl = `${environment.apiUrl}/notifications`;

    private destroy$ = new Subject<void>();

    // Signals pour les notifications
    notifications = signal<Notification[]>([]);
    count = signal<number>(0);
    isLoading = signal<boolean>(false);
    showDropdown = signal<boolean>(false);

    // Computed
    hasNotifications = computed(() => this.count() > 0);

    /**
     * Démarrer le polling des notifications (toutes les 30 secondes)
     */
    startPolling() {
        // Charger immédiatement
        this.loadNotifications();
        this.loadCount();

        // Puis toutes les 30 secondes
        interval(30000)
            .pipe(takeUntil(this.destroy$))
            .subscribe(() => {
                this.loadCount();
            });
    }

    /**
     * Arrêter le polling
     */
    stopPolling() {
        this.destroy$.next();
        this.destroy$.complete();
        this.destroy$ = new Subject<void>();
    }

    /**
     * Charger le nombre de notifications
     */
    loadCount() {
        this.http.get<{ count: number }>(`${this.baseUrl}/count`)
            .subscribe({
                next: (res) => {
                    this.count.set(res.count);
                },
                error: (err) => {
                    console.error('Erreur chargement count notifications:', err);
                }
            });
    }

    /**
     * Charger toutes les notifications
     */
    loadNotifications() {
        this.isLoading.set(true);

        this.http.get<{ notifications: Notification[], count: number }>(`${this.baseUrl}`)
            .subscribe({
                next: (res) => {
                    this.notifications.set(res.notifications);
                    this.count.set(res.count);
                    this.isLoading.set(false);
                },
                error: (err) => {
                    console.error('Erreur chargement notifications:', err);
                    this.isLoading.set(false);
                }
            });
    }

    /**
     * Toggle le dropdown
     */
    toggleDropdown() {
        const newState = !this.showDropdown();
        this.showDropdown.set(newState);

        if (newState) {
            this.loadNotifications();
        }
    }

    /**
     * Fermer le dropdown
     */
    closeDropdown() {
        this.showDropdown.set(false);
    }

    /**
     * Obtenir la classe CSS selon la couleur
     */
    getColorClass(color: string): string {
        switch (color) {
            case 'warning': return 'notif-warning';
            case 'info': return 'notif-info';
            default: return 'notif-default';
        }
    }
}
