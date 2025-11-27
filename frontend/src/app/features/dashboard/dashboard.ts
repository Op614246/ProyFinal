import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService, AuthUser } from '../../core/service/auth.service';
import { MessageService, ConfirmationService } from 'primeng/api';

@Component({
  selector: 'app-dashboard',
  standalone: false,
  templateUrl: './dashboard.html',
  styleUrl: './dashboard.scss',
  providers: [MessageService, ConfirmationService]
})
export class Dashboard implements OnInit {
  user: AuthUser | null = null;
  loading = false;

  constructor(
    private authService: AuthService,
    private router: Router,
    private messageService: MessageService,
    private confirmationService: ConfirmationService
  ) {}

  ngOnInit(): void {
    this.user = this.authService.getCurrentUser();
    
    // Verificar estado de sesión con el servidor
    this.checkSession();
  }

  /**
   * Verifica el estado de la sesión
   */
  checkSession(): void {
    this.authService.checkStatus().subscribe({
      next: (response) => {
        if (response.tipo !== 1) {
          this.messageService.add({
            severity: 'warn',
            summary: 'Sesión',
            detail: 'Tu sesión ha expirado'
          });
          this.logout();
        }
      },
      error: () => {
        // Si hay error, probablemente la sesión expiró
      }
    });
  }

  /**
   * Cierra la sesión
   */
  logout(): void {
    this.confirmationService.confirm({
      message: '¿Estás seguro de que deseas cerrar sesión?',
      header: 'Confirmar',
      icon: 'pi pi-exclamation-triangle',
      accept: () => {
        this.authService.logout();
      }
    });
  }

  /**
   * Logout directo sin confirmación
   */
  doLogout(): void {
    this.authService.logout();
  }

  /**
   * Verifica si el usuario es admin
   */
  isAdmin(): boolean {
    return this.user?.role === 'admin';
  }
}
