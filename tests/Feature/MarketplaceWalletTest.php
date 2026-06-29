<?php

use App\Exceptions\InsufficientFundsException;
use App\Jobs\ProvisionSlaveAccount;
use App\Models\MarketplaceSubscription;
use App\Models\SlaveAccount;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function publicMaster(User $owner, array $overrides = []): \App\Models\BrokerAccount
{
    return $owner->brokerAccounts()->create(array_merge([
        'name'               => 'Pro Trader',
        'platform'           => 'mt5',
        'login'              => '111',
        'server'             => 'Demo',
        'metaapi_account_id' => 'meta-'.uniqid(),
        'provision_state'    => 'deployed',
        'is_enabled'         => true,
        'is_public'          => true,
        'pricing_model'      => 'subscription',
        'subscription_price' => 30,
    ], $overrides));
}

it('recarga y cobra la billetera con transacciones', function () {
    $svc = app(WalletService::class);
    $user = User::factory()->create();
    $wallet = $svc->walletFor($user);

    $svc->deposit($wallet, 100);
    expect((float) $wallet->fresh()->balance)->toBe(100.0);

    $svc->charge($wallet, 30, 'Prueba');
    expect((float) $wallet->fresh()->balance)->toBe(70.0);
    expect($wallet->transactions()->count())->toBe(2);
});

it('no permite cobrar más que el saldo', function () {
    $svc = app(WalletService::class);
    $wallet = $svc->walletFor(User::factory()->create());
    $svc->deposit($wallet, 10);

    expect(fn () => $svc->charge($wallet, 50, 'x'))->toThrow(InsufficientFundsException::class);
    expect((float) $wallet->fresh()->balance)->toBe(10.0);
});

it('suscribirse cobra al seguidor y paga al proveedor menos el take rate', function () {
    Queue::fake();
    config(['marketplace.take_rate_pct' => 15]);

    $provider = User::factory()->create();
    $master = publicMaster($provider);

    $follower = User::factory()->create();
    $svc = app(WalletService::class);
    $svc->deposit($svc->walletFor($follower), 100);

    $this->actingAs($follower)
        ->post(route('marketplace.subscribe', $master->id), [
            'name'     => 'Mi copia',
            'platform' => 'mt5',
            'login'    => '999',
            'server'   => 'Demo',
            'password' => 'secret',
        ])
        ->assertRedirect(route('marketplace.index'));

    // Seguidor: 100 - 30 (cuota) - 7 (cuenta conectada) = 63. Proveedor: 30 - 15% = 25.5.
    expect((float) $svc->walletFor($follower)->fresh()->balance)->toBe(63.0);
    expect((float) $svc->walletFor($provider)->fresh()->balance)->toBe(25.5);

    expect(MarketplaceSubscription::where('subscriber_id', $follower->id)->where('status', 'active')->count())->toBe(1);
    expect(SlaveAccount::where('user_id', $follower->id)->where('master_account_id', $master->id)->count())->toBe(1);
    Queue::assertPushed(ProvisionSlaveAccount::class, 1);
});

it('rechaza suscribirse sin saldo suficiente', function () {
    Queue::fake();
    $provider = User::factory()->create();
    $master = publicMaster($provider);
    $follower = User::factory()->create(); // billetera en 0

    $this->actingAs($follower)
        ->post(route('marketplace.subscribe', $master->id), [
            'name' => 'x', 'platform' => 'mt5', 'login' => '1', 'server' => 'D', 'password' => 'p',
        ])
        ->assertSessionHasErrors('amount');

    expect(SlaveAccount::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('cobra $7 al conectar cada cuenta y $15 por el módulo webhook', function () {
    config(['billing.account_fee' => 7, 'billing.webhook_module_fee' => 15]);

    $svc = app(WalletService::class);
    $billing = app(\App\Services\Wallet\PlatformBilling::class);

    $user = User::factory()->create();
    $svc->deposit($svc->walletFor($user), 100);

    // Conecta 1 bróker + 1 esclava (cobra 7 + 7) y activa el módulo (15).
    $master = $user->brokerAccounts()->create([
        'name' => 'M', 'platform' => 'mt5', 'login' => '1', 'server' => 'D', 'is_enabled' => true,
    ]);
    $billing->subscribeAccount($master);

    $slave = $user->slaveAccounts()->create([
        'master_account_id' => $master->id, 'name' => 'S', 'platform' => 'mt5',
        'login' => '2', 'server' => 'D', 'is_enabled' => true,
    ]);
    $billing->subscribeAccount($slave);

    $billing->enableWebhookModule($user);

    // 100 - 7 - 7 - 15 = 71.
    expect((float) $user->wallet->fresh()->balance)->toBe(71.0);
    expect($billing->monthlyBreakdown($user)['total'])->toBe(29.0); // 2×7 + 15
    expect($billing->hasWebhookModule($user))->toBeTrue();

    // Desconectar la esclava deja de cobrarla.
    $billing->cancelAccount($slave);
    expect($billing->monthlyBreakdown($user)['accounts'])->toBe(1);
});

it('el worker solo copia esclavas con suscripción activa a una maestra ajena', function () {
    config(['services.bot_api.key' => 'test-key']);

    $provider = User::factory()->create();
    $master = publicMaster($provider);
    $follower = User::factory()->create();

    $slave = $follower->slaveAccounts()->create([
        'master_account_id'  => $master->id,
        'name'               => 'copia',
        'platform'           => 'mt5',
        'login'              => '2',
        'server'             => 'D',
        'metaapi_account_id' => 'meta-slave',
        'provision_state'    => 'deployed',
        'is_enabled'         => true,
        'auto_copy'          => true,
    ]);

    // Sin suscripción -> la maestra no aparece para el worker.
    $resp = $this->withHeaders(['X-API-Key' => 'test-key'])->getJson('/api/worker/copy-accounts');
    $resp->assertOk();
    expect($resp->json('count'))->toBe(0);

    // Con suscripción activa -> aparece con su esclava.
    MarketplaceSubscription::create([
        'subscriber_id'     => $follower->id,
        'master_account_id' => $master->id,
        'slave_account_id'  => $slave->id,
        'pricing_model'     => 'subscription',
        'amount'            => 30,
        'status'            => 'active',
    ]);

    $resp2 = $this->withHeaders(['X-API-Key' => 'test-key'])->getJson('/api/worker/copy-accounts');
    expect($resp2->json('count'))->toBe(1);
    expect($resp2->json('masters.0.slaves'))->toHaveCount(1);
});
