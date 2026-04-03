import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { PermissionService } from '../../service/permission.service';
import { LayoutService } from '../../../../shared/layout/service/layout.service';

@Component({
  selector: 'app-permission-index',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './index.html',
  styleUrls: ['./index.scss']
})
export class PermissionIndexComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly service = inject(PermissionService);
  private readonly layoutService = inject(LayoutService);

  permissions: any[] = [];
  start: string | null = null;
  end: string | null = null;
  loading = false;
  errorMsg = '';

  ngOnInit() {
    this.layoutService.setTitle('Permissions');
    this.route.data.subscribe(data => {
      this.permissions = data['permissions'] || [];
      if (!this.permissions.length) this.applyFilter();
    });
  }

  applyFilter() {
    const params: any = {};
    if (this.start) params.start = this.start;
    if (this.end) params.end = this.end;
    this.loading = true; this.errorMsg = '';
    this.service.getPermissions(params).subscribe({
      next: list => { this.permissions = list || []; },
      error: err => { this.errorMsg = err?.message || 'Erreur lors du chargement'; },
      complete: () => { this.loading = false; }
    });
  }
}
