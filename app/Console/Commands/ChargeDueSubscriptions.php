<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSubscription;
use App\Services\Wallet\MarketplaceBilling;
use Illuminate\Console\Command;

class ChargeDueSubscriptions extends Command
{
    protected $signature = 'marketplace:charge-due';

    protected $description = 'Cobra las suscripciones del marketplace cuyo periodo venció';

    public function handle(MarketplaceBilling $billing): int
    {
        $due = MarketplaceSubscription::whereIn('status', ['active', 'past_due'])
            ->where('pricing_model', 'subscription')
            ->where('next_charge_at', '<=', now())
            ->get();

        $charged = 0;
        $failed = 0;

        foreach ($due as $sub) {
            if ($billing->chargeSubscription($sub)) {
                $charged++;
            } else {
                $failed++;
            }
        }

        $this->info("Suscripciones procesadas: {$due->count()} | cobradas: {$charged} | sin saldo: {$failed}");

        return self::SUCCESS;
    }
}
