/**
 * @license
 * Copyright Akveo. All Rights Reserved.
 * Licensed under the MIT License. See License.txt in the project root for license information.
 */
// The file contents for the current environment will overwrite these during build.
// The build system defaults to the dev environment which uses `environment.ts`, but if you do
// `ng build --env=prod` then `environment.prod.ts` will be used instead.
// The list of which env maps to which file can be found in `.angular-cli.json`.

export const environment = {
  production: false,
  apiBaseUrl: window.__POS_CONFIG__?.apiBaseUrl ?? 'http://127.0.0.1:8000/v1',
  stripeKey: window.__POS_CONFIG__?.stripeKey ?? (() => {
    if (typeof window !== 'undefined' && !window.__POS_CONFIG__?.stripeKey) {
      console.warn('[POS] No Stripe publishable key configured. Set window.__POS_CONFIG__.stripeKey before app bootstrap.');
    }
    return '';
  })(),
};
