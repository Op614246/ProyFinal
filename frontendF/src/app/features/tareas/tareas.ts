import { Component, OnInit, OnDestroy, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { MenuController, ModalController } from '@ionic/angular';
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
  faChevronUp
} from '@fortawesome/pro-regular-svg-icons';
import { TareasService, TareaAdmin, Tarea, ResumenTareas } from './service/tareas.service';
import { AuthService } from '../../core/services/auth.service';

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

  // Estado de la página
  selectedTab: 'admin' | 'mis-tareas' = 'admin';
  tareasAdmin: TareaAdmin[] = [];
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
  
  // Usuario
  nombreUsuario: string = 'Usuario';
  
  // Filtros
  filtroEstado: string = 'todos';
  filtroCategoria: string = 'todos';
  searchTerm: string = '';
  
  // Selección múltiple
  modoSeleccion: boolean = false;
  tareasSeleccionadas = new Set<string>();
  
  // Control de carga
  cargando: boolean = true;
  error: string | null = null;
  
  private destroy$ = new Subject<void>();

  constructor(
    private tareasService: TareasService,
    private modalController: ModalController,
    private menuController: MenuController,
    private authService: AuthService,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    this.inicializar();
    this.cargarDatosUsuario();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  inicializar(): void {
    this.actualizarFecha();
    this.generarSemana();
    this.cargarTareas();
  }

  cargarDatosUsuario(): void {
    const usuario = this.authService.getCurrentUser();
    if (usuario) {
      this.nombreUsuario = usuario.username || 'Usuario';
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

  cargarTareas(): void {
    this.cargando = true;
    this.cdr.markForCheck();
    
    this.tareasService.obtenerTareasAdmin()
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response.tipo === 1) {
            this.tareasAdmin = response.data.tareas;
            this.actualizarResumen();
          } else {
            this.error = response.mensajes[0] || 'Error al cargar tareas';
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
  }

  actualizarResumen(): void {
    this.resumen = this.tareasService.calcularResumen();
    this.cdr.markForCheck();
  }

  actualizarFecha(): void {
    this.diaString = this.formatearFecha(this.dia);
    this.nroSemana = this.calcularNumeroSemana(this.dia);
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
  }

  obtenerTareasFiltradas(): TareaAdmin[] {
    let tareas = this.tareasAdmin;

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

  verDetallesTarea(tarea: TareaAdmin): void {
    this.router.navigate(['/features/tareas', tarea.id], { state: { tarea } });
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


