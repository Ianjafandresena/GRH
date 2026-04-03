import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { CongeService } from '../../service/conge.service';
import { InterruptionService, InterruptionPreview } from '../../service/interruption.service';
import { LayoutService } from '../../../../shared/layout/service/layout.service';

@Component({
    selector: 'app-interruption',
    standalone: true,
    imports: [CommonModule, ReactiveFormsModule],
    templateUrl: './interruption.html',
    styleUrls: ['./interruption.scss']
})
export class InterruptionComponent implements OnInit {
    private readonly fb = inject(FormBuilder);
    private readonly router = inject(Router);
    private readonly route = inject(ActivatedRoute);
    private readonly congeService = inject(CongeService);
    private readonly interruptionService = inject(InterruptionService);
    private readonly layoutService = inject(LayoutService);

    // Form
    interruptionForm: FormGroup;

    // Selected leave
    conge: any = null;
    cngCode: number | null = null;

    // Motifs list
    motifs: string[] = ['Nécessité de service'];

    // Preview data
    preview: InterruptionPreview | null = null;

    // UI state
    loading = false;
    errorMsg = '';
    successMsg = '';
    showPreview = false;

    constructor() {
        this.interruptionForm = this.fb.group({
            interupDate: ['', Validators.required],
            interupMotif: ['Nécessité de service', Validators.required]
        });
    }

    ngOnInit() {
        this.layoutService.setTitle('Interruption de Congé');

        // Check if we have a cng_code parameter
        this.route.params.subscribe(params => {
            if (params['id']) {
                this.cngCode = +params['id'];
                this.loadConge(this.cngCode);
            }
        });
    }

    get f() {
        return this.interruptionForm.controls;
    }

    loadConge(cngCode: number) {
        this.loading = true;
        this.congeService.getCongeDetail(cngCode).subscribe({
            next: (data) => {
                this.conge = data.conge;
                this.loading = false;

                // Check if already interrupted
                this.interruptionService.getByConge(cngCode).subscribe(interruption => {
                    if (interruption) {
                        this.errorMsg = 'Ce congé a déjà été interrompu le ' + interruption.interup_date;
                    }
                });
            },
            error: () => {
                this.errorMsg = 'Impossible de charger le congé';
                this.loading = false;
            }
        });
    }

    /**
     * Validate and preview restoration before confirming
     */
    previewRestoration() {
        if (!this.cngCode || this.interruptionForm.invalid) {
            this.errorMsg = 'Veuillez sélectionner une date d\'interruption';
            return;
        }

        const interupDate = this.f['interupDate'].value;

        // Validate date is within leave period
        const debut = new Date(this.conge.cng_debut);
        const fin = new Date(this.conge.cng_fin);
        const interup = new Date(interupDate);

        if (interup < debut || interup > fin) {
            this.errorMsg = `La date doit être entre ${this.conge.cng_debut} et ${this.conge.cng_fin}`;
            return;
        }

        this.loading = true;
        this.errorMsg = '';

        this.interruptionService.previewRestoration(this.cngCode, interupDate).subscribe({
            next: (preview) => {
                this.preview = preview;
                this.showPreview = true;
                this.loading = false;
            },
            error: (err) => {
                this.errorMsg = err?.error?.messages?.error || 'Erreur lors du calcul';
                this.loading = false;
            }
        });
    }

    /**
     * Confirm and create the interruption
     */
    confirmInterruption() {
        if (!this.cngCode || this.interruptionForm.invalid) return;

        this.loading = true;
        this.errorMsg = '';

        const formValue = this.interruptionForm.value;

        this.interruptionService.create({
            cng_code: this.cngCode,
            interup_date: formValue.interupDate,
            interup_motif: formValue.interupMotif
        }).subscribe({
            next: (result) => {
                this.successMsg = result.message || 'Interruption enregistrée avec succès';
                this.loading = false;

                // Redirect to detail after 2 seconds
                setTimeout(() => {
                    this.router.navigate(['/conge/detail', this.cngCode]);
                }, 2000);
            },
            error: (err) => {
                this.errorMsg = err?.error?.messages?.error || 'Erreur lors de l\'interruption';
                this.loading = false;
            }
        });
    }

    /**
     * Cancel and go back
     */
    cancel() {
        if (this.cngCode) {
            this.router.navigate(['/conge/detail', this.cngCode]);
        } else {
            this.router.navigate(['/conge']);
        }
    }

    /**
     * Reset preview to modify date
     */
    resetPreview() {
        this.showPreview = false;
        this.preview = null;
    }
}

