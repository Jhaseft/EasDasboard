<?php

namespace App\Jobs;

use App\Models\SlaveAccount;
use App\Services\MetaApi\MetaApiProvisioning;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProvisionSlaveAccount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public SlaveAccount $account,
        public string $password,
    ) {}

    public function handle(MetaApiProvisioning $metaapi): void
    {
        // Reutiliza el mismo servicio de provisioning pero adaptado para SlaveAccount
        try {
            $accountId = $metaapi->createAccount($this->account, $this->password);

            $this->account->forceFill([
                'metaapi_account_id' => $accountId,
                'region'             => $this->account->region ?: config('services.metaapi.region'),
                'provision_state'    => 'deploying',
                'last_error'         => null,
            ])->save();

            $metaapi->deploy($accountId);

            $this->account->forceFill(['provision_state' => 'deployed'])->save();
        } catch (\Throwable $e) {
            $this->account->forceFill([
                'provision_state' => 'error',
                'last_error'      => $e->getMessage(),
            ])->save();

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->account->forceFill([
            'provision_state' => 'error',
            'last_error'      => $e->getMessage(),
        ])->save();
    }
}
