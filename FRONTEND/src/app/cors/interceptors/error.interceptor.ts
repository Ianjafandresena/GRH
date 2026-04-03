import { HttpInterceptorFn, HttpErrorResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, throwError } from 'rxjs';
import { AuthService } from '../../module/auth/service/auth-service';

/**
 * Intercepteur pour gérer les erreurs HTTP globalement, 
 * notamment la 401 (Unauthorized) pour rediriger vers le login.
 */
export const errorInterceptor: HttpInterceptorFn = (req, next) => {
    const authService = inject(AuthService);

    return next(req).pipe(
        catchError((error: HttpErrorResponse) => {
            // Si on reçoit une 401, c'est que le token est expiré ou invalide
            if (error.status === 401) {
                console.warn('⚠️ Session expirée ou non autorisée (401), déconnexion...');
                authService.logout();
            }

            return throwError(() => error);
        })
    );
};
