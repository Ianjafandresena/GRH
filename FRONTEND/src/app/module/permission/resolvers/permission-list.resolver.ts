import { inject } from '@angular/core';
import { ResolveFn } from '@angular/router';
import { of } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { PermissionService } from '../service/permission.service';

export const permissionListResolver: ResolveFn<any[]> = () => {
  const service = inject(PermissionService);
  return service.getPermissions().pipe(catchError(() => of([])));
};
