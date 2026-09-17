const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');
const rxjs = require('rxjs');
const operators = require('rxjs/operators');
const storage = new Map([['display_currency', 'USD']]);
function load(file) {
  const context = {
    exports: {}, console,
    localStorage: { getItem: key => storage.get(key) || null, setItem: (key, value) => storage.set(key, value) },
    require(name) {
      if (name === '@angular/core') return { Injectable: () => value => value, Component: () => value => value };
      if (name === 'rxjs') return rxjs;
      if (name === 'rxjs/operators') return operators;
      if (name.includes('environment')) return { environment: { apiBaseUrl: '/api/v1' } };
      return {};
    },
  };
  vm.runInNewContext(ts.transpileModule(fs.readFileSync(path.join(__dirname, '../src/app', file), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, experimentalDecorators: true },
  }).outputText, context);
  return context.exports;
}
const { CurrencyService } = load('services/currency.service.ts');
const { SettingsService } = load('services/settings.service.ts');
const { HeaderComponent } = load('@theme/components/header/header.component.ts');
const rates = { SYP: 10000, TRY: 40 };
const currency = new CurrencyService({ getRates: () => rxjs.of(rates), convert: (amount, code) => amount * rates[code] });
const rows = code => [
  { key: 'default_display_currency', value: code },
  { key: 'currency_syp_enabled', value: true },
  { key: 'currency_try_enabled', value: true },
];
let pending;
const settings = new SettingsService({
  get: () => rxjs.of({ data: rows('SYP') }),
  put: () => (pending = new rxjs.Subject()),
});
const header = new HeaderComponent({}, { onItemClick: () => rxjs.NEVER },
  { onMediaQueryChange: () => rxjs.NEVER }, {}, { getBreakpointsMap: () => ({ xl: {} }) },
  { user$: rxjs.of(null) }, settings, {}, { instant: key => key, onLangChange: rxjs.NEVER }, {}, currency);
header.ngOnInit();
assert.equal(header.currentCurrency, 'SYP', 'Backend default replaces stale USD preference');
assert.equal(currency.formatAmount(2), '20,000 ل.س');
header.onCurrencyChange('USD');
currency.applyDisplaySettings('SYP', ['USD', 'SYP', 'TRY']);
assert.equal(currency.getCurrentCurrency(), 'USD', 'Explicit header selection survives unchanged default');
settings.bulkUpdate({ default_display_currency: 'TRY' }).subscribe();
assert.equal(header.currentCurrency, 'USD', 'Do not apply before successful save');
pending.next({ data: rows('TRY') });
assert.equal(header.currentCurrency, 'TRY');
assert.equal(currency.formatAmount(2), '₺80,00');
settings.bulkUpdate({ default_display_currency: 'SYP' }).subscribe({ error() {} });
pending.error({ status: 422 });
assert.equal(header.currentCurrency, 'TRY', 'Failed save preserves currency');
settings.bulkUpdate({ currency_try_enabled: false }).subscribe();
pending.next({ data: rows('SYP').map(row => row.key === 'currency_try_enabled' ? { ...row, value: false } : row) });
assert.equal(header.currentCurrency, 'SYP');
assert.equal(header.supportedCurrencies.some(item => item.code === 'TRY'), false);
header.ngOnDestroy();
console.log('Currency regression passed: initial default, saved settings, conversion, manual choice, disabled currency and failed save. Mock HTTP/rates only.');
