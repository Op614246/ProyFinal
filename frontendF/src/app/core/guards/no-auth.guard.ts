import { Injectable } from '@angular/core';
import { CanActivate, Router } from '@angular/router';
import { TokenService } from '../services/token.service';

/**
 * NoAuthGuard
 * 
 * Redirige a tareas si ya está autenticado
 * Útil para la página de login
 */
@Injectable({
  providedIn: 'root'
})
export class NoAuthGuard implements CanActivate {

  constructor(
    private tokenService: TokenService,
    private router: Router
  ) {}

  canActivate(): boolean {
    if (this.tokenService.isLoggedIn()) {
      this.router.navigate(['/features/tareas']);
      return false;
    }
    return true;
  }
}
