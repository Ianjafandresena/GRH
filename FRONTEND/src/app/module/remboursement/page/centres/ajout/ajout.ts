import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { MatInputModule } from '@angular/material/input';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';

import { CentreSanteService } from '../../../service/centre-sante.service';
import { LayoutService } from '../../../../../shared/layout/service/layout.service';
import { CentreSante, Convention } from '../../../model/centre-sante.model';

@Component({
    selector: 'app-ajout-centre',
    standalone: true,
    imports: [
        CommonModule,
        FormsModule,
        MatInputModule,
        MatFormFieldModule,
        MatSelectModule,
        MatButtonModule,
        MatCardModule,
        MatIconModule
    ],
    templateUrl: './ajout.html',
    styleUrls: ['./ajout.scss']
})
export class AjoutCentreComponent implements OnInit {
    private readonly router = inject(Router);
    private readonly route = inject(ActivatedRoute);
    private readonly centreService = inject(CentreSanteService);
    private readonly layoutService = inject(LayoutService);

    centre: Partial<CentreSante> = { cen_nom: '', cen_adresse: '', cen_ville: '', cnv_code: undefined };
    conventions: Convention[] = [];
    loading = false;
    errorMsg = '';
    isEdit = false;
    centerId: number | null = null;

    ngOnInit() {
        this.loadConventions();

        const id = this.route.snapshot.paramMap.get('id');
        if (id) {
            this.isEdit = true;
            this.centerId = +id;
            this.layoutService.setTitle('Modifier Centre de Santé');
            this.loadCentre(this.centerId);
        } else {
            this.layoutService.setTitle('Nouveau Centre de Santé');
        }
    }

    loadConventions() {
        this.centreService.getConventions().subscribe({
            next: (list) => this.conventions = list || [],
            error: () => this.errorMsg = 'Erreur chargement conventions'
        });
    }

    loadCentre(id: number) {
        this.loading = true;
        this.centreService.getCentre(id).subscribe({
            next: (data) => {
                this.centre = data;
                this.loading = false;
            },
            error: (err) => {
                this.errorMsg = 'Erreur chargement centre';
                this.loading = false;
            }
        });
    }

    submit() {
        if (!this.centre.cen_nom || !this.centre.cnv_code) {
            this.errorMsg = 'Veuillez remplir les champs obligatoires (*)';
            return;
        }

        this.loading = true;
        this.errorMsg = '';

        const obs = this.isEdit && this.centerId
            ? this.centreService.updateCentre(this.centerId, this.centre)
            : this.centreService.createCentre(this.centre as CentreSante);

        obs.subscribe({
            next: () => this.router.navigate(['/remboursement/centres']),
            error: (err) => {
                this.errorMsg = err.error?.messages?.error || 'Erreur enregistrement';
                this.loading = false;
            }
        });
    }

    cancel() {
        this.router.navigate(['/remboursement/centres']);
    }
}
