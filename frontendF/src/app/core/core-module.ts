import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormsModule } from '@angular/forms';
import { HttpClientModule, HTTP_INTERCEPTORS } from '@angular/common/http';

import { CoreRoutingModule } from './core-routing-module';
import { Login } from './login/login';
import { IonicModule } from '@ionic/angular';

// FontAwesome
import { FontAwesomeModule, FaIconLibrary } from '@fortawesome/angular-fontawesome';
import { 
  faLock, faUser, faRightToBracket, faEye, faEyeSlash 
} from '@fortawesome/pro-regular-svg-icons';

// Interceptors
import { AuthInterceptor } from './interceptors/auth.interceptor';

// Services
import { CryptoService } from './services/crypto.service';
import { TokenService } from './services/token.service';
import { AuthService } from './services/auth.service';

@NgModule({
  declarations: [
    Login
  ],
  imports: [
    CommonModule,
    CoreRoutingModule,
    ReactiveFormsModule,
    FormsModule,
    HttpClientModule,
    FontAwesomeModule,
    IonicModule
  ],
  providers: [
    CryptoService,
    TokenService,
    AuthService,
    {
      provide: HTTP_INTERCEPTORS,
      useClass: AuthInterceptor,
      multi: true
    }
  ],
  exports: [
    HttpClientModule,
    FontAwesomeModule
  ]
})
export class CoreModule { 
  constructor(library: FaIconLibrary) {
    library.addIcons(faLock, faUser, faRightToBracket, faEye, faEyeSlash);
  }
}
