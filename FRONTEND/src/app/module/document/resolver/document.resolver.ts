import { Injectable } from '@angular/core';
import { Resolve, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { Observable, forkJoin } from 'rxjs';
import { DocumentService, DocumentStats } from '../service/document.service';
import { DocumentDemande } from '../model/document.model';

export interface DocumentListResolved {
    demandes: DocumentDemande[];
    stats: DocumentStats;
}

@Injectable({ providedIn: 'root' })
export class DocumentResolver implements Resolve<DocumentListResolved> {
    constructor(private documentService: DocumentService) { }

    resolve(route: ActivatedRouteSnapshot, state: RouterStateSnapshot): Observable<DocumentListResolved> {
        return forkJoin({
            demandes: this.documentService.listerDemandes(),
            stats: this.documentService.getStats()
        });
    }
}
