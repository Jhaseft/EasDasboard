import Checkbox from '@/Components/Checkbox';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';

// Grupos de parámetros editables de la estrategia "Asian Range Breakout".
// type: 'number' | 'int' | 'bool'
const groups = [
    {
        title: 'Gestión prop firm (crítico)',
        fields: [
            { key: 'max_daily_loss_pct', label: 'Pérdida diaria máxima (%)', type: 'number', step: '0.1' },
            { key: 'risk_per_trade_pct', label: 'Riesgo por operación (%)', type: 'number', step: '0.01' },
            { key: 'max_daily_trades', label: 'Máx. operaciones por día', type: 'int' },
            { key: 'max_lot_cap', label: 'Tope de lote (0 = sin tope)', type: 'number', step: '0.01' },
        ],
    },
    {
        title: 'Ciclo de intento / forzado',
        fields: [
            { key: 'force_trade_cycle', label: 'Activar ciclo de intento', type: 'bool' },
            { key: 'attempt_interval_min', label: 'Intervalo de intento (min)', type: 'int' },
            { key: 'auto_relax_filters', label: 'Relajar filtros automáticamente', type: 'bool' },
            { key: 'relax_per_attempt', label: 'Relajación por intento (0-1)', type: 'number', step: '0.01' },
            { key: 'max_relax_steps', label: 'Intentos antes de forzar', type: 'int' },
            { key: 'force_entry_at_max', label: 'Forzar entrada al máximo', type: 'bool' },
            { key: 'trade_sessions_only', label: 'Operar solo en sesiones Londres/NY', type: 'bool' },
        ],
    },
    {
        title: 'Sesiones (hora del servidor del broker)',
        fields: [
            { key: 'asian_start_hour', label: 'Asia inicio (h)', type: 'int' },
            { key: 'asian_end_hour', label: 'Asia fin (h)', type: 'int' },
            { key: 'london_start_hour', label: 'Londres inicio (h)', type: 'int' },
            { key: 'london_end_hour', label: 'Londres fin (h)', type: 'int' },
            { key: 'ny_start_hour', label: 'NY inicio (h)', type: 'int' },
            { key: 'ny_end_hour', label: 'NY fin (h)', type: 'int' },
        ],
    },
    {
        title: 'Señal, R:R y técnicos',
        fields: [
            { key: 'volume_surge_multiplier', label: 'Multiplicador de volumen', type: 'number', step: '0.1' },
            { key: 'tp_rr_multiplier', label: 'Ratio TP:SL', type: 'number', step: '0.1' },
            { key: 'atr_period', label: 'Periodo ATR', type: 'int' },
            { key: 'atr_sl_floor_mult', label: 'Piso de SL (× ATR)', type: 'number', step: '0.1' },
            { key: 'volume_lookback', label: 'Velas para volumen medio', type: 'int' },
            { key: 'max_spread_points', label: 'Spread máximo (puntos)', type: 'int' },
            { key: 'breakout_buffer_pips', label: 'Buffer de ruptura (pips)', type: 'number', step: '0.1' },
            { key: 'min_free_margin_pct', label: 'Margen libre mínimo (%)', type: 'number', step: '0.1' },
            { key: 'remove_ea_on_dd', label: 'Quitar EA al alcanzar DD', type: 'bool' },
        ],
    },
];

export default function StrategyAsianBreakout({ params, setParam }) {
    return (
        <div className="space-y-6">
            {groups.map((group) => (
                <div key={group.title}>
                    <h4 className="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        {group.title}
                    </h4>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {group.fields.map((field) => (
                            <div key={field.key}>
                                {field.type === 'bool' ? (
                                    <label className="flex items-center gap-2 pt-6">
                                        <Checkbox
                                            checked={!!params[field.key]}
                                            onChange={(e) =>
                                                setParam(field.key, e.target.checked)
                                            }
                                        />
                                        <span className="text-sm text-gray-700">
                                            {field.label}
                                        </span>
                                    </label>
                                ) : (
                                    <>
                                        <InputLabel value={field.label} />
                                        <TextInput
                                            type="number"
                                            step={field.type === 'int' ? '1' : field.step || 'any'}
                                            className="mt-1 block w-full"
                                            value={params[field.key] ?? ''}
                                            onChange={(e) =>
                                                setParam(field.key, e.target.value)
                                            }
                                        />
                                    </>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}
