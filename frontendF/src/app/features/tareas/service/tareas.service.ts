import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, BehaviorSubject, Subject } from 'rxjs';
import { environment } from '../../../../environments/environment';

// ============================================
// INTERFACES Y MODELOS
// ============================================

export interface Tarea {
  id: string;
  titulo: string;
  descripcion: string;
  estado: 'Pendiente' | 'En progreso' | 'Completada' | 'Cerrada' | 'Activo' | 'Inactiva';
  estadodetarea: 'Activo' | 'Inactiva';
  prioridad: 'Baja' | 'Media' | 'Alta';
  Prioridad: 'Baja' | 'Media' | 'Alta'; // Alias para compatibilidad con vistas
  completada: boolean;
  progreso: number; // 0-100
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
  estado: 'Pendiente' | 'En progreso' | 'Completada';
  fechaAsignacion: string;
  horaprogramada: string;
  horainicio: string;
  horafin: string;
  sucursal: string;
  Categoria: string;
  totalSubtareas: number;
  subtareasCompletadas: number;
  created_by_nombre: string;
  created_by_apellido: string;
  Tarea: Tarea[]; // Array de subtareas
}

export interface ResumenTareas {
  totalTareas: number;
  tareasCompletadas: number;
  tareasEnProgreso: number;
  porcentajeAvance: number;
}

export interface ApiResponse<T> {
  tipo: number; // 1=éxito, 2=advertencia, 3=error
  mensajes: string[];
  data: T;
}

@Injectable({
  providedIn: 'root',
})
export class TareasService {
  private baseUrl = `${environment.apiUrl}/admin`;
  
  // Control de modo admin
  public apartadoadmin = true;
  
  // Usuario actual del sistema
  public usuarioActual = 'Usuario Actual';
  
  // Subject para notificar cambios en subtareas
  private subtareasActualizadasSubject = new Subject<void>();
  public subtareasActualizadas$ = this.subtareasActualizadasSubject.asObservable();
  
  // BehaviorSubjects para estado reactivo
  private tareasAdminSubject = new BehaviorSubject<TareaAdmin[]>([]);
  public tareasAdmin$ = this.tareasAdminSubject.asObservable();
  
  private tareasSeleccionadasSubject = new BehaviorSubject<Set<string>>(new Set());
  public tareasSeleccionadas$ = this.tareasSeleccionadasSubject.asObservable();
  
  private filtrosSubject = new BehaviorSubject<any>({});
  public filtros$ = this.filtrosSubject.asObservable();
  
  // Subject para notificaciones
  private actualizacionSubject = new Subject<void>();
  public actualizacion$ = this.actualizacionSubject.asObservable();
  
  constructor(private http: HttpClient) {
    this.cargarTareasAdmin();
  }
  
  // ============================================
  // CRUD - TAREAS ADMIN
  // ============================================
  
  /**
   * Obtener todas las tareas admin
   */
  obtenerTareasAdmin(): Observable<ApiResponse<{tareas: TareaAdmin[], total: number}>> {
    return this.http.get<ApiResponse<{tareas: TareaAdmin[], total: number}>>(`${this.baseUrl}/`);
  }
  
  /**
   * Cargar tareas admin y actualizar el state
   */
  cargarTareasAdmin(): void {
    this.obtenerTareasAdmin().subscribe({
      next: (response) => {
        if (response.tipo === 1) {
          this.tareasAdminSubject.next(response.data.tareas);
        }
      },
      error: (err) => console.error('Error cargando tareas admin:', err)
    });
  }
  
  /**
   * Obtener tarea admin por ID
   */
  obtenerTareaAdminPorId(id: string): Observable<ApiResponse<TareaAdmin>> {
    return this.http.get<ApiResponse<TareaAdmin>>(`${this.baseUrl}/${id}`);
  }
  
  /**
   * Obtener tareas admin por fecha
   */
  obtenerTareasAdminPorFecha(fecha: string): Observable<ApiResponse<{tareas: TareaAdmin[], total: number}>> {
    return this.http.get<ApiResponse<{tareas: TareaAdmin[], total: number}>>(`${this.baseUrl}/fecha/${fecha}`);
  }
  
  /**
   * Crear tarea admin
   */
  crearTareaAdmin(data: Partial<TareaAdmin>): Observable<ApiResponse<TareaAdmin>> {
    return this.http.post<ApiResponse<TareaAdmin>>(`${this.baseUrl}/`, data);
  }
  
  /**
   * Actualizar tarea admin
   */
  actualizarTareaAdmin(id: string, data: Partial<TareaAdmin>): Observable<ApiResponse<TareaAdmin>> {
    return this.http.put<ApiResponse<TareaAdmin>>(`${this.baseUrl}/${id}`, data);
  }
  
  /**
   * Eliminar tarea admin
   */
  eliminarTareaAdmin(id: string): Observable<ApiResponse<{id: string}>> {
    return this.http.delete<ApiResponse<{id: string}>>(`${this.baseUrl}/${id}`);
  }
  
  // ============================================
  // GESTIÓN DE ESTADO Y FILTROS
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
  aplicarFiltros(filtros: any): void {
    this.filtrosSubject.next(filtros);
  }
  
  /**
   * Obtener tareas filtradas
   */
  obtenerTareasFiltradas(): TareaAdmin[] {
    const tareas = this.tareasAdminSubject.value;
    const filtros = this.filtrosSubject.value;
    
    if (!filtros || Object.keys(filtros).length === 0) {
      return tareas;
    }
    
    return tareas.filter(tarea => {
      if (filtros.estado && tarea.estado !== filtros.estado) return false;
      if (filtros.categoria && tarea.Categoria !== filtros.categoria) return false;
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
}
