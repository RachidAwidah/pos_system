import { Component, Input, TemplateRef } from '@angular/core';
import { NbDialogRef } from '@nebular/theme';

export interface DetailsField {
  key: string;
  label: string;
  value: unknown;
  type?: 'text' | 'table';
  tableHeaders?: string[];
  tableRows?: Array<Record<string, unknown>>;
}

@Component({
  selector: 'ngx-details-dialog',
  templateUrl: './details-dialog.component.html',
  styleUrls: ['./details-dialog.component.scss'],
})
export class DetailsDialogComponent {
  @Input() title = '';
  @Input() fields: DetailsField[] = [];
  @Input() contentTemplate: TemplateRef<unknown> | null = null;
  @Input() showFooter = true;
  @Input() closeDisabled = false;

  constructor(protected ref: NbDialogRef<DetailsDialogComponent>) {}

  close(): void {
    if (this.closeDisabled) return;
    this.ref.close();
  }
}
