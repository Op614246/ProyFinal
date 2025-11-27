import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { FeaturesRoutingModule } from './features-routing-module';
import { Dashboard } from './dashboard/dashboard';

// FontAwesome
import { FontAwesomeModule, FaIconLibrary } from '@fortawesome/angular-fontawesome';
import { 
  faShield, faUser, faRightFromBracket, faCircleCheck, 
  faUserGear, faGear, faUsers, faLockOpen 
} from '@fortawesome/pro-regular-svg-icons';

// PrimeNG Modules
import { ButtonModule } from 'primeng/button';
import { ToastModule } from 'primeng/toast';
import { ConfirmDialogModule } from 'primeng/confirmdialog';


@NgModule({
  declarations: [
    Dashboard
  ],
  imports: [
    CommonModule,
    FeaturesRoutingModule,
    FontAwesomeModule,
    // PrimeNG
    ButtonModule,
    ToastModule,
    ConfirmDialogModule
  ]
})
export class FeaturesModule {
  constructor(library: FaIconLibrary) {
    library.addIcons(
      faShield, faUser, faRightFromBracket, faCircleCheck, 
      faUserGear, faGear, faUsers, faLockOpen
    );
  }
}
