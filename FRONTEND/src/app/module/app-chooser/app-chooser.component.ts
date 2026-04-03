import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { MatIconModule } from '@angular/material/icon';
import { AuthService } from '../auth/service/auth-service';

@Component({
  selector: 'app-chooser',
  standalone: true,
  imports: [CommonModule, MatIconModule],
  template: `
    <div class="chooser-page">
      <!-- Background decorations -->
      <div class="bg-decoration bg-circle-1"></div>
      <div class="bg-decoration bg-circle-2"></div>
      <div class="bg-decoration bg-circle-3"></div>

      <div class="chooser-container">
        <!-- Header -->
        <div class="chooser-header">
          <div class="logo-area">
            <img src="assets/logo.png" alt="Logo ARMP" class="logo-img" />
          </div>
          <h1>Bienvenue sur la Plateforme</h1>
          <p class="subtitle">Choisissez l'application à laquelle vous souhaitez accéder</p>
          <div class="user-badge">
            <mat-icon>account_circle</mat-icon>
            <span>Connecté en tant que <strong>{{ username }}</strong></span>
          </div>
        </div>

        <!-- Cards -->
        <div class="cards-grid">
          <!-- Card GRH -->
          <div class="app-card" (click)="goTo('grh')" tabindex="0" (keyup.enter)="goTo('grh')">
            <div class="card-header-area">
              <div class="card-icon grh-icon">
                <mat-icon>assignment</mat-icon>
              </div>
              <div class="card-arrow">
                <mat-icon>arrow_forward</mat-icon>
              </div>
            </div>
            <h2>GRH</h2>
            <p class="card-desc">Gestion des Ressources Humaines</p>
            <div class="modules-section">
              <span class="modules-label">MODULES INCLUS :</span>
              <div class="modules-grid">
                <div class="module-item"><span class="dot dot-blue"></span>Congés</div>
                <div class="module-item"><span class="dot dot-cyan"></span>Permissions</div>
                <div class="module-item"><span class="dot dot-green"></span>Remboursements</div>
                <div class="module-item"><span class="dot dot-purple"></span>Prises en Charge</div>
              </div>
            </div>
            <div class="card-action">
              <span>Accéder à GRH</span>
              <mat-icon>arrow_forward</mat-icon>
            </div>
          </div>

          <!-- Card Carrière & Stagiaire -->
          <div class="app-card" (click)="goTo('carriere')" tabindex="0" (keyup.enter)="goTo('carriere')">
            <div class="card-header-area">
              <div class="card-icon carriere-icon">
                <mat-icon>trending_up</mat-icon>
              </div>
              <div class="card-arrow">
                <mat-icon>arrow_forward</mat-icon>
              </div>
            </div>
            <h2>Carrière & Stagiaire</h2>
            <p class="card-desc">Gestion de Carrière et des Stages</p>
            <div class="modules-section">
              <span class="modules-label">MODULES INCLUS :</span>
              <div class="modules-grid">
                <div class="module-item"><span class="dot dot-orange"></span>Employés</div>
                <div class="module-item"><span class="dot dot-blue"></span>Postes</div>
                <div class="module-item"><span class="dot dot-green"></span>Affectations</div>
                <div class="module-item"><span class="dot dot-purple"></span>Stages</div>
                <div class="module-item"><span class="dot dot-cyan"></span>Compétences</div>
                <div class="module-item"><span class="dot dot-red"></span>Documents</div>
              </div>
            </div>
            <div class="card-action carriere-action">
              <span>Accéder à Carrière</span>
              <mat-icon>arrow_forward</mat-icon>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="chooser-footer">
          <span>© 2025 ARMP — Plateforme Unifiée</span>
          <a class="logout-link" (click)="logout()">
            <mat-icon>logout</mat-icon>
            Déconnexion
          </a>
        </div>
      </div>
    </div>
  `,
  styles: [`
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

    :host {
      display: block;
      min-height: 100vh;
    }

    .chooser-page {
      min-height: 100vh;
      background: #f0f4f8;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      position: relative;
      overflow: hidden;
    }

    /* Background decorations */
    .bg-decoration {
      position: absolute;
      border-radius: 50%;
      filter: blur(80px);
      opacity: 0.15;
      pointer-events: none;
    }
    .bg-circle-1 {
      width: 600px; height: 600px;
      background: #3B82F6;
      top: -200px; left: -100px;
    }
    .bg-circle-2 {
      width: 500px; height: 500px;
      background: #10B981;
      bottom: -150px; right: -100px;
    }
    .bg-circle-3 {
      width: 300px; height: 300px;
      background: #F59E0B;
      top: 50%; left: 50%;
      transform: translate(-50%, -50%);
    }

    .chooser-container {
      position: relative;
      z-index: 1;
      max-width: 900px;
      width: 100%;
    }

    .chooser-header {
      text-align: center;
      margin-bottom: 2.5rem;
    }

    .logo-area {
      margin-bottom: 1rem;
    }
    .logo-img {
      height: 56px;
      filter: drop-shadow(0 2px 8px rgba(0,0,0,0.1));
    }

    h1 {
      font-size: 2rem;
      font-weight: 800;
      color: #0F2942;
      margin: 0 0 0.5rem 0;
      letter-spacing: -0.5px;
    }

    .subtitle {
      color: #64748b;
      font-size: 1.05rem;
      margin: 0 0 1rem 0;
      font-weight: 500;
    }

    .user-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: white;
      padding: 0.5rem 1.25rem;
      border-radius: 100px;
      font-size: 0.85rem;
      color: #475569;
      box-shadow: 0 1px 3px rgba(0,0,0,0.08);
      border: 1px solid #e2e8f0;
    }
    .user-badge mat-icon {
      font-size: 18px;
      width: 18px; height: 18px;
      color: #3B82F6;
    }
    .user-badge strong {
      color: #1e293b;
    }

    /* Cards Grid */
    .cards-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
    }
    @media (max-width: 700px) {
      .cards-grid { grid-template-columns: 1fr; }
    }

    .app-card {
      background: white;
      border-radius: 20px;
      padding: 2rem;
      cursor: pointer;
      border: 2px solid transparent;
      box-shadow: 0 4px 24px rgba(0,0,0,0.06);
      transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
      display: flex;
      flex-direction: column;
      position: relative;
      overflow: hidden;
    }
    .app-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 4px;
      background: linear-gradient(90deg, transparent, transparent);
      transition: background 0.35s;
    }
    .app-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 12px 40px rgba(0,0,0,0.12);
      border-color: #e2e8f0;
    }
    .app-card:first-child:hover {
      border-color: #3B82F6;
    }
    .app-card:first-child:hover::before {
      background: linear-gradient(90deg, #3B82F6, #06B6D4);
    }
    .app-card:last-child:hover {
      border-color: #10B981;
    }
    .app-card:last-child:hover::before {
      background: linear-gradient(90deg, #10B981, #059669);
    }

    .card-header-area {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 1.25rem;
    }

    .card-icon {
      width: 56px; height: 56px;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .card-icon mat-icon {
      font-size: 28px;
      width: 28px; height: 28px;
    }
    .grh-icon {
      background: #EFF6FF;
      color: #3B82F6;
    }
    .carriere-icon {
      background: #ECFDF5;
      color: #10B981;
    }

    .card-arrow {
      width: 36px; height: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f8fafc;
      transition: all 0.3s;
    }
    .card-arrow mat-icon {
      font-size: 18px;
      width: 18px; height: 18px;
      color: #94a3b8;
      transition: all 0.3s;
    }
    .app-card:hover .card-arrow {
      background: #0F2942;
    }
    .app-card:hover .card-arrow mat-icon {
      color: white;
    }

    .app-card h2 {
      font-size: 1.35rem;
      font-weight: 800;
      color: #0F2942;
      margin: 0 0 0.25rem 0;
    }
    .card-desc {
      font-size: 0.9rem;
      color: #64748b;
      margin: 0 0 1.25rem 0;
      font-weight: 500;
    }

    .modules-section {
      flex: 1;
      margin-bottom: 1.5rem;
    }
    .modules-label {
      font-size: 0.7rem;
      font-weight: 700;
      color: #94a3b8;
      letter-spacing: 0.5px;
      display: block;
      margin-bottom: 0.75rem;
    }
    .modules-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.4rem 0.75rem;
    }
    .module-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.82rem;
      color: #334155;
      font-weight: 500;
    }
    .dot {
      width: 7px; height: 7px;
      border-radius: 50%;
      flex-shrink: 0;
    }
    .dot-blue { background: #3B82F6; }
    .dot-cyan { background: #06B6D4; }
    .dot-green { background: #10B981; }
    .dot-purple { background: #8B5CF6; }
    .dot-orange { background: #F59E0B; }
    .dot-red { background: #EF4444; }

    .card-action {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: #3B82F6;
      font-weight: 600;
      font-size: 0.9rem;
      padding-top: 1rem;
      border-top: 1px solid #f1f5f9;
      transition: gap 0.3s;
    }
    .card-action mat-icon {
      font-size: 18px;
      width: 18px; height: 18px;
      transition: transform 0.3s;
    }
    .app-card:hover .card-action {
      gap: 0.75rem;
    }
    .app-card:hover .card-action mat-icon {
      transform: translateX(4px);
    }
    .carriere-action {
      color: #10B981;
    }

    /* Footer */
    .chooser-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 2rem;
      padding: 0 0.5rem;
      color: #94a3b8;
      font-size: 0.8rem;
    }
    .logout-link {
      display: flex;
      align-items: center;
      gap: 0.35rem;
      color: #64748b;
      cursor: pointer;
      font-weight: 500;
      transition: color 0.2s;
    }
    .logout-link:hover {
      color: #EF4444;
    }
    .logout-link mat-icon {
      font-size: 16px;
      width: 16px; height: 16px;
    }
  `]
})
export class AppChooserComponent {
  private readonly router = inject(Router);
  private readonly authService = inject(AuthService);

  username = localStorage.getItem('username') || 'Administrateur';

  goTo(app: 'grh' | 'carriere') {
    if (app === 'grh') {
      localStorage.setItem('currentApp', 'grh');
      this.router.navigate(['/home']);
    } else {
      localStorage.setItem('currentApp', 'carriere');
      this.router.navigate(['/home-carriere']);
    }
  }

  logout() {
    this.authService.logout();
  }
}
