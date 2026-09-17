const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');
const rxjs = require('rxjs');
const operators = require('rxjs/operators');

// Exercise the actual component without HTTP, browser storage, or a real shift.
const source = fs.readFileSync(path.join(__dirname,
  '../src/app/@theme/components/header/header.component.ts'), 'utf8');
const compiled = ts.transpileModule(source, {
  compilerOptions: { module: ts.ModuleKind.CommonJS, experimentalDecorators: true },
}).outputText;
const context = {
  exports: {},
  require(name) {
    if (name === '@angular/core') return { Component: () => value => value };
    if (name === 'rxjs') return rxjs;
    if (name === 'rxjs/operators') return operators;
    return {};
  },
};
vm.runInNewContext(compiled, context);

function setup() {
  let calls = 0;
  let clears = 0;
  let pending = new rxjs.Subject();
  const navigations = [];
  const errors = [];
  const auth = {
    logout: () => { calls++; return pending; },
    clearSession: () => { clears++; },
  };
  const component = new context.exports.HeaderComponent({}, {}, {}, {}, {}, auth, {},
    { navigate: route => navigations.push(route.join('/')) },
    { instant: key => key },
    { error: (error, message) => errors.push({ error, message }) });
  return { component, navigations, errors, calls: () => calls, clears: () => clears,
    pending: () => pending, retry: () => { pending = new rxjs.Subject(); } };
}

for (const status of [0, 403, 409, 422, 500, 503, 401]) {
  const test = setup();
  test.component.logout();
  test.component.logout();
  assert.equal(test.calls(), 1, 'Ignore duplicate clicks while waiting');
  test.pending().error({ status });
  assert.equal(test.clears(), 0, 'Header must not erase session on failure');
  assert.equal(test.navigations.length, 0, 'Header must not fake successful logout');
  assert.equal(test.errors.length, 1);
  assert.equal(test.errors[0].message, status === 401
    ? 'HEADER.LOGOUT_SESSION_EXPIRED' : 'HEADER.LOGOUT_FAILED');
  test.retry();
  test.component.logout();
  assert.equal(test.calls(), 2, 'Allow retry after failure');
  test.pending().next({});
  test.pending().complete();
  assert.equal(test.navigations[0], '/auth/login');
}
console.log('Logout regression: 7 failure statuses, duplicate prevention and successful retries passed. No network or database calls.');
