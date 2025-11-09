import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { BehaviorSubject, Observable, throwError } from 'rxjs';
import { tap, catchError } from 'rxjs/operators';
import { Router } from '@angular/router';
import { environment } from '../../../../environments/environment';
import { Admin, AuthResponse, LoginCredentials } from '../model/auth-model';

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);
  
  private readonly apiUrl = environment.apiUrl;
  private readonly ADMIN_KEY = 'admin_data';  // On garde juste les données admin

  // BehaviorSubject pour suivre l'état de connexion
  private currentAdminSubject = new BehaviorSubject<Admin | null>(this.getStoredAdmin());
  public currentAdmin$ = this.currentAdminSubject.asObservable();

  /**
   * Connexion de l'admin
   * Le token est maintenant dans un cookie HttpOnly
   */
  login(credentials: LoginCredentials): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(`${this.apiUrl}/auth/login`, credentials, {
      withCredentials: true  //  IMPORTANT : Permet d'envoyer/recevoir les cookies
    }).pipe(
      tap(response => {
        //  Plus besoin de stocker le token (il est dans le cookie)
        // On stocke juste les données de l'admin
        localStorage.setItem(this.ADMIN_KEY, JSON.stringify(response.admin));
        
        // Mettre à jour le BehaviorSubject
        this.currentAdminSubject.next(response.admin);
        
        console.log('✅ Connexion réussie, cookie reçu');
      }),
      catchError(this.handleError)
    );
  }

  /**
   * Déconnexion de l'admin
   */
  logout(): void {
    // Appeler l'endpoint logout pour supprimer le cookie côté serveur
    this.http.post(`${this.apiUrl}/auth/logout`, {}, {
      withCredentials: true
    }).subscribe({
      next: () => {
        // Nettoyer le stockage local
        localStorage.removeItem(this.ADMIN_KEY);
        
        // Réinitialiser le BehaviorSubject
        this.currentAdminSubject.next(null);
        
        console.log('✅ Déconnexion réussie, cookie supprimé');
        
        // Rediriger vers la page de connexion
        this.router.navigate(['']);
      },
      error: (error) => {
        console.error('Erreur lors de la déconnexion', error);
        // Même en cas d'erreur, on déconnecte côté client
        localStorage.removeItem(this.ADMIN_KEY);
        this.currentAdminSubject.next(null);
        this.router.navigate(['']);
      }
    });
  }

  /**
   *  SUPPRIMÉ : Plus besoin de getToken() car le cookie est géré automatiquement
   */

  /**
   * Récupérer les données admin stockées
   */
  private getStoredAdmin(): Admin | null {
    const adminData = localStorage.getItem(this.ADMIN_KEY);
    return adminData ? JSON.parse(adminData) : null;
  }

  /**
   * Obtenir l'admin actuellement connecté
   */
  get currentAdminValue(): Admin | null {
    return this.currentAdminSubject.value;
  }

  /**
   * Vérifier si l'utilisateur est connecté
   * ⭐ On vérifie juste si les données admin existent
   */
  isAuthenticated(): boolean {
    return this.getStoredAdmin() !== null;
  }

  /**
   * Gestion des erreurs HTTP
   */
  private handleError(error: HttpErrorResponse): Observable<never> {
    let errorMessage = 'Une erreur est survenue';
    
    if (error.error instanceof ErrorEvent) {
      errorMessage = `Erreur: ${error.error.message}`;
    } else {
      errorMessage = error.error?.messages?.error || 
                     error.error?.message || 
                     `Erreur ${error.status}: ${error.statusText}`;
    }
    
    console.error('❌ Erreur HTTP:', errorMessage);
    return throwError(() => new Error(errorMessage));
  }
}
