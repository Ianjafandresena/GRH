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

  // Admin user data
  admin: any = null;

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
  width = 400;
  height = 200;
  private maxVal = 10;

  // Tooltip State
  hoveredIndex = -1;
  tooltipX = 0;
  tooltipY = 0;
  tooltipData = { label: '', conges: 0, permissions: 0 };

  ngOnInit() {
    this.layoutService.setTitle('Tableau de Bord');

    // Load admin from session
    const adminStr = sessionStorage.getItem('admin');
    if (adminStr) {
      this.admin = JSON.parse(adminStr);
    }

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

        // Calculate max value for scaling (Dynamic & Proportional)
        const allValues = [...data.conges, ...data.permissions];
        const rawMax = Math.max(...allValues, 0);

        // Determine a nice max value (multiple of 4 for clean steps)
        // If minimal data, default to 10 maybe? User said "max 10 if data < 15".
        // Let's use a minimum of 4 to avoid flat 0.
        let targetMax = rawMax > 0 ? Math.ceil(rawMax / 4) * 4 : 4;

        // Extra buffer only if values are high? No, user wants close fit. 
        // If max is 14, ceil(14/4)*4 = 16. Steps: 0, 4, 8, 12, 16. Perfect.
        // If max is 5, ceil(5/4)*4 = 8. Steps: 0, 2, 4, 6, 8.

        this.maxVal = targetMax;

        // Generate ticks
        this.yAxisTicks = [];
        for (let i = 0; i <= 4; i++) {
          const val = (targetMax / 4) * i;
          // y position for this value
          const y = this.getY(val);
          // We want y lines at 0, 25%, 50%, 75%, 100% of height?
          // Since maxVal corresponds to height (y=0), and 0 to y=height.
          // val=0 -> y=200. val=max -> y=0.
          this.yAxisTicks.push({ value: val, y: y });
        }

        // Generate paths
        this.congePath = this.getSvgPath(data.conges, false);
        this.congeAreaPath = this.getSvgPath(data.conges, true);
        this.permissionPath = this.getSvgPath(data.permissions, false);
        this.permissionAreaPath = this.getSvgPath(data.permissions, true);
      },
      error: (err) => console.error('Failed to load chart data', err)
    });
  }

  // Ticks for the template
  yAxisTicks: { value: number, y: number }[] = [];

  // Helper to get X position for a given index
  getX(index: number): number {
    if (this.chartLabels.length <= 1) return 0;
    return index * (this.width / (this.chartLabels.length - 1));
  }

  // Helper to get Y position for a given value
  getY(value: number): number {
    return this.height - (value / this.maxVal * this.height);
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

  onChartMouseMove(event: MouseEvent) {
    const svgElement = event.currentTarget as SVGSVGElement;
    const rect = svgElement.getBoundingClientRect();

    // ViewBox dimensions (matches HTML)
    const viewBoxWidth = 440;
    const viewBoxHeight = 220;
    const graphOriginX = 30; // The translation
    const graphWidth = 400; // The width used in getX/calculations

    // Scale factors
    const scaleX = viewBoxWidth / rect.width;
    const scaleY = viewBoxHeight / rect.height;

    // Mouse position in SVG coordinates
    const clickX = (event.clientX - rect.left) * scaleX;

    // Position relative to the graph group
    const graphX = clickX - graphOriginX;

    const segmentWidth = graphWidth / (this.chartLabels.length - 1);
    const index = Math.round(graphX / segmentWidth);

    if (index >= 0 && index < this.chartLabels.length) {
      this.hoveredIndex = index;

      // Tooltip positioning
      // Snap to the data point's X (in pixels relative to container)
      const pointSvgX = this.getX(index) + graphOriginX; // In viewBox coords
      const pointPixelX = (pointSvgX / viewBoxWidth) * rect.width;

      this.tooltipX = pointPixelX;

      // Y position: find heightest point (min Y)
      const val1 = this.chartData.conges[index] || 0;
      const val2 = this.chartData.permissions[index] || 0;
      const svgY = Math.min(this.getY(val1), this.getY(val2)); // In viewBox coords
      const pointPixelY = (svgY / viewBoxHeight) * rect.height;

      this.tooltipY = pointPixelY - 15; // Offset

      this.tooltipData = {
        label: this.chartLabels[index],
        conges: val1,
        permissions: val2
      };
    } else {
      this.hoveredIndex = -1;
    }
  }

  onChartMouseLeave() {
    this.hoveredIndex = -1;
  }
}
