
import { Injectable, inject } from '@angular/core';
import { Router, NavigationEnd, ActivatedRouteSnapshot } from '@angular/router';
import { BehaviorSubject, filter } from 'rxjs';

export interface Breadcrumb {
    label: string;
    url: string;
}

@Injectable({
    providedIn: 'root'
})
export class BreadcrumbService {
    private readonly router = inject(Router);
    private readonly breadcrumbsSubject = new BehaviorSubject<Breadcrumb[]>([]);
    public breadcrumbs$ = this.breadcrumbsSubject.asObservable();

    constructor() {
        this.router.events.pipe(
            filter(event => event instanceof NavigationEnd)
        ).subscribe(() => {
            const root = this.router.routerState.snapshot.root;
            const breadcrumbs: Breadcrumb[] = [
                { label: 'Tableau de bord', url: '/home' }
            ];
            this.addBreadcrumb(root, [], breadcrumbs);

            // Éliminer les doublons si le premier breadcrumb dynamique est aussi le tableau de bord
            const uniqueBreadcrumbs = breadcrumbs.filter((vc, index, self) =>
                index === self.findIndex((t) => (t.label === vc.label && t.url === vc.url))
            );

            this.breadcrumbsSubject.next(uniqueBreadcrumbs);
        });
    }

    private addBreadcrumb(route: ActivatedRouteSnapshot, parentUrl: string[], breadcrumbs: Breadcrumb[]) {
        if (route) {
            const routeUrl = parentUrl.concat(route.url.map(segment => segment.path));

            if (route.data && route.data['breadcrumb']) {
                const breadcrumb = {
                    label: route.data['breadcrumb'],
                    url: '/' + routeUrl.join('/')
                };

                // Si le label est dynamique (ex: contient un :id), on pourrait le transformer ici
                // Pour l'instant on garde simple
                breadcrumbs.push(breadcrumb);
            }

            if (route.firstChild) {
                this.addBreadcrumb(route.firstChild, routeUrl, breadcrumbs);
            }
        }
    }
}
