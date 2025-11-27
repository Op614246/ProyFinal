import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormsModule } from '@angular/forms';
import { HttpClientModule, HTTP_INTERCEPTORS } from '@angular/common/http';

import { CoreRoutingModule } from './core-routing-module';
import { Login } from './login/login';

// FontAwesome
import { FontAwesomeModule, FaIconLibrary } from '@fortawesome/angular-fontawesome';
import { 
  faLock, faUser, faRightToBracket, faEye, faEyeSlash 
} from '@fortawesome/pro-regular-svg-icons';

// PrimeNG Modules
import { InputTextModule } from 'primeng/inputtext';
import { PasswordModule } from 'primeng/password';
import { ButtonModule } from 'primeng/button';
import { ToastModule } from 'primeng/toast';
import { MessageModule } from 'primeng/message';

// Interceptors
import { AuthInterceptor } from './interceptors/auth.interceptor';

// Services
import { CryptoService } from './service/crypto.service';
import { TokenService } from './service/token.service';
import { AuthService } from './service/auth.service';


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
    // PrimeNG
    InputTextModule,
    PasswordModule,
    ButtonModule,
    ToastModule,
    MessageModule
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
