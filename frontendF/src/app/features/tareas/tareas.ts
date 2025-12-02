import { Component, OnInit, OnDestroy, ChangeDetectionStrategy, ChangeDetectorRef, ViewChild } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { MenuController, ModalController, IonDatetime, AlertController } from '@ionic/angular';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import {
  faAngleLeft,
  faAngleRight,
  faBars,
  faCalendar,
  faCheck,
  faClock,
  faFlag,
  faLayerGroup,
  faListCheck,
  faSliders,
  faSpinner,
  faStore,
  faTasks,
  faUsers,
  faPlus,
  faTrash,
  faEdit,
  faEllipsisV,
  faSignOut,
  faChevronDown,
  faChevronUp,
  faRotateLeft
} from '@fortawesome/pro-regular-svg-icons';
import { TareasService, TareaAdmin, Tarea, ResumenTareas } from './service/tareas.service';
import { AuthService } from '../../core/services/auth.service';
import { ToastService } from '../../core/services/toast.service';
import { ModalReaperturar } from './pages/modal-reaperturar/modal-reaperturar';
import { ModalCompletar } from './pages/modal-completar/modal-completar';
import { SubtareaInfo } from './pages/subtarea-info/subtarea-info';

@Component({
  selector: 'app-tareas',
  standalone: false,
  templateUrl: './tareas.html',
  styleUrl: './tareas.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class Tareas implements OnInit, OnDestroy {
  // FontAwesome Icons
  faAngleLeft = faAngleLeft;
  faAngleRight = faAngleRight;
  faUsers = faUsers;
  faTasks = faTasks;
  faBars = faBars;
  faCalendar = faCalendar;
  faClock = faClock;
  faSpinner = faSpinner;
  faCheck = faCheck;
  faSliders = faSliders;
  faStore = faStore;
  faLayerGroup = faLayerGroup;
  faListCheck = faListCheck;
  faFlag = faFlag;
  faPlus = faPlus;
  faTrash = faTrash;
  faEdit = faEdit;
  faEllipsisV = faEllipsisV;
  faSignOut = faSignOut;
  faChevronDown = faChevronDown;
  faChevronUp = faChevronUp;
  faRotateLeft = faRotateLeft;

  // Estado de la página
  selectedTab: 'admin' | 'mis-tareas' | 'sin-asignar' = 'admin';
  tareasAdmin: TareaAdmin[] = [];
  misTareas: TareaAdmin[] = [];
  tareasSinAsignar: TareaAdmin[] = [];
  resumen: ResumenTareas = {
    totalTareas: 0,
    tareasCompletadas: 0,
    tareasEnProgreso: 0,
    porcentajeAvance: 0
  };
  
  // Variables de calendario
  dia: Date = new Date();
  nroSemana: number = 1;
  diaString: string = '';
  semana: { fecha: Date; habilitado: boolean }[] = [];
  verCalendario: boolean = true;
  mostrarCalendarioCompleto: boolean = false;
  fechaSeleccionadaISO: string = new Date().toISOString();
  
  // Usuario
  nombreUsuario: string = 'Usuario';
  rolUsuario: string = 'Usuario';
  isAdmin: boolean = false;
  userId: number | null = null;
  
  // Filtros
  filtroEstado: string = 'todos';
  filtroCategoria: string = 'todos';
  filtrarPorFecha: boolean = true;
  searchTerm: string = '';
  
  // Selección múltiple
  modoSeleccion: boolean = false;
  tareasSeleccionadas = new Set<string>();
  
  // Control de carga
  cargando: boolean = true;
  error: string | null = null;
  
  private destroy$ = new Subject<void>();
  private primeraVez = true; // Para evitar doble carga inicial

  constructor(
    private tareasService: TareasService,
    private modalController: ModalController,
    private menuController: MenuController,
    private alertController: AlertController,
    private authService: AuthService,
    private toastService: ToastService,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    // Resetear estado al inicializar
    this.resetearEstado();
    this.cargarDatosUsuario(); // Primero cargar datos del usuario
    this.inicializar();
    
    // Suscribirse a actualizaciones del servicio para recargar tareas automáticamente
    this.tareasService.actualizacion$
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => {
        this.cargarTareas();
      });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  // Resetear todo el estado del componente
  private resetearEstado(): void {
    this.tareasAdmin = [];
    this.misTareas = [];
    this.tareasSinAsignar = [];
    this.tareasSeleccionadas.clear();
    this.modoSeleccion = false;
    this.selectedTab = 'admin';
    this.isAdmin = false;
    this.userId = null;
    this.nombreUsuario = 'Usuario';
    this.rolUsuario = 'Usuario';
  }

  inicializar(): void {
    // Si no es admin, forzar tab "mis-tareas"
    if (!this.isAdmin) {
      this.selectedTab = 'mis-tareas';
    }
    
    // Actualizar fecha sin recargar tareas
    this.diaString = this.formatearFecha(this.dia);
    this.nroSemana = this.calcularNumeroSemana(this.dia);
    this.fechaSeleccionadaISO = this.dia.toISOString();
    this.generarSemana();
    // Primera carga de tareas
    this.cargarTareas();
  }

  cargarDatosUsuario(): void {
    const usuario = this.authService.getCurrentUser();
    if (usuario) {
      this.nombreUsuario = usuario.username || 'Usuario';
      this.rolUsuario = usuario.role === 'admin' ? 'Administrador' : 'Usuario';
      this.isAdmin = usuario.role === 'admin';
      this.userId = usuario.id ?? null;
    }
  }

  generarSemana(): void {
    this.semana = [];
    const inicioSemana = this.getInicioSemana(this.dia);
    
    for (let i = 0; i < 7; i++) {
      const fecha = this.addDays(inicioSemana, i);
      this.semana.push({
        fecha: fecha,
        habilitado: true
      });
    }
    this.cdr.markForCheck();
  }

  private getInicioSemana(fecha: Date): Date {
    const d = new Date(fecha);
    const day = d.getDay();
    const diff = d.getDate() - day + (day === 0 ? -6 : 1);
    return new Date(d.setDate(diff));
  }

  seleccionarDia(fecha: Date): void {
    this.dia = fecha;
    this.actualizarFecha();
  }

  verSemanaAnterior(): void {
    this.dia = this.addDays(this.dia, -7);
    this.actualizarFecha();
    this.generarSemana();
  }

  verSemanaSiguiente(): void {
    this.dia = this.addDays(this.dia, 7);
    this.actualizarFecha();
    this.generarSemana();
  }

  isSameDay(fecha1: Date, fecha2: Date): boolean {
    return fecha1.getDate() === fecha2.getDate() &&
           fecha1.getMonth() === fecha2.getMonth() &&
           fecha1.getFullYear() === fecha2.getFullYear();
  }

  toggleCalendario(): void {
    this.verCalendario = !this.verCalendario;
    this.cdr.markForCheck();
  }

  // Abrir/cerrar calendario completo popup
  toggleCalendarioCompleto(): void {
    this.mostrarCalendarioCompleto = !this.mostrarCalendarioCompleto;
    this.cdr.markForCheck();
  }

  // Cuando se selecciona fecha del calendario popup
  onFechaCalendarioChange(event: any): void {
    const fechaISO = event.detail.value;
    this.fechaSeleccionadaISO = fechaISO;
    this.dia = new Date(fechaISO);
    this.actualizarFecha();
    this.generarSemana();
    this.mostrarCalendarioCompleto = false;
    this.cdr.markForCheck();
  }

  // Toggle filtrar por fecha - recarga tareas al cambiar
  toggleFiltrarPorFecha(): void {
    this.filtrarPorFecha = !this.filtrarPorFecha;
    this.cargarTareas(); // Recargar con o sin filtro de fecha
    this.cdr.markForCheck();
  }

  // Watch del toggle para ngModel
  onFiltrarPorFechaChange(): void {
    this.cargarTareas();
    this.cdr.markForCheck();
  }

  async cerrarSesion(): Promise<void> {
    // Cerrar el menú primero
    await this.menuController.close('main-menu');
    // Llamar al logout del servicio (limpia localStorage y navega)
    this.authService.logout();
  }

  async irACrearTarea(): Promise<void> {
    await this.menuController.close('main-menu');
    this.router.navigate(['/features/tareas/crear-tarea']);
  }

  getDiaNombreCorto(fecha: Date): string {
    const dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    return dias[fecha.getDay()];
  }

  // Formatea la fecha a YYYY-MM-DD para el backend
  private formatearFechaISO(fecha: Date): string {
    const year = fecha.getFullYear();
    const month = (fecha.getMonth() + 1).toString().padStart(2, '0');
    const day = fecha.getDate().toString().padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  cargarTareas(): void {
    this.cargando = true;
    this.cdr.markForCheck();
    
    // Construir filtros para enviar al backend
    const filtros: { fecha?: string; status?: string; assigned_user_id?: number } = {};
    
    if (this.filtrarPorFecha) {
      filtros.fecha = this.formatearFechaISO(this.dia);
    }
    
    if (this.isAdmin) {
      // Admin: cargar todas las tareas (para vista admin) y luego filtrar en tabs
      this.tareasService.obtenerTareasAdmin(filtros)
        .pipe(takeUntil(this.destroy$))
        .subscribe({
          next: (response) => {
            if (response.tipo === 1) {
              // Separar tareas que ya están asignadas de las sin asignar
              const todas = response.data.tareas || [];
              this.tareasAdmin = todas.filter((t: any) => t.usuarioasignado_id !== null && t.usuarioasignado_id !== undefined && t.usuarioasignado_id !== '');
              this.actualizarResumen();
              // Forzar actualización de la vista inmediatamente
              this.cdr.markForCheck();

              // Además, cargar tareas sin asignar para mostrar en la pestaña correspondiente
              const fecha = filtros.fecha ?? undefined;
              this.tareasService.obtenerTareasSinAsignar(fecha)
                .pipe(takeUntil(this.destroy$))
                .subscribe({
                  next: (resSin) => {
                    if (resSin.tipo === 1) {
                      this.tareasSinAsignar = resSin.data.tareas || [];
                    } else {
                      // Fallback: si el endpoint sin_asignar no devuelve datos, usar filtro local
                      this.tareasSinAsignar = todas.filter((t: any) => t.usuarioasignado_id === null || t.usuarioasignado_id === undefined || t.usuarioasignado_id === '');
                    }
                    this.cdr.markForCheck();
                  },
                  error: (err) => {
                    console.error('Error cargando tareas sin asignar (admin):', err);
                    // Fallback local
                    this.tareasSinAsignar = todas.filter((t: any) => t.usuarioasignado_id === null || t.usuarioasignado_id === undefined || t.usuarioasignado_id === '');
                    this.cdr.markForCheck();
                  }
                });
            } else {
              this.error = response.mensajes[0] || 'Error al cargar tareas';
            }
            this.cargando = false;
            this.cdr.markForCheck();
          },
          error: (err) => {
            console.error('Error completo:', err);
            console.error('Status:', err.status);
            console.error('Message:', err.message);
            console.error('Error body:', err.error);
            
            // Mostrar más información del error
            if (err.status === 0) {
              this.error = 'Error de red: No se pudo conectar con el servidor (CORS o servidor no disponible)';
            } else if (err.status === 401) {
              this.error = 'No autorizado: La sesión ha expirado';
            } else if (err.status === 403) {
              this.error = 'Acceso denegado';
            } else if (err.status === 500) {
              this.error = 'Error interno del servidor';
            } else {
              this.error = `Error al conectar con el servidor (${err.status || 'desconocido'})`;
            }
            this.cargando = false;
            this.cdr.markForCheck();
          }
        });
    } else {
      // Usuario: cargar sus tareas asignadas y tareas sin asignar
      this.cargarTareasUsuario();
    }
  }

  // Cargar tareas para usuario normal
  cargarTareasUsuario(): void {
    const filtros: { fecha?: string; assigned_user_id?: number } = {};
    
    if (this.filtrarPorFecha) {
      filtros.fecha = this.formatearFechaISO(this.dia);
    }
    
    if (this.userId) {
      filtros.assigned_user_id = this.userId;
    }

    // Cargar mis tareas asignadas
    this.tareasService.obtenerTareasAdmin(filtros)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response.tipo === 1) {
            // Ordenar por prioridad (Alta > Media > Baja) y luego estado (Completada al final)
            this.misTareas = this.ordenarTareasPorPrioridadYEstado(response.data.tareas);
            this.actualizarResumenUsuario();
          }
          this.cargando = false;
          this.cdr.markForCheck();
        },
        error: (err) => {
          console.error('Error:', err);
          this.error = 'Error al conectar con el servidor';
          this.cargando = false;
          this.cdr.markForCheck();
        }
      });

    // Cargar tareas sin asignar del día
    const fechaHoy = this.formatearFechaISO(this.dia);
    this.tareasService.obtenerTareasSinAsignar(fechaHoy)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response.tipo === 1) {
            this.tareasSinAsignar = response.data.tareas;
          }
          this.cdr.markForCheck();
        },
        error: (err) => console.error('Error cargando tareas sin asignar:', err)
      });
  }

  // Ordenar tareas: primero por prioridad, luego completadas al final
  ordenarTareasPorPrioridadYEstado(tareas: TareaAdmin[]): TareaAdmin[] {
    const prioridadOrden: { [key: string]: number } = { 'Alta': 1, 'Media': 2, 'Baja': 3 };
    const estadoOrden: { [key: string]: number } = { 'En progreso': 1, 'Pendiente': 2, 'Completada': 3 };

    return tareas.sort((a, b) => {
      // Primero por estado (Completada al final)
      const estadoA = estadoOrden[a.estado] || 99;
      const estadoB = estadoOrden[b.estado] || 99;
      if (estadoA !== estadoB) return estadoA - estadoB;

      // Luego por prioridad (Alta primero) - usando las subtareas
      const prioridadA = a.Tarea && a.Tarea[0] ? (prioridadOrden[a.Tarea[0].Prioridad] || 99) : 99;
      const prioridadB = b.Tarea && b.Tarea[0] ? (prioridadOrden[b.Tarea[0].Prioridad] || 99) : 99;
      return prioridadA - prioridadB;
    });
  }

  // Actualizar resumen para usuario
  actualizarResumenUsuario(): void {
    const tareas = this.misTareas;
    this.resumen = {
      totalTareas: tareas.length,
      tareasCompletadas: tareas.filter(t => t.estado === 'Completada').length,
      tareasEnProgreso: tareas.filter(t => t.estado === 'En progreso').length,
      porcentajeAvance: tareas.length > 0 
        ? Math.round((tareas.filter(t => t.estado === 'Completada').length / tareas.length) * 100) 
        : 0
    };
  }

  // Auto-asignar tareas seleccionadas al usuario
  async asignarTareasSeleccionadas(): Promise<void> {
    if (this.tareasSeleccionadas.size === 0) return;

    // Validar que solo se puedan asignar tareas de hoy
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const hoyStr = this.formatearFechaISO(hoy);
    
    // Filtrar solo tareas de hoy
    const tareasValidas = this.tareasSinAsignar.filter(t => {
      return this.tareasSeleccionadas.has(t.id) && t.fechaAsignacion === hoyStr;
    });
    
    if (tareasValidas.length === 0) {
      this.toastService.error('Solo puedes auto-asignarte tareas del día de hoy');
      this.tareasSeleccionadas.clear();
      this.cdr.markForCheck();
      return;
    }

    const tareasIds = tareasValidas.map(t => t.id);
    let exitos = 0;

    for (const tareaId of tareasIds) {
      try {
        await this.tareasService.autoAsignarTarea(tareaId).toPromise();
        exitos++;
      } catch (err) {
        console.error(`Error asignando tarea ${tareaId}:`, err);
      }
    }

    if (exitos > 0) {
      this.tareasSeleccionadas.clear();
      this.toastService.success(`${exitos} tarea(s) asignada(s) correctamente`);
      this.cargarTareas();
    } else {
      this.toastService.error('No se pudieron asignar las tareas');
    }
  }

  // Iniciar tarea (cambiar a En progreso) con confirmación
  async iniciarTarea(tarea: TareaAdmin, event?: Event): Promise<void> {
    if (event) event.stopPropagation();

    // Validar que no se pueda iniciar tareas de días anteriores
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const fechaTarea = new Date(tarea.fechaAsignacion + 'T00:00:00');
    fechaTarea.setHours(0, 0, 0, 0);
    
    if (fechaTarea < hoy) {
      this.toastService.error('No puedes iniciar tareas de días anteriores');
      return;
    }

    const alert = await this.alertController.create({
      header: 'Iniciar Tarea',
      message: `¿Estás seguro de que deseas iniciar la tarea "${tarea.titulo}"?`,
      buttons: [
        {
          text: 'Cancelar',
          role: 'cancel'
        },
        {
          text: 'Iniciar',
          handler: () => {
            this.ejecutarIniciarTarea(tarea);
          }
        }
      ]
    });

    await alert.present();
  }

  // Ejecutar inicio de tarea
  private ejecutarIniciarTarea(tarea: TareaAdmin): void {
    this.tareasService.iniciarTarea(tarea.id)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response.tipo === 1) {
            this.toastService.success('Tarea iniciada correctamente');
            this.cargarTareas();
          } else {
            this.toastService.error(response.mensajes?.join(' ') || 'Error al iniciar tarea');
          }
        },
        error: (err) => {
          console.error('Error iniciando tarea:', err);
          this.toastService.error('Error al iniciar la tarea');
        }
      });
  }

  // Finalizar tarea con evidencia
  async finalizarTarea(tarea: TareaAdmin, event?: Event): Promise<void> {
    if (event) event.stopPropagation();

    const modal = await this.modalController.create({
      component: ModalCompletar,
      componentProps: { tarea },
      breakpoints: [0, 0.85, 0.95],
      initialBreakpoint: 0.85
    });

    modal.onDidDismiss().then((result) => {
      if (result.role === 'confirm' && result.data) {
        this.tareasService.finalizarTarea(tarea.id, result.data.observaciones, result.data.imagen)
          .pipe(takeUntil(this.destroy$))
          .subscribe({
            next: (response) => {
              this.toastService.success('Tarea completada correctamente');
              this.cargarTareas();
            },
            error: (err) => {
              console.error('Error finalizando tarea:', err);
              this.toastService.error('Error al completar la tarea');
            }
          });
      }
    });

    await modal.present();
  }

  // Toggle selección de tarea para auto-asignación
  toggleSeleccionTarea(tareaId: string, event?: Event): void {
    if (event) {
      event.stopPropagation();
    }

    if (this.tareasSeleccionadas.has(tareaId)) {
      this.tareasSeleccionadas.delete(tareaId);
    } else {
      this.tareasSeleccionadas.add(tareaId);
    }
    this.cdr.markForCheck();
  }

  // Verificar si una tarea está seleccionada
  isTareaSeleccionada(tareaId: string): boolean {
    return this.tareasSeleccionadas.has(tareaId);
  }

  actualizarResumen(): void {
    this.resumen = this.tareasService.calcularResumen();
    this.cdr.markForCheck();
  }

  actualizarFecha(): void {
    this.diaString = this.formatearFecha(this.dia);
    this.nroSemana = this.calcularNumeroSemana(this.dia);
    this.fechaSeleccionadaISO = this.dia.toISOString();
    // Recargar tareas al cambiar fecha
    this.cargarTareas();
    this.cdr.markForCheck();
  }

  private formatearFecha(fecha: Date): string {
    const dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const diaNombre = dias[fecha.getDay()];
    const diaNumero = fecha.getDate().toString().padStart(2, '0');
    const mesNombre = meses[fecha.getMonth()];
    return `${diaNombre} ${diaNumero} ${mesNombre}`;
  }

  /**
   * Formatear fecha en formato corto DD/MM
   * Acepta string YYYY-MM-DD o Date
   */
  formatearFechaCorta(fecha: string | Date | null | undefined): string {
    if (!fecha) return '';
    try {
      const d = typeof fecha === 'string' ? new Date(fecha + 'T00:00:00') : fecha;
      if (isNaN(d.getTime())) return String(fecha);
      const dia = d.getDate().toString().padStart(2, '0');
      const mes = (d.getMonth() + 1).toString().padStart(2, '0');
      return `${dia}/${mes}`;
    } catch (e) {
      return String(fecha);
    }
  }

  private addDays(fecha: Date, dias: number): Date {
    const resultado = new Date(fecha);
    resultado.setDate(resultado.getDate() + dias);
    return resultado;
  }

  private calcularNumeroSemana(fecha: Date): number {
    const primerDia = new Date(fecha.getFullYear(), 0, 1);
    const diasDiferencia = (fecha.getTime() - primerDia.getTime()) / (24 * 60 * 60 * 1000);
    return Math.ceil((diasDiferencia + primerDia.getDay() + 1) / 7);
  }

  diaAnterior(): void {
    this.dia = this.addDays(this.dia, -1);
    this.actualizarFecha();
  }

  diaSiguiente(): void {
    this.dia = this.addDays(this.dia, 1);
    this.actualizarFecha();
  }

  irAHoy(): void {
    this.dia = new Date();
    this.actualizarFecha();
    this.generarSemana();
  }

  obtenerTareasFiltradas(): TareaAdmin[] {
    let tareas = this.tareasAdmin;

    // El filtrado por fecha ya se hace en backend
    // Aquí solo filtramos los campos adicionales en frontend

    if (this.filtroEstado !== 'todos') {
      tareas = tareas.filter(t => t.estado === this.filtroEstado);
    }

    if (this.filtroCategoria !== 'todos') {
      tareas = tareas.filter(t => t.Categoria === this.filtroCategoria);
    }

    if (this.searchTerm) {
      tareas = tareas.filter(t => 
        t.titulo.toLowerCase().includes(this.searchTerm.toLowerCase())
      );
    }

    return tareas;
  }

  alternarSeleccion(tareaId: string): void {
    this.tareasService.alternarSeleccion(tareaId);
    this.tareasSeleccionadas = new Set(this.tareasService['tareasSeleccionadasSubject'].value);
    this.cdr.markForCheck();
  }

  cambiarEstadoTarea(tareaId: string, nuevoEstado: string): void {
    this.tareasService.actualizarTareaAdmin(tareaId, { estado: nuevoEstado as any })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: () => this.cargarTareas(),
        error: (err) => console.error('Error:', err)
      });
  }

  eliminarTarea(tareaId: string): void {
    if (confirm('¿Desea eliminar esta tarea?')) {
      this.tareasService.eliminarTareaAdmin(tareaId)
        .pipe(takeUntil(this.destroy$))
        .subscribe({
          next: () => this.cargarTareas(),
          error: (err) => console.error('Error:', err)
        });
    }
  }

  // Abrir modal de información de tarea usando SubtareaInfo
  async verDetallesTarea(tarea: TareaAdmin): Promise<void> {
    // Convertir TareaAdmin a Tarea para compatibilidad con SubtareaInfo
    const tareaInfo: Tarea = {
      id: tarea.id,
      titulo: tarea.titulo,
      descripcion: tarea.descripcion || '',
      estado: tarea.estado as any,
      estadodetarea: 'Activo',
      prioridad: (tarea.Prioridad || tarea.prioridad || 'Media') as any,
      Prioridad: (tarea.Prioridad || tarea.prioridad || 'Media') as any,
      completada: tarea.estado === 'Completada',
      progreso: 0,
      fechaAsignacion: tarea.fechaAsignacion,
      Categoria: tarea.Categoria,
      horaprogramada: tarea.horaprogramada,
      horainicio: tarea.horainicio,
      horafin: tarea.horafin,
      usuarioasignado: tarea.usuarioasignado || '',
      usuarioasignado_id: tarea.usuarioasignado_id || 0,
      totalSubtareas: tarea.totalSubtareas,
      subtareasCompletadas: tarea.subtareasCompletadas
    };

    const modal = await this.modalController.create({
      component: SubtareaInfo,
      componentProps: {
        tarea: tareaInfo,
        tareaadmin: tarea
      },
      breakpoints: [0, 0.75, 1],
      initialBreakpoint: 0.75
    });

    modal.onDidDismiss().then((result) => {
      if (result.role === 'confirm' || result.data?.actualizada) {
        this.cargarTareas();
      }
    });

    await modal.present();
  }

  // Mostrar opciones para tarea completada
  async mostrarOpcionesTareaCompletada(tarea: TareaAdmin): Promise<void> {
    const modal = await this.modalController.create({
      component: ModalReaperturar,
      componentProps: {
        tarea: tarea,
        accion: 'reaperturar'
      },
      breakpoints: [0, 0.5, 0.75, 1],
      initialBreakpoint: 0.75
    });

    modal.onDidDismiss().then((result) => {
      if (result.role === 'confirm' && result.data) {
        // Procesar reapertura
        this.procesarReapertura(tarea.id, result.data);
      }
    });

    await modal.present();
  }

  // Procesar reapertura de tarea
  procesarReapertura(tareaId: string, datos: any): void {
    // Si se solicita reasignación y hay usuario seleccionado, usarlo.
    const hoyIso = this.formatearFechaISO(new Date());
    // Normalizar fecha de vencimiento: usar la fecha dada o reiniciar a hoy
    let fechaVencimiento = datos?.fechaVencimientoNueva ? (String(datos.fechaVencimientoNueva).slice(0,10)) : hoyIso;
    // Normalizar prioridad: convertir a formato capitalizado si viene en minúsculas
    const prioridad = datos?.prioridadNueva ? (String(datos.prioridadNueva).charAt(0).toUpperCase() + String(datos.prioridadNueva).slice(1)) : undefined;

    const actualizacionBase: any = {
      estado: 'Pendiente',
      motivoReapertura: datos.motivo,
      observacionesReapertura: datos.observaciones,
      fechaAsignacion: hoyIso,
      fechaVencimiento: fechaVencimiento
    };
    if (prioridad) {
      actualizacionBase.Prioridad = prioridad;
      actualizacionBase.prioridad = prioridad;
    }

    const hacerActualizacion = (payload: any) => {
      this.tareasService.actualizarTareaAdmin(tareaId, payload)
        .pipe(takeUntil(this.destroy$))
        .subscribe({
          next: () => {
            this.toastService.success('Tarea reaperturada correctamente');
            this.cargarTareas();
          },
          error: (err) => {
            console.error('Error al reaperturar:', err);
            this.toastService.error('Error al reaperturar tarea');
          }
        });
    };

    if (datos?.reasignarTarea) {
        if (datos?.usuarioSeleccionado) {
        // usuarioSeleccionado contiene el id del usuario (string/number)
        const payload = { ...actualizacionBase, usuarioasignado_id: Number(datos.usuarioSeleccionado) };
        hacerActualizacion(payload);
        return;
      }

      if (datos?.cargoSeleccionado) {
        // buscar un usuario disponible con ese cargo y asignar
        this.tareasService.getAvailableUsers().pipe(takeUntil(this.destroy$)).subscribe({
          next: (resp) => {
            if (resp?.tipo === 1 && Array.isArray(resp.data)) {
              const usuarios = resp.data as any[];
              const candidato = usuarios.find(u => u.departamento === datos.cargoSeleccionado) || usuarios[0];
              if (candidato) {
                const payload = { ...actualizacionBase, usuarioasignado_id: candidato.id };
                hacerActualizacion(payload);
                return;
              }
            }
            // si no hay usuarios, hacer la actualización básica sin asignación
            hacerActualizacion(actualizacionBase);
          },
          error: (err) => {
            console.error('Error obteniendo usuarios para reasignar:', err);
            hacerActualizacion(actualizacionBase);
          }
        });
        return;
      }
    }

    // Sin reasignación: sólo reaperturar
    hacerActualizacion(actualizacionBase);
  }

  // Abrir modal para completar tarea
  async abrirModalCompletar(tarea: TareaAdmin, event?: Event): Promise<void> {
    if (event) {
      event.stopPropagation();
    }

    const modal = await this.modalController.create({
      component: ModalCompletar,
      componentProps: {
        tarea: tarea
      },
      breakpoints: [0, 0.5, 0.85, 1],
      initialBreakpoint: 0.85
    });

    modal.onDidDismiss().then((result) => {
      if (result.role === 'confirm' && result.data) {
        this.procesarCompletarTarea(tarea.id, result.data);
      }
    });

    await modal.present();
  }

  // Procesar completar tarea
  procesarCompletarTarea(tareaId: string, datos: any): void {
    this.tareasService.actualizarTareaAdmin(tareaId, { 
      estado: 'Completada',
      observaciones: datos.observaciones,
      imagenes: datos.imagenes,
      fechaCompletado: datos.fechaCompletado
    })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: () => {
          this.cargarTareas();
        },
        error: (err) => console.error('Error al completar:', err)
      });
  }

  // Ver información de tarea (sin importar estado) - navega a tareas-info
  async irAInfoTarea(tarea: TareaAdmin, event?: Event): Promise<void> {
    if (event) {
      event.stopPropagation();
    }
    // Guardar la tarea seleccionada en el servicio para acceso posterior
    this.tareasService.seleccionarTareaAdmin(tarea);
    // Navegar a la vista de detalle de tarea con subtareas
    this.router.navigate(['/features/tareas/tareas-info'], {
      queryParams: { tareaId: tarea.id }
    });
  }

  obtenerCategorias(): string[] {
    const categorias = new Set<string>();
    this.tareasAdmin.forEach(tarea => {
      categorias.add(tarea.Categoria);
      tarea.Tarea.forEach(subtarea => categorias.add(subtarea.Categoria));
    });
    return Array.from(categorias).sort();
  }

  obtenerColorEstado(estado: string): string {
    const colores: {[key: string]: string} = {
      'Pendiente': '#ef4444',
      'En progreso': '#f59e0b',
      'Completada': '#10b981'
    };
    return colores[estado] || '#6b7280';
  }

  obtenerColorPrioridad(prioridad: string): string {
    const colores: {[key: string]: string} = {
      'Baja': '#10b981',
      'Media': '#f59e0b',
      'Alta': '#ef4444'
    };
    return colores[prioridad] || '#6b7280';
  }

  obtenerSubtareasCompletadas(tarea: TareaAdmin): number {
    return tarea.Tarea?.filter(t => t.completada).length || 0;
  }

  obtenerTotalSubtareas(tarea: TareaAdmin): number {
    return tarea.Tarea?.length || 0;
  }

  obtenerPorcentajeSubtareas(tarea: TareaAdmin): number {
    const total = this.obtenerTotalSubtareas(tarea);
    if (total === 0) return 0;
    return Math.round((this.obtenerSubtareasCompletadas(tarea) / total) * 100);
  }

  obtenerProgreso(tarea: TareaAdmin): number {
    const total = this.obtenerTotalSubtareas(tarea);
    if (total === 0) return 0;
    return this.obtenerSubtareasCompletadas(tarea) / total;
  }
}


