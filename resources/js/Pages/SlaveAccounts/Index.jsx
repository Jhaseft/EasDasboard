import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

const STATE_STYLES = {
    deployed:   'bg-green-100 text-green-800',
    deploying:  'bg-yellow-100 text-yellow-800',
    validating: 'bg-yellow-100 text-yellow-800',
    pending:    'bg-gray-100 text-gray-600',
    error:      'bg-red-100 text-red-800',
};

export default function Index({ slaves }) {
    const flash = usePage().props.flash ?? {};

    const toggle = (slave) => {
        router.patch(route('slave-accounts.toggle', slave.id), {}, { preserveScroll: true });
    };

    const destroy = (slave) => {
        if (confirm(`¿Desconectar la cuenta esclava "${slave.name}"? Se eliminará de MetaApi.`)) {
            router.delete(route('slave-accounts.destroy', slave.id), { preserveScroll: true });
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Cuentas esclavas
                    </h2>
                    <Link href={route('slave-accounts.create')}>
                        <PrimaryButton className="w-full justify-center sm:w-auto">
                            Conectar esclava
                        </PrimaryButton>
                    </Link>
                </div>
            }
        >
            <Head title="Cuentas esclavas" />

            <div className="py-6 sm:py-12">
                <div className="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800">
                            {flash.success}
                        </div>
                    )}

                    {slaves.length === 0 ? (
                        <div className="rounded-lg bg-white p-6 text-gray-600 shadow-sm">
                            Aún no has conectado ninguna cuenta esclava.{' '}
                            <Link href={route('slave-accounts.create')} className="text-indigo-600 underline">
                                Conecta una ahora
                            </Link>
                            .
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {slaves.map((slave) => (
                                <div key={slave.id} className="rounded-lg bg-white p-4 shadow-sm sm:p-6">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <div className="font-medium text-gray-900">
                                                {slave.name}
                                            </div>
                                            <div className="text-sm text-gray-500">
                                                {slave.platform.toUpperCase()} · {slave.login} · {slave.server}
                                            </div>
                                            <div className="mt-1 text-xs text-gray-400">
                                                Maestra: <span className="font-medium text-gray-600">{slave.master?.name ?? '—'}</span>
                                                {' · '}
                                                Multiplicador: <span className="font-medium text-gray-600">{slave.lot_multiplier}×</span>
                                            </div>
                                        </div>
                                        <span
                                            className={
                                                'inline-flex rounded-full px-3 py-1 text-xs font-semibold ' +
                                                (STATE_STYLES[slave.provision_state] ?? STATE_STYLES.pending)
                                            }
                                        >
                                            {slave.provision_state}
                                        </span>
                                    </div>

                                    {slave.last_error && (
                                        <div className="mt-3 break-words rounded bg-red-50 p-2 text-xs text-red-700">
                                            {slave.last_error}
                                        </div>
                                    )}

                                    <div className="mt-4 flex items-center justify-end gap-4 border-t border-gray-100 pt-3 text-sm">
                                        <button
                                            onClick={() => toggle(slave)}
                                            className="text-indigo-600 hover:text-indigo-900"
                                        >
                                            {slave.is_enabled ? 'Pausar' : 'Reanudar'}
                                        </button>
                                        <button
                                            onClick={() => destroy(slave)}
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
