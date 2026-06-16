<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],

            'symbols' => ['nullable', 'array'],
            'symbols.*' => ['string', 'max:20'],

            'direction' => ['required', Rule::in(['buy', 'sell', 'both'])],
            'lot_size' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'stop_loss_pips' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'take_profit_pips' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'max_open_trades' => ['required', 'integer', 'min:1', 'max:1000'],

            'risk_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'trailing_stop_pips' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'trading_start_time' => ['nullable', 'date_format:H:i'],
            'trading_end_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * Normalize the symbols to uppercase, trimmed, unique values.
     */
    protected function prepareForValidation(): void
    {
        $symbols = $this->input('symbols');

        if (is_array($symbols)) {
            $symbols = array_values(array_unique(array_filter(array_map(
                fn ($symbol) => strtoupper(trim((string) $symbol)),
                $symbols
            ))));

            $this->merge(['symbols' => $symbols]);
        }
    }
}
