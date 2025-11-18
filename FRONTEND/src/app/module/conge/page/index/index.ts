import { Component, OnInit } from '@angular/core';
import { CongeService } from '../../service/conge.service';
import { Conge } from '../../model/conge.model';

@Component({
  selector: 'app-conge-index',
  templateUrl: './index.html',
  styleUrls: ['./index.css']
})
export class CongeIndexComponent implements OnInit {
  conges: Conge[] = [];
  loading = false;

  constructor(private congeService: CongeService) {}

  ngOnInit() {
    this.loading = true;
    this.congeService.getConges().subscribe({
      next: (res) => { this.conges = res; this.loading = false; },
      error: () => this.loading = false
    });
  }
}
