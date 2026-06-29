<?php

namespace App\Console\Commands;

use App\Models\PlatformSubscription;
use App\Services\Wallet\PlatformBilling;
use Illuminate\Console\Command;

class ChargePlatformFees extends Command
{
    protected $signature = 'billing:charge-platform';

    protected $description = 'Renueva las suscripciones de plataforma ($7/cuenta + módulo webhook) cuyo periodo venció';

    public function handle(PlatformBilling $billing): int
    {
        $due = PlatformSubscription::whereIn('status', ['active', 'past_due'])
            ->where('next_charge_at', '<=', now())
            ->get();

        $charged = 0;
        $failed = 0;

        foreach ($due as $sub) {
            $billing->renew($sub) ? $charged++ : $failed++;
        }

        $this->info("Suscripciones de plataforma procesadas: {$due->count()} | cobradas: {$charged} | sin saldo: {$failed}");

        return self::SUCCESS;
    }
}
