import { Injectable, signal, effect } from '@angular/core';

@Injectable({
    providedIn: 'root'
})
export class ThemeService {
    theme = signal<'light' | 'dark'>('light');

    constructor() {
        // Load saved theme or default to light
        const savedTheme = localStorage.getItem('theme') as 'light' | 'dark';
        if (savedTheme) {
            this.theme.set(savedTheme);
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            // Optional: Auto-detect system preference
            // this.theme.set('dark');
        }

        // Apply theme whenever it changes
        effect(() => {
            const currentTheme = this.theme();
            localStorage.setItem('theme', currentTheme);
            if (currentTheme === 'dark') {
                document.body.classList.add('dark-theme');
            } else {
                document.body.classList.remove('dark-theme');
            }
        });
    }

    toggleTheme() {
        this.theme.update(t => t === 'light' ? 'dark' : 'light');
    }

    setTheme(newTheme: 'light' | 'dark') {
        this.theme.set(newTheme);
    }
}
