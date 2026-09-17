import { Injectable } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { take } from 'rxjs/operators';
import { SettingsService } from '../../services/settings.service';

@Injectable({ providedIn: 'root' })
export class LanguageService {
  private initialized = false;

  constructor(
    private readonly translate: TranslateService,
    private readonly settingsService: SettingsService,
  ) {
    this.translate.addLangs(['ar', 'en']);
    this.translate.setDefaultLang('ar');
  }

  init(): void {
    if (this.initialized) {
      return;
    }
    this.initialized = true;

    this.settingsService.getValue('language')
      .pipe(take(1))
      .subscribe(lang => {
        this.setLanguage((lang as string) || 'ar');
      });
  }

  setLanguage(lang: string): void {
    this.translate.use(lang);

    const dir = lang === 'ar' ? 'rtl' : 'ltr';
    document.documentElement.setAttribute('dir', dir);
    document.documentElement.setAttribute('lang', lang);
  }

  getCurrentLang(): string {
    return this.translate.currentLang || this.translate.defaultLang || 'ar';
  }
}
