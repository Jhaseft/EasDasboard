import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

function StatCard({ href, label, value, sub, icon, gradient, glow }) {
    return (
        <Link
            href={href}
            className={`group relative overflow-hidden rounded-2xl bg-gradient-to-br ${gradient} p-6 text-white shadow-lg ${glow} transition duration-300 hover:-translate-y-1`}
        >
            {/* brillos decorativos */}
            <div className="pointer-events-none absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/20 blur-2xl" />
            <div className="pointer-events-none absolute -bottom-10 -left-6 h-28 w-28 rounded-full bg-black/10 blur-2xl" />
            <div className="relative flex items-start justify-between">
                <div>
                    <div className="text-sm font-medium text-white/80">
                        {label}
                    </div>
                    <div className="mt-2 text-4xl font-extrabold tracking-tight">
                        {value}
                    </div>
                    <div className="mt-1.5 text-xs font-medium text-white/70">
                        {sub}
                    </div>
                </div>
                <span className="flex size-12 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm transition group-hover:scale-110 group-hover:rotate-3">
                    {icon}
                </span>
            </div>
        </Link>
    );
}

function QuickAction({ href, title, desc, icon, tint }) {
    return (
        <Link
            href={href}
            className="group relative flex items-center gap-4 overflow-hidden rounded-xl border border-gray-100 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
        >
            <span
                className={`flex size-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br ${tint} text-white shadow-sm transition group-hover:scale-110`}
            >
                {icon}
            </span>
            <div className="min-w-0">
                <div className="text-sm font-semibold text-gray-800">
                    {title}
                </div>
                <div className="truncate text-xs text-gray-500">{desc}</div>
            </div>
            <svg
                className="ml-auto size-5 shrink-0 text-gray-300 transition group-hover:translate-x-1 group-hover:text-gray-500"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
            >
                <path d="M9 6l6 6-6 6" />
            </svg>
        </Link>
    );
}

const svg = (path) => (
    <svg
        className="size-6"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
        strokeLinejoin="round"
    >
        {path}
    </svg>
);

export default function Dashboard({ stats }) {
    const user = usePage().props.auth.user;
    const brokers = stats?.brokers ?? 0;
    const brokersActive = stats?.brokers_active ?? 0;
    const slaves = stats?.slaves ?? 0;
    const slavesAuto = stats?.slaves_auto ?? 0;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="bg-slate-50 py-6">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {/* Bienvenida */}
                    <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-emerald-950 p-6 text-white shadow-xl sm:p-8">
                        <div className="pointer-events-none absolute -right-12 -top-12 h-56 w-56 rounded-full bg-emerald-500/30 blur-3xl" />
                        <div className="pointer-events-none absolute bottom-0 right-1/3 h-40 w-40 rounded-full bg-cyan-500/20 blur-3xl" />
                        <div
                            className="pointer-events-none absolute inset-0 opacity-[0.12]"
                            style={{
                                backgroundImage:
                                    'linear-gradient(rgba(255,255,255,0.4) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.4) 1px, transparent 1px)',
                                backgroundSize: '36px 36px',
                                maskImage:
                                    'radial-gradient(ellipse 70% 80% at 80% 0%, #000 30%, transparent 100%)',
                                WebkitMaskImage:
                                    'radial-gradient(ellipse 70% 80% at 80% 0%, #000 30%, transparent 100%)',
                            }}
                        />
                        <div className="relative">
                            <p className="text-sm text-emerald-300">
                                Hola, {user.name} 👋
                            </p>
                            <h3 className="mt-1 text-2xl font-bold tracking-tight sm:text-3xl">
                                Bienvenido a tu panel de{' '}
                                <span className="bg-gradient-to-r from-emerald-300 to-cyan-300 bg-clip-text text-transparent">
                                    copy-trading
                                </span>
                            </h3>
                            <p className="mt-2 max-w-xl text-sm text-slate-300">
                                Conecta tus cuentas y deja que cada operación se
                                replique automáticamente en tus cuentas esclavas.
                            </p>
                            <div className="mt-5 flex flex-wrap gap-3">
                                <Link
                                    href={route('broker-accounts.index')}
                                    className="rounded-lg bg-gradient-to-r from-emerald-400 to-cyan-400 px-4 py-2 text-sm font-semibold text-slate-900 shadow-lg shadow-emerald-500/25 transition hover:shadow-emerald-400/40"
                                >
                                    Conectar cuenta
                                </Link>
                                <Link
                                    href={route('marketplace.index')}
                                    className="rounded-lg border border-white/20 bg-white/5 px-4 py-2 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-white/10"
                                >
                                    Explorar marketplace
                                </Link>
                            </div>
                        </div>
                    </div>

                    {/* Métricas */}
                    <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <StatCard
                            href={route('broker-accounts.index')}
                            label="Cuentas conectadas"
                            value={brokers}
                            sub={`${brokersActive} activa(s)`}
                            gradient="from-indigo-500 to-violet-600"
                            glow="shadow-indigo-500/30"
                            icon={svg(
                                <>
                                    <rect x="2.5" y="5" width="19" height="14" rx="2" />
                                    <path d="M2.5 9.5h19M6.5 14.5h4" />
                                </>,
                            )}
                        />
                        <StatCard
                            href={route('slave-accounts.index')}
                            label="Cuentas esclavas"
                            value={slaves}
                            sub={`${slavesAuto} con copia automática`}
                            gradient="from-emerald-500 to-teal-600"
                            glow="shadow-emerald-500/30"
                            icon={svg(
                                <>
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                                </>,
                            )}
                        />
                    </div>

                    {/* Accesos rápidos */}
                    <div>
                        <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-400">
                            Accesos rápidos
                        </h3>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <QuickAction
                                href={route('broker-accounts.index')}
                                title="Cuentas"
                                desc="Conecta y administra tus brókers"
                                tint="from-indigo-500 to-violet-600"
                                icon={svg(
                                    <>
                                        <rect x="2.5" y="5" width="19" height="14" rx="2" />
                                        <path d="M2.5 9.5h19" />
                                    </>,
                                )}
                            />
                            <QuickAction
                                href={route('slave-accounts.index')}
                                title="Esclavas"
                                desc="Destinos de copia automática"
                                tint="from-emerald-500 to-teal-600"
                                icon={svg(
                                    <>
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                                        <circle cx="9" cy="7" r="4" />
                                    </>,
                                )}
                            />
                            <QuickAction
                                href={route('marketplace.index')}
                                title="Marketplace"
                                desc="Suscríbete a traders maestros"
                                tint="from-amber-500 to-orange-600"
                                icon={svg(
                                    <path d="M3 9l1.5-4h15L21 9M3 9v10a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V9M3 9h18" />,
                                )}
                            />
                            <QuickAction
                                href={route('wallet.index')}
                                title="Billetera"
                                desc="Recarga con QR o USDT"
                                tint="from-cyan-500 to-blue-600"
                                icon={svg(
                                    <>
                                        <path d="M3 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2H5a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9" />
                                        <path d="M16 13h.01" />
                                    </>,
                                )}
                            />
                            <QuickAction
                                href={route('profile.edit')}
                                title="Perfil"
                                desc="Configura tu cuenta"
                                tint="from-slate-600 to-slate-800"
                                icon={svg(
                                    <>
                                        <circle cx="12" cy="8" r="4" />
                                        <path d="M4 21a8 8 0 0 1 16 0" />
                                    </>,
                                )}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
