import Checkbox from '@/Components/Checkbox';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';

// Parametros editables de la estrategia "Multi-TF Order Flow + IA" (bot Python
// tradingbot). Los nombres de las claves coinciden con los de config.py para que
// el bot pueda mapearlos directamente al leerlos desde la API.
// type: 'number' | 'int' | 'bool'
const groups = [
    {
        title: 'Modo de ejecución',
        fields: [
            { key: 'live_mode', label: 'Modo real (LIVE) — sin marcar = paper/demo', type: 'bool' },
        ],
    },
    {
        title: 'Tendencia multi-timeframe (D1/H8/H4)',
        fields: [
            { key: 'ema_trend_period', label: 'Periodo EMA de tendencia', type: 'int' },
        ],
    },
    {
        title: 'Order Flow en M5',
        fields: [
            { key: 'of_volume_sma_period', label: 'Velas para volumen medio (SMA)', type: 'int' },
            { key: 'of_volume_multiplier', label: 'Multiplicador de volumen', type: 'number', step: '0.1' },
            { key: 'of_min_body_ratio', label: 'Cuerpo mínimo de vela (0-1)', type: 'number', step: '0.01' },
        ],
    },
    {
        title: 'Gestión de riesgo',
        fields: [
            { key: 'max_open_positions', label: 'Máx. posiciones simultáneas', type: 'int' },
        ],
    },
    {
        title: 'Re-optimización (motor de simulación)',
        fields: [
            { key: 'reoptimize_every_n_cycles', label: 'Re-optimizar cada N ciclos (12 = 1h)', type: 'int' },
            { key: 'n_variants', label: 'Variantes por re-optimización', type: 'int' },
            { key: 'sim_lookback_bars', label: 'Velas M5 para el backtest rápido', type: 'int' },
        ],
    },
    {
        title: 'Métrica IA (clasificador online)',
        fields: [
            { key: 'ai_train_lookback_bars', label: 'Velas de entrenamiento', type: 'int' },
            { key: 'ai_label_horizon_bars', label: 'Horizonte de etiquetado (velas)', type: 'int' },
        ],
    },
    {
        title: 'Filtro de noticias (NLP / FinBERT)',
        fields: [
            { key: 'nlp_block_confidence', label: 'Confianza para bloquear (0-1)', type: 'number', step: '0.01' },
            { key: 'nlp_neutral_when_no_headlines', label: 'Neutral si no hay titulares', type: 'bool' },
        ],
    },
];

export default function StrategyMultiTfOrderFlow({ params, setParam }) {
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
