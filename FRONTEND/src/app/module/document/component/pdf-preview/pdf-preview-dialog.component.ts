import { Component, Inject, OnInit } from '@angular/core';
import { SafeResourceUrl, DomSanitizer } from '@angular/platform-browser';
import { CommonModule } from '@angular/common';
import { MatDialogModule, MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDividerModule } from '@angular/material/divider';

export interface PdfPreviewData {
    pdfBase64: string;
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
    pdfUrl: SafeResourceUrl | null = null;
    loading = true;

    constructor(
        @Inject(MAT_DIALOG_DATA) public data: PdfPreviewData,
        private dialogRef: MatDialogRef<PdfPreviewDialogComponent>,
        private sanitizer: DomSanitizer
    ) { }

    ngOnInit(): void {
        this.generatePdfUrl();
    }

    private generatePdfUrl(): void {
        try {
            const byteCharacters = atob(this.data.pdfBase64);
            const byteNumbers = new Array(byteCharacters.length);
            for (let i = 0; i < byteCharacters.length; i++) {
                byteNumbers[i] = byteCharacters.charCodeAt(i);
            }
            const byteArray = new Uint8Array(byteNumbers);
            const blob = new Blob([byteArray], { type: 'application/pdf' });
            const url = URL.createObjectURL(blob);
            this.pdfUrl = this.sanitizer.bypassSecurityTrustResourceUrl(url);
            this.loading = false;
        } catch (error) {
            console.error('Erreur lors de la préparation de l\'aperçu PDF:', error);
            this.loading = false;
        }
    }

    download(): void {
        const byteCharacters = atob(this.data.pdfBase64);
        const byteNumbers = new Array(byteCharacters.length);
        for (let i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
        }
        const byteArray = new Uint8Array(byteNumbers);
        const blob = new Blob([byteArray], { type: 'application/pdf' });

        const url = window.URL.createObjectURL(blob);
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
