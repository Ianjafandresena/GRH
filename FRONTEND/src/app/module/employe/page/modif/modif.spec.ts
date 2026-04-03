import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Modif } from './modif';

describe('Modif', () => {
  let component: Modif;
  let fixture: ComponentFixture<Modif>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Modif]
    })
    .compileComponents();

    fixture = TestBed.createComponent(Modif);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
