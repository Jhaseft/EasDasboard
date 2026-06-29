import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

function Metric({ label, value, suffix = '', tone }) {
    return (
        <div className="rounded-md bg-gray-50 p-3 text-center">
            <div className={'text-lg font-bold ' + (tone ?? 'text-gray-900')}>
                {value ?? '—'}{value != null ? suffix : ''}
            </div>
            <div className="text-xs text-gray-500">{label}</div>
        </div>
    );
}

const num = (v, d = 2) => (v == null ? null : Number(v).toFixed(d));

export default function Show({ master, subscribed, balance, regions, stats, trades = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        platform: 'mt5',
        login: '',
        server: '',
        password: '',
        region: regions[0] ?? 'new-york',
        lot_multiplier: '1.0',
        copy_mode: 'multiplier',
        fixed_lot: '0.01',
    });

    const price = master.pricing_model === 'profit_share'
        ? `${master.profit_share_pct}% de ganancias`
        : master.subscription_price > 0 ? `$${master.subscription_price}/mes` : 'Gratis';

    const submit = (e) => {
        e.preventDefault();
        post(route('marketplace.subscribe', master.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">{master.name}</h2>
            }
        >
            <Head title={master.name} />

            <div className="py-6 sm:py-12">
                <div className="mx-auto max-w-2xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <Link href={route('marketplace.index')} className="text-sm text-indigo-600 hover:text-indigo-900">
                        ← Volver al marketplace
                    </Link>

                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <div className="flex items-start justify-between">
                            <div>
                                <div className="text-lg font-semibold text-gray-900">{master.name}</div>
                                <div className="mt-1 text-xs text-gray-400">
                                    {master.platform?.toUpperCase()} · {master.followers_count} seguidor(es)
                                </div>
                            </div>
                            <span className="rounded-full bg-indigo-50 px-3 py-1 text-sm font-semibold text-indigo-700">
                                {price}
                            </span>
                        </div>
                        {master.description && (
                            <p className="mt-3 text-sm text-gray-600">{master.description}</p>
                        )}
                    </div>

                    {/* Estadísticas auditadas (directo del bróker vía MetaApi) */}
                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <div className="mb-3 flex items-center justify-between">
                            <span className="text-sm font-medium text-gray-700">Rendimiento auditado</span>
                            <span className="text-xs text-gray-400">Datos reales del bróker · no editables</span>
                        </div>

                        {stats ? (
                            <>
                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    <Metric label="Ganancia total" value={num(stats.gain)} suffix="%"
                                        tone={stats.gain >= 0 ? 'text-green-600' : 'text-red-600'} />
                                    <Metric label="Drawdown máx." value={num(stats.max_drawdown)} suffix="%" tone="text-red-600" />
                                    <Metric label="Profit Factor" value={num(stats.profit_factor)} />
                                    <Metric label="% Aciertos" value={num(stats.win_rate, 1)} suffix="%" />
                                    <Metric label="Operaciones" value={stats.trades} />
                                    <Metric label="Ganancia mensual" value={num(stats.monthly_gain)} suffix="%"
                                        tone={stats.monthly_gain >= 0 ? 'text-green-600' : 'text-red-600'} />
                                </div>
                                {!stats.incognito && stats.balance != null && (
                                    <div className="mt-3 text-xs text-gray-500">
                                        Balance: ${num(stats.balance)} · Equity: ${num(stats.equity)} · P/L: ${num(stats.profit)}
                                    </div>
                                )}
                            </>
                        ) : (
                            <p className="text-sm text-gray-500">
                                Estadísticas aún no disponibles (la cuenta debe estar conectada y con
                                historial). Se actualizan cada pocos minutos.
                            </p>
                        )}
                    </div>

                    {/* Historial de operaciones */}
                    {trades.length > 0 && (
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="mb-3 text-sm font-medium text-gray-700">
                                Últimas operaciones
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="text-left text-xs text-gray-400">
                                            <th className="pb-2">Fecha</th>
                                            <th className="pb-2">Símbolo</th>
                                            <th className="pb-2">Tipo</th>
                                            <th className="pb-2 text-right">Vol.</th>
                                            <th className="pb-2 text-right">Resultado</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {trades.map((t, i) => (
                                            <tr key={i}>
                                                <td className="py-2 text-gray-500">
                                                    {t.close_time ? String(t.close_time).slice(0, 10) : '—'}
                                                </td>
                                                <td className="py-2 font-medium text-gray-800">{t.symbol}</td>
                                                <td className="py-2">
                                                    <span className={
                                                        'rounded px-1.5 py-0.5 text-xs font-medium ' +
                                                        (t.type === 'buy' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700')
                                                    }>
                                                        {t.type}
                                                    </span>
                                                </td>
                                                <td className="py-2 text-right text-gray-600">{t.volume}</td>
                                                <td className={'py-2 text-right font-medium ' +
                                                    (t.success === 'won' ? 'text-green-600' : 'text-red-600')}>
                                                    {t.gain != null ? `${Number(t.gain).toFixed(2)}%` : '—'}
                                                    {t.profit != null && (
                                                        <span className="ml-1 text-xs text-gray-400">(${Number(t.profit).toFixed(2)})</span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {master.is_own ? (
                        <div className="rounded-lg bg-yellow-50 p-4 text-sm text-yellow-800">
                            Esta es tu propia cuenta; no puedes seguirte a ti mismo.
                        </div>
                    ) : subscribed ? (
                        <div className="rounded-lg bg-green-50 p-4 text-sm text-green-800">
                            Ya sigues esta cuenta. Gestiona la suscripción desde el marketplace.
                        </div>
                    ) : (
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="mb-1 text-sm font-medium text-gray-800">Seguir esta cuenta</div>
                            <p className="mb-4 text-xs text-gray-500">
                                Conecta la cuenta que copiará sus operaciones. Se cobrará{' '}
                                <strong>{price}</strong> de tu billetera (saldo: ${Number(balance).toFixed(2)}).
                            </p>

                            {errors.amount && (
                                <div className="mb-3 rounded bg-red-50 p-2 text-xs text-red-700">{errors.amount}</div>
                            )}

                            <form onSubmit={submit} className="space-y-4">
                                <div>
                                    <InputLabel htmlFor="name" value="Nombre (alias)" />
                                    <TextInput id="name" className="mt-1 block w-full" value={data.name}
                                        onChange={(e) => setData('name', e.target.value)} />
                                    <InputError className="mt-1" message={errors.name} />
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <InputLabel htmlFor="platform" value="Plataforma" />
                                        <select id="platform"
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            value={data.platform} onChange={(e) => setData('platform', e.target.value)}>
                                            <option value="mt5">MetaTrader 5</option>
                                            <option value="mt4">MetaTrader 4</option>
                                        </select>
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="region" value="Región" />
                                        <select id="region"
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            value={data.region} onChange={(e) => setData('region', e.target.value)}>
                                            {regions.map((r) => <option key={r} value={r}>{r}</option>)}
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <InputLabel htmlFor="login" value="Login" />
                                    <TextInput id="login" className="mt-1 block w-full" value={data.login}
                                        onChange={(e) => setData('login', e.target.value)} />
                                    <InputError className="mt-1" message={errors.login} />
                                </div>
                                <div>
                                    <InputLabel htmlFor="server" value="Servidor del broker" />
                                    <TextInput id="server" className="mt-1 block w-full" value={data.server}
                                        onChange={(e) => setData('server', e.target.value)} />
                                    <InputError className="mt-1" message={errors.server} />
                                </div>
                                <div>
                                    <InputLabel htmlFor="password" value="Contraseña (no se guarda)" />
                                    <TextInput id="password" type="password" className="mt-1 block w-full" value={data.password}
                                        onChange={(e) => setData('password', e.target.value)} />
                                    <InputError className="mt-1" message={errors.password} />
                                </div>

                                <div>
                                    <InputLabel htmlFor="copy_mode" value="Modo de lote" />
                                    <select id="copy_mode"
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={data.copy_mode} onChange={(e) => setData('copy_mode', e.target.value)}>
                                        <option value="multiplier">Proporcional (multiplicador)</option>
                                        <option value="fixed">Lote fijo</option>
                                    </select>
                                </div>
                                {data.copy_mode === 'fixed' ? (
                                    <div>
                                        <InputLabel htmlFor="fixed_lot" value="Lote fijo" />
                                        <TextInput id="fixed_lot" type="number" step="0.01" min="0.01"
                                            className="mt-1 block w-full" value={data.fixed_lot}
                                            onChange={(e) => setData('fixed_lot', e.target.value)} />
                                        <InputError className="mt-1" message={errors.fixed_lot} />
                                    </div>
                                ) : (
                                    <div>
                                        <InputLabel htmlFor="lot_multiplier" value="Multiplicador de lote" />
                                        <TextInput id="lot_multiplier" type="number" step="0.0001" min="0.0001"
                                            className="mt-1 block w-full" value={data.lot_multiplier}
                                            onChange={(e) => setData('lot_multiplier', e.target.value)} />
                                        <InputError className="mt-1" message={errors.lot_multiplier} />
                                    </div>
                                )}

                                <div className="flex justify-end">
                                    <PrimaryButton disabled={processing}>Suscribirme y conectar</PrimaryButton>
                                </div>
                            </form>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
