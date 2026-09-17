/**
 * @license
 * Copyright Akveo. All Rights Reserved.
 * Licensed under the MIT License. See License.txt in the project root for license information.
 */
export const environment = {
  production: true,
  apiBaseUrl: window.__POS_CONFIG__?.apiBaseUrl ?? '/v1',
  stripeKey: window.__POS_CONFIG__?.stripeKey ?? (() => {
    if (typeof window !== 'undefined' && !window.__POS_CONFIG__?.stripeKey) {
      console.warn('[POS] No Stripe publishable key configured. Set window.__POS_CONFIG__.stripeKey before app bootstrap.');
    }
    return '';
  })(),
};
