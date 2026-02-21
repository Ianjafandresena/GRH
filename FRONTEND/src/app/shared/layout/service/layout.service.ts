
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

    private errorMessageSubject = new BehaviorSubject<string | null>(null);
    public errorMessage$ = this.errorMessageSubject.asObservable();

    constructor() { }

    setTitle(title: string) {
        this.titleSubject.next(title);
    }

    // ➕ NOUVEAU: Afficher message de succès
    showSuccessMessage(message: string) {
        this.successMessageSubject.next(message);
        setTimeout(() => this.successMessageSubject.next(null), 5000);
    }

    showErrorMessage(message: string) {
        this.errorMessageSubject.next(message);
        setTimeout(() => this.errorMessageSubject.next(null), 5000);
    }

    // Effacer manuellement
    clearMessages() {
        this.successMessageSubject.next(null);
        this.errorMessageSubject.next(null);
    }
}
