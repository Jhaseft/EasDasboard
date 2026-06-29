<?php

return [
    /*
    | Porcentaje que se queda el SaaS de cada cobro entre seguidor y proveedor
    | (take rate). Ej: 15 = el SaaS retiene 15%, el proveedor recibe 85%.
    */
    'take_rate_pct' => (float) env('MARKETPLACE_TAKE_RATE_PCT', 15),

    /*
    | Cuenta de usuario que recibe la comisión del SaaS en su billetera.
    | Si es null, la comisión simplemente no se acredita a nadie (se "cobra").
    */
    'platform_user_id' => env('MARKETPLACE_PLATFORM_USER_ID'),
];
