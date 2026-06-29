<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Wallet\PlatformBilling;
use Illuminate\Console\Command;

class ChargePlatformFees extends Command
{
    protected $signature = 'billing:charge-platform';

    protected $description = 'Cobra la tarifa mensual de plataforma ($7/cuenta + módulo webhook) a cada usuario';

    public function handle(PlatformBilling $billing): int
    {
        $charged = 0;
        $skipped = 0;

        User::query()->chunkById(100, function ($users) use ($billing, &$charged, &$skipped) {
            foreach ($users as $user) {
                if ($billing->chargeMonthly($user)) {
                    $charged++;
                } else {
                    $skipped++;
                }
            }
        });

        $this->info("Plataforma cobrada a {$charged} usuario(s) | sin cobro: {$skipped}");

        return self::SUCCESS;
    }
}
