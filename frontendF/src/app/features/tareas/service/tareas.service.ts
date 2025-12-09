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
  completion_notes: string | null;
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

// Interfaces de modelo de datos
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
}

export interface TareaAdmin {
  id: string;
  titulo: string;
  descripcion?: string;
  estado: 'Pendiente' | 'En progreso' | 'Completada' | 'Incompleta' | 'Inactiva';
  fechaAsignacion: string;
  fechaVencimiento?: string;
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
  // URL base para el nuevo schema
  private tasksUrl = `${environment.apiUrl}/tasks`;
  // URL para acceso administrativo
  private adminUrl = `${environment.apiUrl}/admin`;
  
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
  
  // Legacy subjects (pendiente de refactorizar)
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
   * Obtener todas las tareas con filtros (Admin)
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
    
    return this.http.get<ApiResponse<Task[]>>(`${this.tasksUrl}/all/`, { params }).pipe(
      tap(response => {
        if (response.tipo === 1) {
          this.tasksSubject.next(response.data);
        }
      })
    );
  }
  
  /**
   * Obtener tareas disponibles para auto-asignación (solo del día)
   */
  getAvailableTasks(): Observable<ApiResponse<Task[]>> {
    return this.http.get<ApiResponse<Task[]>>(`${this.tasksUrl}/available`);
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
   * Completar tarea con observaciones e imagen
   */
  completeTask(taskId: number, observaciones: string, imagen?: File): Observable<ApiResponse<any>> {
    const formData = new FormData();
    formData.append('observaciones', observaciones);
    
    if (imagen) {
      formData.append('evidence', imagen);
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
  // MÉTODOS DE ADMINISTRACIÓN DE TAREAS
  // ============================================
  
  /**
   * Obtener todas las tareas admin con filtros opcionales
   */
  obtenerTareasAdmin(filtros?: { fecha?: string; status?: string; sucursal_id?: number; categoria_id?: number; assigned_user_id?: number }): Observable<ApiResponse<{tareas: TareaAdmin[], total: number}>> {
    let params = new HttpParams();
    
    if (filtros) {
      if (filtros.fecha) {
        params = params.set('fecha', filtros.fecha);
      }
      if (filtros.status) {
        params = params.set('status', filtros.status);
      }
      if (filtros.sucursal_id) {
        params = params.set('sucursal_id', filtros.sucursal_id.toString());
      }
      if (filtros.categoria_id) {
        params = params.set('categoria_id', filtros.categoria_id.toString());
      }
      if (filtros.assigned_user_id) {
        params = params.set('assigned_user_id', filtros.assigned_user_id.toString());
      }
    }
    
    return this.http.get<ApiResponse<{tareas: TareaAdmin[], total: number}>>(`${this.tasksUrl}/`, { params });
  }

  /**
   * Obtener tareas sin asignar del día (para usuarios)
   */
  obtenerTareasSinAsignar(fecha?: string): Observable<ApiResponse<{tareas: TareaAdmin[], total: number}>> {
    let params = new HttpParams();
    params = params.set('sin_asignar', 'true');
    if (fecha) {
      params = params.set('fecha', fecha);
    }
    return this.http.get<ApiResponse<{tareas: TareaAdmin[], total: number}>>(`${this.tasksUrl}/`, { params });
  }

  /**
   * Auto-asignar tarea al usuario actual
   */
  autoAsignarTarea(tareaId: string): Observable<ApiResponse<any>> {
    return this.http.post<ApiResponse<any>>(`${this.tasksUrl}/${tareaId}/asignar`, {});
  }

  /**
   * Iniciar tarea (cambiar estado a 'En progreso')
   */
  iniciarTarea(tareaId: string): Observable<ApiResponse<any>> {
    return this.http.put<ApiResponse<any>>(`${this.tasksUrl}/${tareaId}/iniciar`, {});
  }

  /**
   * Finalizar tarea con evidencia (soporta múltiples imágenes)
   */
  finalizarTarea(tareaId: string, observaciones: string, imagenes?: File | File[]): Observable<ApiResponse<any>> {
    const formData = new FormData();
    formData.append('observaciones', observaciones);
    
    if (imagenes) {
      const files = Array.isArray(imagenes) ? imagenes : [imagenes];
      files.forEach((file, index) => {
        // Usar 'evidences[]' para múltiples archivos
        formData.append('evidences[]', file, file.name);
      });
    }
    
    return this.http.post<ApiResponse<any>>(`${this.tasksUrl}/${tareaId}/completar`, formData);
  }

  /**
   * Reabrir tarea (Admin)
   */
  reabrirTarea(
    tareaId: string, 
    motivo: string, 
    observaciones?: string,
    assignedUserId?: number,
    deadline?: string,
    priority?: string
  ): Observable<ApiResponse<any>> {
    const data: any = { motivo, observaciones };
    if (assignedUserId) data.assigned_user_id = assignedUserId;
    if (deadline) data.deadline = deadline;
    if (priority) data.priority = priority;
    return this.http.put<ApiResponse<any>>(`${this.tasksUrl}/${tareaId}/reabrir`, data);
  }
  
  /**
   * Obtener tarea admin por ID
   */
  obtenerTareaPorId(id: string): Observable<ApiResponse<TareaAdmin>> {
    return this.http.get<ApiResponse<TareaAdmin>>(`${this.tasksUrl}/${id}`);
  }
  
  /**
   * Obtener tareas admin por fecha
   */
  obtenerTareasAdminPorFecha(fecha: string): Observable<ApiResponse<{tareas: TareaAdmin[], total: number}>> {
    return this.http.get<ApiResponse<{tareas: TareaAdmin[], total: number}>>(`${this.adminUrl}/fecha/${fecha}`);
  }
  
  /**
   * Crear tarea admin
   */
  crearTareaAdmin(data: Partial<TareaAdmin>): Observable<ApiResponse<TareaAdmin>> {
    return this.http.post<ApiResponse<TareaAdmin>>(`${this.adminUrl}/`, data);
  }
  
  /**
   * Actualizar tarea admin
   */
  actualizarTareaAdmin(id: string, data: Partial<TareaAdmin>): Observable<ApiResponse<TareaAdmin>> {
    return this.http.put<ApiResponse<TareaAdmin>>(`${this.adminUrl}/${id}`, data);
  }
  
  /**
   * Eliminar tarea admin
   */
  eliminarTareaAdmin(id: string): Observable<ApiResponse<{id: string}>> {
    return this.http.delete<ApiResponse<{id: string}>>(`${this.tasksUrl}/${id}`);
  }
  
  // ============================================
  // GESTIÓN DE ESTADO Y FILTROS
  // ============================================
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
  completarSubtareaAdmin(tareaAdminId: string, subtareaId: string): Observable<ApiResponse<any>> {
    // Usar la ruta directa de subtareas para completar
    return this.http.put<ApiResponse<any>>(
      `${environment.apiUrl}/subtareas/${subtareaId}/completar`,
      {}
    ).pipe(
      tap((response: any) => {
        if (response?.tipo === 1) {
          // Actualizar estado local
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
      })
    );
  }
  
  /**
   * Descompletar subtarea de una tarea admin
   */
  descompletarSubtareaAdmin(tareaAdminId: string, subtareaId: string): Observable<ApiResponse<any>> {
    return this.http.put<ApiResponse<any>>(
      `${this.adminUrl}/${tareaAdminId}/subtareas/${subtareaId}/descompletar`, 
      {}
    ).pipe(
      tap((response: any) => {
        if (response?.tipo === 1) {
          // Actualizar estado local
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
      })
    );
  }
  
  /**
   * Notificar actualización
   */
  notificarActualizacion(): void {
    this.actualizacionSubject.next();
  }

  /**
   * Calcular resumen de tareas basado en tareasAdmin actuales
   */
  calcularResumen(): ResumenTareas {
    const tareas = this.tareasAdminSubject.value;
    const todas = tareas.reduce((sum, tarea) => sum + (tarea.Tarea?.length || 0), 0);
    const completadas = tareas.reduce((sum, tarea) => 
      sum + (tarea.Tarea?.filter(t => t.completada).length || 0), 0
    );
    
    return {
      totalTareas: todas,
      tareasCompletadas: completadas,
      tareasEnProgreso: todas - completadas,
      porcentajeAvance: todas > 0 ? Math.round((completadas / todas) * 100) : 0
    };
  }

  // ============================================
  // MÉTODOS DE SUBTAREAS
  // ============================================

  /**
   * Obtener subtareas de una tarea
   */
  obtenerSubtareas(taskId: string): Observable<ApiResponse<any[]>> {
    return this.http.get<ApiResponse<any[]>>(`${environment.apiUrl}/subtareas/task/${taskId}`);
  }

  /**
   * Crear nueva subtarea
   */
  crearSubtarea(taskId: string, data: any): Observable<ApiResponse<any>> {
    return this.http.post<ApiResponse<any>>(`${environment.apiUrl}/subtareas/`, {
      ...data,
      task_id: taskId
    }).pipe(
      tap(() => this.notificarActualizacion())
    );
  }

  /**
   * Actualizar subtarea
   */
  actualizarSubtarea(subtareaId: string, data: any): Observable<ApiResponse<any>> {
    return this.http.put<ApiResponse<any>>(`${environment.apiUrl}/subtareas/${subtareaId}`, data).pipe(
      tap(() => this.notificarActualizacion())
    );
  }

  /**
   * Eliminar subtarea
   */
  eliminarSubtarea(subtareaId: string): Observable<ApiResponse<any>> {
    return this.http.delete<ApiResponse<any>>(`${environment.apiUrl}/subtareas/${subtareaId}`).pipe(
      tap(() => this.notificarActualizacion())
    );
  }
}
