import { Location } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { faFlag, faSliders, faPlus } from '@fortawesome/pro-regular-svg-icons';
import { ModalController, ToastController } from '@ionic/angular';
import { ModalForm } from '../modal-form/modal-form';
import { ModalFiltros } from '../pages/modal-filtros/modal-filtros';
import { ModalFiltrosAdmin } from '../pages/modal-filtros-admin/modal-filtros-admin';
import { ModalCrearSubtarea } from '../pages/modal-crear-subtarea/modal-crear-subtarea';
import { SubtareaInfo } from '../pages/subtarea-info/subtarea-info';
import { Tarea, TareaAdmin, TareasService } from '../service/tareas.service';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  selector: 'app-tareas-info',
  standalone: false,
  templateUrl: './tareas-info.html',
  styleUrl: './tareas-info.scss',
})
export class TareasInfo implements OnInit {
  public tareaAdminSeleccionada: TareaAdmin | null = null;
  public subtareas: Tarea[] = [];
  
  // Variables de estado
  searchTerm: string = '';
  cargandoEmpleados: boolean = true;
  isAdmin: boolean = false;
  
  // Iconos FontAwesome
  public faSliders = faSliders;
  public faFlag = faFlag;
  public faPlus = faPlus;

  // Getter para acceder a apartadoadmin del servicio
  get isApartadoAdmin(): boolean {
    return this.tareasService.apartadoadmin;
  }

  // Función para determinar si una subtarea está activa
  isSubtareaActiva(subtarea: Tarea): boolean {
    return subtarea.estado === 'En progreso' || subtarea.estado === 'Completada';
  }

  constructor(
    private location: Location,
    private tareasService: TareasService,
    private modalController: ModalController,
    private toastController: ToastController,
    private route: ActivatedRoute,
    private router: Router,
    private authService: AuthService
  ) {
    const user = this.authService.getCurrentUser();
    this.isAdmin = user?.role === 'admin';
  }

  ngOnInit() {
    this.obtenerParametrosTarea();
  }

  // Obtener parámetros de la tarea seleccionada
  obtenerParametrosTarea() {
    this.route.queryParams.subscribe(params => {
      if (params['tareaId']) {
        // Primero intentar obtener del servicio (tarea recién seleccionada)
        let tarea = this.tareasService.obtenerTareaAdminSeleccionada();
        
        // Si no está en el servicio, buscar en el cache por ID
        if (!tarea || tarea.id !== params['tareaId']) {
          tarea = this.tareasService.obtenerTareaAdminPorIdLocal(params['tareaId']);
        }
        
        if (tarea) {
          this.tareaAdminSeleccionada = tarea;
          // Cargar subtareas del backend
          this.cargarSubtareas();
        }
      }
    });
  }

  // Obtener subtareas completadas
  get tareasCompletadas(): Tarea[] {
    return this.subtareas.filter(tarea => tarea.completada);
  }

  // Obtener subtareas pendientes
  get tareasListaPendientes(): Tarea[] {
    return this.subtareas.filter(tarea => !tarea.completada);
  }

  // Obtener subtareas en proceso
  get tareasListaEnProceso(): Tarea[] {
    return this.subtareas.filter(tarea => 
      tarea.estado === 'En progreso' || (tarea.progreso > 0 && !tarea.completada)
    );
  }

  // Obtener información sobre el progreso total
  get progresoGeneral(): { completadas: number; total: number; porcentaje: number } {
    if (!this.tareaAdminSeleccionada) {
      return { completadas: 0, total: 0, porcentaje: 0 };
    }
    return {
      completadas: this.tareaAdminSeleccionada.subtareasCompletadas,
      total: this.tareaAdminSeleccionada.totalSubtareas,
      porcentaje: this.tareaAdminSeleccionada.totalSubtareas > 0 
        ? (this.tareaAdminSeleccionada.subtareasCompletadas / this.tareaAdminSeleccionada.totalSubtareas) * 100 
        : 0
    };
  }

  // Métodos para completar/descompletar subtareas
  completarSubtarea(subtareaId: string): void {
    if (this.tareaAdminSeleccionada) {
      this.tareasService.completarSubtareaAdmin(this.tareaAdminSeleccionada.id, subtareaId);
      if (this.tareaAdminSeleccionada.Tarea) {
        this.subtareas = this.tareaAdminSeleccionada.Tarea;
      }
    }
  }

  descompletarSubtarea(subtareaId: string): void {
    if (this.tareaAdminSeleccionada) {
      this.tareasService.descompletarSubtareaAdmin(this.tareaAdminSeleccionada.id, subtareaId);
      if (this.tareaAdminSeleccionada.Tarea) {
        this.subtareas = this.tareaAdminSeleccionada.Tarea;
      }
    }
  }

  // Método para abrir modal de subtarea individual
  async navegarASubtarea(subtarea: Tarea) {
    const modal = await this.modalController.create({
      component: SubtareaInfo,
      componentProps: {
        tarea: subtarea,
        tareaadmin: this.tareaAdminSeleccionada
      },
      breakpoints: [0, 0.75, 1],
      initialBreakpoint: 0.75
    });

    modal.onDidDismiss().then((result) => {
      if (result.role === 'confirm' || result.data?.actualizada) {
        // Recargar subtareas si hubo cambios
        this.cargarSubtareas();
      }
    });

    await modal.present();
  }

  navegarACrearTarea() {
    this.router.navigate(['/tareas/crear-tarea'], {
      replaceUrl: false,
      skipLocationChange: false
    }).catch(error => {
      console.error('Error al navegar:', error);
      this.mostrarToast('Error al abrir el formulario de crear tarea', 'danger');
    });
  }

  goBack() {
    this.location.back();
  }

  async openModalfiltro() {
    const modal = await this.modalController.create({
      component: ModalFiltros,
      initialBreakpoint: 1,
      breakpoints: [0, 1],
      cssClass: 'modalamedias',
    });

    await modal.present();

    const { data } = await modal.onWillDismiss();
    
    if (data && data.filtrosAplicados) {
      this.aplicarFiltros(data);
      const mensajeFiltro = this.isApartadoAdmin 
        ? '¡Actividad filtrada satisfactoriamente!'
        : '¡Tarea filtrada satisfactoriamente!';
      this.mostrarToast(mensajeFiltro, 'success');
    }
  }

  async openModalfiltroadmin() {
    const modal = await this.modalController.create({
      component: ModalFiltrosAdmin,
      initialBreakpoint: 1,
      breakpoints: [0, 1],
      cssClass: 'modalamedias',
    });

    await modal.present();

    const { data } = await modal.onWillDismiss();
    
    if (data && data.filtrosAplicados) {
      this.aplicarFiltros(data);
      const mensajeFiltro = this.isApartadoAdmin 
        ? '¡Actividad filtrada satisfactoriamente!'
        : '¡Tarea filtrada satisfactoriamente!';
      this.mostrarToast(mensajeFiltro, 'success');
    }
  }

  openmodalfiltronormaloadmin() {
    if (this.tareasService.apartadoadmin === false) {
      this.openModalfiltro();
    } else {
      this.openModalfiltroadmin();
    }
  }

  async abrirModalReaperturar() {
    if (!this.tareaAdminSeleccionada) {
      this.mostrarToast('No hay tarea seleccionada', 'danger');
      return;
    }

    const modal = await this.modalController.create({
      component: ModalForm,
      initialBreakpoint: 1,
      breakpoints: [0, 1],
      cssClass: 'modalamedias',
      componentProps: {
        tarea: this.tareaAdminSeleccionada,
        accion: 'reaperturar'
      }
    });

    await modal.present();

    const { data } = await modal.onWillDismiss();
    
    if (data && data.reaperturada) {
      if (this.tareaAdminSeleccionada) {
        this.tareaAdminSeleccionada.estado = 'Pendiente';
        this.mostrarToast('Tarea reaperturada correctamente', 'success');
      }
    }
  }

  async abrirModalEditar(subtarea: Tarea) {
    this.router.navigate(['/tareas/crear-tarea'], {
      queryParams: {
        edit: 'true',
        tareaId: subtarea.id,
        titulo: subtarea.titulo,
        descripcion: subtarea.descripcion,
        categoria: subtarea.Categoria,
        prioridad: subtarea.Prioridad,
        horainicio: subtarea.horainicio,
        horafin: subtarea.horafin
      }
    });
  }

  // Eliminar tarea (solo admin)
  async eliminarTarea() {
    if (!this.tareaAdminSeleccionada) {
      this.mostrarToast('No hay tarea seleccionada', 'danger');
      return;
    }

    // Confirmar eliminación
    const confirmado = confirm('¿Estás seguro de que deseas eliminar esta tarea?');
    if (!confirmado) return;

    this.tareasService.eliminarTareaAdmin(this.tareaAdminSeleccionada.id).subscribe({
      next: (response) => {
        if (response.tipo === 1) {
          this.mostrarToast('Tarea eliminada correctamente', 'success');
          this.goBack();
        } else {
          this.mostrarToast(response.mensajes?.[0] || 'Error al eliminar tarea', 'danger');
        }
      },
      error: (err) => {
        console.error('Error eliminando tarea:', err);
        this.mostrarToast('Error al eliminar tarea', 'danger');
      }
    });
  }

  // Abrir modal para crear nueva subtarea
  async abrirModalCrearSubtarea() {
    if (!this.tareaAdminSeleccionada) {
      this.mostrarToast('No hay tarea seleccionada', 'danger');
      return;
    }

    const modal = await this.modalController.create({
      component: ModalCrearSubtarea,
      breakpoints: [0, 0.75, 0.9, 1],
      initialBreakpoint: 0.9,
      componentProps: {
        taskId: this.tareaAdminSeleccionada.id,
        taskTitulo: this.tareaAdminSeleccionada.titulo
      }
    });

    await modal.present();

    const { data, role } = await modal.onWillDismiss();
    
    if (role === 'confirm' && data?.created) {
      // Recargar subtareas
      this.cargarSubtareas();
      this.mostrarToast('Subtarea creada correctamente', 'success');
    }
  }

  // Cargar subtareas desde el backend
  cargarSubtareas() {
    if (!this.tareaAdminSeleccionada) return;
    
    this.tareasService.obtenerSubtareas(this.tareaAdminSeleccionada.id).subscribe({
      next: (response) => {
        if (response.tipo === 1 && response.data) {
          this.subtareas = response.data.map((s: any) => ({
            id: s.id,
            titulo: s.titulo || s.title,
            descripcion: s.descripcion || s.description || '',
            estado: s.estado || 'Pendiente',
            Categoria: s.categoria || s.Categoria || '',
            Prioridad: this.mapPrioridad(s.prioridad || s.priority),
            prioridad: this.mapPrioridad(s.prioridad || s.priority),
            horainicio: s.horainicio || s.hora_inicio || '',
            horafin: s.horafin || s.hora_fin || '',
            horaprogramada: s.horaprogramada || '',
            fechaAsignacion: s.fecha_asignacion || s.fechaAsignacion || '',
            usuarioasignado: s.usuarioasignado || s.usuario_asignado || '',
            usuarioasignado_id: s.usuarioasignado_id || s.assigned_user_id || 0,
            completada: s.completada || s.estado === 'Completada',
            progreso: s.progreso || 0,
            estadodetarea: s.estadodetarea || 'Activo',
            totalSubtareas: 0,
            subtareasCompletadas: 0
          }));
          
          // Actualizar contadores en la tarea admin
          if (this.tareaAdminSeleccionada) {
            this.tareaAdminSeleccionada.totalSubtareas = this.subtareas.length;
            this.tareaAdminSeleccionada.subtareasCompletadas = this.subtareas.filter(s => s.completada).length;
          }
        }
      },
      error: (err) => {
        console.error('Error cargando subtareas:', err);
      }
    });
  }

  // Mapear prioridad del backend al formato del frontend
  private mapPrioridad(prioridad: string): 'Alta' | 'Media' | 'Baja' {
    switch (prioridad?.toLowerCase()) {
      case 'high':
      case 'alta':
        return 'Alta';
      case 'low':
      case 'baja':
        return 'Baja';
      default:
        return 'Media';
    }
  }

  private aplicarFiltros(filtros: any) {
    console.log('Aplicando filtros:', filtros);
  }

  // Método para formatear el ID en formato 000
  getFormattedId(id: string | undefined): string {
    if (!id) return '000';
    
    const numeroMatch = id.match(/\d+/);
    if (numeroMatch) {
      const numero = parseInt(numeroMatch[0], 10);
      return numero.toString().padStart(3, '0');
    }
    
    return '000';
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
