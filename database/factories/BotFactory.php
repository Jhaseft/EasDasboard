<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Bot>
 */
class BotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(2, true).' Bot',
            'is_active' => $this->faker->boolean(),
            'symbols' => $this->faker->randomElements(['EURUSD', 'GBPUSD', 'USDJPY', 'XAUUSD', 'AUDUSD'], 2),
            'direction' => $this->faker->randomElement(['buy', 'sell', 'both']),
            'lot_size' => $this->faker->randomElement([0.01, 0.05, 0.10, 0.50]),
            'stop_loss_pips' => $this->faker->numberBetween(10, 200),
            'take_profit_pips' => $this->faker->numberBetween(10, 400),
            'max_open_trades' => $this->faker->numberBetween(1, 5),
            'risk_percent' => $this->faker->randomFloat(2, 0.5, 5),
            'trailing_stop_pips' => $this->faker->optional()->numberBetween(10, 100),
            'trading_start_time' => '08:00',
            'trading_end_time' => '20:00',
        ];
    }
}
