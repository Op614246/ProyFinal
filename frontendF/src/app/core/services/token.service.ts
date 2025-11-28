import { Injectable, PLATFORM_ID, Inject } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';

/**
 * TokenService
 * 
 * Servicio para manejar el almacenamiento seguro de tokens JWT
 * Compatible con SSR (Server Side Rendering)
 */
@Injectable({
  providedIn: 'root'
})
export class TokenService {
  private readonly TOKEN_KEY = 'auth_token';
  private readonly USER_KEY = 'auth_user';
  private isBrowser: boolean;

  constructor(@Inject(PLATFORM_ID) platformId: Object) {
    this.isBrowser = isPlatformBrowser(platformId);
  }

  /**
   * Guarda el token JWT
   */
  setToken(token: string): void {
    if (this.isBrowser) {
      localStorage.setItem(this.TOKEN_KEY, token);
    }
  }

  /**
   * Obtiene el token JWT
   */
  getToken(): string | null {
    if (this.isBrowser) {
      return localStorage.getItem(this.TOKEN_KEY);
    }
    return null;
  }

  /**
   * Elimina el token JWT
   */
  removeToken(): void {
    if (this.isBrowser) {
      localStorage.removeItem(this.TOKEN_KEY);
    }
  }

  /**
   * Guarda los datos del usuario
   */
  setUser(user: any): void {
    if (this.isBrowser) {
      localStorage.setItem(this.USER_KEY, JSON.stringify(user));
    }
  }

  /**
   * Obtiene los datos del usuario
   */
  getUser(): any {
    if (this.isBrowser) {
      const user = localStorage.getItem(this.USER_KEY);
      return user ? JSON.parse(user) : null;
    }
    return null;
  }

  /**
   * Elimina los datos del usuario
   */
  removeUser(): void {
    if (this.isBrowser) {
      localStorage.removeItem(this.USER_KEY);
    }
  }

  /**
   * Verifica si hay un token válido
   */
  isLoggedIn(): boolean {
    const token = this.getToken();
    if (!token) return false;
    
    // Verificar si el token no ha expirado
    try {
      const payload = this.decodeToken(token);
      if (payload && payload.exp) {
        return payload.exp > Date.now() / 1000;
      }
      return true;
    } catch {
      return false;
    }
  }

  /**
   * Decodifica el payload del JWT (sin verificar firma)
   */
  decodeToken(token: string): any {
    try {
      const parts = token.split('.');
      if (parts.length !== 3) return null;
      
      const payload = parts[1];
      const decoded = atob(payload.replace(/-/g, '+').replace(/_/g, '/'));
      return JSON.parse(decoded);
    } catch {
      return null;
    }
  }

  /**
   * Obtiene el rol del usuario desde el token
   */
  getUserRole(): string | null {
    const token = this.getToken();
    if (!token) return null;
    
    const payload = this.decodeToken(token);
    return payload?.data?.role || null;
  }

  /**
   * Limpia todo el almacenamiento de autenticación
   */
  clear(): void {
    this.removeToken();
    this.removeUser();
  }
}
