import { Component, Input, OnInit } from '@angular/core';
import { ModalController, ToastController } from '@ionic/angular';
import { faXmark } from '@fortawesome/pro-regular-svg-icons';
import { TareasService, Categoria, UserAssignable } from '../../service/tareas.service';

@Component({
  selector: 'app-modal-crear-subtarea',
  standalone: false,
  templateUrl: './modal-crear-subtarea.html',
  styleUrl: './modal-crear-subtarea.scss',
})
export class ModalCrearSubtarea implements OnInit {
  @Input() taskId!: string; // ID de la tarea cabecera
  @Input() taskTitulo: string = '';
  
  faXmark = faXmark;
  
  // Datos del formulario
  titulo: string = '';
  descripcion: string = '';
  prioridad: string = 'medium';
  usuarioAsignadoId: number | null = null;
  
  // Datos del backend
  usuarios: UserAssignable[] = [];
  cargandoUsuarios: boolean = false;

  constructor(
    private modalController: ModalController,
    private toastController: ToastController,
    private tareasService: TareasService
  ) {}

  ngOnInit() {
    this.cargarUsuarios();
  }

  cargarUsuarios() {
    this.cargandoUsuarios = true;
    this.tareasService.getAvailableUsers().subscribe({
      next: (response) => {
        if (response.tipo === 1) {
          this.usuarios = response.data || [];
        }
        this.cargandoUsuarios = false;
      },
      error: (err) => {
        console.error('Error cargando usuarios:', err);
        this.cargandoUsuarios = false;
      }
    });
  }

  cerrar() {
    this.modalController.dismiss(null, 'cancel');
  }

  async crearSubtarea() {
    if (!this.titulo.trim()) {
      await this.mostrarToast('El título es requerido', 'warning');
      return;
    }

    const subtareaData = {
      task_id: this.taskId,
      titulo: this.titulo.trim(),
      descripcion: this.descripcion.trim(),
      prioridad: this.mapearPrioridad(this.prioridad),
      usuarioasignado_id: this.usuarioAsignadoId
    };

    this.tareasService.crearSubtarea(this.taskId, subtareaData).subscribe({
      next: (response) => {
        if (response.tipo === 1) {
          this.mostrarToast('Subtarea creada correctamente', 'success');
          this.modalController.dismiss({ created: true, subtarea: response.data }, 'confirm');
        } else {
          this.mostrarToast(response.mensajes?.[0] || 'Error al crear subtarea', 'danger');
        }
      },
      error: (err) => {
        console.error('Error creando subtarea:', err);
        this.mostrarToast('Error al crear subtarea', 'danger');
      }
    });
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

  private mapearPrioridad(prioridad: string): string {
    const mapeo: Record<string, string> = {
      'high': 'Alta',
      'medium': 'Media',
      'low': 'Baja'
    };
    return mapeo[prioridad] || 'Media';
  }
}
