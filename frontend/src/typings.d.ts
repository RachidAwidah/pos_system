/**
 * @license
 * Copyright Akveo. All Rights Reserved.
 * Licensed under the MIT License. See License.txt in the project root for license information.
 */

/* SystemJS module definition */
declare var module: NodeModule;
interface NodeModule {
  id: string;
}

interface Window {
  __POS_CONFIG__?: {
    apiBaseUrl?: string;
    stripeKey?: string;
  };
}

declare var tinymce: any;

declare var echarts: any;
