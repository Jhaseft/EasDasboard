import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Dashboard({ stats }) {
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

            <div className="py-6 sm:py-12">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <Link
                            href={route('broker-accounts.index')}
                            className="rounded-lg bg-white p-6 shadow-sm transition hover:shadow-md"
                        >
                            <div className="text-sm font-medium text-gray-500">
                                Cuentas conectadas
                            </div>
                            <div className="mt-2 text-3xl font-bold text-gray-900">
                                {brokers}
                            </div>
                            <div className="mt-1 text-xs text-gray-500">
                                {brokersActive} activa(s)
                            </div>
                        </Link>
                        <Link
                            href={route('slave-accounts.index')}
                            className="rounded-lg bg-white p-6 shadow-sm transition hover:shadow-md"
                        >
                            <div className="text-sm font-medium text-gray-500">
                                Cuentas esclavas
                            </div>
                            <div className="mt-2 text-3xl font-bold text-green-600">
                                {slaves}
                            </div>
                            <div className="mt-1 text-xs text-gray-500">
                                {slavesAuto} con copia automática
                            </div>
                        </Link>
                    </div>

                    <div className="rounded-lg bg-white p-6 text-gray-700 shadow-sm">
                        Conecta tus cuentas en{' '}
                        <Link
                            href={route('broker-accounts.index')}
                            className="font-medium text-indigo-600 hover:text-indigo-900"
                        >
                            Cuentas
                        </Link>
                        . Las operaciones se replican a tus{' '}
                        <Link
                            href={route('slave-accounts.index')}
                            className="font-medium text-indigo-600 hover:text-indigo-900"
                        >
                            cuentas esclavas
                        </Link>{' '}
                        automáticamente, ya sea por lectura de tu MetaTrader o por
                        señales recibidas vía webhook.
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
