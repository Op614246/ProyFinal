import { Injectable } from '@angular/core';
import { CanActivate, ActivatedRouteSnapshot, RouterStateSnapshot, Router } from '@angular/router';
import { TokenService } from '../service/token.service';

/**
 * AdminGuard
 * 
 * Protege rutas que requieren rol de administrador.
 * Redirige al dashboard si el usuario no es admin.
 */
@Injectable({
  providedIn: 'root'
})
export class AdminGuard implements CanActivate {

  constructor(
    private tokenService: TokenService,
    private router: Router
  ) {}

  canActivate(
    route: ActivatedRouteSnapshot,
    state: RouterStateSnapshot
  ): boolean {
    // Primero verificar si está logueado
    if (!this.tokenService.isLoggedIn()) {
      this.router.navigate(['/login'], {
        queryParams: { returnUrl: state.url }
      });
      return false;
    }

    // Verificar si tiene rol de admin
    const role = this.tokenService.getUserRole();
    if (role === 'admin') {
      return true;
    }

    // Si no es admin, redirigir al dashboard con mensaje
    this.router.navigate(['/dashboard']);
    return false;
  }
}
