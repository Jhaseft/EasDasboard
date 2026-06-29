import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const STATE_STYLES = {
    deployed: 'bg-green-100 text-green-800',
    deploying: 'bg-yellow-100 text-yellow-800',
    validating: 'bg-yellow-100 text-yellow-800',
    pending: 'bg-gray-100 text-gray-600',
    error: 'bg-red-100 text-red-800',
};

function PublishForm({ account }) {
    const [open, setOpen] = useState(false);
    const { data, setData, patch, processing } = useForm({
        is_public: !!account.is_public,
        display_name: account.display_name ?? '',
        description: account.description ?? '',
        show_balance: !!account.show_balance,
        pricing_model: account.pricing_model ?? 'subscription',
        subscription_price: account.subscription_price ?? '0',
        profit_share_pct: account.profit_share_pct ?? '0',
    });

    const save = (e) => {
        e.preventDefault();
        patch(route('broker-accounts.publish', account.id), { preserveScroll: true });
    };

    return (
        <div className="mt-3 rounded-md border border-gray-200 bg-gray-50 p-3">
            <button
                onClick={() => setOpen((o) => !o)}
                className="flex w-full items-center justify-between text-xs font-medium text-gray-700"
            >
                <span>
                    Marketplace:{' '}
                    {account.is_public ? (
                        <span className="text-green-600">publicada</span>
                    ) : (
                        <span className="text-gray-400">no publicada</span>
                    )}
                    {account.followers_count > 0 && ` · ${account.followers_count} seguidor(es)`}
                </span>
                <span className="text-indigo-600">{open ? 'Cerrar' : 'Configurar'}</span>
            </button>

            {open && (
                <form onSubmit={save} className="mt-3 space-y-3 border-t border-gray-200 pt-3">
                    <label className="flex items-center gap-2 text-sm text-gray-800">
                        <input type="checkbox" className="rounded border-gray-300 text-indigo-600"
                            checked={data.is_public} onChange={(e) => setData('is_public', e.target.checked)} />
                        Publicar en el marketplace (otros pueden copiarla)
                    </label>

                    <input type="text" placeholder="Nombre público (opcional)"
                        className="block w-full rounded-md border-gray-300 text-sm shadow-sm"
                        value={data.display_name} onChange={(e) => setData('display_name', e.target.value)} />

                    <textarea placeholder="Descripción / estrategia (opcional)" rows={2}
                        className="block w-full rounded-md border-gray-300 text-sm shadow-sm"
                        value={data.description} onChange={(e) => setData('description', e.target.value)} />

                    <label className="flex items-center gap-2 text-xs text-gray-600">
                        <input type="checkbox" className="rounded border-gray-300 text-indigo-600"
                            checked={data.show_balance} onChange={(e) => setData('show_balance', e.target.checked)} />
                        Mostrar balance en $ (si no, solo %)
                    </label>

                    <div className="grid grid-cols-2 gap-2">
                        <select className="rounded-md border-gray-300 text-sm shadow-sm"
                            value={data.pricing_model} onChange={(e) => setData('pricing_model', e.target.value)}>
                            <option value="subscription">Suscripción mensual</option>
                            <option value="profit_share">% de ganancias</option>
                        </select>
                        {data.pricing_model === 'subscription' ? (
                            <input type="number" step="1" min="0" placeholder="$ / mes"
                                className="rounded-md border-gray-300 text-sm shadow-sm"
                                value={data.subscription_price} onChange={(e) => setData('subscription_price', e.target.value)} />
                        ) : (
                            <input type="number" step="1" min="0" max="100" placeholder="% ganancias"
                                className="rounded-md border-gray-300 text-sm shadow-sm"
                                value={data.profit_share_pct} onChange={(e) => setData('profit_share_pct', e.target.value)} />
                        )}
                    </div>

                    <div className="flex justify-end">
                        <PrimaryButton disabled={processing}>Guardar</PrimaryButton>
                    </div>
                </form>
            )}
        </div>
    );
}

export default function Index({ accounts, webhookModuleActive, webhookModuleFee }) {
    const flash = usePage().props.flash ?? {};
    const { errors } = usePage().props;

    const toggleWebhookModule = () => {
        if (webhookModuleActive) {
            if (confirm('¿Desactivar el módulo webhook? Tus webhooks dejarán de funcionar.')) {
                router.delete(route('billing.webhook-module.disable'), { preserveScroll: true });
            }
        } else if (confirm(`Activar el módulo webhook cuesta $${webhookModuleFee}/mes. ¿Continuar?`)) {
            router.post(route('billing.webhook-module.enable'), {}, { preserveScroll: true });
        }
    };

    const toggle = (account) => {
        router.patch(route('broker-accounts.toggle', account.id), {}, { preserveScroll: true });
    };

    const destroy = (account) => {
        if (confirm(`¿Desconectar la cuenta "${account.name}"? Se eliminará de MetaApi.`)) {
            router.delete(route('broker-accounts.destroy', account.id), { preserveScroll: true });
        }
    };

    const webhookUrl = (account) =>
        `${window.location.origin}/api/webhook/${account.webhook_token}`;

    const copyWebhook = (account) => {
        navigator.clipboard?.writeText(webhookUrl(account));
    };

    const regenerateWebhook = (account) => {
        if (confirm('¿Regenerar el token? La URL actual del webhook dejará de funcionar.')) {
            router.patch(route('broker-accounts.regenerate-webhook', account.id), {}, { preserveScroll: true });
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Cuentas de broker
                    </h2>
                    <Link href={route('broker-accounts.create')} className="sm:w-auto">
                        <PrimaryButton className="w-full justify-center sm:w-auto">
                            Conectar cuenta
                        </PrimaryButton>
                    </Link>
                </div>
            }
        >
            <Head title="Cuentas de broker" />

            <div className="py-6 sm:py-12">
                <div className="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800">
                            {flash.success}
                        </div>
                    )}
                    {flash.error && (
                        <div className="rounded-md bg-red-50 p-4 text-sm text-red-800">
                            {flash.error}
                        </div>
                    )}
                    {errors?.balance && (
                        <div className="rounded-md bg-red-50 p-4 text-sm text-red-800">
                            {errors.balance}
                        </div>
                    )}

                    <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white p-4 shadow-sm">
                        <div className="text-sm">
                            <span className="font-medium text-gray-800">Módulo Webhook</span>{' '}
                            <span className="text-gray-500">(recibir señales de TradingView/Python · ${webhookModuleFee}/mes)</span>
                            <div className="text-xs">
                                Estado:{' '}
                                {webhookModuleActive
                                    ? <span className="font-semibold text-green-600">activo</span>
                                    : <span className="font-semibold text-gray-400">inactivo</span>}
                            </div>
                        </div>
                        <button
                            onClick={toggleWebhookModule}
                            className={
                                'rounded-md px-3 py-1.5 text-sm font-medium ' +
                                (webhookModuleActive
                                    ? 'text-red-600 hover:text-red-800'
                                    : 'bg-indigo-600 text-white hover:bg-indigo-700')
                            }
                        >
                            {webhookModuleActive ? 'Desactivar' : 'Activar módulo'}
                        </button>
                    </div>

                    {accounts.length === 0 ? (
                        <div className="rounded-lg bg-white p-6 text-gray-600 shadow-sm">
                            Aún no has conectado ninguna cuenta. Conecta tu broker
                            para que el sistema pueda operar automáticamente.
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {accounts.map((account) => (
                                <div
                                    key={account.id}
                                    className="rounded-lg bg-white p-4 shadow-sm sm:p-6"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <div className="font-medium text-gray-900">
                                                {account.name}
                                            </div>
                                            <div className="text-sm text-gray-500">
                                                {account.platform.toUpperCase()} · {account.login} · {account.server}
                                            </div>
                                        </div>
                                        <span
                                            className={
                                                'inline-flex rounded-full px-3 py-1 text-xs font-semibold ' +
                                                (STATE_STYLES[account.provision_state] ?? STATE_STYLES.pending)
                                            }
                                        >
                                            {account.provision_state}
                                        </span>
                                    </div>

                                    {account.last_error && (
                                        <div className="mt-3 break-words rounded bg-red-50 p-2 text-xs text-red-700">
                                            {account.last_error}
                                        </div>
                                    )}

                                    {account.webhook_token && (
                                        <div className="mt-3 rounded-md border border-gray-200 bg-gray-50 p-3">
                                            <div className="text-xs font-medium text-gray-700">
                                                Webhook (TradingView / MT5 / Python)
                                            </div>
                                            <div className="mt-1 flex flex-wrap items-center gap-2">
                                                <code className="min-w-0 flex-1 truncate rounded bg-white px-2 py-1 text-xs text-gray-600 ring-1 ring-gray-200">
                                                    {webhookUrl(account)}
                                                </code>
                                                <button
                                                    onClick={() => copyWebhook(account)}
                                                    className="text-xs font-medium text-indigo-600 hover:text-indigo-900"
                                                >
                                                    Copiar
                                                </button>
                                                <button
                                                    onClick={() => regenerateWebhook(account)}
                                                    className="text-xs font-medium text-gray-500 hover:text-gray-800"
                                                >
                                                    Regenerar
                                                </button>
                                            </div>
                                            <p className="mt-1 text-xs text-gray-500">
                                                Envía un POST con JSON:{' '}
                                                <code className="text-gray-600">{'{ "action": "buy", "symbol": "EURUSD", "volume": 0.1 }'}</code>
                                            </p>
                                        </div>
                                    )}

                                    <PublishForm account={account} />

                                    <div className="mt-4 flex items-center justify-end gap-4 border-t border-gray-100 pt-3 text-sm">
                                        <button
                                            onClick={() => toggle(account)}
                                            className="text-indigo-600 hover:text-indigo-900"
                                        >
                                            {account.is_enabled ? 'Pausar' : 'Reanudar'}
                                        </button>
                                        <Link
                                            href={route('broker-accounts.copy-trade.index', account.id)}
                                            className="text-indigo-600 hover:text-indigo-900"
                                        >
                                            Copy Trading
                                        </Link>
                                        <button
                                            onClick={() => destroy(account)}
                                            className="text-red-600 hover:text-red-900"
                                        >
                                            Desconectar
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
