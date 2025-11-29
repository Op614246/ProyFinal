import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { Tareas } from './tareas';
import { TareasInfo } from './tareas-info/tareas-info';
import { TareaadminAperturar } from './tareaadmin-aperturar/tareaadmin-aperturar';
import { Creartarea } from './creartarea/creartarea';
import { SubtareaInfo } from './pages/subtarea-info/subtarea-info';

const routes: Routes = [
  {
    path: '',
    component: Tareas
  },
  {
    path: 'tarea-info',
    component: TareasInfo
  },
  {
    path: 'crear-tarea',
    component: Creartarea
  },
  {
    path: 'tareaadmin-aperturar',
    component: TareaadminAperturar
  },
  {
    path: 'subtarea-info',
    component: SubtareaInfo
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class TareasRoutingModule { }
