import { Component } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { Role, User, UserPayload, UserService } from '../services/user.service';
import { OperationFeedbackService } from '../services/operation-feedback.service';

@Component({
  selector: 'ngx-pos-users',
  styleUrls: ['./pos-users.component.scss'],
  template: `
    <div class="page-shell">
      <div class="page-header"><div><span class="eyebrow">{{ translate.instant('USERS.TITLE') }}</span><h1>{{ translate.instant('USERS.SUBTITLE') }}</h1></div><div><button nbButton status="primary" (click)="startCreate()">{{ translate.instant('USERS.ADD_NEW') }}</button></div></div>
      <nb-card *ngIf="showForm"><nb-card-body><nb-card-header>{{ editingId ? translate.instant('USERS.EDIT_TITLE') : translate.instant('USERS.ADD_TITLE') }}</nb-card-header>
        <div class="form-grid"><label>{{ translate.instant('USERS.FORM_NAME') }}<input nbInput name="user_name" autocomplete="name" [(ngModel)]="form.name"></label><label>{{ translate.instant('USERS.FORM_EMAIL') }}<input nbInput type="email" name="user_email" autocomplete="email" [(ngModel)]="form.email"></label><label>{{ translate.instant('USERS.FORM_PHONE') }}<input nbInput type="tel" name="user_phone" autocomplete="tel" [(ngModel)]="form.phone"></label>
          <label>{{ translate.instant('USERS.FORM_ROLE') }}<select nbInput name="user_role" [(ngModel)]="form.role_ids[0]"><option value="">{{ translate.instant('USERS.FORM_ROLE_SELECT') }}</option><option *ngFor="let role of roles" [value]="role.id">{{ role.name_ar }}</option></select></label>
          <label>{{ translate.instant('USERS.FORM_PASSWORD') }} {{ editingId ? translate.instant('USERS.FORM_PASSWORD_OPTIONAL') : '' }}<input nbInput type="password" name="user_password" autocomplete="new-password" [(ngModel)]="form.password"></label>
          <label>{{ translate.instant('USERS.FORM_PASSWORD_CONFIRM') }}<input nbInput type="password" name="user_password_confirmation" autocomplete="new-password" [(ngModel)]="form.password_confirmation"></label>
        </div>
        <small class="password-hint">{{ translate.instant('USERS.FORM_PASSWORD_HINT') }}</small>
        <div *ngIf="formError" class="state error">{{ formError }}</div>
        <button nbButton status="primary" [disabled]="saving" (click)="save()">{{ saving ? translate.instant('USERS.FORM_SAVING') : translate.instant('USERS.FORM_SAVE') }}</button><button nbButton status="basic" (click)="closeForm()">{{ translate.instant('USERS.FORM_CANCEL') }}</button>
      </nb-card-body></nb-card>
      <nb-card><nb-card-body><div *ngIf="loading" class="state">{{ translate.instant('USERS.LOADING') }}</div><div *ngIf="errorMessage" class="state error">{{ errorMessage }}</div>
        <table *ngIf="!loading && !errorMessage" class="data-table"><thead><tr><th>{{ translate.instant('USERS.COL_NAME') }}</th><th>{{ translate.instant('USERS.COL_EMAIL') }}</th><th>{{ translate.instant('USERS.COL_PHONE') }}</th><th>{{ translate.instant('USERS.COL_ROLES') }}</th><th>{{ translate.instant('USERS.COL_ACTIONS') }}</th></tr></thead><tbody><tr *ngFor="let user of users"><td>{{ user.name }}</td><td>{{ user.email }}</td><td>{{ user.phone || '—' }}</td><td>{{ roleNames(user) }}</td><td class="actions"><button nbButton size="tiny" status="basic" (click)="startEdit(user)">{{ translate.instant('USERS.EDIT') }}</button><button nbButton size="tiny" status="danger" [disabled]="deletingIds.has(user.id)" (click)="remove(user)">{{ deletingIds.has(user.id) ? translate.instant('USERS.DELETING') : translate.instant('USERS.DELETE') }}</button></td></tr></tbody></table>
        <div *ngIf="!loading && !errorMessage && !users.length" class="state">{{ translate.instant('USERS.NO_USERS') }}</div>                <div *ngIf="totalPages > 1" class="pagination"><select nbInput [(ngModel)]="perPage" (change)="load(1)"><option [ngValue]="10">10</option><option [ngValue]="25">25</option><option [ngValue]="50">50</option></select><button nbButton size="small" status="basic" [disabled]="page === 1" (click)="load(page - 1)">السابق</button><button *ngFor="let pageNumber of pageNumbers" nbButton size="small" [status]="pageNumber === page ? 'primary' : 'basic'" (click)="load(pageNumber)">{{ pageNumber }}</button><span>صفحة {{ page }} من {{ totalPages }} · {{ total }} مستخدم</span><button nbButton size="small" status="basic" [disabled]="page === totalPages" (click)="load(page + 1)">التالي</button></div>
      </nb-card-body></nb-card>
    </div>
  `,
})
export class PosUsersComponent {
  users: User[] = []; roles: Role[] = []; loading = false; saving = false; showForm = false; editingId: string | null = null; errorMessage = ''; formError = ''; page = 1; perPage = 15; totalPages = 1; total = 0;
  form: UserPayload = this.emptyForm();
  deletingIds = new Set<string>();
  constructor(private readonly service: UserService, private readonly feedback: OperationFeedbackService, readonly translate: TranslateService) { this.load(); this.loadRoles(); }
  private emptyForm(): UserPayload { return { name: '', email: '', phone: '', password: '', password_confirmation: '', role_ids: [''] }; }
  load(page = this.page): void { this.loading = true; this.errorMessage = ''; this.service.list(page, this.perPage).subscribe({ next: r => { this.users = r.data; this.page = r.meta.current_page; this.totalPages = r.meta.last_page; this.total = r.meta.total; this.loading = false; }, error: () => { this.loading = false; this.errorMessage = this.translate.instant('USERS.LOAD_FAILED'); } }); }
  get pageNumbers(): number[] { return Array.from({ length: this.totalPages }, (_, index) => index + 1); }
  loadRoles(): void { this.service.roles().subscribe({ next: r => this.roles = r.data, error: () => this.formError = this.translate.instant('USERS.ROLES_LOAD_FAILED') }); }
  startCreate(): void { this.editingId = null; this.form = this.emptyForm(); this.formError = ''; this.showForm = true; }
  startEdit(user: User): void { this.editingId = user.id; this.form = { name: user.name, email: user.email, phone: user.phone || '', password: '', password_confirmation: '', role_ids: [user.roles?.[0]?.id || ''] }; this.formError = ''; this.showForm = true; }
  closeForm(): void { this.showForm = false; this.editingId = null; this.formError = ''; }
  roleNames(user: User): string { return user.roles?.map(role => role.name_ar).join('، ') || '—'; }
  save(): void {
    if (this.saving) return;
    if (!this.form.name.trim() || !this.form.email.trim() || !this.form.role_ids[0]) { this.formError = this.translate.instant('USERS.NAME_EMAIL_ROLE_REQUIRED'); return; }
    if (!this.editingId && !this.form.password) { this.formError = this.translate.instant('USERS.PASSWORD_REQUIRED'); return; }
    if (this.form.password && !this.isStrongPassword(this.form.password)) { this.formError = this.translate.instant('USERS.PASSWORD_INVALID'); return; }
    if (this.form.password !== this.form.password_confirmation) { this.formError = this.translate.instant('USERS.PASSWORD_MISMATCH'); return; }
    const wasEditing = !!this.editingId; this.saving = true; this.formError = ''; const request = this.editingId ? this.service.update(this.editingId, this.form) : this.service.create(this.form);
    request.subscribe({ next: () => { this.saving = false; this.closeForm(); this.feedback.success(wasEditing ? this.translate.instant('USERS.SAVE_SUCCESS') : this.translate.instant('USERS.SAVE_SUCCESS_NEW')); this.load(1); }, error: error => { this.saving = false; this.formError = this.feedback.error(error, this.translate.instant('USERS.SAVE_FAILED')); } });
  }
  private isStrongPassword(password: string): boolean { return password.length >= 8 && /[a-z]/.test(password) && /[A-Z]/.test(password) && /\d/.test(password) && /[^A-Za-z0-9]/.test(password); }
  remove(user: User): void { if (this.deletingIds.has(user.id) || !confirm(this.translate.instant('USERS.DELETE_CONFIRM', { name: user.name }))) return; this.deletingIds.add(user.id); this.service.delete(user.id).subscribe({ next: () => { this.deletingIds.delete(user.id); this.feedback.success(this.translate.instant('USERS.DELETE_SUCCESS')); this.load(this.page); }, error: error => { this.deletingIds.delete(user.id); this.errorMessage = this.feedback.error(error, this.translate.instant('USERS.DELETE_FAILED')); } }); }
}
