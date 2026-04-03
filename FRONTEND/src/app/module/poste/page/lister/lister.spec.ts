import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Lister } from './lister';

describe('Lister', () => {
  let component: Lister;
  let fixture: ComponentFixture<Lister>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Lister]
    })
    .compileComponents();

    fixture = TestBed.createComponent(Lister);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
