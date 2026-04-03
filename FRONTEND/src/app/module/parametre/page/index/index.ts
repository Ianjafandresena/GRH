import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { LayoutService } from '../../../../shared/layout/service/layout.service';
import { ThemeService } from '../../../../core/service/theme.service';
import { FormsModule } from '@angular/forms';

@Component({
    selector: 'app-parametre-index',
    standalone: true,
    imports: [CommonModule, FormsModule],
    templateUrl: './index.html',
    styleUrls: ['./index.scss']
})
export class ParametreIndexComponent implements OnInit {
    private readonly layoutService = inject(LayoutService);
    readonly themeService = inject(ThemeService);

    activeTab = 'general';

    ngOnInit() {
        this.layoutService.setTitle('Paramètres');
    }

    setTab(tab: string) {
        this.activeTab = tab;
    }

    isDarkTheme(): boolean {
        return this.themeService.theme() === 'dark';
    }

    toggleTheme() {
        this.themeService.toggleTheme();
    }
}
