import { Component } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';

import { AuthService } from './auth.service';

@Component({
  selector: 'ngx-login',
  templateUrl: './auth-login.component.html',
  styleUrls: ['./auth-login.component.scss'],
})
export class LoginComponent {
  loginForm: FormGroup;
  errorMessage = '';
  submitting = false;

  constructor(
    private readonly fb: FormBuilder,
    private readonly authService: AuthService,
    private readonly router: Router,
    private readonly translate: TranslateService,
  ) {
    this.loginForm = this.fb.group({
      email: ['', [Validators.required, Validators.email]],
      password: ['', [Validators.required]],
    });
  }

  onSubmit(): void {
    if (this.loginForm.invalid) {
      this.loginForm.markAllAsTouched();
      return;
    }

    const { email, password } = this.loginForm.value;
    this.submitting = true;
    this.errorMessage = '';

    this.authService.login({ email, password }).subscribe({
      next: () => {
        this.submitting = false;
        this.router.navigateByUrl(this.authService.defaultRoute());
      },
      error: (error) => {
        this.submitting = false;
        this.errorMessage = error?.error?.message ?? this.translate.instant('AUTH.LOGIN_FAILED');
      },
    });
  }
}
