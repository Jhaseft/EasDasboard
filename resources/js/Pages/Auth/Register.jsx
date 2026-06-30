import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <>
            <Head title="Crear cuenta" />

            <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#070b14] px-6 py-12 text-slate-200 antialiased selection:bg-emerald-500/30 selection:text-white">
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

                <div className="relative w-full max-w-md">
                    {/* Logo */}
                    <Link
                        href="/"
                        className="mx-auto mb-8 flex w-fit items-center gap-2.5"
                    >
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
                    </Link>

                    {/* Tarjeta */}
                    <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-8 backdrop-blur-sm">
                        <h1 className="text-2xl font-bold tracking-tight text-white">
                            Crea tu cuenta
                        </h1>
                        <p className="mt-1.5 text-sm text-slate-400">
                            Empieza a replicar a los mejores traders en minutos.
                        </p>

                        <form onSubmit={submit} className="mt-7 space-y-5">
                            <div>
                                <label
                                    htmlFor="name"
                                    className="block text-sm font-medium text-slate-300"
                                >
                                    Nombre
                                </label>
                                <input
                                    id="name"
                                    name="name"
                                    value={data.name}
                                    autoComplete="name"
                                    autoFocus
                                    required
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    className="mt-1.5 block w-full rounded-lg border border-white/10 bg-white/5 px-3.5 py-2.5 text-sm text-white placeholder-slate-500 transition focus:border-emerald-400/50 focus:outline-none focus:ring-2 focus:ring-emerald-400/30"
                                    placeholder="Tu nombre"
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2 text-rose-400"
                                />
                            </div>

                            <div>
                                <label
                                    htmlFor="email"
                                    className="block text-sm font-medium text-slate-300"
                                >
                                    Email
                                </label>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    autoComplete="username"
                                    required
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                    className="mt-1.5 block w-full rounded-lg border border-white/10 bg-white/5 px-3.5 py-2.5 text-sm text-white placeholder-slate-500 transition focus:border-emerald-400/50 focus:outline-none focus:ring-2 focus:ring-emerald-400/30"
                                    placeholder="tu@correo.com"
                                />
                                <InputError
                                    message={errors.email}
                                    className="mt-2 text-rose-400"
                                />
                            </div>

                            <div>
                                <label
                                    htmlFor="password"
                                    className="block text-sm font-medium text-slate-300"
                                >
                                    Contraseña
                                </label>
                                <input
                                    id="password"
                                    type="password"
                                    name="password"
                                    value={data.password}
                                    autoComplete="new-password"
                                    required
                                    onChange={(e) =>
                                        setData('password', e.target.value)
                                    }
                                    className="mt-1.5 block w-full rounded-lg border border-white/10 bg-white/5 px-3.5 py-2.5 text-sm text-white placeholder-slate-500 transition focus:border-emerald-400/50 focus:outline-none focus:ring-2 focus:ring-emerald-400/30"
                                    placeholder="••••••••"
                                />
                                <InputError
                                    message={errors.password}
                                    className="mt-2 text-rose-400"
                                />
                            </div>

                            <div>
                                <label
                                    htmlFor="password_confirmation"
                                    className="block text-sm font-medium text-slate-300"
                                >
                                    Confirmar contraseña
                                </label>
                                <input
                                    id="password_confirmation"
                                    type="password"
                                    name="password_confirmation"
                                    value={data.password_confirmation}
                                    autoComplete="new-password"
                                    required
                                    onChange={(e) =>
                                        setData(
                                            'password_confirmation',
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1.5 block w-full rounded-lg border border-white/10 bg-white/5 px-3.5 py-2.5 text-sm text-white placeholder-slate-500 transition focus:border-emerald-400/50 focus:outline-none focus:ring-2 focus:ring-emerald-400/30"
                                    placeholder="••••••••"
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                    className="mt-2 text-rose-400"
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="group inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-emerald-400 to-cyan-500 px-7 py-3 text-base font-semibold text-[#070b14] shadow-xl shadow-emerald-500/30 transition hover:shadow-emerald-400/40 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                Crear cuenta
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
                            </button>
                        </form>
                    </div>

                    <p className="mt-6 text-center text-sm text-slate-400">
                        ¿Ya tienes cuenta?{' '}
                        <Link
                            href={route('login')}
                            className="font-semibold text-emerald-400 transition hover:text-emerald-300"
                        >
                            Iniciar sesión
                        </Link>
                    </p>
                </div>
            </div>
        </>
    );
}
