import { Head, Link } from '@inertiajs/react';

function FeatureCard({ icon, title, children }) {
    return (
        <div className="group relative overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03] p-6 backdrop-blur-sm transition duration-300 hover:-translate-y-1 hover:border-emerald-400/40 hover:bg-white/[0.06]">
            <div className="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-emerald-500/10 blur-2xl transition group-hover:bg-emerald-400/20" />
            <div className="relative flex size-12 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400/20 to-cyan-400/10 text-emerald-300 ring-1 ring-emerald-400/30">
                {icon}
            </div>
            <h3 className="relative mt-5 text-lg font-semibold text-white">
                {title}
            </h3>
            <p className="relative mt-2 text-sm leading-relaxed text-slate-400">
                {children}
            </p>
        </div>
    );
}

function Stat({ value, label, accent = 'text-emerald-400' }) {
    return (
        <div className="text-center">
            <div className={`text-3xl font-bold sm:text-4xl ${accent}`}>
                {value}
            </div>
            <div className="mt-1 text-xs uppercase tracking-wider text-slate-500">
                {label}
            </div>
        </div>
    );
}

export default function Welcome({ auth }) {
    const isAuth = !!auth?.user;

    return (
        <>
            <Head title="CopyTrade — Replica operaciones en automático" />

            <div className="relative min-h-screen overflow-hidden bg-[#070b14] text-slate-200 antialiased selection:bg-emerald-500/30 selection:text-white">
                {/* Fondo: brillos y rejilla */}
                <div className="pointer-events-none absolute inset-0">
                    <div className="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-emerald-600/20 blur-[120px]" />
                    <div className="absolute right-0 top-1/3 h-96 w-96 rounded-full bg-cyan-500/10 blur-[120px]" />
                    <div className="absolute bottom-0 left-1/3 h-80 w-80 rounded-full bg-emerald-500/10 blur-[120px]" />
                    <div
                        className="absolute inset-0 opacity-[0.15]"
                        style={{
                            backgroundImage:
                                'linear-gradient(rgba(148,163,184,0.25) 1px, transparent 1px), linear-gradient(90deg, rgba(148,163,184,0.25) 1px, transparent 1px)',
                            backgroundSize: '48px 48px',
                            maskImage:
                                'radial-gradient(ellipse 80% 60% at 50% 0%, #000 40%, transparent 100%)',
                            WebkitMaskImage:
                                'radial-gradient(ellipse 80% 60% at 50% 0%, #000 40%, transparent 100%)',
                        }}
                    />
                </div>

                <div className="relative mx-auto flex min-h-screen max-w-7xl flex-col px-6 lg:px-8">
                    {/* Navbar */}
                    <header className="flex items-center justify-between py-6">
                        <div className="flex items-center gap-2.5">
                            <div className="flex size-9 items-center justify-center rounded-lg bg-gradient-to-br from-emerald-400 to-cyan-500 text-[#070b14] shadow-lg shadow-emerald-500/30">
                                <svg
                                    className="size-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2.5"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                >
                                    <path d="M3 17l5-5 4 4 7-8" />
                                    <path d="M16 8h3v3" />
                                </svg>
                            </div>
                            <span className="text-lg font-bold tracking-tight text-white">
                                Copy<span className="text-emerald-400">Trade</span>
                            </span>
                        </div>

                        <nav className="flex items-center gap-2">
                            {isAuth ? (
                                <Link
                                    href={route('dashboard')}
                                    className="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-[#070b14] shadow-lg shadow-emerald-500/25 transition hover:bg-emerald-400"
                                >
                                    Ir al Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        className="rounded-lg px-4 py-2 text-sm font-medium text-slate-300 transition hover:text-white"
                                    >
                                        Iniciar sesión
                                    </Link>
                                    <Link
                                        href={route('register')}
                                        className="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-[#070b14] shadow-lg shadow-emerald-500/25 transition hover:bg-emerald-400"
                                    >
                                        Crear cuenta
                                    </Link>
                                </>
                            )}
                        </nav>
                    </header>

                    {/* Hero */}
                    <main className="flex flex-1 flex-col items-center justify-center py-16 text-center">
                        <span className="inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-500/10 px-4 py-1.5 text-xs font-medium text-emerald-300">
                            <span className="relative flex size-2">
                                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                <span className="relative inline-flex size-2 rounded-full bg-emerald-400" />
                            </span>
                            Copia de operaciones en tiempo real
                        </span>

                        <h1 className="mt-7 max-w-4xl text-balance text-4xl font-extrabold leading-[1.1] tracking-tight text-white sm:text-6xl">
                            Replica las operaciones de los{' '}
                            <span className="bg-gradient-to-r from-emerald-300 via-emerald-400 to-cyan-400 bg-clip-text text-transparent">
                                mejores traders
                            </span>{' '}
                            de forma automática
                        </h1>

                        <p className="mt-6 max-w-2xl text-pretty text-lg leading-relaxed text-slate-400">
                            Conecta tus cuentas de MetaTrader, suscríbete a señales
                            del marketplace y deja que cada apertura y cierre se
                            replique en tus cuentas esclavas. Sin estar pegado a la
                            pantalla.
                        </p>

                        <div className="mt-9 flex flex-col items-center gap-3 sm:flex-row">
                            <Link
                                href={isAuth ? route('dashboard') : route('register')}
                                className="group inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-400 to-cyan-500 px-7 py-3.5 text-base font-semibold text-[#070b14] shadow-xl shadow-emerald-500/30 transition hover:shadow-emerald-400/40"
                            >
                                {isAuth ? 'Ir a mi panel' : 'Comenzar gratis'}
                                <svg
                                    className="size-5 transition group-hover:translate-x-0.5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2.5"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                >
                                    <path d="M5 12h14M13 6l6 6-6 6" />
                                </svg>
                            </Link>
                            {!isAuth && (
                                <Link
                                    href={route('login')}
                                    className="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/5 px-7 py-3.5 text-base font-semibold text-white transition hover:bg-white/10"
                                >
                                    Ya tengo cuenta
                                </Link>
                            )}
                        </div>

                        {/* Métricas */}
                        <div className="mt-16 grid w-full max-w-2xl grid-cols-3 gap-8 border-t border-white/10 pt-10">
                            <Stat value="24/7" label="Monitoreo" />
                            <Stat
                                value="<1s"
                                label="Latencia de copia"
                                accent="text-cyan-400"
                            />
                            <Stat value="MT4/MT5" label="Compatibilidad" />
                        </div>
                    </main>

                    {/* Features */}
                    <section className="grid gap-5 pb-16 sm:grid-cols-2 lg:grid-cols-4">
                        <FeatureCard
                            title="Copia automática"
                            icon={
                                <svg
                                    className="size-6"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="1.8"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                >
                                    <path d="M4 4v6h6M20 20v-6h-6" />
                                    <path d="M20 9a8 8 0 0 0-14-3M4 15a8 8 0 0 0 14 3" />
                                </svg>
                            }
                        >
                            Cada apertura y cierre del trader maestro se replica al
                            instante en tus cuentas esclavas.
                        </FeatureCard>

                        <FeatureCard
                            title="Multicuenta MT4/MT5"
                            icon={
                                <svg
                                    className="size-6"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="1.8"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                >
                                    <rect x="3" y="4" width="18" height="12" rx="2" />
                                    <path d="M8 20h8M12 16v4" />
                                </svg>
                            }
                        >
                            Conecta varias cuentas de bróker y gestiónalas todas
                            desde un único panel centralizado.
                        </FeatureCard>

                        <FeatureCard
                            title="Marketplace de señales"
                            icon={
                                <svg
                                    className="size-6"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="1.8"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                >
                                    <path d="M3 9l1.5-4h15L21 9M3 9v10a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V9M3 9h18M9 13h6" />
                                </svg>
                            }
                        >
                            Suscríbete a los traders maestros con mejor desempeño y
                            empieza a copiar en minutos.
                        </FeatureCard>

                        <FeatureCard
                            title="Billetera integrada"
                            icon={
                                <svg
                                    className="size-6"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="1.8"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                >
                                    <path d="M3 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v0H5a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9" />
                                    <path d="M16 13h.01" />
                                </svg>
                            }
                        >
                            Recarga con QR o USDT y administra tu saldo sin salir de
                            la plataforma.
                        </FeatureCard>
                    </section>

                    {/* Footer */}
                    <footer className="border-t border-white/10 py-8 text-center text-sm text-slate-500">
                        © {new Date().getFullYear()} CopyTrade · Plataforma de
                        copy-trading
                    </footer>
                </div>
            </div>
        </>
    );
}
