import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Modification } from './modification';

describe('Modification', () => {
  let component: Modification;
  let fixture: ComponentFixture<Modification>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Modification]
    })
    .compileComponents();

    fixture = TestBed.createComponent(Modification);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
