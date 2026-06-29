<?php

use App\Models\User;
use App\Services\MetaApi\MetaStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function statsMaster(bool $showBalance): \App\Models\BrokerAccount
{
    return User::factory()->create()->brokerAccounts()->create([
        'name'               => 'Pro',
        'platform'           => 'mt5',
        'login'              => '1',
        'server'             => 'D',
        'metaapi_account_id' => 'acc-123',
        'region'             => 'new-york',
        'is_public'          => true,
        'show_balance'       => $showBalance,
    ]);
}

function fakeMetaStats(): void
{
    Http::fake([
        '*/metrics' => Http::response(['metrics' => [
            'gain' => 50, 'maxDrawdown' => 12, 'profitFactor' => 1.8,
            'wonTradesPercent' => 60, 'trades' => 100,
            'balance' => 1000, 'equity' => 1100, 'profit' => 100,
        ]]),
        '*/historical-trades/*' => Http::response(['historicalTrades' => [[
            'symbol' => 'EURUSD', 'type' => 'DEAL_TYPE_BUY', 'volume' => 0.1,
            'gain' => 2.5, 'profit' => 25, 'success' => 'won',
            'openTime' => '2026-06-01 10:00:00.000', 'closeTime' => '2026-06-01 12:00:00.000',
        ]]]),
    ]);
}

it('expone métricas y operaciones con cifras en $ cuando NO es incógnito', function () {
    fakeMetaStats();
    $stats = app(MetaStats::class)->publicStats(statsMaster(true));

    expect($stats['metrics']['gain'])->toBe(50);
    expect($stats['metrics'])->toHaveKey('balance');
    expect($stats['trades'][0]['type'])->toBe('buy');
    expect($stats['trades'][0])->toHaveKey('profit');
});

it('oculta las cifras en $ en modo incógnito', function () {
    fakeMetaStats();
    $stats = app(MetaStats::class)->publicStats(statsMaster(false));

    expect($stats['metrics']['gain'])->toBe(50);          // % sí
    expect($stats['metrics'])->not->toHaveKey('balance'); // $ no
    expect($stats['trades'][0])->not->toHaveKey('profit');
    expect($stats['trades'][0]['gain'])->toBe(2.5);
});

it('devuelve null si MetaApi falla', function () {
    Http::fake(['*' => Http::response([], 500)]);
    expect(app(MetaStats::class)->publicStats(statsMaster(true)))->toBeNull();
});
