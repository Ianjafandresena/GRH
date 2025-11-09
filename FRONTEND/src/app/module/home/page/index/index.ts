import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { AuthService } from '../../../auth/service/auth-service';
import { Admin } from '../../../auth/model/auth-model';

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './index.html',
  styleUrls: ['./index.css']
})
export class HomeComponent implements OnInit {
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);

  admin: Admin | null = null;

  ngOnInit() {
    // ⭐ S'abonner aux changements de l'utilisateur connecté
    this.authService.currentAdmin$.subscribe(admin => {
      this.admin = admin;
      console.log('👤 Admin connecté:', admin);
    });
  }

  logout() {
    if (confirm('Voulez-vous vraiment vous déconnecter ?')) {
      this.authService.logout();
    }
  }
}
