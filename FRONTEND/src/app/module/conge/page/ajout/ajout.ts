import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, FormArray, Validators, AbstractControl, ReactiveFormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { EmployeeService } from '../../../employee/service/employee.service';
import { Employee } from '../../../employee/model/employee.model';
import { CongeService } from '../../service/conge.service';
import { Conge, TypeConge } from '../../model/conge.model';
import { InterimConge } from '../../model/interimconge.model';
import { Region } from '../../model/region.model';

@Component({
  selector: 'app-ajout-conge',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './ajout.html',
  styleUrls: ['./ajout.css']
})
export class AjoutCongeComponent implements OnInit {
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
  errorMsg: string = '';
  filteredInterims: Employee[][] = [];

  showRecap: boolean = false;
  lastCongeDraft: any = null;
  motifLibelle: string = '';

  dernierSolde: any = null;
  joursRestants: number | null = null;
  anneeSolde: string = '';
  numeroDecision: string = '';

  constructor(
    private fb: FormBuilder,
    private employeeService: EmployeeService,
    private congeService: CongeService
  ) {
    this.congeForm = this.fb.group({
      emp_search: ['', Validators.required],
      typ_code: [null, Validators.required],
      region_search: ['', Validators.required],
      cng_debut: [null, Validators.required],
      cng_fin: [null, Validators.required],
      interims: this.fb.array([this.fb.group({
        interim_search: [''],
        selectedInterim: [null]
      })])
    });
  }

  get interims(): FormArray {
    return this.congeForm.get('interims') as FormArray;
  }

  ngOnInit() {
    this.employeeService.getEmployees().subscribe((employees: Employee[]) => {
      this.employees = employees ?? [];
      this.filteredEmployees = employees ?? [];
      this.filteredInterims = [this.employees];
      this.interims.controls.forEach((ctrl, i) => this.interimSearchListener(ctrl, i));
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
      if (!val) this.selectedEmployee = null;
    });

    this.congeForm.get('region_search')?.valueChanges.subscribe((val: string) => {
      const filterVal = val ? val.toLowerCase() : '';
      this.filteredRegions = this.regions.filter(region =>
        region.reg_nom.toLowerCase().includes(filterVal)
      );
      if (!val) this.selectedRegion = null;
    });

    this.congeForm.get('cng_debut')?.valueChanges.subscribe(() => this.updateNbJours());
    this.congeForm.get('cng_fin')?.valueChanges.subscribe(() => this.updateNbJours());
  }

  filterEmployees(value: string) {
    const filterVal = value ? value.toLowerCase() : '';
    this.filteredEmployees = this.employees.filter(emp =>
      (`${emp.nom} ${emp.prenom}`.toLowerCase().includes(filterVal))
    );
  }

 selectEmployee(emp: Employee) {
  this.selectedEmployee = emp;
  this.congeForm.patchValue({ emp_search: `${emp.nom} ${emp.prenom}` });
  this.filteredEmployees = [];
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

  selectRegion(region: Region) {
    this.selectedRegion = region;
    this.congeForm.patchValue({ region_search: region.reg_nom });
    this.filteredRegions = [];
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

  interimSearchListener(ctrl: AbstractControl, idx: number) {
    ctrl.get('interim_search')?.valueChanges.subscribe((val: string) => {
      const filterVal = val ? val.toLowerCase() : '';
      this.filteredInterims[idx] = this.employees
        .filter(e =>
          (!this.selectedEmployee || e.emp_code !== this.selectedEmployee.emp_code) &&
          (`${e.nom} ${e.prenom}`.toLowerCase().includes(filterVal))
        );
    });
  }

  selectInterim(idx: number, emp: Employee) {
    this.interims.at(idx).patchValue({ interim_search: `${emp.nom} ${emp.prenom}`, selectedInterim: emp });
    this.filteredInterims[idx] = [];
  }

  addInterimField() {
    this.interims.push(this.fb.group({
      interim_search: [''],
      selectedInterim: [null]
    }));
    this.filteredInterims.push(this.employees);
    this.interimSearchListener(this.interims.at(this.interims.length - 1), this.interims.length - 1);
  }

  removeInterimField(idx: number) {
    this.interims.removeAt(idx);
    this.filteredInterims.splice(idx, 1);
  }

  submit() {
    this.errorMsg = '';
    if (
      this.congeForm.invalid ||
      !this.selectedEmployee ||
      !this.selectedRegion ||
      this.calculatedNbJours == null ||
      this.calculatedNbJours < 1
    ) {
      this.errorMsg = "Veuillez remplir tous les champs";
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
      nom_emp: this.selectedEmployee.nom + ' ' + this.selectedEmployee.prenom,
      matricule: this.selectedEmployee.matricule,
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
    this.showRecap = true;
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
      alert('Congé et interims enregistrés !');
      this.resetAll();
    },
    error: (errRes) => {
      this.loading = false;
      console.error('Erreur backend:', errRes);
      this.errorMsg = errRes?.error?.messages?.error || "Erreur lors de l'enregistrement du congé.";
      // Affiche l'erreur complète en debug
      alert("Erreur API : " + JSON.stringify(errRes));
    }
  });
}

  annulerRecap() {
    this.showRecap = false;
  }

  resetAll() {
    this.showRecap = false;
    this.congeForm.reset();
    this.selectedEmployee = null;
    this.selectedRegion = null;
    this.calculatedNbJours = null;
  }
}
