import { NgModule, provideBrowserGlobalErrorListeners, provideZonelessChangeDetection } from '@angular/core';
import { BrowserModule, provideClientHydration, withEventReplay } from '@angular/platform-browser';
import { HttpClientModule } from '@angular/common/http';

import { provideIonicAngular } from '@ionic/angular/standalone';
import { AppRoutingModule } from './app-routing-module';
import { App } from './app';

import { CoreModule } from './core/core-module';
import { FeaturesModule } from './features/features-module';

@NgModule({
  declarations: [
    App
  ],
  imports: [
    BrowserModule,
    AppRoutingModule,
    HttpClientModule,
    CoreModule,
    FeaturesModule
  ],
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideZonelessChangeDetection(),
    provideClientHydration(withEventReplay()),
    provideIonicAngular({}),
  ],
  bootstrap: [App]
})
export class AppModule { }
