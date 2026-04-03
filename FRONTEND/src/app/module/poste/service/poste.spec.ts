import { TestBed } from '@angular/core/testing';

import { Poste } from './poste';

describe('Poste', () => {
  let service: Poste;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(Poste);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
