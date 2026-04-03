import { TestBed } from '@angular/core/testing';

import { PosteResolver } from './poste-resolver';

describe('PosteResolver', () => {
  let service: PosteResolver;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(PosteResolver);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
