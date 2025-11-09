import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { AuthService } from '../../service/auth-service';
import { LoginCredentials } from '../../model/auth-model';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './login.html',
  styleUrls: ['./login.css']
})
export class LoginComponent {
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  
  credentials: LoginCredentials = {
    username: '',
    password: ''
  };

  errorMessage = '';
  isLoading = false;

  onSubmit() {
    this.errorMessage = '';
    this.isLoading = true;

    this.authService.login(this.credentials).subscribe({
      next: (response) => {
        console.log('✅ Connexion réussie:', response.admin);
        
        // Récupérer l'URL de retour ou aller au dashboard
        const returnUrl = this.route.snapshot.queryParams['returnUrl'] || '/dashboard';
        this.router.navigate([returnUrl]);
      },
      error: (error) => {
        console.error('❌ Erreur de connexion:', error);
        this.errorMessage = error.message || 'Email ou mot de passe incorrect';
        this.isLoading = false;
      },
      complete: () => {
        this.isLoading = false;
      }
    });
  }
}
