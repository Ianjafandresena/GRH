import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AjoutCongeComponent } from './ajout';

describe('Ajout', () => {
  let component:  AjoutCongeComponent;
  let fixture: ComponentFixture<  AjoutCongeComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AjoutCongeComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(AjoutCongeComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
