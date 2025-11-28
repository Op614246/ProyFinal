import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, from, switchMap, catchError, throwError } from 'rxjs';
import { environment } from '../../../environments/environment';
import { CryptoService } from './crypto.service';
import { ApiResponse } from '../models/auth';
import { User, CreateUserRequest } from '../models/user';

/**
 * UserService
 * 
 * Servicio para la gestión de usuarios (solo admin)
 */
@Injectable({
  providedIn: 'root'
})
export class UserService {
  private apiUrl = `${environment.apiUrl}/auth`;

  constructor(
    private http: HttpClient,
    private cryptoService: CryptoService
  ) {}

  /**
   * Obtiene la lista de todos los usuarios
   */
  getUsers(): Observable<ApiResponse<User[]>> {
    return this.http.get<ApiResponse<User[]>>(`${this.apiUrl}/users`).pipe(
      catchError(error => this.handleError(error))
    );
  }

  /**
   * Crea un nuevo usuario (datos encriptados)
   */
  createUser(userData: CreateUserRequest): Observable<ApiResponse> {
    return from(this.cryptoService.encrypt(userData)).pipe(
      switchMap(encryptedData => {
        return this.http.post<ApiResponse>(`${this.apiUrl}/register`, encryptedData);
      }),
      catchError(error => this.handleError(error))
    );
  }

  /**
   * Activa o desactiva un usuario (toggle)
   */
  toggleUserStatus(userId: number): Observable<ApiResponse> {
    return this.http.put<ApiResponse>(`${this.apiUrl}/users/${userId}/toggle-status`, {}).pipe(
      catchError(error => this.handleError(error))
    );
  }

  /**
   * Desbloquea una cuenta de usuario
   */
  unlockUser(username: string): Observable<ApiResponse> {
    return from(this.cryptoService.encrypt({ username })).pipe(
      switchMap(encryptedData => {
        return this.http.post<ApiResponse>(`${this.apiUrl}/unlock`, encryptedData);
      }),
      catchError(error => this.handleError(error))
    );
  }

  /**
   * Elimina un usuario
   */
  deleteUser(userId: number): Observable<ApiResponse> {
    return this.http.delete<ApiResponse>(`${this.apiUrl}/users/${userId}`).pipe(
      catchError(error => this.handleError(error))
    );
  }

  /**
   * Maneja errores de la API
   */
  private handleError(error: any): Observable<never> {
    let errorMessage = 'Error de conexión con el servidor';
    
    if (error.error?.mensajes) {
      errorMessage = error.error.mensajes.join(' ');
    } else if (error.status === 0) {
      errorMessage = 'No se pudo conectar con el servidor';
    } else if (error.status === 403) {
      errorMessage = 'No tiene permisos para realizar esta acción';
    }
    
    return throwError(() => new Error(errorMessage));
  }
}
