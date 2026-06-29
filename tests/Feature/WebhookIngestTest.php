<?php

use App\Jobs\ExecuteSignal;
use App\Models\BrokerAccount;
use App\Models\Signal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function makeAccount(array $overrides = [], bool $withWebhookModule = true): BrokerAccount
{
    $user = User::factory()->create();

    // El webhook es un add-on de pago: el módulo debe estar activo.
    if ($withWebhookModule) {
        \App\Models\PlatformSubscription::create([
            'user_id' => $user->id,
            'item'    => 'webhook_module',
            'amount'  => 15,
            'status'  => 'active',
        ]);
    }

    return $user->brokerAccounts()->create(array_merge([
        'name'            => 'Maestra',
        'platform'        => 'mt5',
        'login'           => '12345',
        'server'          => 'Demo-Server',
        'webhook_token'   => 'tok_'.str()->random(40),
        'ingest_mode'     => 'both',
        'is_enabled'      => true,
        'provision_state' => 'deployed',
    ], $overrides));
}

it('acepta una señal válida, crea la señal y despacha el job', function () {
    Queue::fake();
    $account = makeAccount();

    $res = $this->postJson("/api/webhook/{$account->webhook_token}", [
        'action' => 'buy',
        'symbol' => 'EURUSD',
        'volume' => 0.1,
    ]);

    $res->assertStatus(202)->assertJson(['ok' => true]);
    expect(Signal::count())->toBe(1);
    expect(Signal::first()->action)->toBe('buy');
    Queue::assertPushed(ExecuteSignal::class, 1);
});

it('rechaza un token inválido con 404', function () {
    Queue::fake();

    $this->postJson('/api/webhook/token-que-no-existe', [
        'action' => 'buy',
        'symbol' => 'EURUSD',
        'volume' => 0.1,
    ])->assertStatus(404);

    Queue::assertNothingPushed();
});

it('es idempotente con el mismo external id', function () {
    Queue::fake();
    $account = makeAccount();

    $payload = ['action' => 'sell', 'symbol' => 'EURUSD', 'volume' => 0.2, 'id' => 'abc-123'];

    $this->postJson("/api/webhook/{$account->webhook_token}", $payload)->assertStatus(202);
    $dup = $this->postJson("/api/webhook/{$account->webhook_token}", $payload);

    $dup->assertStatus(200)->assertJson(['duplicate' => true]);
    expect(Signal::count())->toBe(1);
    Queue::assertPushed(ExecuteSignal::class, 1);
});

it('valida que buy/sell requieran volumen', function () {
    Queue::fake();
    $account = makeAccount();

    $this->postJson("/api/webhook/{$account->webhook_token}", [
        'action' => 'buy',
        'symbol' => 'EURUSD',
    ])->assertStatus(422);
});

it('rechaza el webhook si el módulo no está activo (402)', function () {
    Queue::fake();
    $account = makeAccount(withWebhookModule: false);

    $this->postJson("/api/webhook/{$account->webhook_token}", [
        'action' => 'buy', 'symbol' => 'EURUSD', 'volume' => 0.1,
    ])->assertStatus(402);

    Queue::assertNothingPushed();
});

it('no acepta webhook si ingest_mode es solo read', function () {
    Queue::fake();
    $account = makeAccount(['ingest_mode' => 'read']);

    $this->postJson("/api/webhook/{$account->webhook_token}", [
        'action' => 'buy',
        'symbol' => 'EURUSD',
        'volume' => 0.1,
    ])->assertStatus(404);

    Queue::assertNothingPushed();
});
