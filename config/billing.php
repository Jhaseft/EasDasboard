<?php

return [
    /*
    | Tarifa base mensual por CADA cuenta de bróker conectada (maestra o esclava)
    | que esté habilitada. Es la "Cuenta Conectada" del modelo de negocio.
    */
    'account_fee' => (float) env('BILLING_ACCOUNT_FEE', 7),

    /*
    | Add-on mensual para usar el módulo de webhooks (ingesta de señales externas
    | desde TradingView / Python). Se cobra una sola vez por usuario, no por cuenta.
    */
    'webhook_module_fee' => (float) env('BILLING_WEBHOOK_MODULE_FEE', 15),
];
