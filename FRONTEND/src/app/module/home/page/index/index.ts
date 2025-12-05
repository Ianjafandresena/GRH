import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { Router } from '@angular/router';
import { DashboardService } from '../../service/dashboard.service';
import { LayoutService } from '../../../layout/service/layout.service';

@Component({
  selector: 'app-home-index',
  standalone: true,
  imports: [CommonModule, MatIconModule],
  templateUrl: './index.html',
  styleUrls: ['./index.css']
})
export class HomeComponent implements OnInit {
  private readonly router = inject(Router);
  private readonly dashboardService = inject(DashboardService);
  private readonly layoutService = inject(LayoutService);

  // Statistics data
  stats = {
    totalEmployees: 0,
    employeesActive: 0,
    employeesChange: '',
    congesEnCours: 0,
    congesPrevision: '',
    congesChange: '',
    permissions: 0,
    permissionsStatus: 'Attente de validation',
    permissionsChange: ''
  };

  // Chart Data
  chartLabels: string[] = [];
  chartData = { conges: [] as number[], permissions: [] as number[] };
  congePath = '';
  congeAreaPath = '';
  permissionPath = '';
  permissionAreaPath = '';

  // Chart dimensions (viewBox 0 0 400 200)
  private width = 400;
  private height = 200;
  private maxVal = 10;

  // Tooltip State
  hoveredIndex = -1;

  ngOnInit() {
    this.layoutService.setTitle('Tableau de Bord');
    this.loadChartData();
    this.loadStats();
  }
  loadStats() {
    this.dashboardService.getDashboardStats().subscribe({
      next: (stats) => {
        // Update Congés card
        this.stats.congesEnCours = stats.congesEnCours;

        const evoConges = stats.congesEvolution;
        const arrowConges = evoConges >= 0 ? '↑' : '↓';
        const signConges = evoConges > 0 ? '+' : '';
        this.stats.congesChange = `${arrowConges} ${signConges}${evoConges}% vs mois dernier`;

        // Update Employees card
        this.stats.totalEmployees = stats.totalEmployees;
        this.stats.employeesActive = stats.activeEmployees;

        const evoEmp = stats.employeesEvolution;
        const arrowEmp = evoEmp >= 0 ? '↑' : '↓';
        const signEmp = evoEmp > 0 ? '+' : '';
        this.stats.employeesChange = `${arrowEmp} ${signEmp}${evoEmp}% vs mois dernier`;
      },
      error: (err) => console.error('Failed to load dashboard stats', err)
    });
  }

  loadChartData() {
    this.dashboardService.getEvolutionStats().subscribe({
      next: (data) => {
        this.chartLabels = data.labels;
        this.chartData.conges = data.conges;
        this.chartData.permissions = data.permissions;

        // Calculate max value for scaling
        const allValues = [...data.conges, ...data.permissions];
        this.maxVal = Math.max(...allValues, 10) * 1.2;

        // Generate paths
        this.congePath = this.getSvgPath(data.conges, false);
        this.congeAreaPath = this.getSvgPath(data.conges, true);
        this.permissionPath = this.getSvgPath(data.permissions, false);
        this.permissionAreaPath = this.getSvgPath(data.permissions, true);
      },
      error: (err) => console.error('Failed to load chart data', err)
    });
  }

  // Helper to get X position for a given index
  getX(index: number): number {
    if (this.chartLabels.length <= 1) return 0;
    return index * (this.width / (this.chartLabels.length - 1));
  }

  // Helper to get Y position for a given value
  getY(value: number): number {
    return this.height - (value / this.maxVal * (this.height * 0.8));
  }

  // Tooltip positioning
  getTooltipX(index: number): number {
    return this.getX(index);
  }

  getTooltipY(index: number): number {
    // Position tooltip slightly above the highest point at this index
    const y1 = this.getY(this.chartData.conges[index]);
    const y2 = this.getY(this.chartData.permissions[index]);
    return Math.min(y1, y2) - 10;
  }

  getSvgPath(data: number[], isArea: boolean): string {
    if (!data || data.length === 0) return '';

    const points = data.map((val, i) => {
      return [this.getX(i), this.getY(val)];
    });

    let path = `M ${points[0][0]} ${points[0][1]}`;

    // Cubic Bezier Smoothing
    for (let i = 0; i < points.length - 1; i++) {
      const p0 = points[i === 0 ? i : i - 1];
      const p1 = points[i];
      const p2 = points[i + 1];
      const p3 = points[i + 2] || p2;

      const cp1x = p1[0] + (p2[0] - p0[0]) / 6;
      const cp1y = p1[1] + (p2[1] - p0[1]) / 6;

      const cp2x = p2[0] - (p3[0] - p1[0]) / 6;
      const cp2y = p2[1] - (p3[1] - p1[1]) / 6;

      path += ` C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${p2[0]} ${p2[1]}`;
    }

    if (isArea) {
      path += ` L ${this.width} ${this.height} L 0 ${this.height} Z`;
    }

    return path;
  }

  logout() {
    sessionStorage.removeItem('admin');
    document.cookie = 'sid=; Max-Age=0; path=/;';
    this.router.navigate(['/login']);
  }
}
