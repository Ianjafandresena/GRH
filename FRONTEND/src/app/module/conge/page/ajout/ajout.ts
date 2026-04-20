import { Component, OnInit, inject } from '@angular/core';
import { FormBuilder, FormGroup, FormArray, Validators, AbstractControl, ReactiveFormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';  // ➕ AJOUTÉ import Router
import { EmployeeService } from '../../../employee/service/employee.service';
import { Employee } from '../../../employee/model/employee.model';
import { CongeService } from '../../service/conge.service';
import { Conge, TypeConge } from '../../model/conge.model';
import { InterimConge } from '../../model/interimconge.model';
import { Region } from '../../model/region.model';
import { LayoutService } from '../../../../shared/layout/service/layout.service';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { PreviewCongeDialogComponent } from './preview-dialog/preview-dialog';

@Component({
  selector: 'app-ajout-conge',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, MatDialogModule],
  templateUrl: './ajout.html',
  styleUrls: ['./ajout.scss']
})
export class AjoutCongeComponent implements OnInit {
  private readonly layoutService = inject(LayoutService);
  private readonly dialog = inject(MatDialog);
  private readonly router = inject(Router);  // ➕ AJOUTÉ

  // ============ TYPE D'ABSENCE (Fusion Congé/Permission) ============
  absenceType: 'conge' | 'permission' = 'conge';

  // ============ CONGÉ (existant - NE PAS MODIFIER) ============
  congeForm: FormGroup;
  employees: Employee[] = [];
  filteredEmployees: Employee[] = [];
  selectedEmployee: Employee | null = null;
  regions: Region[] = [];
  filteredRegions: Region[] = [];
  selectedRegion: Region | null = null;
  typesConge: TypeConge[] = [];
  calculatedNbJours: number | null = null;
  loading = false;
  filteredInterims: Employee[][] = [];

  // showRecap: boolean = false; // Removed
  lastCongeDraft: any = null;
  motifLibelle: string = '';

  dernierSolde: any = null;
  joursRestants: number | null = null;
  anneeSolde: string = '';
  numeroDecision: string = '';

  showEmpDropdown = false;
  showRegionDropdown = false;
  showInterimDropdowns: boolean[] = [];

  // ============ PERMISSION (nouveau) ============
  permissionForm: FormGroup;
  permissionCalculatedHours: number | null = null;
  permissionCalculatedDays: number | null = null;
  permissionSelectedEmployee: Employee | null = null;
  permissionFilteredEmployees: Employee[] = [];
  permissionShowEmpDropdown = false;
  permissionLoading = false;

  // Intérimaires Permission
  permissionFilteredInterims: Employee[][] = [];
  permissionShowInterimDropdowns: boolean[] = [];

  constructor(
    private fb: FormBuilder,
    private employeeService: EmployeeService,
    private congeService: CongeService
  ) {
    // Formulaire Congé (existant)
    this.congeForm = this.fb.group({
      emp_search: ['', Validators.required],
      typ_code: [null, Validators.required],
      region_search: ['', Validators.required],
      cng_debut: [null, Validators.required],
      cng_fin: [null, Validators.required],
      cng_demande: [new Date().toISOString().split('T')[0]], // Default to today
      interims: this.fb.array([this.fb.group({
        interim_search: [''],
        selectedInterim: [null]
      })])
    });

    // Formulaire Permission (nouveau format Matin/Après-midi)
    this.permissionForm = this.fb.group({
      emp_search: ['', Validators.required],
      prm_date_debut: [new Date().toISOString().split('T')[0], Validators.required],
      prm_moment_debut: ['matin', Validators.required],
      prm_date_fin: [new Date().toISOString().split('T')[0], Validators.required],
      prm_moment_fin: ['matin', Validators.required],
      interims: this.fb.array([this.fb.group({
        interim_search: [''],
        selectedInterim: [null]
      })])
    });
  }

  get interims(): FormArray {
    return this.congeForm.get('interims') as FormArray;
  }

  get permissionInterims(): FormArray {
    return this.permissionForm.get('interims') as FormArray;
  }

  ngOnInit() {
    this.layoutService.setTitle('Gestion des Absences');
    this.employeeService.getEmployees().subscribe((employees: Employee[]) => {
      this.employees = employees ?? [];
      this.filteredEmployees = employees ?? [];
      this.filteredInterims = [this.employees];
      this.showInterimDropdowns = [false];
      this.interims.controls.forEach((ctrl, i) => this.interimSearchListener(ctrl, i));

      // Init interims permission
      this.permissionFilteredInterims = [this.employees];
      this.permissionShowInterimDropdowns = [false];
      this.permissionInterims.controls.forEach((ctrl, i) => this.permissionInterimSearchListener(ctrl, i));
    });

    this.congeService.getTypesConge().subscribe((types: TypeConge[]) => {
      this.typesConge = types;
    });

    this.congeService.getRegions().subscribe((regions: Region[]) => {
      this.regions = regions;
      this.filteredRegions = regions;
    });

    this.congeForm.get('emp_search')?.valueChanges.subscribe((val: string) => {
      this.filterEmployees(val ?? '');
      if (!val) {
        this.selectedEmployee = null;
        this.filteredEmployees = this.employees; // Show all if empty
      }
    });

    this.congeForm.get('region_search')?.valueChanges.subscribe((val: string) => {
      const filterVal = val ? val.toLowerCase() : '';
      this.filteredRegions = this.regions.filter(region =>
        region.reg_nom.toLowerCase().includes(filterVal)
      );
      if (!val) {
        this.selectedRegion = null;
        this.filteredRegions = this.regions;
      }
    });

    this.congeForm.get('cng_debut')?.valueChanges.subscribe(() => this.updateNbJours());
    this.congeForm.get('cng_fin')?.valueChanges.subscribe(() => this.updateNbJours());
  }

  // Employee Dropdown Handlers
  onEmpFocus() {
    this.showEmpDropdown = true;
    if (!this.congeForm.get('emp_search')?.value) {
      this.filteredEmployees = this.employees;
    }
  }

  onEmpBlur() {
    setTimeout(() => { this.showEmpDropdown = false; }, 200);
  }

  filterEmployees(value: string) {
    const filterVal = value ? value.toLowerCase() : '';
    this.filteredEmployees = this.employees.filter(emp =>
    (`${emp.emp_nom} ${emp.emp_prenom}`.toLowerCase().includes(filterVal) ||
      emp.emp_im_armp.toLowerCase().includes(filterVal))
    );
    this.showEmpDropdown = true;
  }

  selectEmployee(emp: Employee) {
    this.selectedEmployee = emp;
    this.congeForm.patchValue({ emp_search: `${emp.emp_nom} ${emp.emp_prenom}` }, { emitEvent: false });
    this.showEmpDropdown = false;
    // Appel direct à l'API métier unique !
    this.congeService.getLastSoldeDispo(emp.emp_code).subscribe((solde) => {
      if (solde) {
        this.dernierSolde = solde;
        this.joursRestants = solde.sld_restant;
        this.anneeSolde = solde.sld_anne;
        this.numeroDecision = solde.dec_num;
      } else {
        this.dernierSolde = null;
        this.joursRestants = null;
        this.anneeSolde = '';
        this.numeroDecision = '';
      }
    }, () => {
      this.dernierSolde = null;
      this.joursRestants = null;
      this.anneeSolde = '';
      this.numeroDecision = '';
    });
  }

  // Region Dropdown Handlers
  onRegionFocus() {
    this.showRegionDropdown = true;
    if (!this.congeForm.get('region_search')?.value) {
      this.filteredRegions = this.regions;
    }
  }

  onRegionBlur() {
    setTimeout(() => { this.showRegionDropdown = false; }, 200);
  }

  selectRegion(region: Region) {
    this.selectedRegion = region;
    this.congeForm.patchValue({ region_search: region.reg_nom }, { emitEvent: false });
    this.showRegionDropdown = false;
  }

  updateNbJours() {
    const debut = this.congeForm.get('cng_debut')?.value ?? null;
    const fin = this.congeForm.get('cng_fin')?.value ?? null;
    if (debut && fin) {
      const d1 = new Date(debut);
      const d2 = new Date(fin);
      const diff = (d2.getTime() - d1.getTime()) / (1000 * 60 * 60 * 24);
      this.calculatedNbJours = diff >= 0 ? diff + 1 : null;
    } else {
      this.calculatedNbJours = null;
    }
  }

  // Interim Dropdown Handlers
  onInterimFocus(idx: number) {
    this.showInterimDropdowns[idx] = true;
    const ctrl = this.interims.at(idx);
    if (!ctrl.get('interim_search')?.value) {
      this.filteredInterims[idx] = this.employees.filter(e =>
        !this.selectedEmployee || e.emp_code !== this.selectedEmployee.emp_code
      );
    }
  }

  onInterimBlur(idx: number) {
    setTimeout(() => { this.showInterimDropdowns[idx] = false; }, 200);
  }

  interimSearchListener(ctrl: AbstractControl, idx: number) {
    ctrl.get('interim_search')?.valueChanges.subscribe((val: string) => {
      const filterVal = val ? val.toLowerCase() : '';
      this.filteredInterims[idx] = this.employees
        .filter(e =>
          (!this.selectedEmployee || e.emp_code !== this.selectedEmployee.emp_code) &&
          (`${e.emp_nom} ${e.emp_prenom}`.toLowerCase().includes(filterVal) ||
            e.emp_im_armp.toLowerCase().includes(filterVal))
        );
      this.showInterimDropdowns[idx] = true;
    });
  }

  selectInterim(idx: number, emp: Employee) {
    this.interims.at(idx).patchValue({ interim_search: `${emp.emp_nom} ${emp.emp_prenom}`, selectedInterim: emp }, { emitEvent: false });
    this.showInterimDropdowns[idx] = false;
  }

  addInterimField() {
    this.interims.push(this.fb.group({
      interim_search: [''],
      selectedInterim: [null]
    }));
    this.filteredInterims.push(this.employees);
    this.showInterimDropdowns.push(false);
    this.interimSearchListener(this.interims.at(this.interims.length - 1), this.interims.length - 1);
  }

  removeInterimField(idx: number) {
    this.interims.removeAt(idx);
    this.filteredInterims.splice(idx, 1);
    this.showInterimDropdowns.splice(idx, 1);
  }

  submit() {
    if (
      this.congeForm.invalid ||
      !this.selectedEmployee ||
      !this.selectedRegion ||
      this.calculatedNbJours == null ||
      this.calculatedNbJours < 1
    ) {
      this.layoutService.showErrorMessage("Veuillez remplir tous les champs");
      return;
    }
    const interimsData = this.interims.controls
      .map(ctrl => ctrl.get('selectedInterim')?.value)
      .filter(interim => !!interim);

    const typ = this.typesConge.find(t => t.typ_code === this.congeForm.value.typ_code);
    this.motifLibelle = typ ? typ.typ_appelation : '';
    this.lastCongeDraft = {
      typ_code: this.congeForm.value.typ_code,
      emp_code: this.selectedEmployee.emp_code,
      nom_emp: this.selectedEmployee.emp_nom + ' ' + this.selectedEmployee.emp_prenom,
      matricule: this.selectedEmployee.emp_im_armp,
      id_region: this.selectedRegion.reg_code,
      nom_region: this.selectedRegion.reg_nom,
      cng_debut: this.congeForm.value.cng_debut,
      cng_fin: this.congeForm.value.cng_fin,
      cng_nb_jour: this.calculatedNbJours,
      interims: interimsData,
      numeroDecision: this.numeroDecision,
      anneeSolde: this.anneeSolde,
      joursRestants: this.joursRestants
    };
    this.prepareDraft();

    // Open Dialog
    const dialogRef = this.dialog.open(PreviewCongeDialogComponent, {
      width: '600px',
      maxHeight: '90vh',
      data: {
        draft: this.lastCongeDraft,
        motif: this.motifLibelle
      }
    });

    dialogRef.afterClosed().subscribe(result => {
      if (result === true) {
        this.confirmEnvoi();
      }
    });
  }

  prepareDraft() {
    if (this.congeForm.invalid) return; // double check

    const interimsData = this.interims.controls
      .map(ctrl => ctrl.get('selectedInterim')?.value)
      .filter(interim => !!interim);

    const typ = this.typesConge.find(t => t.typ_code === this.congeForm.value.typ_code);
    this.motifLibelle = typ ? typ.typ_appelation : '';
    this.lastCongeDraft = {
      typ_code: this.congeForm.value.typ_code,
      emp_code: this.selectedEmployee?.emp_code,
      nom_emp: this.selectedEmployee?.emp_nom + ' ' + this.selectedEmployee?.emp_prenom,
      matricule: this.selectedEmployee?.emp_im_armp,
      id_region: this.selectedRegion?.reg_code,
      nom_region: this.selectedRegion?.reg_nom,
      cng_debut: this.congeForm.value.cng_debut,
      cng_fin: this.congeForm.value.cng_fin,
      cng_nb_jour: this.calculatedNbJours,
      interims: interimsData,
      numeroDecision: this.numeroDecision,
      anneeSolde: this.anneeSolde,
      joursRestants: this.joursRestants
    };
    // this.showRecap = true; // Removed
  }

  confirmEnvoi() {
    this.loading = true;
    const congePayload = {
      typ_code: this.lastCongeDraft.typ_code,
      emp_code: this.lastCongeDraft.emp_code,
      reg_code: this.lastCongeDraft.id_region,
      cng_debut: this.lastCongeDraft.cng_debut,
      cng_fin: this.lastCongeDraft.cng_fin,
      cng_nb_jour: this.lastCongeDraft.cng_nb_jour
    };
    console.log("Payload envoyé à l'API:", congePayload); // Pour traquer les champs manquants

    this.congeService.createConge(congePayload).subscribe({
      next: (congeRes: any) => {
        const cng_code = congeRes.cng_code;
        (this.lastCongeDraft.interims || []).forEach((interim: any) => {
          if (interim && interim.emp_code) {
            const interimPayload: InterimConge = {
              emp_code: interim.emp_code,
              cng_code: cng_code,
              int_debut: this.lastCongeDraft.cng_debut,
              int_fin: this.lastCongeDraft.cng_fin
            };
            this.congeService.createInterimConge(interimPayload).subscribe();
          }
        });
        this.loading = false;

        // 🔄 MODIFIÉ: Navigation au lieu d'alert
        this.layoutService.showSuccessMessage('Congé et intérimaires enregistrés avec succès !');
        this.router.navigate(['/conge/index']);
      },
      error: (errRes) => {
        this.loading = false;
        console.error('Erreur backend:', errRes);
        const msg = errRes?.error?.messages?.error || "Erreur lors de l'enregistrement du congé.";
        this.layoutService.showErrorMessage(msg);
      }
    });
  }



  resetAll() {
    // this.showRecap = false;
    this.congeForm.reset();
    this.selectedEmployee = null;
    this.selectedRegion = null;
    this.calculatedNbJours = null;
  }

  // ========== MÉTHODES PERMISSION (nouveau) ==========

  /**
   * Changement de type d'absence (Congé/Permission)
   */
  onTypeChange() {
    if (this.absenceType === 'permission') {
      this.updatePermissionDurations();
    }
  }

  /**
   * Filtrer employés pour permission
   */
  filterPermissionEmployees(value: string) {
    const filterVal = value ? value.toLowerCase() : '';
    this.permissionFilteredEmployees = this.employees.filter(emp =>
    (`${emp.emp_nom} ${emp.emp_prenom}`.toLowerCase().includes(filterVal) ||
      emp.emp_im_armp.toLowerCase().includes(filterVal))
    );
    this.permissionShowEmpDropdown = true;
  }

  onPermissionEmpFocus() {
    this.permissionShowEmpDropdown = true;
    if (!this.permissionForm.get('emp_search')?.value) {
      this.permissionFilteredEmployees = this.employees;
    }
  }

  onPermissionEmpBlur() {
    setTimeout(() => { this.permissionShowEmpDropdown = false; }, 200);
  }

  selectPermissionEmployee(emp: Employee) {
    this.permissionSelectedEmployee = emp;
    this.permissionForm.patchValue({ emp_search: `${emp.emp_nom} ${emp.emp_prenom}` }, { emitEvent: false });
    this.permissionShowEmpDropdown = false;
  }

  /**
   * Calculer durée permettant basée sur Matin/Après-midi (Heures de travail uniquement)
   */
  updatePermissionDurations() {
    const dDate = this.permissionForm.get('prm_date_debut')?.value;
    const dMom = this.permissionForm.get('prm_moment_debut')?.value;
    const fDate = this.permissionForm.get('prm_date_fin')?.value;
    const fMom = this.permissionForm.get('prm_moment_fin')?.value;

    if (!dDate || !dMom || !fDate || !fMom) {
      this.permissionCalculatedHours = null;
      this.permissionCalculatedDays = null;
      return;
    }

    const start = new Date(dDate);
    const end = new Date(fDate);

    if (end < start) {
      this.permissionCalculatedHours = 0;
      this.permissionCalculatedDays = 0;
      return;
    }

    const diffDays = Math.round((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24));
    let totalH = 0;

    if (diffDays === 0) {
      // Même jour
      if (dMom === 'matin' && fMom === 'matin') totalH = 4;
      else if (dMom === 'matin' && fMom === 'apres_midi') totalH = 8;
      else if (dMom === 'apres_midi' && fMom === 'apres_midi') totalH = 4;
      else totalH = 0; // Apres-midi à Matin sur même jour = invalide (0h)
    } else {
      // Jours différents
      // Heures jour 1
      totalH += (dMom === 'matin') ? 8 : 4;
      // Heures jour final
      totalH += (fMom === 'matin') ? 4 : 8;
      // Heures jours intermédiaires
      if (diffDays > 1) {
        totalH += (diffDays - 1) * 8;
      }
    }

    this.permissionCalculatedHours = parseFloat(totalH.toFixed(2));
    this.permissionCalculatedDays = parseFloat((totalH / 8).toFixed(2));
  }

  /**
   * Vérifier si la durée dépasse 8 heures (1 jour de travail)
   */
  get isDurationExceeded(): boolean {
    return (this.permissionCalculatedHours || 0) > 8;
  }

  /**
   * Soumettre permission
   */
  submitPermission() {
    if (this.permissionForm.invalid || !this.permissionSelectedEmployee) {
      this.layoutService.showErrorMessage('Veuillez remplir tous les champs');
      return;
    }
    this.updatePermissionDurations();

    if (this.isDurationExceeded) {
      this.layoutService.showErrorMessage('La durée d\'une permission ne peut pas dépasser 3 jours (24 heures)');
      return;
    }

    if ((this.permissionCalculatedHours || 0) <= 0) {
      this.layoutService.showErrorMessage('La date de fin doit être après la date de début');
      return;
    }

    // Convertir en format attendu par le backend (ISO string avec heures calculées)
    const dDate = this.permissionForm.get('prm_date_debut')?.value;
    const dMom = this.permissionForm.get('prm_moment_debut')?.value;
    const fDate = this.permissionForm.get('prm_date_fin')?.value;
    const fMom = this.permissionForm.get('prm_moment_fin')?.value;

    const startH = (dMom === 'matin') ? 8 : 12;
    const endH = (fMom === 'matin') ? 12 : 16;

    const startDate = new Date(dDate);
    startDate.setHours(startH, 0, 0, 0);

    const endDate = new Date(fDate);
    endDate.setHours(endH, 0, 0, 0);

    const payload = {
      emp_code: this.permissionSelectedEmployee.emp_code,
      prm_debut: startDate.toISOString(),
      prm_fin: endDate.toISOString(),
      prm_moment_debut: dMom,
      prm_moment_fin: fMom
    };

    this.permissionLoading = true;
    const interimsData = this.permissionInterims.controls
      .map(ctrl => ctrl.get('selectedInterim')?.value)
      .filter(interim => !!interim);

    this.congeService.createPermission(payload).subscribe({
      next: (res: any) => {
        const prm_code = res.prm_code;
        interimsData.forEach((interim: any) => {
          if (interim && interim.emp_code) {
            const interimPayload = {
              emp_code: interim.emp_code,
              prm_code: prm_code,
              int_prm_debut: payload.prm_debut,
              int_prm_fin: payload.prm_fin
            };
            this.congeService.createPermission(interimPayload as any).subscribe();
          }
        });

        this.permissionLoading = false;
        this.layoutService.showSuccessMessage('Permission enregistrée avec succès !');
        this.router.navigate(['/conge/index']);
      },
      error: (err) => {
        this.permissionLoading = false;
        const msg = err?.error?.messages?.error || 'Erreur lors de l\'enregistrement';
        this.layoutService.showErrorMessage(msg);
      }
    });
  }

  // ========== UTILS INTERIM PERMISSION ==========

  onPermissionInterimFocus(idx: number) {
    this.permissionShowInterimDropdowns[idx] = true;
    const ctrl = this.permissionInterims.at(idx);
    if (!ctrl.get('interim_search')?.value) {
      this.permissionFilteredInterims[idx] = this.employees.filter(e =>
        !this.permissionSelectedEmployee || e.emp_code !== this.permissionSelectedEmployee.emp_code
      );
    }
  }

  onPermissionInterimBlur(idx: number) {
    setTimeout(() => { this.permissionShowInterimDropdowns[idx] = false; }, 200);
  }

  permissionInterimSearchListener(ctrl: AbstractControl, idx: number) {
    ctrl.get('interim_search')?.valueChanges.subscribe((val: string) => {
      const filterVal = val ? val.toLowerCase() : '';
      this.permissionFilteredInterims[idx] = this.employees
        .filter(e =>
          (!this.permissionSelectedEmployee || e.emp_code !== this.permissionSelectedEmployee.emp_code) &&
          (`${e.emp_nom} ${e.emp_prenom}`.toLowerCase().includes(filterVal) ||
            e.emp_im_armp.toLowerCase().includes(filterVal))
        );
      this.permissionShowInterimDropdowns[idx] = true;
    });
  }

  selectPermissionInterim(idx: number, emp: Employee) {
    this.permissionInterims.at(idx).patchValue({
      interim_search: `${emp.emp_nom} ${emp.emp_prenom}`,
      selectedInterim: emp
    }, { emitEvent: false });
    this.permissionShowInterimDropdowns[idx] = false;
  }

  addPermissionInterimField() {
    const group = this.fb.group({
      interim_search: [''],
      selectedInterim: [null]
    });
    this.permissionInterims.push(group);
    this.permissionFilteredInterims.push(this.employees);
    this.permissionShowInterimDropdowns.push(false);
    this.permissionInterimSearchListener(group, this.permissionInterims.length - 1);
  }

  removePermissionInterimField(idx: number) {
    this.permissionInterims.removeAt(idx);
    this.permissionFilteredInterims.splice(idx, 1);
    this.permissionShowInterimDropdowns.splice(idx, 1);
  }

  resetPermission() {
    this.permissionForm.reset();
    this.permissionSelectedEmployee = null;
    this.permissionCalculatedHours = null;
    this.permissionCalculatedDays = null;
  }
}
