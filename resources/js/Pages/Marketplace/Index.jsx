import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

function priceLabel(m) {
    if (m.pricing_model === 'profit_share') {
        return `${m.profit_share_pct}% de ganancias`;
    }
    return m.subscription_price > 0 ? `$${m.subscription_price}/mes` : 'Gratis';
}

export default function Index({ masters, mySubs, balance }) {
    const flash = usePage().props.flash ?? {};

    const cancel = (sub) => {
        if (confirm('¿Cancelar la suscripción? Dejarás de copiar nuevas operaciones.')) {
            router.delete(route('marketplace.unsubscribe', sub.id), { preserveScroll: true });
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Marketplace</h2>
                    <span className="text-sm text-gray-500">
                        Saldo: <span className="font-semibold text-gray-800">${Number(balance).toFixed(2)}</span>
                    </span>
                </div>
            }
        >
            <Head title="Marketplace" />

            <div className="py-6 sm:py-12">
                <div className="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800">{flash.success}</div>
                    )}

                    {mySubs.length > 0 && (
                        <div className="rounded-lg bg-white p-4 shadow-sm sm:p-6">
                            <div className="mb-3 text-sm font-medium text-gray-700">Mis suscripciones</div>
                            <div className="divide-y divide-gray-100">
                                {mySubs.map((s) => (
                                    <div key={s.id} className="flex items-center justify-between py-2 text-sm">
                                        <div>
                                            <div className="font-medium text-gray-800">{s.master}</div>
                                            <div className="text-xs text-gray-500">
                                                {s.pricing_model === 'profit_share' ? 'Profit share' : `$${s.amount}/mes`} ·{' '}
                                                <span className={s.status === 'active' ? 'text-green-600' : s.status === 'past_due' ? 'text-red-600' : 'text-gray-400'}>
                                                    {s.status}
                                                </span>
                                            </div>
                                        </div>
                                        {s.status !== 'cancelled' && (
                                            <button onClick={() => cancel(s)} className="text-red-600 hover:text-red-900">
                                                Cancelar
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {masters.length === 0 ? (
                        <div className="rounded-lg bg-white p-6 text-gray-600 shadow-sm">
                            Todavía no hay cuentas públicas en el marketplace.
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {masters.map((m) => (
                                <Link
                                    key={m.id}
                                    href={route('marketplace.show', m.id)}
                                    className="rounded-lg bg-white p-5 shadow-sm transition hover:shadow-md"
                                >
                                    <div className="flex items-start justify-between">
                                        <div className="font-medium text-gray-900">{m.name}</div>
                                        <span className="rounded-full bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700">
                                            {priceLabel(m)}
                                        </span>
                                    </div>
                                    {m.description && (
                                        <p className="mt-2 line-clamp-2 text-sm text-gray-500">{m.description}</p>
                                    )}
                                    <div className="mt-3 text-xs text-gray-400">
                                        {m.platform?.toUpperCase()} · {m.followers_count} seguidor(es)
                                    </div>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
