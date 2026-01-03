
import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';

@Injectable({
    providedIn: 'root'
})
export class LayoutService {
    private titleSubject = new BehaviorSubject<string>('Tableau de Bord');
    public title$ = this.titleSubject.asObservable();

    // ➕ NOUVEAU: Système de notification
    private successMessageSubject = new BehaviorSubject<string | null>(null);
    public successMessage$ = this.successMessageSubject.asObservable();

    constructor() { }

    setTitle(title: string) {
        this.titleSubject.next(title);
    }

    // ➕ NOUVEAU: Afficher message de succès
    showSuccessMessage(message: string) {
        this.successMessageSubject.next(message);
        // Auto-clear après 5 secondes
        setTimeout(() => {
            this.successMessageSubject.next(null);
        }, 5000);
    }

    // Effacer manuellement
    clearSuccessMessage() {
        this.successMessageSubject.next(null);
    }
}
