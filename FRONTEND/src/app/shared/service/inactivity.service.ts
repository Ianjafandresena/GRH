
import { Injectable, inject, NgZone } from '@angular/core';
import { AuthService } from '../../module/auth/service/auth-service';
import { fromEvent, merge, Subscription, timer } from 'rxjs';
import { switchMap, throttleTime } from 'rxjs/operators';

@Injectable({
    providedIn: 'root'
})
export class InactivityService {
    private readonly authService = inject(AuthService);
    private readonly ngZone = inject(NgZone);

    private inactivitySubscription?: Subscription;
    private readonly INACTIVITY_TIMEOUT = 45 * 60 * 1000; // 45 minutes en millisecondes

    /**
     * Démarrer la surveillance de l'inactivité
     */
    startMonitoring() {
        this.stopMonitoring();

        // On s'exécute en dehors de la zone Angular pour éviter de déclencher 
        // la détection de changement sur chaque mouvement de souris
        this.ngZone.runOutsideAngular(() => {
            const activity$ = merge(
                fromEvent(window, 'mousemove'),
                fromEvent(window, 'mousedown'),
                fromEvent(window, 'keypress'),
                fromEvent(window, 'touchstart'),
                fromEvent(window, 'scroll')
            ).pipe(
                throttleTime(5000) // On ne traite l'activité qu'une fois toutes les 5 secondes
            );

            this.inactivitySubscription = activity$.pipe(
                // À chaque activité, on redémarre le timer
                switchMap(() => timer(this.INACTIVITY_TIMEOUT))
            ).subscribe(() => {
                // Le timer a expiré sans nouvelle activité
                this.ngZone.run(() => {
                    console.warn(`🕒 Inactivité de ${this.INACTIVITY_TIMEOUT / 60000} minutes détectée. Déconnexion...`);
                    this.authService.logout();
                });
            });

            // Initialiser le timer au démarrage (si l'utilisateur ne bouge pas du tout au début)
            // On utilise un timer séparé car activity$ n'émet que sur événement
            const initialTimer = timer(this.INACTIVITY_TIMEOUT).subscribe(() => {
                this.ngZone.run(() => {
                    if (this.authService.isAuthenticated()) {
                        console.warn('🕒 Inactivité initiale détectée. Déconnexion...');
                        this.authService.logout();
                    }
                });
            });

            // Fusionner les abonnements pourrait être plus propre mais ceci est efficace
            this.inactivitySubscription.add(initialTimer);
        });
    }

    /**
     * Arrêter la surveillance
     */
    stopMonitoring() {
        if (this.inactivitySubscription) {
            this.inactivitySubscription.unsubscribe();
            this.inactivitySubscription = undefined;
        }
    }
}
