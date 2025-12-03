import { Component, Input, OnInit, ChangeDetectorRef } from '@angular/core';
import { faCamera, faCheck, faImage, faXmark, faTrash, faExclamationTriangle } from '@fortawesome/pro-regular-svg-icons';
import { ModalController, ToastController, ActionSheetController, AlertController } from '@ionic/angular';
import { TareaAdmin, Subtarea, TareasService } from '../../service/tareas.service';

@Component({
  selector: 'app-modal-completar',
  standalone: false,
  templateUrl: './modal-completar.html',
  styleUrl: './modal-completar.scss',
})
export class ModalCompletar implements OnInit {
  @Input() tarea!: TareaAdmin;
  @Input() subtarea?: Subtarea;

  // Iconos
  faCamera = faCamera;
  faCheck = faCheck;
  faImage = faImage;
  faXmark = faXmark;
  faTrash = faTrash;
  faExclamationTriangle = faExclamationTriangle;

  // Datos del formulario
  observaciones: string = '';
  imagenes: string[] = [];
  imagenesArchivos: File[] = []; // Archivos reales para enviar al servidor
  cargandoImagen: boolean = false;

  // Constantes de validación
  readonly MAX_FILE_SIZE = 1.5 * 1024 * 1024; // 1.5MB en bytes
  readonly ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

  constructor(
    private modalController: ModalController,
    private toastController: ToastController,
    private actionSheetController: ActionSheetController,
    private alertController: AlertController,
    private cdr: ChangeDetectorRef,
    private tareasService: TareasService
  ) {}

  ngOnInit() {}

  // Cerrar modal
  cerrarModal() {
    this.modalController.dismiss(null, 'cancel');
  }

  // Abrir opciones para agregar imagen
  async agregarImagen() {
    const actionSheet = await this.actionSheetController.create({
      header: 'Agregar imagen',
      buttons: [
        {
          text: 'Tomar foto',
          icon: 'camera-outline',
          handler: () => {
            this.tomarFoto();
          }
        },
        {
          text: 'Seleccionar de galería',
          icon: 'image-outline',
          handler: () => {
            this.seleccionarDeGaleria();
          }
        },
        {
          text: 'Cancelar',
          icon: 'close',
          role: 'cancel'
        }
      ]
    });
    await actionSheet.present();
  }

  // Simular tomar foto (en una app real usaría Camera API)
  async tomarFoto() {
    this.cargandoImagen = true;
    this.cdr.markForCheck();
    
    // Simular delay de carga
    setTimeout(() => {
      // En una implementación real aquí iría la lógica de la cámara
      const imagenPlaceholder = `https://picsum.photos/200/200?random=${Date.now()}`;
      this.imagenes.push(imagenPlaceholder);
      this.cargandoImagen = false;
      this.cdr.markForCheck();
      this.mostrarToast('Imagen agregada', 'success');
    }, 1000);
  }

  // Simular selección de galería
  async seleccionarDeGaleria() {
    // Crear input file temporal
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/jpeg,image/png,image/gif,image/webp';
    input.multiple = false; // Una imagen a la vez para validar correctamente
    
    input.onchange = async (event: any) => {
      const file = event.target.files[0];
      if (!file) return;

      // Validar tipo de archivo
      if (!this.ALLOWED_TYPES.includes(file.type)) {
        await this.mostrarAlertaError(
          'Formato no permitido',
          'Solo se permiten imágenes en formato JPEG, PNG, GIF o WebP.'
        );
        return;
      }

      // Validar tamaño (máximo 1.5MB)
      if (file.size > this.MAX_FILE_SIZE) {
        const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
        await this.mostrarAlertaError(
          'Imagen demasiado grande',
          `El archivo pesa ${sizeInMB}MB. El tamaño máximo permitido es 1.5MB. Por favor, reduce el tamaño de la imagen.`
        );
        return;
      }

      this.cargandoImagen = true;
      this.cdr.markForCheck();

      const reader = new FileReader();
      reader.onload = (e: any) => {
        this.imagenes.push(e.target.result);
        this.imagenesArchivos.push(file);
        this.cargandoImagen = false;
        this.cdr.markForCheck();
        this.mostrarToast('Imagen agregada correctamente', 'success');
      };
      reader.readAsDataURL(file);
    };
    
    input.click();
  }

  // Mostrar alerta de error
  async mostrarAlertaError(titulo: string, mensaje: string) {
    const alert = await this.alertController.create({
      header: titulo,
      message: mensaje,
      buttons: ['Entendido'],
      cssClass: 'alert-error'
    });
    await alert.present();
  }

  // Eliminar imagen
  eliminarImagen(index: number) {
    this.imagenes.splice(index, 1);
    this.imagenesArchivos.splice(index, 1);
    this.cdr.markForCheck();
    this.mostrarToast('Imagen eliminada', 'warning');
  }

  // Validar si puede confirmar
  get puedeConfirmar(): boolean {
    return this.observaciones.trim().length > 0;
  }

  // Confirmar completar tarea/subtarea
  async confirmarCompletar() {
    if (!this.puedeConfirmar) {
      this.mostrarToast('Por favor ingrese las observaciones', 'warning');
      return;
    }

    // Si hay subtarea, completamos la subtarea con evidencias
    if (this.subtarea) {
      try {
        const response = await this.tareasService.completarSubtareaConEvidencia(
          this.subtarea.id,
          this.observaciones.trim(),
          this.imagenesArchivos
        ).toPromise();

        if (response && response.tipo === 1) {
          this.mostrarToast('Subtarea completada exitosamente', 'success');
          this.modalController.dismiss({ 
            success: true, 
            subtareaId: this.subtarea.id,
            data: response.data 
          }, 'confirm');
        } else {
          this.mostrarToast(response?.mensajes?.[0] || 'Error al completar', 'danger');
        }
      } catch (error: any) {
        console.error('Error al completar subtarea:', error);
        this.mostrarToast(error?.error?.mensajes?.[0] || 'Error al completar subtarea', 'danger');
      }
    } else {
      // Modo legacy: retornar datos sin llamar al servicio (para tareas completas)
      const datosCompletado = {
        completada: true,
        observaciones: this.observaciones.trim(),
        imagenes: this.imagenesArchivos,
        fechaCompletado: new Date().toISOString(),
        tareaId: this.tarea?.id,
        subtareaId: null
      };

      this.modalController.dismiss(datosCompletado, 'confirm');
    }
  }

  // Mostrar toast
  private async mostrarToast(mensaje: string, color: string = 'primary') {
    const toast = await this.toastController.create({
      message: mensaje,
      duration: 2000,
      color: color,
      position: 'bottom'
    });
    await toast.present();
  }
}
