import { Location } from '@angular/common';
import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { Subject, forkJoin } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { ActivatedRoute, Router } from '@angular/router';
import { faFlag, faSliders, faPlus, faPencil, faTrash, faArrowLeft } from '@fortawesome/pro-regular-svg-icons';
import { ModalController, ToastController, AlertController } from '@ionic/angular';
import { environment } from '../../../../environments/environment';
import { ModalForm } from '../modal-form/modal-form';
import { ModalCompletar } from '../pages/modal-completar/modal-completar';
import { ModalFiltros } from '../pages/modal-filtros/modal-filtros';
import { ModalFiltrosAdmin } from '../pages/modal-filtros-admin/modal-filtros-admin';
import { ModalCrearSubtarea } from '../pages/modal-crear-subtarea/modal-crear-subtarea';
import { ModalReaperturar } from '../pages/modal-reaperturar/modal-reaperturar';
import { SubtareaInfo } from '../pages/subtarea-info/subtarea-info';
import { Creartarea } from '../creartarea/creartarea';
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
  public asignando: boolean = false;
  comentario: string = '';
  
  // Variables de estado
  searchTerm: string = '';
  cargandoEmpleados: boolean = true;
  isAdmin: boolean = false;
  
  // Iconos FontAwesome
  public faSliders = faSliders;
  public faFlag = faFlag;
  public faPlus = faPlus;
  public faPencil = faPencil;
  public faTrash = faTrash;
  public faArrowLeft = faArrowLeft;

  // Getter para acceder a apartadoadmin del servicio
  get isApartadoAdmin(): boolean {
    return this.tareasService.apartadoadmin;
  }

  // Función para determinar si una subtarea está activa
  isSubtareaActiva(subtarea: Tarea): boolean {
    return subtarea.estado === 'En progreso' || subtarea.estado === 'Completada';
  }

  // Obtener URL absoluta para evidencias
  getEvidenceUrl(path: string): string {
    if (!path) return '';
    try {
      if (path.startsWith('http://') || path.startsWith('https://')) return path;
    } catch (e) {
      // noop
    }
    // La ruta guardada es 'uploads/evidencias/archivo.jpg'
    // La URL base de la API es 'http://localhost/ProyFinal/backend/api/rest'
    // Necesitamos: 'http://localhost/ProyFinal/backend/uploads/evidencias/archivo.jpg'
    const baseUrl = environment.apiUrl.replace('/api/rest', '');
    return `${baseUrl}/${path}`;
  }

  // Parsear la propiedad imagenes que puede ser string JSON, lista separada, array de strings o array de objetos
  parseImagenes(imagenes: any): string[] {
    if (!imagenes) return [];
    
    // Si es un array
    if (Array.isArray(imagenes)) {
      return imagenes.map(img => {
        // Si es un objeto con propiedades file_path o path
        if (typeof img === 'object' && img !== null) {
          return img.file_path || img.path || '';
        }
        // Si es un string
        return img;
      }).filter(Boolean);
    }
    
    // Si es un string
    if (typeof imagenes === 'string') {
      const trimmed = imagenes.trim();
      // Intentar parsear JSON
      if (trimmed.startsWith('[') || trimmed.startsWith('{')) {
        try {
          const parsed = JSON.parse(trimmed);
          if (Array.isArray(parsed)) {
            return parsed.map(img => {
              if (typeof img === 'object' && img !== null) {
                return img.file_path || img.path || '';
              }
              return img;
            }).filter(Boolean);
          }
          if (parsed && typeof parsed === 'object') {
            return [parsed.file_path || parsed.path || ''];
          }
        } catch (e) {
          // no JSON
        }
      }
      // Separadores comunes
      if (trimmed.includes('|')) return trimmed.split('|').map(s => s.trim()).filter(Boolean);
      if (trimmed.includes(',')) return trimmed.split(',').map(s => s.trim()).filter(Boolean);
      return [trimmed];
    }
    
    return [];
  }

  constructor(
    private location: Location,
    private tareasService: TareasService,
    private modalController: ModalController,
    private toastController: ToastController,
    private route: ActivatedRoute,
    private router: Router,
    private authService: AuthService,
    private alertController: AlertController,
    private cdr: ChangeDetectorRef
  ) {
    const user = this.authService.getCurrentUser();
    this.isAdmin = user?.role === 'admin';
  }

  private destroy$ = new Subject<void>();

  ngOnInit() {
    this.obtenerParametrosTarea();

    // Subscribirse a notificaciones globales de actualización para recargar subtareas
    this.tareasService.actualizacion$
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => {
        this.cargarSubtareas();
        // Forzar detección de cambios explícitamente
        this.cdr.markForCheck();
        this.cdr.detectChanges();
      });
  }

  // Permite que un usuario no-admin se auto-asigne la tarea (Añadir a mis tareas)
  asignarATarea(event?: Event) {
    if (event) {
      event.stopPropagation();
    }

    if (!this.tareaAdminSeleccionada) {
      this.mostrarToast('No hay tarea seleccionada', 'danger');
      return;
    }

    const tareaId = this.tareaAdminSeleccionada.id;
    const user = this.authService.getCurrentUser();

    this.asignando = true;
    this.tareasService.autoAsignarTarea(tareaId).subscribe({
      next: (response) => {
        this.asignando = false;
        if (response?.tipo === 1) {
          // Actualizar estado localmente
          if (user) {
            this.tareaAdminSeleccionada!.usuarioasignado = user.username || user.id?.toString() || 'Yo';
            this.tareaAdminSeleccionada!.usuarioasignado_id = user.id as any;
          }

          // Recargar subtareas y notificar al resto de componentes
          this.cargarSubtareas();
          this.cdr.markForCheck();
          this.cdr.detectChanges();
          this.tareasService.notificarActualizacion();
          this.mostrarToast('Tarea añadida a tus tareas', 'success');
        } else {
          this.mostrarToast(response?.mensajes?.[0] || 'No se pudo asignar la tarea', 'danger');
        }
      },
      error: (err) => {
        console.error('Error auto-asignando tarea:', err);
        this.asignando = false;
        this.mostrarToast('Error al asignar la tarea', 'danger');
      }
    });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  // Obtener parámetros de la tarea seleccionada
  obtenerParametrosTarea() {
    this.route.queryParams.subscribe(params => {
      if (params['tareaId']) {
        const tareaId = params['tareaId'];
        
        // Primero intentar obtener del servicio (tarea recién seleccionada)
        let tareaLocal = this.tareasService.obtenerTareaAdminSeleccionada();
        
        // Si no está en el servicio, buscar en el cache por ID
        if (!tareaLocal || tareaLocal.id !== tareaId) {
          tareaLocal = this.tareasService.obtenerTareaAdminPorIdLocal(tareaId);
        }
        
        // Usar datos locales inicialmente para carga rápida
        if (tareaLocal) {
          this.tareaAdminSeleccionada = tareaLocal;
          this.cdr.markForCheck();
        }
        
        // Cargar tarea y subtareas en paralelo desde el servidor
        forkJoin({
          tarea: this.tareasService.obtenerTareaPorId(tareaId),
          subtareas: this.tareasService.obtenerSubtareas(tareaId)
        }).subscribe({
          next: (results: any) => {
            // Actualizar tarea
            if (results.tarea?.tipo === 1 && results.tarea.data) {
              this.tareaAdminSeleccionada = results.tarea.data;
              this.cdr.markForCheck();
              this.cdr.detectChanges();
            }
            
            // Actualizar subtareas
            if (results.subtareas?.tipo === 1 && results.subtareas.data) {
              const nuevasSubtareas = results.subtareas.data.map((s: any) => ({
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
              
              this.subtareas = [...nuevasSubtareas];
              this.cdr.markForCheck();
              this.cdr.detectChanges();
            }
          },
          error: (err: any) => {
            console.error('Error cargando tarea y subtareas:', err);
          }
        });
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
        this.subtareas = [...this.tareaAdminSeleccionada.Tarea];
        this.cdr.markForCheck();
        this.cdr.detectChanges();
      }
    }
  }

  descompletarSubtarea(subtareaId: string): void {
    if (this.tareaAdminSeleccionada) {
      this.tareasService.descompletarSubtareaAdmin(this.tareaAdminSeleccionada.id, subtareaId);
      if (this.tareaAdminSeleccionada.Tarea) {
        this.subtareas = [...this.tareaAdminSeleccionada.Tarea];
        this.cdr.markForCheck();
        this.cdr.detectChanges();
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
      console.log('SubtareaInfo dismissed:', result);
      
      // Si viene de un clic en editar
      if (result.role === 'edit' && result.data?.editarSubtarea) {
        this.editarSubtareaModal(result.data.subtarea);
        return;
      }
      
      if (result.role === 'confirm' || result.data?.actualizada) {
        // Recargar subtareas si hubo cambios
        this.cargarSubtareas();
      }
    });

    await modal.present();
  }

  async editarSubtareaModal(subtarea: Tarea) {
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
        taskTitulo: this.tareaAdminSeleccionada.titulo,
        edit: true,
        subtareaId: subtarea.id,
        titulo: subtarea.titulo,
        descripcion: subtarea.descripcion,
        estado: subtarea.estado,
        prioridad: subtarea.Prioridad
      }
    });

    await modal.present();

    const { data, role } = await modal.onWillDismiss();
    
    if (role === 'confirm' && data?.created) {
      // Recargar subtareas
      this.cargarSubtareas();
      this.mostrarToast('Subtarea actualizada correctamente', 'success');
    }
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
      component: ModalReaperturar,
      initialBreakpoint: 1,
      breakpoints: [0, 1, 1],
      componentProps: {
        tarea: this.tareaAdminSeleccionada
      }
    });

    await modal.present();

    const { data } = await modal.onWillDismiss();
    
    if (data && data.reaperturada) {
      // Llamar al servicio para reabrir la tarea
      this.tareasService.reabrirTarea(
        this.tareaAdminSeleccionada.id,
        data.motivo,
        data.observaciones,
        data.reasignarTarea ? data.usuarioSeleccionado : undefined,
        data.fechaVencimientoNueva,
        data.prioridadNueva
      ).subscribe({
        next: (response: any) => {
          if (response?.tipo === 1) {
            this.tareaAdminSeleccionada!.estado = 'Pendiente';
            this.tareasService.notificarActualizacion();
            this.mostrarToast('Tarea reaperturada correctamente', 'success');
            this.cargarSubtareas();
          } else {
            this.mostrarToast(response?.mensajes?.[0] || 'Error al reabrir tarea', 'danger');
          }
        },
        error: (err: any) => {
          console.error('Error reabriendo tarea:', err);
          this.mostrarToast('Error al reabrir tarea', 'danger');
        }
      });
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

  // Editar tarea (solo admin)
  editarTarea() {
    if (!this.tareaAdminSeleccionada) {
      this.mostrarToast('No hay tarea seleccionada', 'danger');
      return;
    }

    this.abrirModalEditarTarea();
  }

  private async abrirModalEditarTarea() {
    if (!this.tareaAdminSeleccionada) return;

    const modal = await this.modalController.create({
      component: Creartarea,
      breakpoints: [0, 0.5, 0.75, 1],
      initialBreakpoint: 1,
      componentProps: {
        edit: true,
        tareaId: this.tareaAdminSeleccionada.id,
        titulo: this.tareaAdminSeleccionada.titulo,
        descripcion: this.tareaAdminSeleccionada.descripcion,
        categoria: this.tareaAdminSeleccionada.Categoria,
        prioridad: this.tareaAdminSeleccionada.Prioridad,
        horainicio: this.tareaAdminSeleccionada.horainicio,
        horafin: this.tareaAdminSeleccionada.horafin,
        sucursal: this.tareaAdminSeleccionada.sucursal
      }
    });

    await modal.present();

    const { data, role } = await modal.onWillDismiss();
    
    if (role === 'confirm' && data?.updated) {
      this.mostrarToast('Tarea actualizada correctamente', 'success');
      this.cargarTareaAdmin();
    }
  }

  // Eliminar tarea (solo admin)
  async eliminarTarea() {
    if (!this.tareaAdminSeleccionada) {
      this.mostrarToast('No hay tarea seleccionada', 'danger');
      return;
    }

    // Crear modal de confirmación
    const alert = await this.alertController.create({
      header: 'Eliminar Tarea',
      message: `¿Estás seguro de que deseas eliminar la tarea "${this.tareaAdminSeleccionada.titulo}"? Esta acción no se puede deshacer.`,
      buttons: [
        {
          text: 'Cancelar',
          role: 'cancel',
          handler: () => {
            // No hacer nada
          }
        },
        {
          text: 'Eliminar',
          role: 'destructive',
          handler: () => {
            this.confirmarEliminacion();
          }
        }
      ]
    });

    await alert.present();
  }

  private confirmarEliminacion() {
    if (!this.tareaAdminSeleccionada) return;

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
      breakpoints: [0, 1, 1, 1],
      initialBreakpoint: 1,
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

  private cargarTareaAdmin() {
    if (!this.tareaAdminSeleccionada) return;

    const tareaId = this.tareaAdminSeleccionada.id;
    
    // Recargar la tarea actualizada desde el servidor
    this.tareasService.obtenerTareaPorId(tareaId).subscribe({
      next: (response: any) => {
        if (response?.tipo === 1 && response.data) {
          this.tareaAdminSeleccionada = response.data;
          this.cargarSubtareas();
          this.cdr.markForCheck();
          this.cdr.detectChanges();
        }
      },
      error: (err: any) => {
        console.error('Error cargando tarea actualizada:', err);
      }
    });
  }

  // Cargar subtareas desde el backend
  cargarSubtareas() {
    if (!this.tareaAdminSeleccionada) return;
    console.log('cargarSubtareas called for task', this.tareaAdminSeleccionada?.id);
    
    this.tareasService.obtenerSubtareas(this.tareaAdminSeleccionada.id).subscribe({
      next: (response) => {
        console.log('GET /subtareas/task response', response);
        if (response.tipo === 1 && response.data) {
          const nuevasSubtareas = response.data.map((s: any) => ({
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
          
          // Reasignar el array para forzar la detección de cambios
          this.subtareas = [...nuevasSubtareas];
          
          // Actualizar contadores en la tarea admin
          if (this.tareaAdminSeleccionada) {
            this.tareaAdminSeleccionada.totalSubtareas = this.subtareas.length;
            this.tareaAdminSeleccionada.subtareasCompletadas = this.subtareas.filter(s => s.completada).length;
          }

          // Forzar detección de cambios explícitamente
          this.cdr.markForCheck();
          this.cdr.detectChanges();
          
          console.log('subtareas assigned:', this.subtareas.length, 'tarea.totalSubtareas', this.tareaAdminSeleccionada?.totalSubtareas);
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

  // Texto del botón de acción según estado de la tarea
  textoBotonAccion(): string {
    if (!this.tareaAdminSeleccionada) return '';
    switch (this.tareaAdminSeleccionada.estado) {
      case 'Pendiente': return 'Iniciar tarea';
      case 'En progreso': return 'Finalizar tarea';
      case 'Completada': return this.isAdmin ? 'Reaperturar tarea' : '';
      default: return 'Iniciar tarea';
    }
  }

  // Color del botón según estado
  colorBotonAccion(): string {
    if (!this.tareaAdminSeleccionada) return 'primary';
    switch (this.tareaAdminSeleccionada.estado) {
      case 'Pendiente': return 'primary';
      case 'En progreso': return 'success';
      case 'Completada': return 'warning';
      default: return 'primary';
    }
  }

  // Ejecuta la acción principal (iniciar / finalizar / reaperturar según estado)
  ejecutarAccionPrincipal() {
    if (!this.tareaAdminSeleccionada) return;
    switch (this.tareaAdminSeleccionada.estado) {
      case 'Pendiente':
        this.iniciarTarea();
        break;
      case 'En progreso':
        this.finalizarTarea();
        break;
      case 'Completada':
        if (this.isAdmin) this.abrirModalReaperturar();
        break;
    }
  }

  // Llamada para iniciar tarea (cambia estado a 'En progreso')
  iniciarTarea() {
    if (!this.tareaAdminSeleccionada) return;
    this.tareasService.iniciarTarea(this.tareaAdminSeleccionada.id).subscribe({
      next: (response) => {
        if (response.tipo === 1) {
          this.tareaAdminSeleccionada!.estado = 'En progreso';
          this.mostrarToast('Tarea iniciada', 'success');
          this.tareasService.notificarActualizacion();
          this.cdr.markForCheck();
          this.cdr.detectChanges();
        } else {
          this.mostrarToast(response.mensajes?.[0] || 'Error al iniciar tarea', 'danger');
        }
      },
      error: (err) => {
        console.error('Error al iniciar tarea:', err);
        this.mostrarToast('Error al iniciar la tarea', 'danger');
      }
    });
  }

  // Llamada para finalizar tarea (pide confirmación)
  async finalizarTarea() {
    if (!this.tareaAdminSeleccionada) return;

    const modal = await this.modalController.create({
      component: ModalCompletar,
      componentProps: {
        tarea: this.tareaAdminSeleccionada
      },
      breakpoints: [0, 1, 1, 1],
      initialBreakpoint: 1
    });

    modal.onDidDismiss().then((result) => {
      if (result.role === 'confirm' && result.data) {
        const datos = result.data;
        // Enviar múltiples imágenes si existen
        const imagenes = datos.imagenesArchivos?.length > 0 ? datos.imagenesArchivos : undefined;
        this.tareasService.finalizarTarea(this.tareaAdminSeleccionada!.id, datos.observaciones || '', imagenes)
          .subscribe({
            next: (response: any) => {
              if (response.tipo === 1) {
                // Recargar la tarea completa para obtener las imágenes guardadas
                this.tareasService.getTaskById(parseInt(this.tareaAdminSeleccionada!.id)).subscribe({
                  next: (tareaActualizada: any) => {
                    if (tareaActualizada.tipo === 1 && tareaActualizada.data) {
                      this.tareaAdminSeleccionada = tareaActualizada.data;
                      this.mostrarToast('Tarea finalizada exitosamente', 'success');
                      this.tareasService.notificarActualizacion();
                      this.cdr.markForCheck();
                      this.cdr.detectChanges();
                    }
                  },
                  error: (err: any) => {
                    console.error('Error al recargar tarea:', err);
                    // Aun así actualizar el estado local
                    this.tareaAdminSeleccionada!.estado = 'Completada';
                    this.tareaAdminSeleccionada!.completada = true;
                    this.tareaAdminSeleccionada!.observaciones = datos.observaciones;
                    this.mostrarToast('Tarea finalizada (intenta recargar la página para ver las imágenes)', 'success');
                    this.tareasService.notificarActualizacion();
                    this.cdr.markForCheck();
                    this.cdr.detectChanges();
                  }
                });
              } else {
                this.mostrarToast(response.mensajes?.[0] || 'Error al finalizar tarea', 'danger');
              }
            },
            error: (err: any) => {
              console.error('Error al finalizar tarea:', err);
              this.mostrarToast('Error al finalizar la tarea', 'danger');
            }
          });
      }
    });

    await modal.present();
  }
}
