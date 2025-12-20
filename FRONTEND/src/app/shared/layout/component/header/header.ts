
import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { AuthService } from '../../../../module/auth/service/auth-service';
import { LayoutService } from '../../service/layout.service';

@Component({
    selector: 'app-header',
    standalone: true,
    imports: [CommonModule, MatIconModule],
    templateUrl: './header.html',
    styleUrls: ['./header.scss']
})
export class HeaderComponent implements OnInit {
    private readonly authService = inject(AuthService);
    private readonly layoutService = inject(LayoutService);

    admin: any = null;
    pageTitle: string = 'Tableau de Bord';

    ngOnInit() {
        this.authService.currentAdmin$.subscribe(admin => {
            this.admin = admin;
        });

        this.layoutService.title$.subscribe(title => {
            this.pageTitle = title;
        });
    }
}
