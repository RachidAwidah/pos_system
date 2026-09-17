import { Component } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';

@Component({
  selector: 'ngx-forbidden',
  styleUrls: ['./forbidden.component.scss'],
  template: `
    <div class="forbidden-wrap">
      <nb-card>
        <nb-card-body>
          <h1>403</h1>
          <h2>{{ 'FORBIDDEN.TITLE' | translate }}</h2>
          <p>{{ 'FORBIDDEN.MESSAGE' | translate }}</p>
          <button nbButton status="primary" routerLink="/pages/dashboard">{{ 'FORBIDDEN.BACK' | translate }}</button>
        </nb-card-body>
      </nb-card>
    </div>
  `,
})
export class ForbiddenComponent {
  constructor(private readonly translate: TranslateService) {}
}
