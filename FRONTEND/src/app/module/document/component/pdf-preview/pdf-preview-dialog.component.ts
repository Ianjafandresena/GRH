import { Component, Inject, OnInit } from '@angular/core';
import { SafeResourceUrl, DomSanitizer } from '@angular/platform-browser';
import { CommonModule } from '@angular/common';
import { MatDialogModule, MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDividerModule } from '@angular/material/divider';

export interface PdfPreviewData {
    pdfBase64?: string;
    pdfUrl?: string;
    filename: string;
    title?: string;
}

@Component({
    selector: 'app-pdf-preview-dialog',
    standalone: true,
    imports: [
        CommonModule,
        MatDialogModule,
        MatButtonModule,
        MatIconModule,
        MatProgressSpinnerModule,
        MatDividerModule
    ],
    templateUrl: './pdf-preview-dialog.component.html',
    styleUrls: ['./pdf-preview-dialog.component.scss']
})
export class PdfPreviewDialogComponent implements OnInit {
    pdfSafeUrl: SafeResourceUrl | null = null;
    loading = true;

    constructor(
        @Inject(MAT_DIALOG_DATA) public data: PdfPreviewData,
        private dialogRef: MatDialogRef<PdfPreviewDialogComponent>,
        private sanitizer: DomSanitizer
    ) { }

    ngOnInit(): void {
        this.loadPdf();
    }

    private loadPdf(): void {
        if (this.data.pdfBase64) {
            this.generatePdfUrlFromBase64(this.data.pdfBase64);
        } else if (this.data.pdfUrl) {
            this.pdfSafeUrl = this.sanitizer.bypassSecurityTrustResourceUrl(this.data.pdfUrl);
            this.loading = false;
        } else {
            this.loading = false;
        }
    }

    private generatePdfUrlFromBase64(base64: string): void {
        try {
            const byteCharacters = atob(base64);
            const byteNumbers = new Array(byteCharacters.length);
            for (let i = 0; i < byteCharacters.length; i++) {
                byteNumbers[i] = byteCharacters.charCodeAt(i);
            }
            const byteArray = new Uint8Array(byteNumbers);
            const blob = new Blob([byteArray], { type: 'application/pdf' });
            const url = URL.createObjectURL(blob);
            this.pdfSafeUrl = this.sanitizer.bypassSecurityTrustResourceUrl(url);
            this.loading = false;
        } catch (error) {
            console.error('Erreur lors de la préparation de l\'aperçu PDF:', error);
            this.loading = false;
        }
    }

    download(): void {
        if (this.data.pdfBase64) {
            this.downloadFromBase64(this.data.pdfBase64);
        } else if (this.data.pdfUrl) {
            this.downloadFromUrl(this.data.pdfUrl);
        }
    }

    private downloadFromBase64(base64: string): void {
        const byteCharacters = atob(base64);
        const byteNumbers = new Array(byteCharacters.length);
        for (let i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
        }
        const byteArray = new Uint8Array(byteNumbers);
        const blob = new Blob([byteArray], { type: 'application/pdf' });

        const url = window.URL.createObjectURL(blob);
        this.triggerDownload(url);
    }

    private downloadFromUrl(url: string): void {
        // Pour les URLs, on peut souvent juste ouvrir ou créer un lien
        // Mais pour forcer le nom de fichier, un fetch est parfois nécessaire
        // Pour faire simple et robuste :
        fetch(url)
            .then(res => res.blob())
            .then(blob => {
                const blobUrl = window.URL.createObjectURL(blob);
                this.triggerDownload(blobUrl);
            })
            .catch(err => {
                console.error('Erreur de téléchargement:', err);
                // Fallback: ouvrir simplement l'URL
                window.open(url, '_blank');
            });
    }

    private triggerDownload(url: string): void {
        const link = document.createElement('a');
        link.href = url;
        link.download = this.data.filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    }

    close(): void {
        this.dialogRef.close();
    }
}
