import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

const STATE_STYLES = {
    deployed: 'bg-green-100 text-green-800',
    deploying: 'bg-yellow-100 text-yellow-800',
    validating: 'bg-yellow-100 text-yellow-800',
    pending: 'bg-gray-100 text-gray-600',
    error: 'bg-red-100 text-red-800',
};

export default function Index({ accounts }) {
    const flash = usePage().props.flash ?? {};

    const toggle = (account) => {
        router.patch(route('broker-accounts.toggle', account.id), {}, { preserveScroll: true });
    };

    const destroy = (account) => {
        if (confirm(`¿Desconectar la cuenta "${account.name}"? Se eliminará de MetaApi.`)) {
            router.delete(route('broker-accounts.destroy', account.id), { preserveScroll: true });
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

                                    <div className="mt-4 flex items-center justify-end gap-4 border-t border-gray-100 pt-3 text-sm">
                                        <button
                                            onClick={() => toggle(account)}
                                            className="text-indigo-600 hover:text-indigo-900"
                                        >
                                            {account.is_enabled ? 'Pausar' : 'Reanudar'}
                                        </button>
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
