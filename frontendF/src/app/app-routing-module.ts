import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AuthGuard } from './core/guards/auth.guard';
import { NoAuthGuard } from './core/guards/no-auth.guard';
import { Login } from './core/login/login';

const routes: Routes = [
  {
    path: '',
    redirectTo: 'login',
    pathMatch: 'full'
  },
  {
    path: 'login',
    component: Login,
    canActivate: [NoAuthGuard]
  },
  {
    path: 'features',
    loadChildren: () => import('./features/features-module').then(m => m.FeaturesModule),
    canActivate: [AuthGuard]
  },
  {
    path: 'tareas',
    redirectTo: 'features/tareas',
    pathMatch: 'full'
  },
  {
    path: '**',
    redirectTo: 'login'
  }
];

@NgModule({
  imports: [RouterModule.forRoot(routes, {
    useHash: true
  })],
  exports: [RouterModule]
})
export class AppRoutingModule {}
