import { Component, OnInit, ChangeDetectorRef, OnDestroy, ChangeDetectionStrategy } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { Subject, takeUntil } from 'rxjs';
import { AuthService } from '../services/auth.service';
import { ToastService } from '../../shared/services/toast.service';

@Component({
  selector: 'app-login',
  standalone: false,
  templateUrl: './login.html',
  styleUrl: './login.scss',
  providers: [ToastService],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class Login implements OnInit, OnDestroy {
  loginForm!: FormGroup;
  loading = false;
  returnUrl: string = '/dashboard';
  private destroy$ = new Subject<void>();

  constructor(
    private fb: FormBuilder,
    private authService: AuthService,
    private router: Router,
    private route: ActivatedRoute,
    private toastService: ToastService,
    private cdr: ChangeDetectorRef
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
      this.toastService.presentToast('Por favor, complete todos los campos.', 'warning');
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
            this.toastService.presentToast(response.mensajes[0], 'success');
            
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
          this.cdr.markForCheck();
          this.showErrors(error.mensajes || ['Error de conexión']);
        }
      });
  }

  /**
   * Muestra mensajes de error
   */
  private showErrors(messages: string[]): void {
    messages.forEach(msg => {
      this.toastService.presentToast(msg, 'danger');
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

  get f() {
    return this.loginForm.controls;
  }
}
