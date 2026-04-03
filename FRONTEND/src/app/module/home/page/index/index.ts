import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { ActivatedRoute } from '@angular/router';
import { DashboardService } from '../../service/dashboard.service';
import { LayoutService } from '../../../../shared/layout/service/layout.service';

@Component({
  selector: 'app-home-index',
  standalone: true,
  imports: [CommonModule, MatIconModule],
  templateUrl: './index.html',
  styleUrls: ['./index.scss']
})
export class HomeComponent implements OnInit {
  private readonly layoutService = inject(LayoutService);
  private readonly route = inject(ActivatedRoute);
  private readonly dashboardService = inject(DashboardService);

  admin: any = null;

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

  chartLabels: string[] = [];
  chartData = { conges: [] as number[], permissions: [] as number[] };
  congePath = '';
  congeAreaPath = '';
  permissionPath = '';
  permissionAreaPath = '';

  width = 400;
  height = 200;
  private maxVal = 10;

  hoveredIndex = -1;
  tooltipX = 0;
  tooltipY = 0;
  tooltipData = { label: '', conges: 0, permissions: 0 };

  employeesOnLeave = signal<any[]>([]);
  pendingRequests = signal<any>({ count: 0, total: 0 });
  recentActivity = signal<any[]>([]);
  donutData = signal<any>({ stats: { approuve: 0, en_attente: 0, total: 0 }, montants: { en_attente: 0 } });
  topAbsent = signal<any[]>([]);
  topReimbursements = signal<any[]>([]);

  // UI State for Top Lists
  showAbsentList = signal(true);
  viewModeAbsent = signal<'list' | 'chart'>('list');
  showRembList = signal(true);
  viewModeRemb = signal<'list' | 'chart'>('list');

  // Period Filtering
  selectedPeriod = signal<string>('month');
  startDate = signal<string | null>(null);
  endDate = signal<string | null>(null);

  ngOnInit() {
    this.layoutService.setTitle('Tableau de Bord');

    const adminStr = sessionStorage.getItem('admin');
    if (adminStr) {
      this.admin = JSON.parse(adminStr);
    }

    // Récupérer les données du resolver
    const resolvedData = this.route.snapshot.data['dashboardData'] as any;
    if (resolvedData) {
      this.processStats(resolvedData.stats);
      this.processChartData(resolvedData.evolution);
      this.employeesOnLeave.set(resolvedData.employeesOnLeave);
      this.pendingRequests.set(resolvedData.pendingRequests);
      this.recentActivity.set(resolvedData.recentActivity);
      this.donutData.set(resolvedData.donutData);

      // On initialise les dates par défaut pour l'UI même si on a les données
      const now = new Date();
      const start = new Date(now.getFullYear(), now.getMonth(), 1);
      const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
      this.startDate.set(this.formatDate(start));
      this.endDate.set(this.formatDate(end));
    } else {
      // Fallback si pas de resolver
      this.changePeriod('month');
    }
  }

  changePeriod(period: string) {
    this.selectedPeriod.set(period);
    const now = new Date();

    if (period === 'month') {
      const start = new Date(now.getFullYear(), now.getMonth(), 1);
      const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
      this.startDate.set(this.formatDate(start));
      this.endDate.set(this.formatDate(end));
    } else if (period === 'year') {
      const start = new Date(now.getFullYear(), 0, 1);
      const end = new Date(now.getFullYear(), 11, 31);
      this.startDate.set(this.formatDate(start));
      this.endDate.set(this.formatDate(end));
    } else if (period === 'custom') {
      // Keep current values or set defaults if null
      if (!this.startDate()) this.startDate.set(this.formatDate(now));
      if (!this.endDate()) this.endDate.set(this.formatDate(now));
    }

    this.loadData();
  }

  updateStartDate(date: string) {
    this.startDate.set(date);
    this.selectedPeriod.set('custom');
    this.loadData();
  }

  updateEndDate(date: string) {
    this.endDate.set(date);
    this.selectedPeriod.set('custom');
    this.loadData();
  }

  private formatDate(date: Date): string {
    return date.toISOString().split('T')[0];
  }

  private loadData() {
    const start = this.startDate() || undefined;
    const end = this.endDate() || undefined;

    this.dashboardService.getDashboardStats(start, end).subscribe(stats => this.processStats(stats));
    this.dashboardService.getEvolutionStats(start, end).subscribe(evo => this.processChartData(evo));
    this.dashboardService.getEmployeesOnLeave().subscribe(data => this.employeesOnLeave.set(data));
    this.dashboardService.getPendingReimbursements(start, end).subscribe(data => this.pendingRequests.set(data));
    this.dashboardService.getRecentActivity(start, end).subscribe(data => this.recentActivity.set(data));
    this.dashboardService.getReimbursementDistribution(start, end).subscribe(data => this.donutData.set(data));
    this.dashboardService.getTopAbsent(start, end).subscribe(data => this.topAbsent.set(data));
    this.dashboardService.getTopReimbursements(start, end).subscribe(data => this.topReimbursements.set(data));
  }

  private processStats(stats: any) {
    if (!stats) return;

    this.stats.congesEnCours = stats.congesEnCours;
    const evoConges = stats.congesEvolution || 0;
    const arrowConges = evoConges >= 0 ? '↑' : '↓';
    const signConges = evoConges > 0 ? '+' : '';
    this.stats.congesChange = `${arrowConges} ${signConges}${evoConges}% vs mois dernier`;

    this.stats.totalEmployees = stats.totalEmployees;
    this.stats.employeesActive = stats.activeEmployees;
    const evoEmp = stats.employeesEvolution || 0;
    const arrowEmp = evoEmp >= 0 ? '↑' : '↓';
    const signEmp = evoEmp > 0 ? '+' : '';
    this.stats.employeesChange = `${arrowEmp} ${signEmp}${evoEmp}% vs mois dernier`;
  }

  private processChartData(data: any) {
    if (!data || !data.labels) return;
    this.chartLabels = data.labels;
    this.chartData.conges = data.conges || [];
    this.chartData.permissions = data.permissions || [];

    const allValues = [...this.chartData.conges, ...this.chartData.permissions];
    const rawMax = allValues.length > 0 ? Math.max(...allValues, 0) : 0;
    let targetMax = rawMax > 0 ? Math.ceil(rawMax / 4) * 4 : 4;
    if (rawMax >= targetMax) targetMax += 4;
    this.maxVal = targetMax;

    this.yAxisTicks = [];
    for (let i = 0; i <= 4; i++) {
      const val = (targetMax / 4) * i;
      this.yAxisTicks.push({ value: val, y: this.getY(val) });
    }

    this.congePath = this.getSvgPath(this.chartData.conges, false);
    this.congeAreaPath = this.getSvgPath(this.chartData.conges, true);
    this.permissionPath = this.getSvgPath(this.chartData.permissions, false);
    this.permissionAreaPath = this.getSvgPath(this.chartData.permissions, true);
  }

  yAxisTicks: { value: number, y: number }[] = [];

  getX(index: number): number {
    if (this.chartLabels.length <= 1) return 0;
    return index * (this.width / (this.chartLabels.length - 1));
  }

  getY(value: number): number {
    return this.height - (value / this.maxVal * this.height);
  }

  getSvgPath(data: number[], isArea: boolean): string {
    if (!data || data.length === 0) return '';
    const points = data.map((val, i) => [this.getX(i), this.getY(val)]);
    let path = `M ${points[0][0]} ${points[0][1]}`;
    for (let i = 0; i < points.length - 1; i++) {
      const p0 = points[i === 0 ? i : i - 1];
      const p1 = points[i];
      const p2 = points[i + 1];
      const p3 = points[i + 2] || p2;
      const cp1x = p1[0] + (p2[0] - p0[0]) / 6;
      let cp1y = p1[1] + (p2[1] - p0[1]) / 6;
      const cp2x = p2[0] - (p3[0] - p1[0]) / 6;
      let cp2y = p2[1] - (p3[1] - p1[1]) / 6;

      // Clamping Y to avoid negative dips below X axis (y > height)
      if (cp1y > this.height) cp1y = this.height;
      if (cp2y > this.height) cp2y = this.height;
      if (cp1y < 0) cp1y = 0;
      if (cp2y < 0) cp2y = 0;

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
    const viewBoxWidth = 440;
    const viewBoxHeight = 220;
    const graphOriginX = 30;
    const graphWidth = 400;
    const scaleX = viewBoxWidth / rect.width;
    const scaleY = viewBoxHeight / rect.height;
    const clickX = (event.clientX - rect.left) * scaleX;
    const graphX = clickX - graphOriginX;
    const segmentWidth = graphWidth / (this.chartLabels.length - 1);
    const index = Math.round(graphX / segmentWidth);

    if (index >= 0 && index < this.chartLabels.length) {
      this.hoveredIndex = index;
      const pointSvgX = this.getX(index) + graphOriginX;
      this.tooltipX = (pointSvgX / viewBoxWidth) * rect.width;
      const val1 = this.chartData.conges[index] || 0;
      const val2 = this.chartData.permissions[index] || 0;
      const svgY = Math.min(this.getY(val1), this.getY(val2));
      this.tooltipY = (svgY / viewBoxHeight) * rect.height - 15;
      this.tooltipData = { label: this.chartLabels[index], conges: val1, permissions: val2 };
    } else {
      this.hoveredIndex = -1;
    }
  }

  onChartMouseLeave() { this.hoveredIndex = -1; }
  activityIcon(type: string): string { return type === 'conge' ? 'event_available' : 'local_hospital'; }

  formatTime(dateStr: string): string {
    if (!dateStr) return 'Récemment';
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffHrs = Math.floor(diffMs / (1000 * 60 * 60));
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    if (diffHrs < 1) return 'Il y a quelques minutes';
    if (diffHrs < 24) return `Il y a ${diffHrs} heure${diffHrs > 1 ? 's' : ''}`;
    if (diffDays < 7) return `Il y a ${diffDays} jour${diffDays > 1 ? 's' : ''}`;
    return date.toLocaleDateString('fr-FR');
  }

  // --- UI TOGGLES ---
  toggleAbsentVisibility() { this.showAbsentList.update(v => !v); }
  toggleAbsentViewMode() { this.viewModeAbsent.update(m => m === 'list' ? 'chart' : 'list'); }

  toggleRembVisibility() { this.showRembList.update(v => !v); }
  toggleRembViewMode() { this.viewModeRemb.update(m => m === 'list' ? 'chart' : 'list'); }

  getBarWidth(value: number, data: any[], key: string): string {
    if (!data || data.length === 0) return '0%';
    const max = Math.max(...data.map(d => parseFloat(d[key]) || 0));
    if (max === 0) return '0%';
    return (value / max * 100) + '%';
  }
}
