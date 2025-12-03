import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, BehaviorSubject, Subject, tap, map, from, switchMap } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { CryptoService } from '../../../core/services/crypto.service';

// ============================================
// INTERFACES Y MODELOS (Schema Imagen 2)
// ============================================

export interface Task {
  id: number;
  title: string;
  description: string;
  categoria_id: number | null;
  categoria_nombre: string | null;
  categoria_color: string | null;
  status: 'pending' | 'in_process' | 'completed' | 'incomplete' | 'inactive';
  priority: 'high' | 'medium' | 'low';
  deadline: string | null;
  fecha_asignacion: string;
  horainicio: string | null;
  horafin: string | null;
  assigned_user_id: number | null;
  assigned_username: string | null;
  assigned_fullname: string | null;
  created_by_user_id: number | null;
  created_by_name: string | null;
  sucursal: string | null;
  progreso: number;
  completed_at: string | null;
  evidence_image: string | null;
  motivo_reapertura: string | null;
  observaciones_reapertura: string | null;
  fecha_reapertura: string | null;
  evidencia_count: number;
  evidencias: TaskEvidence[];
  created_at: string;
  updated_at: string;
}

export interface TaskEvidence {
  id: number;
  file_path: string;
  file_name: string;
  file_size_kb: number;
  mime_type: string;
  observaciones: string | null;
  uploaded_at: string;
  username: string;
  uploaded_by: string;
}

// Interfaz para evidencias de subtareas (nuevo modelo)
export interface SubtareaEvidence {
  id: number;
  subtarea_id: number;
  archivo: string;
  tipo: 'imagen' | 'documento' | 'otro';
  nombre_original: string | null;
  tamanio: number | null;
  uploaded_by: number;
  uploaded_by_nombre: string | null;
  observaciones: string | null;
  created_at: string;
}

// Interfaz de Subtarea actualizada
export interface Subtarea {
  id: number;
  task_id: number;
  titulo: string;
  descripcion: string | null;
  estado: 'Pendiente' | 'En progreso' | 'Completada' | 'Cerrada' | 'Activo' | 'Inactiva';
  prioridad: 'Baja' | 'Media' | 'Alta';
  completada: boolean;
  progreso: number;
  fechaAsignacion: string | null;
  fechaVencimiento: string | null;
  horainicio: string | null;
  horafin: string | null;
  categoria_id: number | null;
  categoria_nombre: string | null;
  categoria_color: string | null;
  usuarioasignado_id: number | null;
  usuario_asignado: string | null;
  usuario_username: string | null;
  completed_at: string | null;
  completed_by: number | null;
  completion_notes: string | null;
  evidencias?: SubtareaEvidence[];
  evidencia_count?: number;
  created_at: string;
  updated_at: string;
}

export interface Categoria {
  id: number;
  nombre: string;
  descripcion: string | null;
  color: string;
}

export interface UserAssignable {
  id: number;
  username: string;
  nombre_completo: string;
  departamento: string | null;
  estado: string | null;
  tareas_activas: number;
}

export interface TaskStatistics {
  total: number;
  pendientes: number;
  en_proceso: number;
  completadas: number;
  incompletas: number;
  inactivas: number;
  alta_prioridad: number;
  vencidas: number;
}

export interface TaskFilter {
  fecha?: string;
  fecha_inicio?: string;
  fecha_fin?: string;
  status?: string;
  priority?: string;
  assigned_user_id?: number;
  sucursal?: string;
  categoria_id?: number;
}

export interface ApiResponse<T> {
  tipo: number; // 1=éxito, 0=error
  mensajes: string[];
  data: T;
}

// Interfaces legacy para compatibilidad
export interface Tarea {
  id: string;
  titulo: string;
  descripcion: string;
  estado: 'Pendiente' | 'En progreso' | 'Completada' | 'Cerrada' | 'Activo' | 'Inactiva';
  estadodetarea: 'Activo' | 'Inactiva';
  prioridad: 'Baja' | 'Media' | 'Alta';
  Prioridad: 'Baja' | 'Media' | 'Alta';
  completada: boolean;
  progreso: number;
  fechaAsignacion: string;
  fechaVencimiento?: string;
  Categoria: string;
  horaprogramada: string;
  horainicio: string;
  horafin: string;
  usuarioasignado: string;
  usuarioasignado_id: number;
  totalSubtareas: number;
  subtareasCompletadas: number;
  // Campos de evidencia (opcionales para compatibilidad)
  completed_at?: string;
  completed_by?: number;
  completion_notes?: string;
  evidencia_count?: number;
  evidencias?: SubtareaEvidence[];
}

export interface TareaAdmin {
  id: string;
  titulo: string;
  descripcion?: string;
  estado: 'Pendiente' | 'En progreso' | 'Completada' | 'Incompleta' | 'Inactiva';
  fechaAsignacion: string;
  fechaVencimiento?: string; // deadline
  horaprogramada: string;
  horainicio: string;
  horafin: string;
  sucursal: string;
  Categoria: string;
  prioridad?: string;
  Prioridad?: string;
  totalSubtareas: number;
  subtareasCompletadas: number;
  created_by_nombre: string;
  created_by_apellido: string;
  usuarioasignado?: string;
  usuarioasignado_id?: number;
  completada?: boolean;
  Tarea: Tarea[];
  motivoReapertura?: string;
  observacionesReapertura?: string;
  observaciones?: string;
  imagenes?: string[];
  fechaCompletado?: string;
}

export interface ResumenTareas {
  totalTareas: number;
  tareasCompletadas: number;
  tareasEnProgreso: number;
  porcentajeAvance: number;
}

@Injectable({
  providedIn: 'root',
})
export class TareasService {
  // URL base para tareas (unificado)
  private tasksUrl = `${environment.apiUrl}/tasks`;
  // URL para subtareas
  private subtareasUrl = `${environment.apiUrl}/subtareas`;
  
  // Control de modo admin
  public apartadoadmin = true;
  
  // Usuario actual del sistema
  public usuarioActual = 'Usuario Actual';
  
  // Subject para notificar cambios
  private subtareasActualizadasSubject = new Subject<void>();
  public subtareasActualizadas$ = this.subtareasActualizadasSubject.asObservable();
  
  // BehaviorSubjects para estado reactivo - Nuevo schema
  private tasksSubject = new BehaviorSubject<Task[]>([]);
  public tasks$ = this.tasksSubject.asObservable();
  
  private statisticsSubject = new BehaviorSubject<TaskStatistics | null>(null);
  public statistics$ = this.statisticsSubject.asObservable();
  
  private categoriasSubject = new BehaviorSubject<Categoria[]>([]);
  public categorias$ = this.categoriasSubject.asObservable();
  
  // Legacy subjects
  private tareasAdminSubject = new BehaviorSubject<TareaAdmin[]>([]);
  public tareasAdmin$ = this.tareasAdminSubject.asObservable();
  
  private tareasSeleccionadasSubject = new BehaviorSubject<Set<string>>(new Set());
  public tareasSeleccionadas$ = this.tareasSeleccionadasSubject.asObservable();
  
  private filtrosSubject = new BehaviorSubject<TaskFilter>({});
  public filtros$ = this.filtrosSubject.asObservable();
  
  // Subject para notificaciones
  private actualizacionSubject = new Subject<void>();
  public actualizacion$ = this.actualizacionSubject.asObservable();
  
  constructor(
    private http: HttpClient,
    private cryptoService: CryptoService
  ) {
    this.cargarCategorias();
    this.cargarEstadisticas();
  }
  
  // ============================================
  // CRUD - NUEVO SCHEMA (TASKS)
  // ============================================
  
  /**
   * Obtener todas las tareas con filtros
   * - Admin: devuelve array de todas las tareas
   * - User: devuelve { my_tasks: [], available_tasks: [] }, se normaliza a array
   */
  getTasks(filters?: TaskFilter): Observable<ApiResponse<Task[]>> {
    let params = new HttpParams();
    
    if (filters) {
      Object.keys(filters).forEach(key => {
        const value = (filters as any)[key];
        if (value !== undefined && value !== null && value !== '') {
          params = params.set(key, value.toString());
        }
      });
    }
    
    return this.http.get<ApiResponse<any>>(`${this.tasksUrl}/`, { params }).pipe(
      map(response => {
        // Normalizar respuesta: si viene formato usuario, extraer my_tasks
        let tasks: Task[] = [];
        
        if (response.data) {
          if (Array.isArray(response.data)) {
            // Formato Admin: response.data es directamente el array
            tasks = response.data;
          } else if (response.data.my_tasks) {
            // Formato User: response.data = { my_tasks: [], available_tasks: [] }
            tasks = response.data.my_tasks || [];
          }
        }
        
        return {
          tipo: response.tipo,
          mensajes: response.mensajes,
          data: tasks
        } as ApiResponse<Task[]>;
      }),
      tap(response => {
        if (response.tipo === 1) {
          this.tasksSubject.next(response.data);
        }
      })
    );
  }
  
  /**
   * Obtener tareas disponibles para auto-asignación (solo del día)
   * Maneja tanto formato directo como formato usuario
   */
  getAvailableTasks(): Observable<ApiResponse<Task[]>> {
    return this.http.get<ApiResponse<any>>(`${this.tasksUrl}/available`).pipe(
      map(response => {
        let tasks: Task[] = [];
        
        if (response.data) {
          if (Array.isArray(response.data)) {
            tasks = response.data;
          } else if (response.data.available_tasks) {
            tasks = response.data.available_tasks || [];
          }
        }
        
        return {
          tipo: response.tipo,
          mensajes: response.mensajes,
          data: tasks
        } as ApiResponse<Task[]>;
      })
    );
  }
  
  /**
   * Obtener una tarea por ID
   */
  getTaskById(id: number): Observable<ApiResponse<Task>> {
    return this.http.get<ApiResponse<Task>>(`${this.tasksUrl}/${id}`);
  }
  
  /**
   * Crear nueva tarea (Admin)
   */
  createTask(data: Partial<Task>): Observable<ApiResponse<{ task_id: number }>> {
    return from(this.cryptoService.encrypt(data)).pipe(
      switchMap(encrypted => this.http.post<ApiResponse<{ task_id: number }>>(`${this.tasksUrl}/`, encrypted)),
      tap(response => {
        if (response.tipo === 1) {
          this.notificarActualizacion();
        }
      })
    );
  }
  
  /**
   * Asignar tarea
   * - User: auto-asigna
   * - Admin: asigna a cualquier usuario
   */
  assignTask(taskId: number, userId?: number): Observable<ApiResponse<any>> {
    const data = userId ? { user_id: userId } : {};
    return from(this.cryptoService.encrypt(data)).pipe(
      switchMap(encrypted => this.http.put<ApiResponse<any>>(`${this.tasksUrl}/${taskId}/assign`, encrypted)),
      tap(response => {
        if (response.tipo === 1) {
          this.notificarActualizacion();
        }
      })
    );
  }
  
  /**
   * Completar tarea con observaciones e imágenes
   */
  completeTask(taskId: number, observaciones: string, imagenes?: File | File[]): Observable<ApiResponse<any>> {
    const formData = new FormData();
    formData.append('observaciones', observaciones);
    
    if (imagenes) {
      // Soportar tanto una imagen como múltiples
      const files = Array.isArray(imagenes) ? imagenes : [imagenes];
      files.forEach((file, index) => {
        formData.append('evidence[]', file);
      });
    }
    
    return this.http.post<ApiResponse<any>>(`${this.tasksUrl}/${taskId}/complete`, formData).pipe(
      tap(response => {
        if (response.tipo === 1) {
          this.notificarActualizacion();
        }
      })
    );
  }
  
  /**
   * Reabrir tarea (Admin)
   */
  reopenTask(taskId: number, motivo: string, observaciones?: string): Observable<ApiResponse<any>> {
    const data = { motivo, observaciones };
    return from(this.cryptoService.encrypt(data)).pipe(
      switchMap(encrypted => this.http.put<ApiResponse<any>>(`${this.tasksUrl}/${taskId}/reopen`, encrypted)),
      tap(response => {
        if (response.tipo === 1) {
          this.notificarActualizacion();
        }
      })
    );
  }
  
  /**
   * Actualizar estado de tarea (Admin)
   */
  updateTaskStatus(taskId: number, status: string): Observable<ApiResponse<any>> {
    return from(this.cryptoService.encrypt({ status })).pipe(
      switchMap(encrypted => this.http.put<ApiResponse<any>>(`${this.tasksUrl}/${taskId}/status`, encrypted)),
      tap(response => {
        if (response.tipo === 1) {
          this.notificarActualizacion();
        }
      })
    );
  }
  
  /**
   * Eliminar tarea (Admin)
   */
  deleteTask(taskId: number): Observable<ApiResponse<any>> {
    return this.http.delete<ApiResponse<any>>(`${this.tasksUrl}/${taskId}`).pipe(
      tap(response => {
        if (response.tipo === 1) {
          this.notificarActualizacion();
        }
      })
    );
  }
  
  /**
   * Obtener estadísticas
   */
  getStatistics(): Observable<ApiResponse<TaskStatistics>> {
    return this.http.get<ApiResponse<TaskStatistics>>(`${this.tasksUrl}/statistics`);
  }
  
  /**
   * Obtener usuarios disponibles para asignación (Admin)
   */
  getAvailableUsers(): Observable<ApiResponse<UserAssignable[]>> {
    return this.http.get<ApiResponse<UserAssignable[]>>(`${this.tasksUrl}/users`);
  }
  
  /**
   * Obtener categorías (desde endpoint dedicado)
   */
  getCategorias(): Observable<ApiResponse<Categoria[]>> {
    return this.http.get<ApiResponse<Categoria[]>>(`${environment.apiUrl}/categorias/`);
  }
  
  /**
   * Obtener sucursales
   */
  getSucursales(): Observable<ApiResponse<any[]>> {
    return this.http.get<ApiResponse<any[]>>(`${environment.apiUrl}/sucursales/`);
  }
  
  // ============================================
  // MÉTODOS DE CARGA
  // ============================================
  
  cargarTareas(filters?: TaskFilter): void {
    this.getTasks(filters).subscribe({
      next: (response) => {
        if (response.tipo === 1) {
          this.tasksSubject.next(response.data);
        }
      },
      error: (err) => console.error('Error cargando tareas:', err)
    });
  }
  
  cargarEstadisticas(): void {
    this.getStatistics().subscribe({
      next: (response) => {
        if (response.tipo === 1) {
          this.statisticsSubject.next(response.data);
        }
      },
      error: (err) => console.error('Error cargando estadísticas:', err)
    });
  }
  
  cargarCategorias(): void {
    this.getCategorias().subscribe({
      next: (response) => {
        if (response.tipo === 1) {
          this.categoriasSubject.next(response.data);
        }
      },
      error: (err) => console.error('Error cargando categorías:', err)
    });
  }
  
  // ============================================
  // UTILIDADES
  // ============================================

  /**
   * Mapea Task (nuevo schema) a TareaAdmin (legacy)
   */
  private mapTaskToTareaAdmin(task: Task): TareaAdmin {
    return {
      id: task.id.toString(),
      titulo: task.title,
      descripcion: task.description,
      estado: this.translateStatus(task.status) as any,
      fechaAsignacion: task.fecha_asignacion,
      fechaVencimiento: task.deadline || undefined,
      horaprogramada: task.horainicio || '',
      horainicio: task.horainicio || '',
      horafin: task.horafin || '',
      sucursal: task.sucursal || '',
      Categoria: task.categoria_nombre || '',
      prioridad: this.translatePriority(task.priority),
      Prioridad: this.translatePriority(task.priority),
      totalSubtareas: (task as any).subtareas_total || 0,
      subtareasCompletadas: (task as any).subtareas_completadas || 0,
      created_by_nombre: task.created_by_name?.split(' ')[0] || '',
      created_by_apellido: task.created_by_name?.split(' ').slice(1).join(' ') || '',
      usuarioasignado: task.assigned_fullname || task.assigned_username || undefined,
      usuarioasignado_id: task.assigned_user_id || undefined,
      completada: task.status === 'completed',
      Tarea: [],
      motivoReapertura: (task as any).motivo_reapertura,
      observacionesReapertura: (task as any).observaciones_reapertura,
      observaciones: task.evidencias?.[0]?.observaciones || undefined,
      fechaCompletado: task.completed_at || undefined
    };
  }

  /**
   * Mapea array de Task a TareaAdmin
   */
  private mapTasksToTareasAdmin(tasks: Task[]): TareaAdmin[] {
    return tasks.map(t => this.mapTaskToTareaAdmin(t));
  }

  /**
   * Traducir estado de inglés a español
   */
  translateStatus(status: string): string {
    const map: Record<string, string> = {
      'pending': 'Pendiente',
      'in_process': 'En progreso',
      'completed': 'Completada',
      'incomplete': 'Incompleta',
      'inactive': 'Inactiva'
    };
    return map[status] || status;
  }
  
  /**
   * Traducir prioridad de inglés a español
   */
  translatePriority(priority: string): string {
    const map: Record<string, string> = {
      'high': 'Alta',
      'medium': 'Media',
      'low': 'Baja'
    };
    return map[priority] || priority;
  }
  
  /**
   * Obtener clase CSS para estado
   */
  getStatusClass(status: string): string {
    const map: Record<string, string> = {
      'pending': 'warning',
      'in_process': 'primary',
      'completed': 'success',
      'incomplete': 'danger',
      'inactive': 'medium'
    };
    return map[status] || 'medium';
  }
  
  /**
   * Obtener clase CSS para prioridad
   */
  getPriorityClass(priority: string): string {
    const map: Record<string, string> = {
      'high': 'danger',
      'medium': 'warning',
      'low': 'success'
    };
    return map[priority] || 'medium';
  }
  
  // ============================================
  // LEGACY METHODS (Redirigen a métodos nuevos)
  // ============================================
  
  /**
   * Obtener todas las tareas con filtros opcionales
   * Mantiene compatibilidad con componentes existentes que usan TareaAdmin
   */
  obtenerTareasAdmin(filtros?: { fecha?: string; status?: string; sucursal_id?: number; categoria_id?: number; assigned_user_id?: number }): Observable<ApiResponse<{tareas: TareaAdmin[], total: number}>> {
    const taskFilters: TaskFilter = {};
    if (filtros?.fecha) taskFilters.fecha = filtros.fecha;
    if (filtros?.status) taskFilters.status = filtros.status;
    if (filtros?.categoria_id) taskFilters.categoria_id = filtros.categoria_id;
    if (filtros?.assigned_user_id) taskFilters.assigned_user_id = filtros.assigned_user_id;
    
    return this.getTasks(taskFilters).pipe(
      map(response => ({
        tipo: response.tipo,
        mensajes: response.mensajes,
        data: {
          tareas: (response.data || []).map(t => this.mapTaskToTareaAdmin(t)),
          total: Array.isArray(response.data) ? response.data.length : 0
        }
      }))
    );
  }

  /**
   * Obtener tareas sin asignar del día (para usuarios)
   */
  obtenerTareasSinAsignar(fecha?: string): Observable<ApiResponse<{tareas: TareaAdmin[], total: number}>> {
    // Si hay fecha, filtrar las disponibles por fecha
    const filters: TaskFilter = {};
    if (fecha) {
      filters.fecha = fecha;
    }
    
    return this.getAvailableTasksFiltered(filters).pipe(
      map(response => ({
        tipo: response.tipo,
        mensajes: response.mensajes,
        data: {
          tareas: (response.data || []).map(t => this.mapTaskToTareaAdmin(t)),
          total: Array.isArray(response.data) ? response.data.length : 0
        }
      }))
    );
  }
  
  /**
   * Obtener tareas disponibles con filtros
   */
  private getAvailableTasksFiltered(filters?: TaskFilter): Observable<ApiResponse<Task[]>> {
    let params = new HttpParams();
    
    if (filters) {
      Object.keys(filters).forEach(key => {
        const value = (filters as any)[key];
        if (value !== undefined && value !== null && value !== '') {
          params = params.set(key, value.toString());
        }
      });
    }
    
    return this.http.get<ApiResponse<any>>(`${this.tasksUrl}/available`, { params }).pipe(
      map(response => {
        let tasks: Task[] = [];
        
        if (response.data) {
          if (Array.isArray(response.data)) {
            tasks = response.data;
          }
        }
        
        return {
          tipo: response.tipo,
          mensajes: response.mensajes,
          data: tasks
        } as ApiResponse<Task[]>;
      })
    );
  }

  /**
   * Auto-asignar tarea al usuario actual
   */
  autoAsignarTarea(tareaId: string): Observable<ApiResponse<any>> {
    return this.assignTask(parseInt(tareaId));
  }

  /**
   * Iniciar tarea (cambiar estado a 'in_process')
   */
  iniciarTarea(tareaId: string): Observable<ApiResponse<any>> {
    return this.updateTaskStatus(parseInt(tareaId), 'in_process');
  }

  /**
   * Finalizar tarea con evidencias
   */
  finalizarTarea(tareaId: string, observaciones: string, imagenes?: File | File[]): Observable<ApiResponse<any>> {
    return this.completeTask(parseInt(tareaId), observaciones, imagenes);
  }

  /**
   * Reabrir tarea (Admin)
   */
  reabrirTarea(tareaId: string, motivo: string, observaciones?: string): Observable<ApiResponse<any>> {
    return this.reopenTask(parseInt(tareaId), motivo, observaciones);
  }
  
  /**
   * Cargar tareas y actualizar el state
   */
  cargarTareasAdmin(): void {
    this.cargarTareas();
  }
  
  /**
   * Obtener tarea por ID
   * @deprecated Usar getTaskById()
   */
  obtenerTareaAdminPorId(id: string): Observable<ApiResponse<Task>> {
    return this.getTaskById(parseInt(id));
  }
  
  /**
   * Obtener tareas por fecha
   * @deprecated Usar getTasks({ fecha })
   */
  obtenerTareasAdminPorFecha(fecha: string): Observable<ApiResponse<Task[]>> {
    return this.getTasks({ fecha });
  }
  
  /**
   * Crear tarea
   * @deprecated Usar createTask()
   */
  crearTareaAdmin(data: Partial<Task>): Observable<ApiResponse<{ task_id: number }>> {
    return this.createTask(data);
  }
  
  /**
   * Eliminar tarea
   * @deprecated Usar deleteTask()
   */
  eliminarTareaAdmin(id: string): Observable<ApiResponse<any>> {
    return this.deleteTask(parseInt(id));
  }

  /**
   * Actualizar tarea (Admin)
   * Acepta tanto formato Task como formato legacy con 'estado'
   */
  actualizarTareaAdmin(id: string, data: Partial<Task> | { estado?: string; observaciones?: string; imagenes?: any; fechaCompletado?: string }): Observable<ApiResponse<any>> {
    // Traducir 'estado' legacy a 'status'
    const legacyData = data as any;
    let status: string | undefined;
    
    if (legacyData.estado) {
      status = this.reverseTranslateStatus(legacyData.estado);
    } else if (legacyData.status) {
      status = legacyData.status;
    }
    
    // Si es completar tarea, usar completeTask
    if (status === 'completed' && (legacyData.observaciones || legacyData.imagenes)) {
      return this.completeTask(parseInt(id), legacyData.observaciones || '', legacyData.imagenes);
    }
    
    // Si solo viene estado, usar updateTaskStatus
    if (status) {
      return this.updateTaskStatus(parseInt(id), status);
    }
    
    // Fallback: devolver observable vacío
    return new Observable(observer => {
      observer.next({ tipo: 0, mensajes: ['Actualización no soportada'], data: null });
      observer.complete();
    });
  }
  
  /**
   * Traduce estado legible a status técnico
   */
  private reverseTranslateStatus(estado: string): string {
    const map: { [key: string]: string } = {
      'Pendiente': 'pending',
      'En Proceso': 'in_process',
      'En proceso': 'in_process',
      'Completada': 'completed',
      'Incompleta': 'incomplete',
      'Inactiva': 'inactive'
    };
    return map[estado] || estado.toLowerCase();
  }
  
  // ============================================
  // GESTIÓN DE ESTADO Y FILTROS (legacy)
  // ============================================
  
  /**
   * Obtener tareas admin actuales
   */
  obtenerTareasActuales(): TareaAdmin[] {
    return this.tareasAdminSubject.value;
  }
  
  /**
   * Aplicar filtros a las tareas
   */
  aplicarFiltros(filtros: TaskFilter): void {
    this.filtrosSubject.next(filtros);
    this.cargarTareas(filtros);
  }
  
  /**
   * Obtener tareas filtradas (legacy)
   */
  obtenerTareasFiltradas(): TareaAdmin[] {
    const tareas = this.tareasAdminSubject.value;
    const filtros = this.filtrosSubject.value;
    
    if (!filtros || Object.keys(filtros).length === 0) {
      return tareas;
    }
    
    return tareas.filter(tarea => {
      if (filtros.status && tarea.estado !== filtros.status) return false;
      if (filtros.sucursal && tarea.sucursal !== filtros.sucursal) return false;
      return true;
    });
  }
  
  /**
   * Calcular resumen de tareas
   */
  calcularResumen(): ResumenTareas {
    const tareas = this.tareasAdminSubject.value;
    const todas = tareas.reduce((sum, tarea) => sum + tarea.Tarea.length, 0);
    const completadas = tareas.reduce((sum, tarea) => 
      sum + tarea.Tarea.filter(t => t.completada).length, 0
    );
    
    return {
      totalTareas: todas,
      tareasCompletadas: completadas,
      tareasEnProgreso: todas - completadas,
      porcentajeAvance: todas > 0 ? Math.round((completadas / todas) * 100) : 0
    };
  }
  
  /**
   * Seleccionar/Deseleccionar tarea
   */
  alternarSeleccion(tareaId: string): void {
    const seleccionadas = new Set(this.tareasSeleccionadasSubject.value);
    if (seleccionadas.has(tareaId)) {
      seleccionadas.delete(tareaId);
    } else {
      seleccionadas.add(tareaId);
    }
    this.tareasSeleccionadasSubject.next(seleccionadas);
  }
  
  /**
   * Limpiar selecciones
   */
  limpiarSelecciones(): void {
    this.tareasSeleccionadasSubject.next(new Set());
  }
  
  /**
   * Obtener tarea admin por ID desde el cache
   */
  obtenerTareaAdminPorIdLocal(id: string): TareaAdmin | null {
    const tareas = this.tareasAdminSubject.value;
    return tareas.find(t => t.id === id) || null;
  }

  /**
   * Tarea admin seleccionada para ver detalle
   */
  private tareaAdminSeleccionada: TareaAdmin | null = null;

  /**
   * Guardar la tarea admin seleccionada
   */
  seleccionarTareaAdmin(tarea: TareaAdmin): void {
    this.tareaAdminSeleccionada = tarea;
  }

  /**
   * Obtener la tarea admin seleccionada
   */
  obtenerTareaAdminSeleccionada(): TareaAdmin | null {
    return this.tareaAdminSeleccionada;
  }
  
  /**
   * Completar subtarea de una tarea admin
   */
  completarSubtareaAdmin(tareaAdminId: string, subtareaId: string): void {
    const tareas = this.tareasAdminSubject.value;
    const tareaIndex = tareas.findIndex(t => t.id === tareaAdminId);
    
    if (tareaIndex !== -1 && tareas[tareaIndex].Tarea) {
      const subtareaIndex = tareas[tareaIndex].Tarea.findIndex(s => s.id === subtareaId);
      if (subtareaIndex !== -1) {
        tareas[tareaIndex].Tarea[subtareaIndex].completada = true;
        tareas[tareaIndex].Tarea[subtareaIndex].estado = 'Completada';
        tareas[tareaIndex].Tarea[subtareaIndex].progreso = 100;
        this.tareasAdminSubject.next([...tareas]);
        this.subtareasActualizadasSubject.next();
      }
    }
  }
  
  /**
   * Descompletar subtarea de una tarea admin
   */
  descompletarSubtareaAdmin(tareaAdminId: string, subtareaId: string): void {
    const tareas = this.tareasAdminSubject.value;
    const tareaIndex = tareas.findIndex(t => t.id === tareaAdminId);
    
    if (tareaIndex !== -1 && tareas[tareaIndex].Tarea) {
      const subtareaIndex = tareas[tareaIndex].Tarea.findIndex(s => s.id === subtareaId);
      if (subtareaIndex !== -1) {
        tareas[tareaIndex].Tarea[subtareaIndex].completada = false;
        tareas[tareaIndex].Tarea[subtareaIndex].estado = 'Pendiente';
        tareas[tareaIndex].Tarea[subtareaIndex].progreso = 0;
        this.tareasAdminSubject.next([...tareas]);
        this.subtareasActualizadasSubject.next();
      }
    }
  }
  
  /**
   * Notificar actualización
   */
  notificarActualizacion(): void {
    this.actualizacionSubject.next();
  }

  // ============================================
  // MÉTODOS DE SUBTAREAS
  // ============================================

  /**
   * Obtener subtareas de una tarea
   * Accesible para Admin y User
   */
  obtenerSubtareas(taskId: string): Observable<ApiResponse<any[]>> {
    return this.http.get<ApiResponse<any[]>>(`${this.subtareasUrl}/task/${taskId}`);
  }

  /**
   * Obtener mis subtareas asignadas
   */
  obtenerMisSubtareas(): Observable<ApiResponse<any[]>> {
    return this.http.get<ApiResponse<any[]>>(`${this.subtareasUrl}/mis-subtareas`);
  }

  /**
   * Crear nueva subtarea (Solo Admin)
   */
  crearSubtarea(taskId: string, data: any): Observable<ApiResponse<any>> {
    return from(this.cryptoService.encrypt({ ...data, task_id: parseInt(taskId) })).pipe(
      switchMap(encrypted => this.http.post<ApiResponse<any>>(`${this.subtareasUrl}/`, encrypted)),
      tap(() => this.notificarActualizacion())
    );
  }

  /**
   * Actualizar subtarea
   */
  actualizarSubtarea(subtareaId: string, data: any): Observable<ApiResponse<any>> {
    return from(this.cryptoService.encrypt(data)).pipe(
      switchMap(encrypted => this.http.put<ApiResponse<any>>(`${this.subtareasUrl}/${subtareaId}`, encrypted)),
      tap(() => this.notificarActualizacion())
    );
  }

  /**
   * Eliminar subtarea (Solo Admin)
   */
  eliminarSubtarea(subtareaId: string): Observable<ApiResponse<any>> {
    return this.http.delete<ApiResponse<any>>(`${this.subtareasUrl}/${subtareaId}`).pipe(
      tap(() => this.notificarActualizacion())
    );
  }

  /**
   * Completar subtarea
   */
  completarSubtarea(subtareaId: string): Observable<ApiResponse<any>> {
    return this.http.put<ApiResponse<any>>(`${this.subtareasUrl}/${subtareaId}/completar`, {}).pipe(
      tap(() => {
        this.notificarActualizacion();
        this.subtareasActualizadasSubject.next();
      })
    );
  }

  /**
   * Iniciar subtarea
   */
  iniciarSubtarea(subtareaId: string): Observable<ApiResponse<any>> {
    return this.http.put<ApiResponse<any>>(`${this.subtareasUrl}/${subtareaId}/iniciar`, {}).pipe(
      tap(() => this.notificarActualizacion())
    );
  }

  /**
   * Asignar subtarea a usuario
   */
  asignarSubtarea(subtareaId: string, userId: number): Observable<ApiResponse<any>> {
    return from(this.cryptoService.encrypt({ user_id: userId })).pipe(
      switchMap(encrypted => this.http.put<ApiResponse<any>>(`${this.subtareasUrl}/${subtareaId}/asignar`, encrypted)),
      tap(() => this.notificarActualizacion())
    );
  }

  // ============================================
  // MÉTODOS DE EVIDENCIAS DE SUBTAREAS
  // ============================================

  /**
   * Completar subtarea con evidencia (imagen + observaciones)
   * Este es el método principal para completar subtareas
   */
  completarSubtareaConEvidencia(
    subtareaId: number, 
    observaciones: string, 
    imagenes?: File | File[]
  ): Observable<ApiResponse<any>> {
    const formData = new FormData();
    formData.append('observaciones', observaciones);
    
    if (imagenes) {
      const files = Array.isArray(imagenes) ? imagenes : [imagenes];
      files.forEach((file, index) => {
        formData.append(`imagenes[${index}]`, file);
      });
    }
    
    return this.http.post<ApiResponse<any>>(`${this.subtareasUrl}/${subtareaId}/completar-con-evidencia`, formData).pipe(
      tap(response => {
        if (response.tipo === 1) {
          this.notificarActualizacion();
          this.subtareasActualizadasSubject.next();
        }
      })
    );
  }

  /**
   * Obtener evidencias de una subtarea
   */
  obtenerEvidenciasSubtarea(subtareaId: string): Observable<ApiResponse<SubtareaEvidence[]>> {
    return this.http.get<ApiResponse<SubtareaEvidence[]>>(`${this.subtareasUrl}/${subtareaId}/evidencias`);
  }

  /**
   * Agregar evidencia adicional a una subtarea (sin completarla)
   */
  agregarEvidenciaSubtarea(
    subtareaId: string, 
    imagen: File, 
    observaciones?: string
  ): Observable<ApiResponse<any>> {
    const formData = new FormData();
    formData.append('evidence', imagen);
    if (observaciones) {
      formData.append('observaciones', observaciones);
    }
    
    return this.http.post<ApiResponse<any>>(`${this.subtareasUrl}/${subtareaId}/evidencias`, formData).pipe(
      tap(() => this.notificarActualizacion())
    );
  }

  /**
   * Eliminar evidencia de una subtarea
   */
  eliminarEvidenciaSubtarea(evidenciaId: string): Observable<ApiResponse<any>> {
    return this.http.delete<ApiResponse<any>>(`${this.subtareasUrl}/evidencias/${evidenciaId}`).pipe(
      tap(() => this.notificarActualizacion())
    );
  }

  /**
   * Obtener detalle de una subtarea con sus evidencias
   */
  obtenerSubtareaDetalle(subtareaId: string): Observable<ApiResponse<Subtarea>> {
    return this.http.get<ApiResponse<Subtarea>>(`${this.subtareasUrl}/${subtareaId}`);
  }

  /**
   * Verificar si una subtarea puede ser completada
   */
  puedeCompletarSubtarea(subtarea: Subtarea): boolean {
    const estadosCompletables = ['Pendiente', 'En progreso'];
    return !subtarea.completada && estadosCompletables.includes(subtarea.estado);
  }

  /**
   * Obtener URL de imagen de evidencia
   */
  getEvidenciaUrl(archivo: string): string {
    if (!archivo) return '';
    // Si ya es URL absoluta, retornarla
    if (archivo.startsWith('http://') || archivo.startsWith('https://')) {
      return archivo;
    }
    // Construir URL relativa al backend
    // El archivo viene como nombre de archivo, el backend lo guarda en uploads/evidencias/
    const baseUrl = environment.apiUrl.replace('/api/rest', '');
    return `${baseUrl}/uploads/evidencias/${archivo}`;
  }
}
