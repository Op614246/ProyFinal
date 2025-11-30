import { Injectable } from '@angular/core';
import { ToastController } from '@ionic/angular';

export type ToastType = 'success' | 'error' | 'warning' | 'info';

@Injectable({
  providedIn: 'root'
})
export class ToastService {
  
  constructor(private toastController: ToastController) {}

  /**
   * Muestra un toast de éxito
   */
  async success(message: string, duration: number = 2500): Promise<void> {
    await this.show(message, 'success', duration);
  }

  /**
   * Muestra un toast de error
   */
  async error(message: string, duration: number = 3500): Promise<void> {
    await this.show(message, 'error', duration);
  }

  /**
   * Muestra un toast de advertencia
   */
  async warning(message: string, duration: number = 3000): Promise<void> {
    await this.show(message, 'warning', duration);
  }

  /**
   * Muestra un toast informativo
   */
  async info(message: string, duration: number = 2500): Promise<void> {
    await this.show(message, 'info', duration);
  }

  /**
   * Muestra un toast genérico
   */
  private async show(message: string, type: ToastType, duration: number): Promise<void> {
    const toast = await this.toastController.create({
      message,
      duration,
      position: 'bottom',
      cssClass: `toast-${type}`,
      icon: this.getIcon(type),
      color: this.getColor(type),
      buttons: [
        {
          icon: 'close',
          role: 'cancel'
        }
      ]
    });

    await toast.present();
  }

  /**
   * Muestra toast basado en respuesta del API
   */
  async showFromResponse(response: { tipo: number; mensajes: string[] }): Promise<void> {
    const message = response.mensajes?.join(' ') || 'Operación completada';
    
    switch (response.tipo) {
      case 1:
        await this.success(message);
        break;
      case 2:
        await this.warning(message);
        break;
      case 3:
        await this.error(message);
        break;
      default:
        await this.info(message);
    }
  }

  private getIcon(type: ToastType): string {
    switch (type) {
      case 'success': return 'checkmark-circle';
      case 'error': return 'close-circle';
      case 'warning': return 'warning';
      case 'info': return 'information-circle';
    }
  }

  private getColor(type: ToastType): string {
    switch (type) {
      case 'success': return 'success';
      case 'error': return 'danger';
      case 'warning': return 'warning';
      case 'info': return 'primary';
    }
  }
}
