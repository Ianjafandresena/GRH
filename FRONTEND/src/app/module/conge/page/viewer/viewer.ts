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
    styleUrls: ['./viewer.css']
})
export class ViewerCongeComponent implements OnInit {
    private readonly route = inject(ActivatedRoute);
    private readonly sanitizer = inject(DomSanitizer);

    pdfUrl: SafeResourceUrl | null = null;

    ngOnInit() {
        const id = this.route.snapshot.paramMap.get('id');
        if (!id) return;

        const apiUrl = environment.apiUrl;
        const url = `${apiUrl}/conge/attestation/${id}`;
        this.pdfUrl = this.sanitizer.bypassSecurityTrustResourceUrl(url);
    }
}
