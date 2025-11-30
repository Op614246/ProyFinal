import { Component, OnInit, ChangeDetectorRef, OnDestroy, ChangeDetectionStrategy } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { Subject, takeUntil } from 'rxjs';
import { AuthService } from '../services/auth.service';
import { TokenService } from '../services/token.service';
import { ToastService } from '../services/toast.service';

@Component({
  selector: 'app-login',
  standalone: false,
  templateUrl: './login.html',
  styleUrl: './login.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class Login implements OnInit, OnDestroy {
  loginForm!: FormGroup;
  loading = false;
  returnUrl: string = '/features/tareas';
  private destroy$ = new Subject<void>();

  constructor(
    private fb: FormBuilder,
    private authService: AuthService,
    private tokenService: TokenService,
    private router: Router,
    private route: ActivatedRoute,
    private toastService: ToastService,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    // Crear formulario vacío - siempre limpio al cargar
    this.loginForm = this.fb.group({
      username: ['', [Validators.required, Validators.minLength(3)]],
      password: ['', [Validators.required, Validators.minLength(6)]]
    });

    // Obtener URL de retorno si existe
    this.returnUrl = this.route.snapshot.queryParams['returnUrl'] || '/features/tareas';
    
    // Si ya está autenticado, redirigir
    if (this.tokenService.isLoggedIn()) {
      this.router.navigate([this.returnUrl]);
    }
    
    this.cdr.markForCheck();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  /**
   * Envía el formulario de login
   */
  onSubmit(): void {
    if (this.loginForm.invalid) {
      this.markFormTouched();
      this.toastService.warning('Por favor, complete todos los campos.');
      return;
    }

    // Establecer loading y notificar cambios
    this.loading = true;
    this.cdr.markForCheck();
    
    const { username, password } = this.loginForm.value;

    this.authService.login(username, password)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          this.loading = false;
          this.cdr.markForCheck();
          
          if (response.tipo === 1) {
            // Login exitoso
            this.toastService.success(response.mensajes[0] || 'Bienvenido');
            
            // Redirigir después de un pequeño delay para mostrar el mensaje
            setTimeout(() => {
              this.router.navigate([this.returnUrl]);
            }, 800);
          } else {
            // Error o advertencia
            this.showErrors(response.mensajes || ['Error en el inicio de sesión']);
          }
        },
        error: (error) => {
          this.loading = false;
          this.cdr.markForCheck();
          console.error('Error de login:', error);
          this.showErrors(error.mensajes || ['Error de conexión con el servidor']);
        }
      });
  }

  /**
   * Muestra mensajes de error
   */
  private showErrors(messages: string[]): void {
    if (messages && messages.length > 0) {
      messages.forEach(msg => {
        this.toastService.error(msg);
      });
    } else {
      this.toastService.error('Error desconocido');
    }
  }

  /**
   * Marca todos los campos como tocados para mostrar errores
   */
  private markFormTouched(): void {
    Object.keys(this.loginForm.controls).forEach(key => {
      this.loginForm.get(key)?.markAsTouched();
    });
  }

  get f() {
    return this.loginForm.controls;
  }
}
