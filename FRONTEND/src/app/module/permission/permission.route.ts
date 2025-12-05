import { Routes } from '@angular/router';
import { AjoutPermissionComponent } from './page/ajout/ajout';
import { PermissionIndexComponent } from './page/index/index';
import { permissionListResolver } from './resolvers/permission-list.resolver';

export const permissionRoutes: Routes = [
  { path: 'create', component: AjoutPermissionComponent },
  { path: 'index', component: PermissionIndexComponent, resolve: { permissions: permissionListResolver } },
  { path: '', redirectTo: 'index', pathMatch: 'full' }
];
