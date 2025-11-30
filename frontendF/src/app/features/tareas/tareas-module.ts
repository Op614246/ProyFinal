import { NgModule, CUSTOM_ELEMENTS_SCHEMA } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { IonicModule } from '@ionic/angular';
import { FontAwesomeModule } from '@fortawesome/angular-fontawesome';

import { TareasRoutingModule } from './tareas-routing-module';
import { Tareas } from './tareas';
import { TareasInfo } from './tareas-info/tareas-info';
import { TareaadminAperturar } from './tareaadmin-aperturar/tareaadmin-aperturar';
import { Creartarea } from './creartarea/creartarea';
import { ModalForm } from './modal-form/modal-form';
import { ModalFiltros } from './pages/modal-filtros/modal-filtros';
import { ModalFiltrosAdmin } from './pages/modal-filtros-admin/modal-filtros-admin';
import { ModalReaperturar } from './pages/modal-reaperturar/modal-reaperturar';
import { ModalCompletar } from './pages/modal-completar/modal-completar';
import { SubtareaInfo } from './pages/subtarea-info/subtarea-info';

@NgModule({
  declarations: [
    Tareas,
    TareasInfo,
    TareaadminAperturar,
    Creartarea,
    ModalForm,
    ModalFiltros,
    ModalFiltrosAdmin,
    ModalReaperturar,
    ModalCompletar,
    SubtareaInfo
  ],
  imports: [
    CommonModule,
    FormsModule,
    IonicModule,
    FontAwesomeModule,
    TareasRoutingModule
  ],
  schemas: [CUSTOM_ELEMENTS_SCHEMA]
})
export class TareasModule { }
