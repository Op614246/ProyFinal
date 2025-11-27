import { Injectable } from '@angular/core';
import { CanActivate, ActivatedRouteSnapshot, RouterStateSnapshot, Router } from '@angular/router';
import { TokenService } from '../service/token.service';

/**
 * RoleGuard
 * 
 * Protege rutas basándose en el rol del usuario
 * 
 * Uso en rutas:
 * {
 *   path: 'admin',
 *   canActivate: [RoleGuard],
 *   data: { roles: ['admin'] }
 * }
 */
@Injectable({
  providedIn: 'root'
})
export class RoleGuard implements CanActivate {

  constructor(
    private tokenService: TokenService,
    private router: Router
  ) {}

  canActivate(
    route: ActivatedRouteSnapshot,
    state: RouterStateSnapshot
  ): boolean {
    // Primero verificar si está autenticado
    if (!this.tokenService.isLoggedIn()) {
      this.router.navigate(['/login']);
      return false;
    }

    // Obtener roles permitidos de la ruta
    const allowedRoles = route.data['roles'] as string[];
    
    if (!allowedRoles || allowedRoles.length === 0) {
      // Si no hay roles definidos, permitir acceso
      return true;
    }

    // Verificar si el usuario tiene el rol requerido
    const userRole = this.tokenService.getUserRole();
    
    if (userRole && allowedRoles.includes(userRole)) {
      return true;
    }

    // No tiene permisos, redirigir al dashboard
    this.router.navigate(['/dashboard']);
    return false;
  }
}
