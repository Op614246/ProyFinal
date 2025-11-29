import { Injectable } from '@angular/core';
import { CanActivate, ActivatedRouteSnapshot, RouterStateSnapshot, Router } from '@angular/router';
import { TokenService } from '../services/token.service';

/**
 * AuthGuard
 * 
 * Protege rutas que requieren autenticación
 */
@Injectable({
  providedIn: 'root'
})
export class AuthGuard implements CanActivate {

  constructor(
    private tokenService: TokenService,
    private router: Router
  ) {}

  canActivate(
    route: ActivatedRouteSnapshot,
    state: RouterStateSnapshot
  ): boolean {
    if (this.tokenService.isLoggedIn()) {
      return true;
    }

    // Limpiar cualquier token inválido
    this.tokenService.clear();
    
    // Redirigir al login
    this.router.navigate(['/login']);
    return false;
  }
}
