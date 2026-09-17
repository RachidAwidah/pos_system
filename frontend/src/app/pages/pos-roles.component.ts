import { Component } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { Permission, Role, RoleService } from '../services/role.service';
import { downloadExcel } from '../shared/export.util';
import { OperationFeedbackService } from '../services/operation-feedback.service';

@Component({
  selector: 'ngx-pos-roles',
  styleUrls: ['./pos-roles.component.scss'],
  template: `
    <div class="page-shell">
      <div class="page-header"><div><span class="eyebrow">{{ this.translate.instant('ROLES.TITLE') }}</span><h1>{{ this.translate.instant('ROLES.SUBTITLE') }}</h1></div><div> <button nbButton status="primary" (click)="startCreate()">{{ this.translate.instant('ROLES.ADD_NEW') }}</button></div></div>
      <nb-card *ngIf="showForm"><nb-card-body><nb-card-header>{{ editingId ? this.translate.instant('ROLES.EDIT_TITLE') : this.translate.instant('ROLES.ADD_TITLE') }}</nb-card-header>
        <input nbInput fullWidth [placeholder]="this.translate.instant('ROLES.FORM_NAME')" [(ngModel)]="formName">
        <div class="permission-groups"><div *ngFor="let group of permissionGroups"><strong>{{ group }}</strong><label *ngFor="let permission of permissions[group]" class="checkbox"><input type="checkbox" [checked]="selectedPermissionIds.has(permission.id)" (change)="togglePermission(permission.id)"> <span>{{ permission.name_ar }}</span><small>{{ permission.description_ar }}</small></label></div></div>
        <div *ngIf="formError" class="state error">{{ formError }}</div><button nbButton status="primary" [disabled]="saving" (click)="save()">{{ saving ? this.translate.instant('ROLES.FORM_SAVING') : this.translate.instant('ROLES.FORM_SAVE') }}</button><button nbButton status="basic" (click)="closeForm()">{{ this.translate.instant('ROLES.FORM_CANCEL') }}</button>
      </nb-card-body></nb-card>
      <nb-card><nb-card-body><div *ngIf="loading" class="state">{{ this.translate.instant('ROLES.LOADING') }}</div><div *ngIf="errorMessage" class="state error">{{ errorMessage }}</div>
        <table *ngIf="!loading && !errorMessage" class="data-table"><thead><tr><th>{{ this.translate.instant('ROLES.COL_ROLE') }}</th><th>{{ this.translate.instant('ROLES.COL_DESCRIPTION') }}</th><th>{{ this.translate.instant('ROLES.COL_TYPE') }}</th><th>{{ this.translate.instant('ROLES.COL_PERMS_COUNT') }}</th><th>{{ this.translate.instant('ROLES.COL_ACTIONS') }}</th></tr></thead><tbody><tr *ngFor="let role of roles"><td>{{ role.name_ar }}</td><td>{{ role.description_ar }}</td><td>{{ role.is_system ? this.translate.instant('ROLES.TYPE_BUILTIN') : this.translate.instant('ROLES.TYPE_CUSTOM') }}</td><td>{{ role.permissions?.length || 0 }}</td><td class="actions"><button nbButton size="tiny" status="basic" (click)="startEdit(role)">{{ this.translate.instant('ROLES.EDIT') }}</button><button nbButton size="tiny" status="danger" [disabled]="role.is_system" (click)="remove(role)">{{ this.translate.instant('ROLES.DELETE') }}</button></td></tr></tbody></table>
      </nb-card-body></nb-card>
    </div>
  `,
})
export class PosRolesComponent {
  roles: Role[] = []; permissions: Record<string, Permission[]> = {}; permissionGroups: string[] = [];
  loading = false; saving = false; showForm = false; editingId: string | null = null; formName = ''; formError = ''; errorMessage = '';
  selectedPermissionIds = new Set<string>();
  exportRoles(): void { downloadExcel('roles.csv', [this.translate.instant('ROLES.COL_ROLE'), this.translate.instant('ROLES.TYPE_CUSTOM'), this.translate.instant('ROLES.COL_PERMS_COUNT')], this.roles.map(role => [role.name_ar, role.is_system ? this.translate.instant('ROLES.TYPE_BUILTIN') : this.translate.instant('ROLES.TYPE_CUSTOM'), role.permissions?.length || 0])); this.feedback.success(this.translate.instant('ROLES.EXPORT_SUCCESS')); }
  constructor(private readonly service: RoleService, private readonly feedback: OperationFeedbackService, readonly translate: TranslateService) { this.load(); this.loadPermissions(); }
  load(): void { this.loading = true; this.service.list().subscribe({ next: r => { this.roles = r.data; this.loading = false; }, error: () => { this.loading = false; this.errorMessage = this.translate.instant('ROLES.LOAD_FAILED'); } }); }
  loadPermissions(): void { this.service.permissions().subscribe({ next: r => { this.permissions = r.data; this.permissionGroups = Object.keys(r.data); }, error: () => this.formError = this.translate.instant('ROLES.PERMS_LOAD_FAILED') }); }
  startCreate(): void { this.editingId = null; this.formName = ''; this.selectedPermissionIds = new Set(); this.formError = ''; this.showForm = true; }
  startEdit(role: Role): void { this.editingId = role.id; this.formName = role.name; this.selectedPermissionIds = new Set((role.permissions || []).map(permission => permission.id)); this.formError = ''; this.showForm = true; }
  closeForm(): void { this.showForm = false; this.editingId = null; this.formError = ''; }
  togglePermission(id: string): void { this.selectedPermissionIds.has(id) ? this.selectedPermissionIds.delete(id) : this.selectedPermissionIds.add(id); }
  save(): void { if (this.saving) return; if (!this.formName.trim()) { this.formError = this.translate.instant('ROLES.NAME_REQUIRED'); return; } const wasEditing = !!this.editingId; this.saving = true; this.formError = ''; const payload = { name: this.formName.trim(), permission_ids: Array.from(this.selectedPermissionIds) }; const request = this.editingId ? this.service.update(this.editingId, payload) : this.service.create(payload); request.subscribe({ next: () => { this.saving = false; this.closeForm(); this.feedback.success(wasEditing ? this.translate.instant('ROLES.SAVE_SUCCESS') : this.translate.instant('ROLES.SAVE_SUCCESS_NEW')); this.load(); }, error: error => { this.saving = false; this.formError = this.feedback.error(error, this.translate.instant('ROLES.SAVE_FAILED')); } }); }
  remove(role: Role): void { if (role.is_system || !confirm(this.translate.instant('ROLES.DELETE_CONFIRM', { name: role.name }))) return; this.service.delete(role.id).subscribe({ next: () => { this.feedback.success(this.translate.instant('ROLES.DELETE_SUCCESS')); this.load(); }, error: error => this.errorMessage = this.feedback.error(error, this.translate.instant('ROLES.DELETE_FAILED')) }); }
}
