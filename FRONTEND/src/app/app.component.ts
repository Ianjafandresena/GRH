import { Component, signal, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { ThemeService } from './core/service/theme.service';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet],
  template: '<router-outlet/>'
})

export class App {
  protected readonly title = signal('FRONT');
  // Inject ThemeService to initialize theme on startup
  private readonly themeService = inject(ThemeService);
}
