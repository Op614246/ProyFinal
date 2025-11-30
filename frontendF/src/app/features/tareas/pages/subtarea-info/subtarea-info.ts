import { Location } from '@angular/common';
import { Component, Input, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { faFlag, faXmark } from '@fortawesome/pro-regular-svg-icons';
import { ActionSheetController, AlertController, ModalController, ToastController, NavParams } from '@ionic/angular';
import { ModalForm } from '../../modal-form/modal-form';
import { Tarea, TareaAdmin, TareasService } from '../../service/tareas.service';
import { AuthService } from '../../../../core/services/auth.service';

@Component({
  selector: 'app-subtarea-info',
  standalone: false,
  templateUrl: './subtarea-info.html',
  styleUrl: './subtarea-info.scss',
})
export class SubtareaInfo implements OnInit {
  @Input() tarea: Tarea | null = null;
  @Input() tareaadmin: TareaAdmin | null = null;
  
  tareaId: string = '';
  fromTab: string = '';
  comentario: string = '';
  archivosSubidos: any[] = [];
  
  // Flag para detectar si está en modo modal
  esModal: boolean = false;
  
  // Flag para verificar si es admin
  isAdmin: boolean = false;
  
  public faFlag = faFlag;
  public faXmark = faXmark;

  get isApartadoAdmin(): boolean {
    return this.tareasService.apartadoadmin;
  }

  constructor(
    private router: Router,
    private route: ActivatedRoute,
    private location: Location,
    private alertController: AlertController,
    private modalController: ModalController,
    private actionSheetController: ActionSheetController,
    private toastController: ToastController,
    public tareasService: TareasService,
    private authService: AuthService
  ) {
    // Verificar si el usuario es admin
    const user = this.authService.getCurrentUser();
    this.isAdmin = user?.role === 'admin';
  }

  ngOnInit() {
    // Detectar si está en modo modal (si recibió tarea directamente como Input)
    if (this.tarea) {
      this.esModal = true;
      this.tareaId = this.tarea.id;
      return;
    }
    
    this.route.queryParams.subscribe(params => {
      this.tareaId = params['tareaId'] || '';
      this.fromTab = params['fromTab'] || '';
      
      if (this.fromTab === 'tareas-admin') {
        this.construirTareaDesdeParametros(params);
      } else {
        this.cargarTarea();
      }
    });
  }

  construirTareaDesdeParametros(params: any) {
    this.tarea = {
      id: params['tareaId'] || '',
      titulo: params['titulo'] || '',
      estado: params['estado'] || 'Pendiente',
      Categoria: params['categoria'] || '',
      estadodetarea: params['estadodetarea'] || 'Activo',
      horaprogramada: params['horaprogramada'] || '',
      horainicio: params['horainicio'] || '',
      horafin: params['horafin'] || '',
      descripcion: params['descripcion'] || '',
      prioridad: params['prioridad'] || 'Media',
      Prioridad: params['prioridad'] || 'Media',
      completada: params['completada'] === 'true',
      progreso: parseInt(params['progreso']) || 0,
      fechaAsignacion: params['fechaAsignacion'] || '',
      totalSubtareas: 0,
      subtareasCompletadas: 0,
      usuarioasignado: params['usuarioasignado'] || '',
      usuarioasignado_id: 0
    };
  }

  cargarTarea() {
    if (this.tareaId) {
      // Buscar la tarea en el servicio usando el método existente
      const tareaLocal = this.tareasService.obtenerTareaAdminPorIdLocal(this.tareaId);
      if (tareaLocal && tareaLocal.Tarea) {
        this.tarea = tareaLocal.Tarea.find((t: Tarea) => t.id === this.tareaId) || null;
      }
    }
  }

  goBack() {
    if (this.esModal) {
      this.modalController.dismiss(null, 'cancel');
    } else {
      this.location.back();
    }
  }
  
  cerrarModal(data?: any, role: string = 'confirm') {
    if (this.esModal) {
      this.modalController.dismiss(data, role);
    } else {
      this.location.back();
    }
  }

  async subirArchivo() {
    if (this.archivosSubidos.length >= 5) {
      await this.mostrarToast('Máximo 5 imágenes permitidas', 'warning');
      return;
    }

    const actionSheet = await this.actionSheetController.create({
      header: 'Seleccionar imagen',
      buttons: [
        {
          text: 'Tomar foto',
          icon: 'camera',
          handler: () => {
            this.agregarImagenSimulada('camera');
          }
        },
        {
          text: 'Elegir de galería',
          icon: 'images',
          handler: () => {
            this.agregarImagenSimulada('gallery');
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

  agregarImagenSimulada(origen: string) {
    const nuevaImagen = {
      id: Date.now(),
      src: 'assets/images/placeholder.png',
      nombre: `IMG_${Date.now()}`,
      origen: origen,
      tipo: 'imagen'
    };
    this.archivosSubidos.push(nuevaImagen);
    this.mostrarToast('Imagen agregada exitosamente', 'success');
  }

  async eliminarArchivo(archivo: any) {
    const alert = await this.alertController.create({
      header: '¿Eliminar archivo?',
      message: 'Esta acción no se puede deshacer.',
      buttons: [
        {
          text: 'Cancelar',
          role: 'cancel'
        },
        {
          text: 'Eliminar',
          role: 'destructive',
          handler: () => {
            this.archivosSubidos = this.archivosSubidos.filter(a => a.id !== archivo.id);
            this.mostrarToast('Archivo eliminado', 'success');
          }
        }
      ]
    });
    await alert.present();
  }

  async iniciarTarea() {
    if (this.tarea) {
      // Llamar al servicio para iniciar la tarea
      this.tareasService.iniciarTarea(this.tarea.id).subscribe({
        next: (response) => {
          if (response.tipo === 1) {
            this.tarea!.estado = 'En progreso';
            this.mostrarToast('Tarea iniciada', 'success');
          } else {
            this.mostrarToast(response.mensajes[0] || 'Error al iniciar', 'danger');
          }
        },
        error: (err) => {
          console.error('Error al iniciar tarea:', err);
          this.mostrarToast('Error al iniciar la tarea', 'danger');
        }
      });
    }
  }

  async finalizarTarea() {
    const alert = await this.alertController.create({
      header: '¿Finalizar tarea?',
      message: 'Una vez completada, no podrás volver a editar.',
      buttons: [
        {
          text: 'Cancelar',
          role: 'cancel'
        },
        {
          text: 'Finalizar',
          handler: () => {
            if (this.tarea) {
              // Llamar al servicio para completar la tarea
              this.tareasService.finalizarTarea(this.tarea.id, this.comentario).subscribe({
                next: (response) => {
                  if (response.tipo === 1) {
                    this.tarea!.estado = 'Completada';
                    this.tarea!.completada = true;
                    this.mostrarToast('Tarea finalizada exitosamente', 'success');
                    this.cerrarModal({ actualizada: true }, 'confirm');
                  } else {
                    this.mostrarToast(response.mensajes[0] || 'Error al finalizar', 'danger');
                  }
                },
                error: (err) => {
                  console.error('Error al finalizar tarea:', err);
                  this.mostrarToast('Error al finalizar la tarea', 'danger');
                }
              });
            }
          }
        }
      ]
    });
    await alert.present();
  }

  async abrirModalReaperturar() {
    const modal = await this.modalController.create({
      component: ModalForm,
      initialBreakpoint: 1,
      breakpoints: [0, 1],
      cssClass: 'modalamedias',
      componentProps: {
        tarea: this.tarea,
        accion: 'reaperturar'
      }
    });

    await modal.present();

    const { data } = await modal.onWillDismiss();
    
    if (data && data.reaperturada) {
      if (this.tarea) {
        this.tarea.estado = 'Pendiente';
        this.tarea.completada = false;
        this.mostrarToast('Tarea reaperturada correctamente', 'success');
      }
    }
  }

  get textoBotonAccion(): string {
    if (!this.tarea) return 'Iniciar tarea';
    switch (this.tarea.estado) {
      case 'Pendiente': return 'Iniciar tarea';
      case 'En progreso': return 'Finalizar tarea';
      case 'Completada': return this.isAdmin ? 'Reaperturar tarea' : '';
      default: return 'Iniciar tarea';
    }
  }

  get colorBotonAccion(): string {
    if (!this.tarea) return 'primary';
    switch (this.tarea.estado) {
      case 'Pendiente': return 'primary';
      case 'En progreso': return 'success';
      case 'Completada': return 'warning';
      default: return 'primary';
    }
  }

  ejecutarAccionPrincipal() {
    if (!this.tarea) return;
    switch (this.tarea.estado) {
      case 'Pendiente':
        this.iniciarTarea();
        break;
      case 'En progreso':
        this.finalizarTarea();
        break;
      case 'Completada':
        // Solo admin puede reaperturar
        if (this.isAdmin) {
          this.abrirModalReaperturar();
        }
        break;
    }
  }

  private async mostrarToast(mensaje: string, color: string) {
    const toast = await this.toastController.create({
      message: mensaje,
      duration: 2000,
      color: color,
      position: 'bottom'
    });
    await toast.present();
  }
}
