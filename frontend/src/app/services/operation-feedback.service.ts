import { HttpErrorResponse } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { NbToastrService } from '@nebular/theme';
import { TranslateService } from '@ngx-translate/core';

interface ApiErrorBody {
  message?: string;
  errors?: Record<string, string[]>;
}

@Injectable({ providedIn: 'root' })
export class OperationFeedbackService {
  constructor(
    private readonly toastr: NbToastrService,
    private readonly translate: TranslateService,
  ) {}

  success(message: string): void {
    this.toastr.success(message, this.translate.instant('COMMON.SUCCESS'));
  }

  error(error: unknown, fallbackMessage: string): string {
    const message = this.resolveErrorMessage(error, fallbackMessage);
    this.toastr.danger(message, this.translate.instant('COMMON.ERROR'));

    return message;
  }

  private resolveErrorMessage(error: unknown, fallbackMessage: string): string {
    if (!(error instanceof HttpErrorResponse) || !this.isApiErrorBody(error.error)) {
      return fallbackMessage;
    }

    const validationMessage = Object.values(error.error.errors ?? {}).reduce<string[]>(
      (messages, fieldMessages) => messages.concat(fieldMessages),
      [],
    ).find(message => !!message);

    return validationMessage ?? error.error.message ?? fallbackMessage;
  }

  private isApiErrorBody(value: unknown): value is ApiErrorBody {
    return typeof value === 'object' && value !== null;
  }
}
