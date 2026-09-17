const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');
const { of, Subject, NEVER } = require('rxjs');
function load(file) {
  const context = { exports: {}, console, localStorage: { getItem: () => null, setItem() {}, removeItem() {} },
    require(name) {
      if (name === '@angular/core') return { Component: () => x => x, Injectable: () => x => x };
      if (name.includes('environment')) return { environment: { apiBaseUrl: '/v1' } };
      return {};
    } };
  vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname, '../src/app', file), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, experimentalDecorators: true },
  }).outputText, context);
  return context.exports;
}
const { PosSalesComponent } = load('pages/pos-sales.component.ts');
const { ShiftService } = load('services/shift.service.ts');
let posted;
const wire = new ShiftService({ post: (url, body) => { posted = { url, body }; return of({ data: {} }); } });
wire.close('own', 170, 'Counted excess', 'Approved excess').subscribe();
assert.equal(posted.body.admin_override_reason, 'Approved excess');
const summaries = [];
const openings = [];
const closes = [];
const service = {
  referenceData: () => NEVER,
  summary: id => { const result = new Subject(); summaries.push({ id, result }); return result; },
  lastClosed: id => { const result = new Subject(); openings.push({ id, result }); return result; },
  close: (...args) => { const result = new Subject(); closes.push({ args, result, force: false }); return result; },
  forceClose: (...args) => { const result = new Subject(); closes.push({ args, result, force: true }); return result; },
};
const component = new PosSalesComponent(service, { success() {}, error: () => 'error' }, {}, {},
  { instant: key => key }, { currentCurrency$: NEVER }, {}, { detectChanges() {} });
component.currentShift = { id: 'own' };
component.canForceCloseShifts = true;
component.openCloseShiftForm();
assert.equal(component.closingCash, null);
component.closeShift();
assert.equal(closes.length, 0, 'Cannot close before summary/count');
summaries[0].result.next({ data: { expected_cash: '100.00' } });
component.closingCash = 170;
component.closingNotes = 'Counted excess';
component.adminOverrideReason = 'Approved excess';
assert.equal(component.closingDifference, 70);
component.closeShift();
component.closeShift();
assert.equal(closes.length, 1, 'No duplicate closing request');
assert.equal(closes[0].args[3], 'Approved excess');
closes[0].result.error({ status: 422 });
assert.equal(component.currentShift.id, 'own', 'Failed close preserves shift');
component.registers = [{ id: 'A', open_shift: { id: 'other', owned_by_current_user: false } }, { id: 'B' }];
component.selectedRegisterId = 'A';
component.continueShift();
assert.equal(component.currentShift.id, 'own', 'Cannot continue another owner shift');
component.openCloseShiftForm(true);
summaries[1].result.next({ data: { expected_cash: '100.00' } });
component.closingCash = 0;
component.forceCloseReason = 'Reviewed shortage';
component.selectedRegisterId = 'B';
component.closeShift();
assert.equal(closes[1].force, true);
assert.equal(closes[1].args[0], 'other', 'Administrative target stays the selected closing shift');
assert.equal(closes[1].args[1], 0, 'Explicit zero counted cash is valid');
closes[1].result.next({ data: {} });
assert.equal(component.showCloseShiftForm, false);
component.registers = [{ id: 'A' }, { id: 'B' }];
component.openingCash = 800;
component.onRegisterChange('A');
assert.equal(component.openingCash, 0);
component.onRegisterChange('B');
openings[0].result.next({ data: { closing_cash: '800' } });
assert.equal(component.openingCash, 0, 'Ignore previous register response');
openings[1].result.next({ data: null });
assert.equal(component.openingCash, 0, 'No previous closing cash means zero');
component.onRegisterChange('A');
component.openingCash = 33;
openings[2].result.next({ data: { closing_cash: '800' } });
assert.equal(component.openingCash, 33, 'Do not overwrite manual opening amount');
for (const invalid of [null, undefined, -1, NaN, Infinity, 1.001]) {
  component.closingCash = invalid;
  assert.equal(component.validClosingCash, false);
}
console.log('Shift UI regression passed: payload, ordinary/admin paths, explicit count, duplicate/failure handling, owner restriction and register races. Mock HTTP only.');
const template = fs.readFileSync(path.join(__dirname, '../src/app/pages/pos-sales.component.html'), 'utf8');
for (const locale of ['ar', 'en']) {
  const translations = JSON.parse(fs.readFileSync(path.join(__dirname, '../src/assets/i18n', locale + '.json'), 'utf8'));
  for (const match of template.matchAll(/SALES\.([A-Z_]+)/g)) {
    assert.ok(translations.SALES[match[1]], `${locale}: ${match[1]}`);
    assert.ok(!translations.SALES[match[1]].includes('??'), `Corrupt translation: ${locale}: ${match[1]}`);
  }
}
console.log('Shift template translations verified in Arabic and English.');
