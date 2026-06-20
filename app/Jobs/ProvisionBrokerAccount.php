<?php

namespace App\Jobs;

use App\Models\BrokerAccount;
use App\Services\MetaApi\MetaApiProvisioning;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Provisiona en MetaApi la cuenta de broker (create con reintentos de
 * validacion + deploy). Corre en cola porque la validacion puede tardar
 * 1-2 minutos y no debe bloquear la peticion web.
 *
 * La contrasena se pasa al job y vive solo en el payload de la cola; nunca se
 * guarda en la tabla broker_accounts.
 */
class ProvisionBrokerAccount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public BrokerAccount $account,
        public string $password,
    ) {
    }

    public function handle(MetaApiProvisioning $metaapi): void
    {
        $metaapi->provision($this->account, $this->password);
    }

    public function failed(\Throwable $e): void
    {
        $this->account->forceFill([
            'provision_state' => 'error',
            'last_error' => $e->getMessage(),
        ])->save();
    }
}
