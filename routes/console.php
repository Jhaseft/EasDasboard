<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cobro recurrente de las suscripciones del marketplace (una vez al día).
Schedule::command('marketplace:charge-due')->dailyAt('03:00');

// Renovación de la tarifa de plataforma por cuenta ($7/cuenta + módulo webhook).
// Cada suscripción tiene su propia fecha de renovación, por eso corre a diario.
Schedule::command('billing:charge-platform')->dailyAt('04:00');
