import { Component, OnInit, ViewChild, ChangeDetectorRef, AfterViewInit } from '@angular/core';
import { ConfirmationService, MessageService } from 'primeng/api';
import { UserService, User } from '../../core/service/user.service';
import { ModalCrearUsuario } from './modal-crear-usuario/modal-crear-usuario';

@Component({
  selector: 'app-gestion-usuarios',
  standalone: false,
  templateUrl: './gestion-usuarios.html',
  styleUrl: './gestion-usuarios.scss',
  providers: [MessageService, ConfirmationService]
})
export class GestionUsuarios implements OnInit, AfterViewInit {
  @ViewChild(ModalCrearUsuario) modalCrearUsuario!: ModalCrearUsuario;

  usuarios: User[] = [];
  loading = true;
  
  constructor(
    private userService: UserService,
    private messageService: MessageService,
    private confirmationService: ConfirmationService,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    // No cargar aquí para evitar NG0100
  }

  ngAfterViewInit(): void {
    // Cargar después de que la vista esté inicializada
    setTimeout(() => {
      this.cargarUsuarios();
    }, 0);
  }

  /**
   * Carga la lista de usuarios desde el backend
   */
  cargarUsuarios(): void {
    this.loading = true;
    this.cdr.detectChanges();
    
    this.userService.getUsers().subscribe({
      next: (response) => {
        if (response.tipo === 1 && response.data) {
          this.usuarios = response.data;
        } else {
          this.messageService.add({
            severity: 'error',
            summary: 'Error',
            detail: response.mensajes?.join(' ') || 'Error al cargar usuarios'
          });
        }
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: (error) => {
        this.messageService.add({
          severity: 'error',
          summary: 'Error',
          detail: error.message || 'Error de conexión'
        });
        this.loading = false;
        this.cdr.detectChanges();
      }
    });
  }

  /**
   * Abre el modal para crear un nuevo usuario
   */
  abrirModalCrear(): void {
    this.modalCrearUsuario.abrir();
  }

  /**
   * Callback cuando se crea un usuario exitosamente
   */
  onUsuarioCreado(): void {
    this.messageService.add({
      severity: 'success',
      summary: 'Éxito',
      detail: 'Usuario creado correctamente'
    });
    this.cargarUsuarios();
  }

  /**
   * Cambia el estado activo/inactivo de un usuario
   */
  toggleEstado(usuario: User): void {
    const accion = usuario.isPermanentlyLocked ? 'activar' : 'desactivar';
    
    this.confirmationService.confirm({
      message: `¿Está seguro que desea ${accion} al usuario "${usuario.username}"?`,
      header: 'Confirmar acción',
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: 'Sí',
      rejectLabel: 'No',
      accept: () => {
        this.userService.toggleUserStatus(usuario.id).subscribe({
          next: (response) => {
            if (response.tipo === 1) {
              this.messageService.add({
                severity: 'success',
                summary: 'Éxito',
                detail: response.mensajes?.join(' ') || `Usuario ${accion}do`
              });
              this.cargarUsuarios();
            } else {
              this.messageService.add({
                severity: 'error',
                summary: 'Error',
                detail: response.mensajes?.join(' ') || 'Error al cambiar estado'
              });
            }
          },
          error: (error) => {
            this.messageService.add({
              severity: 'error',
              summary: 'Error',
              detail: error.message
            });
          }
        });
      }
    });
  }

  /**
   * Desbloquea una cuenta (limpia intentos fallidos y bloqueos temporales)
   */
  desbloquearCuenta(usuario: User): void {
    this.confirmationService.confirm({
      message: `¿Desea desbloquear la cuenta de "${usuario.username}" y reiniciar sus intentos fallidos?`,
      header: 'Desbloquear cuenta',
      icon: 'pi pi-unlock',
      acceptLabel: 'Sí',
      rejectLabel: 'No',
      accept: () => {
        this.userService.unlockUser(usuario.username).subscribe({
          next: (response) => {
            if (response.tipo === 1) {
              this.messageService.add({
                severity: 'success',
                summary: 'Éxito',
                detail: response.mensajes?.join(' ') || 'Cuenta desbloqueada'
              });
              this.cargarUsuarios();
            } else {
              this.messageService.add({
                severity: 'error',
                summary: 'Error',
                detail: response.mensajes?.join(' ') || 'Error al desbloquear'
              });
            }
          },
          error: (error) => {
            this.messageService.add({
              severity: 'error',
              summary: 'Error',
              detail: error.message
            });
          }
        });
      }
    });
  }

  /**
   * Elimina un usuario permanentemente
   */
  eliminarUsuario(usuario: User): void {
    this.confirmationService.confirm({
      message: `¿Está seguro que desea eliminar permanentemente al usuario "${usuario.username}"? Esta acción no se puede deshacer.`,
      header: 'Eliminar usuario',
      icon: 'pi pi-trash',
      acceptLabel: 'Eliminar',
      rejectLabel: 'Cancelar',
      acceptButtonStyleClass: 'p-button-danger',
      accept: () => {
        this.userService.deleteUser(usuario.id).subscribe({
          next: (response) => {
            if (response.tipo === 1) {
              this.messageService.add({
                severity: 'success',
                summary: 'Éxito',
                detail: 'Usuario eliminado correctamente'
              });
              this.cargarUsuarios();
            } else {
              this.messageService.add({
                severity: 'error',
                summary: 'Error',
                detail: response.mensajes?.join(' ') || 'Error al eliminar usuario'
              });
            }
          },
          error: (error) => {
            this.messageService.add({
              severity: 'error',
              summary: 'Error',
              detail: error.message
            });
          }
        });
      }
    });
  }

  /**
   * Obtiene la severidad del tag según el estado
   */
  getSeverity(usuario: User): "success" | "info" | "warn" | "danger" | "secondary" | "contrast" | undefined {
    if (usuario.isPermanentlyLocked) {
      return 'danger';
    }
    if (usuario.lockoutUntil) {
      return 'warn';
    }
    return 'success';
  }

  /**
   * Obtiene el texto del estado
   */
  getEstadoTexto(usuario: User): string {
    if (usuario.isPermanentlyLocked) {
      return 'Inactivo';
    }
    if (usuario.lockoutUntil) {
      return 'Bloqueado temp.';
    }
    return 'Activo';
  }

  /**
   * Formatea la fecha para mostrar
   */
  formatearFecha(fecha: string | null): string {
    if (!fecha) return '-';
    return new Date(fecha).toLocaleString('es-PE', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }
}
