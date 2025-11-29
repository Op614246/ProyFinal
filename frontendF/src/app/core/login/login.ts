import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';

import { AuthService } from '../services/auth.service';
import { ToastService } from '../../shared/services/toast.service';

@Component({
  selector: 'app-login',
  templateUrl: './login.html',
  styleUrls: ['./login.scss'],
  standalone: false
})
export class Login implements OnInit {
  loginForm!: FormGroup;
  loading = false;
  returnUrl: string = '/login';

  constructor(
    private fb: FormBuilder,
    private authService: AuthService,
    private router: Router,
    private route: ActivatedRoute,
    private toastService: ToastService
  ) {}

  ngOnInit(): void {
    this.loginForm = this.fb.group({
      username: ['', [Validators.required, Validators.minLength(3)]],
      password: ['', [Validators.required, Validators.minLength(6)]]
    });

    this.returnUrl = this.route.snapshot.queryParams['returnUrl'] || '/login';

    if (this.authService.isAuthenticated()) {
      this.router.navigate([this.returnUrl]);
    }
  }

  onSubmit(): void {
    if (this.loginForm.invalid) {
      this.markFormTouched();
      this.toastService.presentToast('Por favor, complete todos los campos.', 'warning');
      return;
    }

    this.loading = true;
    const { username, password } = this.loginForm.value;

    this.authService.login(username, password).subscribe({
      next: (response) => {
        this.loading = false;
        
        if (response.tipo === 1) {
          this.toastService.presentToast(response.mensajes[0], 'success');
          
          setTimeout(() => {
            this.router.navigate([this.returnUrl]);
          }, 1000);
        } else {
          this.showErrors(response.mensajes);
        }
      },
      error: (error) => {
        this.loading = false;
        this.showErrors(error.mensajes || ['Error de conexión. Inténtelo de nuevo.']);
      }
    });
  }

  private showErrors(messages: string[]): void {
    messages.forEach(msg => {
      this.toastService.presentToast(msg, 'danger');
    });
  }

  private markFormTouched(): void {
    Object.keys(this.loginForm.controls).forEach(key => {
      this.loginForm.get(key)?.markAsTouched();
    });
  }

  get f() {
    return this.loginForm.controls;
  }
}
