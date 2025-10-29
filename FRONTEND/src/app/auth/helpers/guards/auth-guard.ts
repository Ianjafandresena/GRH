import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { CanActivateFn } from '@angular/router';
import { AuthService } from '../../service/auth-service';

export const authGuard: CanActivateFn = (route, state) => {
  const authService = inject(AuthService);
  const router = inject(Router);

  //  Vérifier si l'utilisateur est authentifié
  if (authService.isAuthenticated()) {
    console.log('✅ Utilisateur authentifié, accès autorisé');
    return true;  // Autoriser l'accès
  }

  console.log('❌ Utilisateur non authentifié, redirection vers /login');
  router.navigate(['/login'], {
    queryParams: { returnUrl: state.url } 
  });
  
  return false; 
};
