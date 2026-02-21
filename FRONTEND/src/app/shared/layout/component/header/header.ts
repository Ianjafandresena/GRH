
import { Component, inject, OnInit, OnDestroy, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../../../module/auth/service/auth-service';
import { LayoutService } from '../../service/layout.service';
import { NotificationService } from '../../../../shared/service/notification.service';
import { BreadcrumbService, Breadcrumb } from '../../../../shared/service/breadcrumb.service';

@Component({
    selector: 'app-header',
    standalone: true,
    imports: [CommonModule, MatIconModule, RouterLink],
    templateUrl: './header.html',
    styleUrls: ['./header.scss']
})
export class HeaderComponent implements OnInit, OnDestroy {
    private readonly authService = inject(AuthService);
    private readonly layoutService = inject(LayoutService);
    readonly notificationService = inject(NotificationService);
    private readonly breadcrumbService = inject(BreadcrumbService);

    admin: any = null;
    pageTitle: string = 'Tableau de Bord';
    breadcrumbs: Breadcrumb[] = [];
    successMsg: string | null = null;
    errorMsg: string | null = null;

    ngOnInit() {
        this.authService.currentAdmin$.subscribe(admin => {
            this.admin = admin;
        });

        this.layoutService.title$.subscribe(title => {
            this.pageTitle = title;
        });

        this.breadcrumbService.breadcrumbs$.subscribe(breadcrumbs => {
            this.breadcrumbs = breadcrumbs;
        });

        this.layoutService.successMessage$.subscribe(msg => {
            this.successMsg = msg;
        });

        this.layoutService.errorMessage$.subscribe(msg => {
            this.errorMsg = msg;
        });

        // Démarrer le polling des notifications
        this.notificationService.startPolling();
    }

    ngOnDestroy() {
        this.notificationService.stopPolling();
    }

    toggleNotifications() {
        this.notificationService.toggleDropdown();
    }

    // Fermer le dropdown si on clique en dehors
    @HostListener('document:click', ['$event'])
    onDocumentClick(event: MouseEvent) {
        const target = event.target as HTMLElement;
        if (!target.closest('.notification-container')) {
            this.notificationService.closeDropdown();
        }
    }
}
