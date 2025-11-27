import { Component, EventEmitter, Output } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { UserService, CreateUserRequest } from '../../../core/service/user.service';
import { MessageService } from 'primeng/api';

@Component({
  selector: 'app-modal-crear-usuario',
  standalone: false,
  templateUrl: './modal-crear-usuario.html',
  styleUrl: './modal-crear-usuario.scss',
  providers: [MessageService]
})
export class ModalCrearUsuario {
  @Output() usuarioCreado = new EventEmitter<void>();

  visible = false;
  loading = false;
  userForm: FormGroup;

  roles = [
    { label: 'Usuario', value: 'user' },
    { label: 'Administrador', value: 'admin' }
  ];

  constructor(
    private fb: FormBuilder,
    private userService: UserService,
    private messageService: MessageService
  ) {
    this.userForm = this.fb.group({
      username: ['', [Validators.required, Validators.minLength(3), Validators.maxLength(50)]],
      password: ['', [Validators.required, Validators.minLength(6)]],
      confirmPassword: ['', [Validators.required]],
      role: ['user', [Validators.required]]
    }, {
      validators: this.passwordMatchValidator
    });
  }

  /**
   * Validador para verificar que las contraseñas coincidan
   */
  passwordMatchValidator(form: FormGroup) {
    const password = form.get('password');
    const confirmPassword = form.get('confirmPassword');
    
    if (password && confirmPassword && password.value !== confirmPassword.value) {
      confirmPassword.setErrors({ passwordMismatch: true });
      return { passwordMismatch: true };
    }
    return null;
  }

  /**
   * Getter para acceder a los controles del formulario
   */
  get f() {
    return this.userForm.controls;
  }

  /**
   * Abre el modal y resetea el formulario
   */
  abrir(): void {
    this.userForm.reset({ role: 'user' });
    this.visible = true;
  }

  /**
   * Cierra el modal
   */
  cerrar(): void {
    this.visible = false;
    this.userForm.reset({ role: 'user' });
  }

  /**
   * Envía el formulario para crear el usuario
   */
  onSubmit(): void {
    if (this.userForm.invalid) {
      // Marcar todos los campos como tocados para mostrar errores
      Object.keys(this.userForm.controls).forEach(key => {
        this.userForm.get(key)?.markAsTouched();
      });
      return;
    }

    this.loading = true;
    
    const userData: CreateUserRequest = {
      username: this.userForm.value.username.trim(),
      password: this.userForm.value.password,
      role: this.userForm.value.role
    };

    this.userService.createUser(userData).subscribe({
      next: (response) => {
        if (response.tipo === 1) {
          this.usuarioCreado.emit();
          this.cerrar();
        } else {
          this.messageService.add({
            severity: 'error',
            summary: 'Error',
            detail: response.mensajes?.join(' ') || 'Error al crear usuario'
          });
        }
        this.loading = false;
      },
      error: (error) => {
        this.messageService.add({
          severity: 'error',
          summary: 'Error',
          detail: error.message || 'Error de conexión'
        });
        this.loading = false;
      }
    });
  }
}
