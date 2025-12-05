import { Component } from '@angular/core';
import { RouterOutlet, RouterLink, RouterLinkActive } from '@angular/router';
import { MatIconModule } from '@angular/material/icon';
import { CommonModule } from '@angular/common';
import { HeaderComponent } from './component/header/header';

@Component({
  selector: 'app-layout',
  standalone: true,
  imports: [CommonModule, RouterOutlet, RouterLink, RouterLinkActive, MatIconModule, HeaderComponent],
  templateUrl: './layout.html',
  styleUrls: ['./layout.css']
})
export class LayoutComponent {
  isCollapsed = false;
  showConge = false;
  showPermission = false;
  toggleConge() { this.showConge = !this.showConge; }
  togglePermission() { this.showPermission = !this.showPermission; }
  toggleSidebar() { this.isCollapsed = !this.isCollapsed; }
}
