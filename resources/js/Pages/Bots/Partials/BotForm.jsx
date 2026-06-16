import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Link } from '@inertiajs/react';
import StrategyAsianBreakout from './StrategyAsianBreakout';

const directionOptions = [
    { value: 'both', label: 'Ambos (compra y venta)' },
    { value: 'buy', label: 'Solo compra (buy)' },
    { value: 'sell', label: 'Solo venta (sell)' },
];

const timeframeOptions = ['M1', 'M5', 'M10', 'M15', 'M30', 'H1', 'H4', 'D1'];

const strategyOptions = [
    { value: 'simple', label: 'Simple (dirección fija, una por vela)' },
    { value: 'asian_breakout', label: 'Asian Range Breakout (prop firm)' },
];

export default function BotForm({
    data,
    setData,
    errors,
    processing,
    onSubmit,
    submitLabel,
    strategyDefaults = {},
}) {
    // symbols se guarda como array; lo editamos como texto separado por comas.
    const symbolsText = Array.isArray(data.symbols) ? data.symbols.join(', ') : '';

    const handleSymbols = (value) => {
        const list = value
            .split(',')
            .map((s) => s.trim().toUpperCase())
            .filter(Boolean);
        setData('symbols', list);
    };

    // Actualiza un parámetro suelto de la estrategia.
    const setParam = (key, value) => {
        setData('parameters', { ...(data.parameters || {}), [key]: value });
    };

    // Al cambiar de estrategia, rellena parámetros con los defaults que faltan.
    const changeStrategy = (value) => {
        setData('strategy', value);
        const defaults = strategyDefaults[value] || {};
        setData('parameters', { ...defaults, ...(data.parameters || {}) });
    };

    return (
        <form onSubmit={onSubmit} className="space-y-6">
            <section className="space-y-4">
                <div>
                    <InputLabel htmlFor="name" value="Nombre del bot" />
                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        isFocused
                    />
                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="symbols" value="Pares / símbolos (separados por coma)" />
                    <TextInput
                        id="symbols"
                        className="mt-1 block w-full"
                        value={symbolsText}
                        onChange={(e) => handleSymbols(e.target.value)}
                        placeholder="EURUSD, GBPUSD, XAUUSD"
                    />
                    <InputError className="mt-2" message={errors.symbols} />
                </div>

                <div>
                    <InputLabel htmlFor="timeframe" value="Temporalidad (vela)" />
                    <select
                        id="timeframe"
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.timeframe}
                        onChange={(e) => setData('timeframe', e.target.value)}
                    >
                        {timeframeOptions.map((tf) => (
                            <option key={tf} value={tf}>
                                {tf}
                            </option>
                        ))}
                    </select>
                    <p className="mt-1 text-xs text-gray-500">
                        El bot abrirá como máximo una operación por vela de esta
                        temporalidad. Se puede cambiar sin tocar MT5.
                    </p>
                    <InputError className="mt-2" message={errors.timeframe} />
                </div>

                <div>
                    <InputLabel htmlFor="strategy" value="Estrategia" />
                    <select
                        id="strategy"
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.strategy}
                        onChange={(e) => changeStrategy(e.target.value)}
                    >
                        {strategyOptions.map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </select>
                    <InputError className="mt-2" message={errors.strategy} />
                </div>

                <label className="flex items-center">
                    <Checkbox
                        checked={!!data.is_active}
                        onChange={(e) => setData('is_active', e.target.checked)}
                    />
                    <span className="ms-2 text-sm text-gray-700">
                        Bot activo (el runner abrirá operaciones)
                    </span>
                </label>
            </section>

            {data.strategy === 'asian_breakout' && (
                <section className="space-y-4 border-t border-gray-100 pt-6">
                    <h3 className="text-sm font-semibold uppercase tracking-wide text-gray-500">
                        Parámetros · Asian Range Breakout
                    </h3>
                    <StrategyAsianBreakout
                        params={data.parameters || {}}
                        setParam={setParam}
                    />
                </section>
            )}

            {data.strategy === 'simple' && (
            <>
            <section className="space-y-4 border-t border-gray-100 pt-6">
                <h3 className="text-sm font-semibold uppercase tracking-wide text-gray-500">
                    Cómo abre la operación
                </h3>

                <div>
                    <InputLabel htmlFor="direction" value="Dirección" />
                    <select
                        id="direction"
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.direction}
                        onChange={(e) => setData('direction', e.target.value)}
                    >
                        {directionOptions.map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </select>
                    <InputError className="mt-2" message={errors.direction} />
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="lot_size" value="Lotaje" />
                        <TextInput
                            id="lot_size"
                            type="number"
                            step="0.01"
                            min="0.01"
                            className="mt-1 block w-full"
                            value={data.lot_size}
                            onChange={(e) => setData('lot_size', e.target.value)}
                        />
                        <InputError className="mt-2" message={errors.lot_size} />
                    </div>
                    <div>
                        <InputLabel htmlFor="max_open_trades" value="Máx. operaciones abiertas" />
                        <TextInput
                            id="max_open_trades"
                            type="number"
                            min="1"
                            className="mt-1 block w-full"
                            value={data.max_open_trades}
                            onChange={(e) => setData('max_open_trades', e.target.value)}
                        />
                        <InputError className="mt-2" message={errors.max_open_trades} />
                    </div>
                    <div>
                        <InputLabel htmlFor="stop_loss_pips" value="Stop loss (pips)" />
                        <TextInput
                            id="stop_loss_pips"
                            type="number"
                            min="0"
                            className="mt-1 block w-full"
                            value={data.stop_loss_pips ?? ''}
                            onChange={(e) => setData('stop_loss_pips', e.target.value)}
                        />
                        <InputError className="mt-2" message={errors.stop_loss_pips} />
                    </div>
                    <div>
                        <InputLabel htmlFor="take_profit_pips" value="Take profit (pips)" />
                        <TextInput
                            id="take_profit_pips"
                            type="number"
                            min="0"
                            className="mt-1 block w-full"
                            value={data.take_profit_pips ?? ''}
                            onChange={(e) => setData('take_profit_pips', e.target.value)}
                        />
                        <InputError className="mt-2" message={errors.take_profit_pips} />
                    </div>
                </div>
            </section>

            <section className="space-y-4 border-t border-gray-100 pt-6">
                <h3 className="text-sm font-semibold uppercase tracking-wide text-gray-500">
                    Gestión de riesgo
                </h3>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="risk_percent" value="Riesgo por operación (%)" />
                        <TextInput
                            id="risk_percent"
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            className="mt-1 block w-full"
                            value={data.risk_percent ?? ''}
                            onChange={(e) => setData('risk_percent', e.target.value)}
                        />
                        <InputError className="mt-2" message={errors.risk_percent} />
                    </div>
                    <div>
                        <InputLabel htmlFor="trailing_stop_pips" value="Trailing stop (pips)" />
                        <TextInput
                            id="trailing_stop_pips"
                            type="number"
                            min="0"
                            className="mt-1 block w-full"
                            value={data.trailing_stop_pips ?? ''}
                            onChange={(e) => setData('trailing_stop_pips', e.target.value)}
                        />
                        <InputError className="mt-2" message={errors.trailing_stop_pips} />
                    </div>
                    <div>
                        <InputLabel htmlFor="trading_start_time" value="Horario inicio" />
                        <TextInput
                            id="trading_start_time"
                            type="time"
                            className="mt-1 block w-full"
                            value={data.trading_start_time ?? ''}
                            onChange={(e) => setData('trading_start_time', e.target.value)}
                        />
                        <InputError className="mt-2" message={errors.trading_start_time} />
                    </div>
                    <div>
                        <InputLabel htmlFor="trading_end_time" value="Horario fin" />
                        <TextInput
                            id="trading_end_time"
                            type="time"
                            className="mt-1 block w-full"
                            value={data.trading_end_time ?? ''}
                            onChange={(e) => setData('trading_end_time', e.target.value)}
                        />
                        <InputError className="mt-2" message={errors.trading_end_time} />
                    </div>
                </div>
            </section>
            </>
            )}

            <div className="flex items-center gap-4">
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
                <Link
                    href={route('bots.index')}
                    className="text-sm text-gray-600 underline hover:text-gray-900"
                >
                    Cancelar
                </Link>
            </div>
        </form>
    );
}
