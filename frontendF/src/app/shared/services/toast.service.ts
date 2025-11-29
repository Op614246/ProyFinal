import { Injectable } from '@angular/core';
import { ToastController } from '@ionic/angular/standalone';

@Injectable({
  providedIn: 'root'
})
export class ToastService {

  constructor(private toastController: ToastController) { }

  async presentToast(message: string, color: 'success' | 'danger' | 'warning' = 'success', duration: number = 2000) {
    const toast = await this.toastController.create({
      message: message,
      duration: duration,
      color: color,
      position: 'top',
      icon: this.getIcon(color)
    });
    toast.present();
  }

  private getIcon(color: string): string {
    switch (color) {
      case 'success':
        return 'checkmark-circle-outline';
      case 'danger':
        return 'alert-circle-outline';
      case 'warning':
        return 'warning-outline';
      default:
        return '';
    }
  }
}
