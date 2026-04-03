import { Component, inject, OnInit, OnDestroy } from '@angular/core';
import { RouterOutlet, RouterLink, RouterLinkActive, Router } from '@angular/router';
import { MatIconModule } from '@angular/material/icon';
import { CommonModule } from '@angular/common';
import { HeaderComponent } from './component/header/header';
import { AuthService } from '../../module/auth/service/auth-service';
import { ChatbotComponent } from '../chatbot/chatbot.component';
import { ChatbotService } from '../chatbot/chatbot.service';
import { InactivityService } from '../service/inactivity.service';

@Component({
  selector: 'app-layout',
  standalone: true,
  imports: [
    CommonModule,
    RouterOutlet,
    RouterLink,
    RouterLinkActive,
    MatIconModule,
    HeaderComponent,
    ChatbotComponent
  ],
  templateUrl: './layout.html',
  styleUrls: ['./layout.scss']
})
export class LayoutComponent implements OnInit, OnDestroy {
  private readonly authService = inject(AuthService);
  private readonly chatbotService = inject(ChatbotService);
  private readonly inactivityService = inject(InactivityService);
  private readonly router = inject(Router);

  isCollapsed = false;
  currentApp: 'grh' | 'carriere' = 'grh';

  // GRH toggles
  showConge = false;
  showPermission = false;
  showRemb = false;
  showPec = false;
  showEmployee = false;

  // Carrière toggles
  showEmployes = false;
  showAffectations = false;
  showStages = false;
  showCompetences = false;
  showDocuments = false;

  // ========== GRH TOGGLES ==========
  toggleEmployee() {
    this.showEmployee = !this.showEmployee;
    if (this.showEmployee) {
      this.showConge = false;
      this.showPermission = false;
      this.showRemb = false;
      this.showPec = false;
    }
  }

  toggleConge() {
    this.showConge = !this.showConge;
    if (this.showConge) {
      this.showPermission = false;
      this.showRemb = false;
      this.showPec = false;
    }
  }

  togglePermission() {
    this.showPermission = !this.showPermission;
    if (this.showPermission) {
      this.showConge = false;
      this.showRemb = false;
      this.showPec = false;
    }
  }

  toggleRemb() {
    this.showRemb = !this.showRemb;
    if (this.showRemb) {
      this.showConge = false;
      this.showPermission = false;
      this.showPec = false;
    }
  }

  togglePec() {
    this.showPec = !this.showPec;
    if (this.showPec) {
      this.showConge = false;
      this.showPermission = false;
      this.showRemb = false;
    }
  }

  // ========== CARRIÈRE TOGGLES ==========
  toggleEmployes() {
    this.showEmployes = !this.showEmployes;
    if (this.showEmployes) {
      this.showAffectations = false;
      this.showStages = false;
      this.showCompetences = false;
      this.showDocuments = false;
    }
  }

  toggleAffectations() {
    this.showAffectations = !this.showAffectations;
    if (this.showAffectations) {
      this.showEmployes = false;
      this.showStages = false;
      this.showCompetences = false;
      this.showDocuments = false;
    }
  }

  toggleStages() {
    this.showStages = !this.showStages;
    if (this.showStages) {
      this.showEmployes = false;
      this.showAffectations = false;
      this.showCompetences = false;
      this.showDocuments = false;
    }
  }

  toggleCompetences() {
    this.showCompetences = !this.showCompetences;
    if (this.showCompetences) {
      this.showEmployes = false;
      this.showAffectations = false;
      this.showStages = false;
      this.showDocuments = false;
    }
  }

  toggleDocuments() {
    this.showDocuments = !this.showDocuments;
    if (this.showDocuments) {
      this.showEmployes = false;
      this.showAffectations = false;
      this.showStages = false;
      this.showCompetences = false;
    }
  }

  showAppSwitcher = false;

  toggleAppSwitcher(event: Event) {
    event.stopPropagation();
    if (!this.isCollapsed) {
      this.showAppSwitcher = !this.showAppSwitcher;
    }
  }

  switchApp(app: 'grh' | 'carriere') {
    localStorage.setItem('currentApp', app);
    this.currentApp = app;
    this.showAppSwitcher = false;

    // Fermer tous les menus ouverts
    this.showConge = false;
    this.showRemb = false;
    this.showPec = false;
    this.showEmployes = false;
    this.showAffectations = false;
    this.showStages = false;
    this.showCompetences = false;
    this.showDocuments = false;

    if (app === 'grh') {
      this.router.navigate(['/home']);
    } else {
      this.router.navigate(['/home-carriere']);
    }
  }

  toggleSidebar() {
    this.isCollapsed = !this.isCollapsed;
    if (this.isCollapsed) {
      this.showAppSwitcher = false;
    }
  }

  goToChooser() {
    this.router.navigate(['/dashboard']);
  }

  private readonly clickListener = () => {
    this.showAppSwitcher = false;
  };

  ngOnInit() {
    // Détecter l'application courante
    this.currentApp = (localStorage.getItem('currentApp') as 'grh' | 'carriere') || 'grh';

    // Fermer le switcher si on clique ailleurs
    document.addEventListener('click', this.clickListener);

    // Initialiser chatbot avec emp_code de l'utilisateur connecté
    const empCode = localStorage.getItem('emp_code');
    if (empCode) {
      this.chatbotService.setEmployee(parseInt(empCode));
    }

    // Démarrer la surveillance de l'inactivité
    this.inactivityService.startMonitoring();
  }

  ngOnDestroy() {
    this.inactivityService.stopMonitoring();
    document.removeEventListener('click', this.clickListener);
  }

  logout() {
    this.authService.logout();
  }
}
