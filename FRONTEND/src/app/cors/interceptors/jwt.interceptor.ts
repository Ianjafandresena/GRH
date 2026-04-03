import { HttpInterceptorFn } from '@angular/common/http';

export const jwtInterceptor: HttpInterceptorFn = (req, next) => {
 // Le cookie est automatiquement envoyé par le navigateur
  
  // On s'assure juste que les cookies sont envoyés
  const clonedRequest = req.clone({
    withCredentials: true  // Envoie automatiquement les cookies
  });

  console.log('Requête avec cookies:', clonedRequest.url);

  return next(clonedRequest);
};
