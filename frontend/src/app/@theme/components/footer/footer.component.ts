import { Component } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';

@Component({
  selector: 'ngx-footer',
  styleUrls: ['./footer.component.scss'],
  template: `
    <span class="footer-text">
      {{ 'FOOTER.COPYRIGHT' | translate }} &copy; {{ currentYear }}
    </span>
  `,
})
export class FooterComponent {
  currentYear = new Date().getFullYear();

  constructor(private readonly translate: TranslateService) {}
}
