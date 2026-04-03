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

    private readonly STORAGE_KEY = 'gprh_notifications_history';

    // Computed
    hasNotifications = computed(() => this.count() > 0);

    private getHistory(): Record<string, { firstSeen: number, deleted: boolean }> {
        const stored = localStorage.getItem(this.STORAGE_KEY);
        return stored ? JSON.parse(stored) : {};
    }

    private saveHistory(history: Record<string, { firstSeen: number, deleted: boolean }>) {
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(history));
    }

    /**
     * Démarrer le polling des notifications (toutes les 30 secondes)
     */
    startPolling() {
        this.loadNotifications();
        this.loadCount();

        interval(30000)
            .pipe(takeUntil(this.destroy$))
            .subscribe(() => {
                this.loadNotifications(); // Reload all to filter properly
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
     * Charger le nombre de notifications (après filtrage local)
     */
    loadCount() {
        this.loadNotifications(); // We rely on loadNotifications to refresh the count after local filtering
    }

    /**
     * Charger toutes les notifications
     */
    loadNotifications() {
        if (this.showDropdown() === false && this.notifications().length > 0) {
            // If dropdown is closed, just update count from backend for the bell badge
            // But to be accurate with deletions, we better load and filter
        }

        this.isLoading.set(true);
        this.http.get<{ notifications: Notification[], count: number }>(`${this.baseUrl}`)
            .subscribe({
                next: (res) => {
                    const history = this.getHistory();
                    const now = Date.now();
                    const fiveDaysInMs = 5 * 24 * 60 * 60 * 1000;
                    let hasChanges = false;

                    const filtered = res.notifications.filter(notif => {
                        const h = history[notif.id];

                        // New notification
                        if (!h) {
                            history[notif.id] = { firstSeen: now, deleted: false };
                            hasChanges = true;
                            return true;
                        }

                        // Already deleted manually
                        if (h.deleted) return false;

                        // Auto-delete after 5 days
                        if (now - h.firstSeen > fiveDaysInMs) {
                            h.deleted = true;
                            hasChanges = true;
                            return false;
                        }

                        return true;
                    });

                    if (hasChanges) {
                        this.saveHistory(history);
                    }

                    this.notifications.set(filtered);
                    this.count.set(filtered.length);
                    this.isLoading.set(false);
                },
                error: (err) => {
                    console.error('Erreur chargement notifications:', err);
                    this.isLoading.set(false);
                }
            });
    }

    /**
     * Supprimer manuellement une notification
     */
    removeNotification(id: string) {
        const history = this.getHistory();
        if (history[id]) {
            history[id].deleted = true;
        } else {
            history[id] = { firstSeen: Date.now(), deleted: true };
        }
        this.saveHistory(history);

        // Update state locally for immediate feedback
        const current = this.notifications();
        const updated = current.filter(n => n.id !== id);
        this.notifications.set(updated);
        this.count.set(updated.length);
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
