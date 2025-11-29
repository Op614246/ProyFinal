import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { TareasRoutingModule } from './tareas-routing-module';
import { TareasInfo } from './tareas-info/tareas-info';
import { TareaadminAperturar } from './tareaadmin-aperturar/tareaadmin-aperturar';
import { Creartarea } from './creartarea/creartarea';
import { ModalForm } from './modal-form/modal-form';
import { ModalFiltros } from './pages/modal-filtros/modal-filtros';
import { ModalFiltrosAdmin } from './pages/modal-filtros-admin/modal-filtros-admin';
import { ModalReaperturar } from './pages/modal-reaperturar/modal-reaperturar';
import { SubtareaInfo } from './pages/subtarea-info/subtarea-info';


@NgModule({
  declarations: [
    TareasInfo,
    TareaadminAperturar,
    Creartarea,
    ModalForm,
    ModalFiltros,
    ModalFiltrosAdmin,
    ModalReaperturar,
    SubtareaInfo
  ],
  imports: [
    CommonModule,
    TareasRoutingModule
  ]
})
export class TareasModule { }
