import { Injectable } from '@angular/core';
import { ActivatedRouteSnapshot, CanActivate, Router, UrlTree } from '@angular/router';

import { AuthService } from './auth.service';

@Injectable({ providedIn: 'root' })
export class PermissionGuard implements CanActivate {
  constructor(
    private readonly authService: AuthService,
    private readonly router: Router,
  ) {}

  canActivate(route: ActivatedRouteSnapshot): boolean | UrlTree {
    const permission = route.data['permission'] as string | undefined;

    if (!permission) {
      return true;
    }

    const hasPermission = this.authService.hasPermission(permission);
    if (hasPermission) {
      return true;
    }

    return this.router.createUrlTree(['/pages/403']);
  }
}
