import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { RemboursementService } from '../../../service/remboursement.service';
import { LayoutService } from '../../../../../shared/layout/service/layout.service';
import { PrisEnChargeService } from '../../../service/prise-en-charge.service';
import { CentreSanteService } from '../../../service/centre-sante.service';
import { EmployeeService } from '../../../../employee/service/employee.service';
import { ObjetFactureService, ObjetRemboursement } from '../../../service/objet-facture.service';
import { PrisEnCharge } from '../../../model/prise-en-charge.model';
import { CentreSante } from '../../../model/centre-sante.model';
import { Employee } from '../../../../employee/model/employee.model';

// Interface pour une demande stockée localement
export interface DemandeLocal {
    id: string;                  // UUID temporaire
    emp_code: number;
    emp_nom: string;
    emp_prenom: string;
    emp_imarmp: string;
    pec_code: number;
    pec_num: string;
    beneficiaire_type: string;
    beneficiaire_nom: string;
    cen_code: number;
    cen_nom: string;
    obj_code: number;
    obj_article: string;
    rem_montant: number;
    rem_montant_lettre: string;
    fac_num: string;
    fac_date: string;
    pieces: string[];
}

@Component({
    selector: 'app-ajout-demande',
    standalone: true,
    imports: [CommonModule, FormsModule],
    templateUrl: './ajout.html',
    styleUrls: ['./ajout.scss']
})
export class AjoutDemandeComponent implements OnInit {
    private readonly router = inject(Router);
    private readonly rembService = inject(RemboursementService);
    private readonly layoutService = inject(LayoutService);
    private readonly pecService = inject(PrisEnChargeService);
    private readonly centreService = inject(CentreSanteService);
    private readonly employeeService = inject(EmployeeService);
    private readonly objetFactureService = inject(ObjetFactureService);

    // ========== MODE ==========
    demandeMode: 'agent' | 'centre' = 'agent';

    // ========== PANIER LOCAL ==========
    demandesLocales: DemandeLocal[] = [];
    editingId: string | null = null;

    // ========== Agent/Centre Lock & Facture Sharing ==========
    get isAgentLocked(): boolean {
        return this.demandeMode === 'agent' && this.demandesLocales.length > 0;
    }

    get isCentreLocked(): boolean {
        return this.demandeMode === 'centre' && this.demandesLocales.length > 0;
    }

    get existingFactures(): { fac_num: string; fac_date: string }[] {
        const unique = new Map<string, { fac_num: string; fac_date: string }>();
        this.demandesLocales.forEach(d => {
            if (d.fac_num && !unique.has(d.fac_num)) {
                unique.set(d.fac_num, { fac_num: d.fac_num, fac_date: d.fac_date });
            }
        });
        return Array.from(unique.values());
    }

    selectedFactureId: string | null = null; // null = nouvelle facture

    // ========== Données formulaire ==========
    demande = {
        rem_montant: 0,
        rem_montant_lettre: '',
        obj_code: null as number | null,
        pec_code: null as number | null,
        has_ordonnance: false,
        has_facture: false,
        emp_code: null as number | null,
        pieces: [] as string[]
    };
    factureData = { fac_num: '', fac_date: '' };

    // Employee
    employees: Employee[] = [];
    filteredEmployees: Employee[] = [];
    selectedEmployee: Employee | null = null;
    empSearchText = '';
    showEmpDropdown = false;

    // PECs
    pecs: PrisEnCharge[] = [];
    selectedPec: PrisEnCharge | null = null;

    // Centres & Articles
    centres: CentreSante[] = [];
    selectedCentre: CentreSante | null = null;  // Pour mode Centre
    filteredCentres: CentreSante[] = [];  // Pour dropdown mode Centre
    showCentreDropdown = false;  // Pour dropdown mode Centre
    filteredCentresModal: CentreSante[] = [];
    centreSearchText = '';
    showCentreModalDropdown = false;
    selectedCentreModal: CentreSante | null = null;

    objets: ObjetRemboursement[] = [];
    filteredArticles: ObjetRemboursement[] = [];
    selectedArticle: ObjetRemboursement | null = null;
    articleSearchText = '';
    showArticleDropdown = false;

    selectedPieces: { [key: string]: boolean } = {
        Ordonnance: false,
        Facture: false
    };

    // ========== Modals ==========
    showPreviewModal = false;     // Preview avant ajout au panier
    showPecModal = false;         // Validation PEC
    showFinalModal = false;       // Récap final avant envoi

    pecModalData = { pec_date_arrive: '', pec_date_depart: '', cen_code: null as number | null };

    // UI State
    loading = false;
    errorMsg = '';

    ngOnInit() {
        this.layoutService.setTitle('Nouvelle Demande de Remboursement');
        this.loadEmployees();
        this.loadCentres();
        this.loadObjets();
        const today = new Date().toISOString().split('T')[0];
        this.factureData.fac_date = today;
        this.pecModalData.pec_date_arrive = today;
    }

    loadEmployees() {
        this.employeeService.getEmployees().subscribe({
            next: (emps) => { this.employees = emps; this.filteredEmployees = emps; },
            error: (err) => console.error('Erreur chargement employés', err)
        });
    }

    loadCentres() {
        this.centreService.getCentres().subscribe({
            next: (res) => {
                this.centres = res || [];
                this.filteredCentres = this.centres;
                this.filteredCentresModal = this.centres;
            },
            error: (err) => console.error('Erreur chargement centres', err)
        });
    }

    loadObjets() {
        this.objetFactureService.getObjets().subscribe({
            next: (res) => { this.objets = res || []; this.filteredArticles = this.objets; },
            error: (err) => console.error('Erreur chargement objets', err)
        });
    }

    // ========== Centre Modal Search ==========
    onCentreModalFocus() { this.showCentreModalDropdown = true; }
    onCentreModalBlur() { setTimeout(() => this.showCentreModalDropdown = false, 200); }

    filterCentresModal() {
        const s = this.centreSearchText.toLowerCase();
        this.filteredCentresModal = this.centres.filter(c => c.cen_nom.toLowerCase().includes(s));
    }

    selectCentreModal(c: CentreSante) {
        this.selectedCentreModal = c;
        this.pecModalData.cen_code = c.cen_code ?? null;
        this.centreSearchText = '';
        this.showCentreModalDropdown = false;
    }

    clearCentreModal() {
        this.selectedCentreModal = null;
        this.pecModalData.cen_code = null;
        this.centreSearchText = '';
    }

    // ========== Employee Search ==========
    onEmpFocus() { this.showEmpDropdown = true; }
    onEmpBlur() { setTimeout(() => this.showEmpDropdown = false, 200); }

    filterEmployees() {
        const s = this.empSearchText.toLowerCase();
        this.filteredEmployees = this.employees.filter(e =>
            e.emp_nom?.toLowerCase().includes(s) ||
            e.emp_prenom?.toLowerCase().includes(s) ||
            e.emp_imarmp?.toLowerCase().includes(s)
        );
    }

    selectEmployee(emp: Employee) {
        this.selectedEmployee = emp;
        this.demande.emp_code = emp.emp_code;
        this.empSearchText = `${emp.emp_nom} ${emp.emp_prenom}`;
        this.showEmpDropdown = false;
        this.loadPecs();
    }

    // ========== MODE SWITCHING ==========
    onModeChange() {
        // Reset form when switching modes
        this.resetForm();
        this.demandesLocales = [];
        this.selectedEmployee = null;
        this.selectedCentre = null;
        this.empSearchText = '';
        this.pecs = [];
    }

    onCentreSelect(centre: CentreSante | null) {
        this.selectedCentre = centre;
        if (centre) {
            this.loadPecs();
        } else {
            this.pecs = [];
        }
    }

    // Centre dropdown helpers
    filterCentres() {
        const searchTerm = this.centreSearchText.toLowerCase();
        if (!searchTerm) {
            this.filteredCentres = this.centres;
        } else {
            this.filteredCentres = this.centres.filter(c =>
                c.cen_nom.toLowerCase().includes(searchTerm) ||
                (c.tp_cen && c.tp_cen.toLowerCase().includes(searchTerm))
            );
        }
    }

    selectCentre(centre: CentreSante) {
        this.selectedCentre = centre;
        this.centreSearchText = centre.cen_nom;
        this.showCentreDropdown = false;
        this.onCentreSelect(centre);
    }

    clearCentre() {
        this.selectedCentre = null;
        this.centreSearchText = '';
        this.filteredCentres = this.centres;
        this.onCentreSelect(null);
    }

    onCentreBlur() {
        setTimeout(() => {
            this.showCentreDropdown = false;
            // Si aucun centre sélectionné, restaurer le texte
            if (!this.selectedCentre) {
                this.centreSearchText = '';
            }
        }, 200);
    }

    loadPecs() {
        if (this.demandeMode === 'agent') {
            // Mode Agent: filtrer par emp_code
            if (!this.demande.emp_code) return;
            this.rembService.getMyPecs(this.demande.emp_code).subscribe({
                next: (res) => this.pecs = res || [],
                error: (err) => console.error('Erreur chargement PECs', err)
            });
        } else if (this.demandeMode === 'centre') {
            // Mode Centre: logique spécifique
            if (!this.selectedCentre) return;

            this.pecService.getAll().subscribe({
                next: (res) => {
                    this.pecs = (res || []).filter(p => {
                        const isApproved = p.pec_approuver === true || p.pec_approuver === 't';

                        // PEC NON validée : toujours afficher (le centre choisi deviendra son centre)
                        if (!isApproved) return true;

                        // PEC VALIDÉE : afficher seulement si même centre
                        return p.cen_code === this.selectedCentre?.cen_code;
                    });
                },
                error: (err) => console.error('Erreur chargement PECs', err)
            });
        }
    }

    // ========== PEC Selection ==========
    onPecSelect(pecCode: any) {
        if (!pecCode) { this.selectedPec = null; return; }
        this.selectedPec = this.pecs.find(p => Number(p.pec_code) === Number(pecCode)) || null;

        if (this.selectedPec && !this.isPecApproved(this.selectedPec)) {
            // Si mode Centre, pré-remplir le centre et le verrouiller
            if (this.demandeMode === 'centre' && this.selectedCentre) {
                this.pecModalData.cen_code = this.selectedCentre.cen_code ?? null;
                this.selectedCentreModal = this.selectedCentre;
            }
            this.showPecModal = true;
        }
    }

    isPecApproved(pec: PrisEnCharge): boolean {
        return pec.pec_approuver === true || pec.pec_approuver === 't';
    }

    closePecModal() {
        this.showPecModal = false;
        if (this.selectedPec && !this.isPecApproved(this.selectedPec)) {
            this.demande.pec_code = null;
            this.selectedPec = null;
        }
    }

    confirmPecApproval() {
        if (!this.selectedPec?.pec_code || !this.pecModalData.pec_date_arrive) {
            this.errorMsg = 'Date d\'arrivée requise';
            return;
        }
        // Centre requis seulement si pas déjà dans PEC
        const cenCode = this.selectedPec.cen_code || this.pecModalData.cen_code;
        if (!cenCode) {
            this.errorMsg = 'Veuillez sélectionner un centre de santé';
            return;
        }
        this.loading = true;
        this.pecService.approuver(this.selectedPec.pec_code, {
            pec_date_arrive: this.pecModalData.pec_date_arrive,
            pec_date_depart: this.pecModalData.pec_date_depart || undefined,
            cen_code: cenCode
        }).subscribe({
            next: () => {
                if (this.selectedPec) {
                    this.selectedPec.pec_approuver = true;
                    this.selectedPec.cen_code = cenCode;
                    const centre = this.centres.find(c => c.cen_code === cenCode);
                    this.selectedPec.cen_nom = centre?.cen_nom || this.selectedPec.cen_nom || '';
                }
                this.showPecModal = false;
                this.loading = false;
            },
            error: (err) => {
                this.errorMsg = err?.error?.messages?.error || 'Erreur validation PEC';
                this.loading = false;
            }
        });
    }

    // ========== Article Search ==========
    onArticleFocus() { this.showArticleDropdown = true; }
    onArticleBlur() { setTimeout(() => this.showArticleDropdown = false, 200); }

    filterArticles() {
        const s = this.articleSearchText.toLowerCase();
        this.filteredArticles = this.objets.filter(o => o.obj_article.toLowerCase().includes(s));
    }

    selectArticle(obj: ObjetRemboursement) {
        this.selectedArticle = obj;
        this.demande.obj_code = obj.obj_code ?? null;
        this.articleSearchText = '';
        this.showArticleDropdown = false;
    }

    clearArticle() {
        this.selectedArticle = null;
        this.demande.obj_code = null;
    }

    // ========== Montant ==========
    onMontantChange() {
        this.demande.rem_montant_lettre = this.numberToWords(this.demande.rem_montant);
    }

    numberToWords(num: number): string {
        if (!num || num <= 0) return '';
        const units = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix',
            'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        const tens = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'];

        const convert = (n: number): string => {
            if (n < 20) return units[n];
            if (n < 100) {
                const t = Math.floor(n / 10), u = n % 10;
                if (t === 7 || t === 9) return tens[t] + '-' + units[10 + u];
                return tens[t] + (u ? '-' + units[u] : '');
            }
            if (n < 1000) {
                const h = Math.floor(n / 100), r = n % 100;
                return (h === 1 ? 'cent' : units[h] + ' cent') + (r ? ' ' + convert(r) : '');
            }
            if (n < 1000000) {
                const k = Math.floor(n / 1000), r = n % 1000;
                return (k === 1 ? 'mille' : convert(k) + ' mille') + (r ? ' ' + convert(r) : '');
            }
            return num.toString();
        };
        return convert(num) + ' ariary';
    }

    // ========== AJOUTER AU PANIER (Preview) ==========
    openPreview() {
        this.errorMsg = '';

        // Validation selon le mode
        if (this.demandeMode === 'agent') {
            if (!this.selectedEmployee || !this.selectedPec || !this.selectedArticle || !this.demande.rem_montant) {
                this.errorMsg = 'Veuillez remplir tous les champs obligatoires';
                return;
            }
        } else { // mode centre
            if (!this.selectedCentre || !this.selectedPec || !this.selectedArticle || !this.demande.rem_montant) {
                this.errorMsg = 'Veuillez remplir tous les champs obligatoires';
                return;
            }
        }

        if (!this.isPecApproved(this.selectedPec)) {
            this.errorMsg = 'La PEC doit être validée';
            return;
        }
        if (this.selectedPieces['Facture'] && !this.factureData.fac_num) {
            this.errorMsg = 'Numéro de facture requis';
            return;
        }
        this.demande.pieces = Object.keys(this.selectedPieces).filter(k => this.selectedPieces[k]);
        this.showPreviewModal = true;
    }

    closePreview() { this.showPreviewModal = false; }

    // Valider et ajouter au panier
    validateAndAddToCart() {
        // Construire l'objet selon le mode
        const local: DemandeLocal = {
            id: this.editingId || this.generateId(),
            emp_code: this.demandeMode === 'agent'
                ? this.selectedEmployee!.emp_code
                : this.selectedPec!.emp_code || 0,
            emp_nom: this.demandeMode === 'agent'
                ? (this.selectedEmployee!.emp_nom || '')
                : (this.selectedPec!.nom_emp || ''),
            emp_prenom: this.demandeMode === 'agent'
                ? (this.selectedEmployee!.emp_prenom || '')
                : (this.selectedPec!.prenom_emp || ''),
            emp_imarmp: this.demandeMode === 'agent'
                ? (this.selectedEmployee!.emp_imarmp || '')
                : '',
            pec_code: this.selectedPec!.pec_code!,
            pec_num: this.selectedPec!.pec_num || '',
            beneficiaire_type: this.selectedPec!.beneficiaire_type || 'AGENT',
            beneficiaire_nom: this.selectedPec!.beneficiaire_nom || '',
            cen_code: this.demandeMode === 'agent'
                ? (this.selectedPec!.cen_code!)
                : (this.selectedCentre!.cen_code!),
            cen_nom: this.demandeMode === 'agent'
                ? (this.selectedPec!.cen_nom || '')
                : (this.selectedCentre!.cen_nom || ''),
            obj_code: this.selectedArticle!.obj_code!,
            obj_article: this.selectedArticle!.obj_article || '',
            rem_montant: this.demande.rem_montant,
            rem_montant_lettre: this.demande.rem_montant_lettre,
            fac_num: this.factureData.fac_num,
            fac_date: this.factureData.fac_date,
            pieces: this.demande.pieces
        };

        if (this.editingId) {
            const idx = this.demandesLocales.findIndex(d => d.id === this.editingId);
            if (idx >= 0) this.demandesLocales[idx] = local;
            this.editingId = null;
        } else {
            this.demandesLocales.push(local);
        }

        this.showPreviewModal = false;
        this.resetForm();
    }

    generateId(): string {
        return 'dem_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    // ========== GESTION PANIER ==========
    removeFromCart(id: string) {
        this.demandesLocales = this.demandesLocales.filter(d => d.id !== id);
    }

    editFromCart(dem: DemandeLocal) {
        this.editingId = dem.id;
        // Restaurer les données dans le formulaire
        this.selectedEmployee = this.employees.find(e => e.emp_code === dem.emp_code) || null;
        this.empSearchText = `${dem.emp_nom} ${dem.emp_prenom}`;
        this.demande.emp_code = dem.emp_code;
        this.loadPecs();

        setTimeout(() => {
            this.selectedPec = this.pecs.find(p => p.pec_code === dem.pec_code) || null;
            this.demande.pec_code = dem.pec_code;
        }, 500);

        this.selectedArticle = this.objets.find(o => o.obj_code === dem.obj_code) || null;
        this.demande.obj_code = dem.obj_code;
        this.demande.rem_montant = dem.rem_montant;
        this.demande.rem_montant_lettre = dem.rem_montant_lettre;
        this.factureData.fac_num = dem.fac_num;
        this.factureData.fac_date = dem.fac_date;
        this.selectedPieces['Ordonnance'] = dem.pieces.includes('Ordonnance');
        this.selectedPieces['Facture'] = dem.pieces.includes('Facture');
    }

    resetForm() {
        this.demande = {
            rem_montant: 0, rem_montant_lettre: '', obj_code: null, pec_code: null,
            has_ordonnance: false, has_facture: false, emp_code: this.demande.emp_code, pieces: []
        };
        this.selectedPec = null;
        this.selectedArticle = null;
        this.factureData = { fac_num: '', fac_date: new Date().toISOString().split('T')[0] };
        this.selectedPieces = { Ordonnance: false, Facture: false };
        this.editingId = null;
    }

    clearAll() {
        if (this.isAgentLocked) {
            // Si panier non vide, on ne peut pas changer d'agent
            this.resetForm();
            return;
        }
        this.resetForm();
        this.selectedEmployee = null;
        this.empSearchText = '';
        this.demande.emp_code = null;
        this.pecs = [];
        this.demandesLocales = [];
    }

    // Sélectionner une facture existante ou nouvelle
    onFactureSelect(value: string) {
        if (value === 'new') {
            this.selectedFactureId = null;
            this.factureData = { fac_num: '', fac_date: new Date().toISOString().split('T')[0] };
        } else {
            this.selectedFactureId = value;
            const existing = this.existingFactures.find(f => f.fac_num === value);
            if (existing) {
                this.factureData = { ...existing };
            }
        }
    }

    // ========== ENVOI FINAL ==========
    openFinalModal() {
        if (this.demandesLocales.length === 0) {
            this.errorMsg = 'Aucune demande à envoyer';
            return;
        }
        this.showFinalModal = true;
    }

    closeFinalModal() { this.showFinalModal = false; }

    async submitAll() {
        this.loading = true;
        this.errorMsg = '';

        try {
            // Envoyer le batch au backend
            const payload = this.demandesLocales.map(d => ({
                emp_code: d.emp_code,
                pec_code: d.pec_code,
                obj_code: d.obj_code,
                rem_montant: d.rem_montant,
                rem_montant_lettre: d.rem_montant_lettre,
                rem_is_centre: this.demandeMode === 'centre',  // Définir le type
                fac_num: d.fac_num,
                fac_date: d.fac_date,
                pieces: d.pieces
            }));

            this.rembService.createBatch(payload).subscribe({
                next: (res: any) => {
                    alert(`${this.demandesLocales.length} demande(s) créée(s) avec succès!`);
                    this.demandesLocales = [];
                    this.showFinalModal = false;
                    this.router.navigate(['/remboursement/demandes']);
                },
                error: (err: any) => {
                    this.errorMsg = err?.error?.messages?.error || 'Erreur lors de l\'envoi';
                    this.loading = false;
                }
            });
        } catch (err: any) {
            this.errorMsg = err?.message || 'Erreur';
            this.loading = false;
        }
    }

    cancel() {
        this.router.navigate(['/remboursement/demandes']);
    }
}
