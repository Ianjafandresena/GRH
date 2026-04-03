import { inject } from '@angular/core';
import { ResolveFn } from '@angular/router';
import { EtatRembService, EtatRemb } from '../service/etat-remb.service';

export const etatsListResolver: ResolveFn<EtatRemb[]> = () => {
    return inject(EtatRembService).getAll();
};
