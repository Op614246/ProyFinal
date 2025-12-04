import { Component, Input, OnInit, ChangeDetectorRef } from '@angular/core';
import { faCamera, faCheck, faImage, faXmark, faTrash, faExclamationTriangle } from '@fortawesome/pro-regular-svg-icons';
import { ModalController, ToastController, ActionSheetController, AlertController } from '@ionic/angular';
import { TareaAdmin, Tarea } from '../../service/tareas.service';

@Component({
  selector: 'app-modal-completar',
  standalone: false,
  templateUrl: './modal-completar.html',
  styleUrl: './modal-completar.scss',
})
export class ModalCompletar implements OnInit {
  @Input() tarea!: TareaAdmin;
  @Input() subtarea?: Tarea;

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
    private cdr: ChangeDetectorRef
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
    input.multiple = true; // Permitir múltiples imágenes
    
    input.onchange = async (event: any) => {
      const files: FileList = event.target.files;
      if (!files || files.length === 0) return;

      for (let i = 0; i < files.length; i++) {
        const file = files[i];
        
        // Validar tipo de archivo
        if (!this.ALLOWED_TYPES.includes(file.type)) {
          await this.mostrarAlertaError(
            'Formato no permitido',
            `El archivo "${file.name}" no está permitido. Solo se aceptan JPEG, PNG, GIF o WebP.`
          );
          continue;
        }

        // Validar tamaño (máximo 1.5MB)
        if (file.size > this.MAX_FILE_SIZE) {
          const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
          await this.mostrarAlertaError(
            'Imagen demasiado grande',
            `El archivo "${file.name}" pesa ${sizeInMB}MB. El máximo permitido es 1.5MB.`
          );
          continue;
        }

        // Procesar archivo válido
        await this.procesarArchivo(file);
      }
    };
    
    input.click();
  }

  // Procesar un archivo de imagen
  private procesarArchivo(file: File): Promise<void> {
    return new Promise((resolve) => {
      this.cargandoImagen = true;
      this.cdr.markForCheck();

      const reader = new FileReader();
      reader.onload = (e: any) => {
        this.imagenes.push(e.target.result);
        this.imagenesArchivos.push(file);
        this.cargandoImagen = false;
        this.cdr.markForCheck();
        this.mostrarToast('Imagen agregada correctamente', 'success');
        resolve();
      };
      reader.readAsDataURL(file);
    });
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

  // Confirmar completar tarea
  async confirmarCompletar() {
    if (!this.puedeConfirmar) {
      this.mostrarToast('Por favor ingrese las observaciones', 'warning');
      return;
    }

    const datosCompletado = {
      completada: true,
      observaciones: this.observaciones.trim(),
      imagenes: this.imagenes,
      imagenesArchivos: this.imagenesArchivos, // Todos los archivos
      fechaCompletado: new Date().toISOString(),
      tareaId: this.tarea?.id,
      subtareaId: this.subtarea?.id
    };

    this.modalController.dismiss(datosCompletado, 'confirm');
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
