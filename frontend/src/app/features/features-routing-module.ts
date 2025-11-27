import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { Dashboard } from './dashboard/dashboard';
import { GestionUsuarios } from './gestion-usuarios/gestion-usuarios';
import { AuthGuard } from '../core/guard/auth.guard';
import { AdminGuard } from '../core/guard/admin.guard';

const routes: Routes = [
  {
    path: 'dashboard',
    component: Dashboard,
    canActivate: [AuthGuard]
  },
  {
    path: 'gestion-usuarios',
    component: GestionUsuarios,
    canActivate: [AdminGuard]  // Solo admin puede acceder
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class FeaturesRoutingModule { }
