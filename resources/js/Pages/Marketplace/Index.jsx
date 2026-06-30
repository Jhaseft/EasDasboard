import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

function priceLabel(m) {
    if (m.pricing_model === 'profit_share') {
        return `${m.profit_share_pct}% de ganancias`;
    }
    return m.subscription_price > 0 ? `$${m.subscription_price}/mes` : 'Gratis';
}

function isFree(m) {
    return m.pricing_model !== 'profit_share' && !(m.subscription_price > 0);
}

function MasterCard({ master: m }) {
    return (
        <Link
            href={route('marketplace.show', m.id)}
            className="group flex flex-col rounded-xl border border-gray-100 bg-white p-5 shadow-sm transition hover:border-indigo-200 hover:shadow-md"
        >
            <div className="flex items-start gap-3">
                <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-500 text-base font-semibold text-white">
                    {m.name?.charAt(0).toUpperCase() ?? '?'}
                </div>
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                        <span className="truncate font-semibold text-gray-900">{m.name}</span>
                        {m.is_own && (
                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">
                                Tuya
                            </span>
                        )}
                    </div>
                    {m.platform && (
                        <div className="mt-0.5 text-xs font-medium uppercase tracking-wide text-gray-400">
                            {m.platform}
                        </div>
                    )}
                </div>
                <span
                    className={
                        'shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ' +
                        (isFree(m) ? 'bg-green-50 text-green-700' : 'bg-indigo-50 text-indigo-700')
                    }
                >
                    {priceLabel(m)}
                </span>
            </div>

            {m.description ? (
                <p className="mt-3 line-clamp-2 text-sm text-gray-500">{m.description}</p>
            ) : (
                <p className="mt-3 text-sm italic text-gray-300">Sin descripción</p>
            )}

            <div className="mt-4 flex items-center justify-between border-t border-gray-100 pt-3">
                <div className="flex items-center gap-1.5 text-xs text-gray-500">
                    <svg className="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" strokeWidth={1.7} stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                    </svg>
                    {m.followers_count} {m.followers_count === 1 ? 'seguidor' : 'seguidores'}
                </div>
                <span className="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 transition-all group-hover:gap-2">
                    Ver detalles
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </span>
            </div>
        </Link>
    );
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

            <div className="py-4">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800">{flash.success}</div>
                    )}

                    {mySubs.length > 0 && (
                        <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm sm:p-6">
                            <div className="mb-3 text-sm font-medium text-gray-700">Mis suscripciones</div>
                            <div className="divide-y divide-gray-100">
                                {mySubs.map((s) => (
                                    <div key={s.id} className="flex items-center justify-between py-2.5 text-sm">
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
                                            <button onClick={() => cancel(s)} className="text-sm font-medium text-red-600 hover:text-red-700">
                                                Cancelar
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    <div>
                        <h3 className="mb-3 text-sm font-medium text-gray-700">Cuentas disponibles</h3>
                        {masters.length === 0 ? (
                            <div className="rounded-xl border border-dashed border-gray-200 bg-white p-10 text-center">
                                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-50">
                                    <svg className="h-6 w-6 text-gray-300" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z" />
                                    </svg>
                                </div>
                                <p className="mt-3 text-sm text-gray-500">
                                    Todavía no hay cuentas públicas en el marketplace.
                                </p>
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
                                {masters.map((m) => (
                                    <MasterCard key={m.id} master={m} />
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
