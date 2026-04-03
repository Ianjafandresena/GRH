import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute } from '@angular/router';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { environment } from '../../../../../environments/environment';

@Component({
    selector: 'app-viewer-conge',
    standalone: true,
    imports: [CommonModule],
    templateUrl: './viewer.html',
    styleUrls: ['./viewer.scss']
})
export class ViewerCongeComponent implements OnInit {
    private readonly route = inject(ActivatedRoute);
    private readonly sanitizer = inject(DomSanitizer);

    pdfUrl: SafeResourceUrl | null = null;

    ngOnInit() {
        const id = this.route.snapshot.paramMap.get('id');
        const type = this.route.snapshot.queryParamMap.get('type') || 'conge';
        if (!id) return;

        const apiUrl = environment.apiUrl;
        let url = `${apiUrl}/conge/attestation/${id}`;
        if (type === 'permission') {
            url = `${apiUrl}/permission/${id}/pdf`;
        }
        
        this.pdfUrl = this.sanitizer.bypassSecurityTrustResourceUrl(url);
    }
}
