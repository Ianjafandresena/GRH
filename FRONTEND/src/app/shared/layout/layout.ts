import { Component, inject, OnInit } from '@angular/core';
import { RouterOutlet, RouterLink, RouterLinkActive } from '@angular/router';
import { MatIconModule } from '@angular/material/icon';
import { CommonModule } from '@angular/common';
import { HeaderComponent } from './component/header/header';
import { AuthService } from '../../module/auth/service/auth-service';
import { ChatbotComponent } from '../chatbot/chatbot.component';  // ➕ CHATBOT
import { ChatbotService } from '../chatbot/chatbot.service';       // ➕ CHATBOT

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
    ChatbotComponent  // ➕ CHATBOT
  ],
  templateUrl: './layout.html',
  styleUrls: ['./layout.scss']
})
export class LayoutComponent implements OnInit {
  private readonly authService = inject(AuthService);
  private readonly chatbotService = inject(ChatbotService);  // ➕ CHATBOT

  isCollapsed = false;
  showConge = false;
  showPermission = false;
  showRemb = false;
  showPec = false;

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

  toggleSidebar() { this.isCollapsed = !this.isCollapsed; }

  ngOnInit() {
    // ➕ Initialiser chatbot avec emp_code de l'utilisateur connecté
    const empCode = localStorage.getItem('emp_code');
    if (empCode) {
      this.chatbotService.setEmployee(parseInt(empCode));
    }
  }

  logout() {
    this.authService.logout();
  }
}
