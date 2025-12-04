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
  @Input() edit: boolean = false; // Si es modo edición
  @Input() subtareaId?: string; // ID de la subtarea a editar
  @Input() titulo: string = '';
  @Input() descripcion: string = '';
  @Input() prioridad: string = 'medium';
  @Input() estado: string = 'Pendiente';
  @Input() usuarioAsignadoId: number | null = null;
  
  faXmark = faXmark;
  
  // Datos del formulario
  tituloForm: string = '';
  descripcionForm: string = '';
  prioridadForm: string = 'medium';
  estadoForm: string = 'Pendiente';
  usuarioAsignadoIdForm: number | null = null;
  
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
    
    // Si es modo edición, cargar los datos
    if (this.edit) {
      this.tituloForm = this.titulo;
      this.descripcionForm = this.descripcion;
      this.prioridadForm = this.mapPrioridadToInternal(this.prioridad);
      this.estadoForm = this.estado;
      this.usuarioAsignadoIdForm = this.usuarioAsignadoId;
    }
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
    if (!this.tituloForm.trim()) {
      await this.mostrarToast('El título es requerido', 'warning');
      return;
    }

    if (this.edit && this.subtareaId) {
      // Modo actualización
      this.actualizarSubtarea();
    } else {
      // Modo creación
      const subtareaData = {
        task_id: this.taskId,
        titulo: this.tituloForm.trim(),
        descripcion: this.descripcionForm.trim(),
        prioridad: this.mapearPrioridad(this.prioridadForm),
        usuarioasignado_id: this.usuarioAsignadoIdForm
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
  }

  private actualizarSubtarea() {
    const subtareaData = {
      titulo: this.tituloForm.trim(),
      descripcion: this.descripcionForm.trim(),
      estado: this.estadoForm,
      prioridad: this.mapearPrioridad(this.prioridadForm),
      usuarioasignado_id: this.usuarioAsignadoIdForm
    };

    // Llamar al servicio para actualizar subtarea
    this.tareasService.actualizarSubtarea(this.subtareaId!, subtareaData).subscribe({
      next: (response) => {
        if (response.tipo === 1) {
          this.mostrarToast('Subtarea actualizada correctamente', 'success');
          this.modalController.dismiss({ updated: true, subtarea: response.data }, 'confirm');
        } else {
          this.mostrarToast(response.mensajes?.[0] || 'Error al actualizar subtarea', 'danger');
        }
      },
      error: (err) => {
        console.error('Error actualizando subtarea:', err);
        this.mostrarToast('Error al actualizar subtarea', 'danger');
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

  private mapPrioridadToInternal(prioridad: string): string {
    const mapeo: Record<string, string> = {
      'Alta': 'high',
      'Media': 'medium',
      'Baja': 'low'
    };
    return mapeo[prioridad] || 'medium';
  }

  get textoBoton(): string {
    return this.edit ? 'Actualizar Subtarea' : 'Crear Subtarea';
  }

  get tituloModal(): string {
    return this.edit ? 'Editar Subtarea' : 'Nueva Subtarea';
  }
}
