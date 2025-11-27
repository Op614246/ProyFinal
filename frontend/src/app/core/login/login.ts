import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { AuthService } from '../service/auth.service';
import { MessageService } from 'primeng/api';

@Component({
  selector: 'app-login',
  standalone: false,
  templateUrl: './login.html',
  styleUrl: './login.scss',
  providers: [MessageService]
})
export class Login implements OnInit {
  loginForm!: FormGroup;
  loading = false;
  returnUrl: string = '/dashboard';

  constructor(
    private fb: FormBuilder,
    private authService: AuthService,
    private router: Router,
    private route: ActivatedRoute,
    private messageService: MessageService
  ) {}

  ngOnInit(): void {
    // Crear formulario
    this.loginForm = this.fb.group({
      username: ['', [Validators.required, Validators.minLength(3)]],
      password: ['', [Validators.required, Validators.minLength(6)]]
    });

    // Obtener URL de retorno si existe
    this.returnUrl = this.route.snapshot.queryParams['returnUrl'] || '/dashboard';

    // Si ya está autenticado, redirigir
    if (this.authService.isAuthenticated()) {
      this.router.navigate([this.returnUrl]);
    }
  }

  /**
   * Envía el formulario de login
   */
  onSubmit(): void {
    if (this.loginForm.invalid) {
      this.markFormTouched();
      return;
    }

    this.loading = true;
    const { username, password } = this.loginForm.value;

    this.authService.login(username, password).subscribe({
      next: (response) => {
        this.loading = false;
        
        if (response.tipo === 1) {
          // Login exitoso
          this.messageService.add({
            severity: 'success',
            summary: '¡Bienvenido!',
            detail: response.mensajes[0]
          });
          
          // Redirigir después de un pequeño delay para mostrar el mensaje
          setTimeout(() => {
            this.router.navigate([this.returnUrl]);
          }, 1000);
        } else {
          // Error o advertencia
          this.showErrors(response.mensajes);
        }
      },
      error: (error) => {
        this.loading = false;
        this.showErrors(error.mensajes || ['Error de conexión']);
      }
    });
  }

  /**
   * Muestra mensajes de error
   */
  private showErrors(messages: string[]): void {
    messages.forEach(msg => {
      this.messageService.add({
        severity: 'error',
        summary: 'Error',
        detail: msg,
        life: 5000
      });
    });
  }

  /**
   * Marca todos los campos como tocados para mostrar errores
   */
  private markFormTouched(): void {
    Object.keys(this.loginForm.controls).forEach(key => {
      this.loginForm.get(key)?.markAsTouched();
    });
  }

  /**
   * Getters para acceso fácil a los controles
   */
  get f() {
    return this.loginForm.controls;
  }
}
