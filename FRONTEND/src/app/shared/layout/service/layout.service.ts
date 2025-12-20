
import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';

@Injectable({
    providedIn: 'root'
})
export class LayoutService {
    private titleSubject = new BehaviorSubject<string>('Tableau de Bord');
    public title$ = this.titleSubject.asObservable();

    constructor() { }

    setTitle(title: string) {
        this.titleSubject.next(title);
    }
}
