import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable, from, switchMap, map, tap, catchError, throwError } from 'rxjs';
import { Router } from '@angular/router';
import { environment } from '../../../environments/environment';
import { CryptoService } from './crypto.service';
import { TokenService } from './token.service';

/**
 * Interfaz para la respuesta del API
 */
export interface ApiResponse<T = any> {
  tipo: number;       // 1 = éxito, 2 = advertencia, 3 = error
  mensajes: string[];
  data: T | null;
}

/**
 * Interfaz para la respuesta encriptada
 */
export interface EncryptedResponse {
  encrypted: boolean;
  payload: string;
  iv: string;
}

/**
 * Interfaz para el usuario autenticado
 */
export interface AuthUser {
  id?: number;
  username: string;
  role: string;
  token?: string;
}

/**
 * AuthService
 * 
 * Servicio de autenticación con encriptación AES-256
 */
@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private apiUrl = `${environment.apiUrl}/auth`;

  constructor(
    private http: HttpClient,
    private cryptoService: CryptoService,
    private tokenService: TokenService,
    private router: Router
  ) {}

  /**
   * Inicia sesión con credenciales encriptadas
   */
  login(username: string, password: string): Observable<ApiResponse> {
    const credentials = { username, password };
    
    return from(this.cryptoService.encrypt(credentials)).pipe(
      switchMap(encryptedData => {
        return this.http.post<ApiResponse<EncryptedResponse>>(
          `${this.apiUrl}/login`,
          encryptedData
        );
      }),
      switchMap(response => this.processLoginResponse(response)),
      catchError(error => this.handleError(error))
    );
  }

  /**
   * Procesa la respuesta del login (desencripta si es necesario)
   */
  private processLoginResponse(response: ApiResponse<EncryptedResponse>): Observable<ApiResponse> {
    if (response.tipo === 1 && response.data?.encrypted) {
      // Desencriptar la respuesta
      return from(this.cryptoService.decrypt(response.data.payload, response.data.iv)).pipe(
        map(decryptedData => {
          // Guardar token y datos del usuario
          if (decryptedData.token) {
            this.tokenService.setToken(decryptedData.token);
            this.tokenService.setUser({
              username: decryptedData.username,
              role: decryptedData.role
            });
          }
          
          return {
            tipo: response.tipo,
            mensajes: response.mensajes,
            data: decryptedData
          };
        })
      );
    }
    
    return new Observable(subscriber => {
      subscriber.next(response);
      subscriber.complete();
    });
  }

  /**
   * Cierra la sesión
   */
  logout(): void {
    this.tokenService.clear();
    this.router.navigate(['/login']);
  }

  /**
   * Verifica si el usuario está autenticado
   */
  isAuthenticated(): boolean {
    return this.tokenService.isLoggedIn();
  }

  /**
   * Obtiene el rol del usuario actual
   */
  getUserRole(): string | null {
    return this.tokenService.getUserRole();
  }

  /**
   * Obtiene los datos del usuario actual
   */
  getCurrentUser(): AuthUser | null {
    return this.tokenService.getUser();
  }

  /**
   * Obtiene el token actual
   */
  getToken(): string | null {
    return this.tokenService.getToken();
  }

  /**
   * Verifica el estado de la sesión con el servidor
   */
  checkStatus(): Observable<ApiResponse> {
    return this.http.get<ApiResponse>(`${this.apiUrl}/status`).pipe(
      catchError(error => this.handleError(error))
    );
  }

  /**
   * Registra un nuevo usuario (solo admin)
   */
  register(username: string, password: string, role: string): Observable<ApiResponse> {
    const userData = { username, password, role };
    
    return from(this.cryptoService.encrypt(userData)).pipe(
      switchMap(encryptedData => {
        return this.http.post<ApiResponse>(`${this.apiUrl}/register`, encryptedData);
      }),
      catchError(error => this.handleError(error))
    );
  }

  /**
   * Desbloquea una cuenta (solo admin)
   */
  unlockAccount(username: string): Observable<ApiResponse> {
    const data = { username };
    
    return from(this.cryptoService.encrypt(data)).pipe(
      switchMap(encryptedData => {
        return this.http.post<ApiResponse>(`${this.apiUrl}/unlock`, encryptedData);
      }),
      catchError(error => this.handleError(error))
    );
  }

  /**
   * Maneja errores HTTP
   */
  private handleError(error: any): Observable<never> {
    let message = 'Error de conexión con el servidor';
    
    if (error.error?.mensajes) {
      message = error.error.mensajes.join(' ');
    } else if (error.status === 401) {
      message = 'Sesión expirada. Por favor, inicie sesión nuevamente.';
      this.logout();
    } else if (error.status === 403) {
      message = 'No tiene permisos para realizar esta acción.';
    } else if (error.status === 0) {
      message = 'No se puede conectar con el servidor.';
    }
    
    return throwError(() => ({ tipo: 3, mensajes: [message], data: null }));
  }
}
