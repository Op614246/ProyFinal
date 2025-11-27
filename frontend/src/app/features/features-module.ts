import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { FeaturesRoutingModule } from './features-routing-module';
import { Dashboard } from './dashboard/dashboard';

// FontAwesome
import { FontAwesomeModule, FaIconLibrary } from '@fortawesome/angular-fontawesome';
import { 
  faShield, faUser, faRightFromBracket, faCircleCheck, 
  faUserGear, faGear, faUsers, faLockOpen, faUserPlus,
  faTrash, faLock, faUnlock, faPen, faCheck, faXmark,
  faArrowLeft, faEye, faEyeSlash
} from '@fortawesome/pro-regular-svg-icons';

// PrimeNG Modules
import { ButtonModule } from 'primeng/button';
import { ToastModule } from 'primeng/toast';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { TableModule } from 'primeng/table';
import { DialogModule } from 'primeng/dialog';
import { InputTextModule } from 'primeng/inputtext';
import { SelectModule } from 'primeng/select';
import { TagModule } from 'primeng/tag';
import { TooltipModule } from 'primeng/tooltip';
import { PasswordModule } from 'primeng/password';

import { GestionUsuarios } from './gestion-usuarios/gestion-usuarios';
import { ModalCrearUsuario } from './gestion-usuarios/modal-crear-usuario/modal-crear-usuario';


@NgModule({
  declarations: [
    Dashboard,
    GestionUsuarios,
    ModalCrearUsuario
  ],
  imports: [
    CommonModule,
    ReactiveFormsModule,
    RouterModule,
    FeaturesRoutingModule,
    FontAwesomeModule,
    // PrimeNG
    ButtonModule,
    ToastModule,
    ConfirmDialogModule,
    TableModule,
    DialogModule,
    InputTextModule,
    SelectModule,
    TagModule,
    TooltipModule,
    PasswordModule
  ]
})
export class FeaturesModule {
  constructor(library: FaIconLibrary) {
    library.addIcons(
      faShield, faUser, faRightFromBracket, faCircleCheck, 
      faUserGear, faGear, faUsers, faLockOpen, faUserPlus,
      faTrash, faLock, faUnlock, faPen, faCheck, faXmark,
      faArrowLeft, faEye, faEyeSlash
    );
  }
}
