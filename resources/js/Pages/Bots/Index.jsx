import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

const REPORT_STYLES = {
    opened:          'bg-green-100 text-green-800',
    rejected_limits: 'bg-red-100 text-red-800',
    failed:          'bg-orange-100 text-orange-800',
};

const REPORT_LABELS = {
    opened:          'Abierta',
    rejected_limits: 'Fuera de límites',
    failed:          'Error',
};

export default function Index({ bots, reports = [] }) {
    const flash = usePage().props.flash ?? {};

    const toggle = (bot) => {
        router.patch(route('bots.toggle', bot.id), {}, { preserveScroll: true });
    };

    const destroy = (bot) => {
        if (confirm(`¿Eliminar el bot "${bot.name}"?`)) {
            router.delete(route('bots.destroy', bot.id), { preserveScroll: true });
        }
    };

    const StatusBadge = ({ bot }) => (
        <button
            onClick={() => toggle(bot)}
            className={
                'inline-flex rounded-full px-3 py-1 text-xs font-semibold ' +
                (bot.is_active
                    ? 'bg-green-100 text-green-800'
                    : 'bg-gray-100 text-gray-600')
            }
        >
            {bot.is_active ? 'Activo' : 'Inactivo'}
        </button>
    );

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Bots
                    </h2>
                    <Link href={route('bots.create')} className="sm:w-auto">
                        <PrimaryButton className="w-full justify-center sm:w-auto">
                            Nuevo bot
                        </PrimaryButton>
                    </Link>
                </div>
            }
        >
            <Head title="Bots" />

            <div className="py-6 sm:py-12">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800">
                            {flash.success}
                        </div>
                    )}

                    {bots.length === 0 ? (
                        <div className="overflow-hidden rounded-lg bg-white p-6 text-gray-600 shadow-sm">
                            Aún no tienes bots. Crea el primero con el botón
                            «Nuevo bot».
                        </div>
                    ) : (
                        <>
                            {/* Móvil: tarjetas */}
                            <div className="space-y-3 sm:hidden">
                                {bots.map((bot) => (
                                    <div
                                        key={bot.id}
                                        className="rounded-lg bg-white p-4 shadow-sm"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <div className="truncate font-medium text-gray-900">
                                                    {bot.name}
                                                </div>
                                                <div className="font-mono text-xs text-gray-500">
                                                    #{bot.id}
                                                </div>
                                            </div>
                                            <StatusBadge bot={bot} />
                                        </div>

                                        <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                            <div className="col-span-2">
                                                <dt className="text-xs uppercase tracking-wider text-gray-400">
                                                    Pares
                                                </dt>
                                                <dd className="break-words text-gray-700">
                                                    {(bot.symbols ?? []).join(', ') || '—'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs uppercase tracking-wider text-gray-400">
                                                    Dirección
                                                </dt>
                                                <dd className="text-gray-700">
                                                    {bot.direction}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs uppercase tracking-wider text-gray-400">
                                                    Lotaje
                                                </dt>
                                                <dd className="text-gray-700">
                                                    {bot.lot_size}
                                                </dd>
                                            </div>
                                        </dl>

                                        <div className="mt-4 flex items-center justify-end gap-4 border-t border-gray-100 pt-3 text-sm">
                                            <Link
                                                href={route('bots.edit', bot.id)}
                                                className="text-indigo-600 hover:text-indigo-900"
                                            >
                                                Editar
                                            </Link>
                                            <button
                                                onClick={() => destroy(bot)}
                                                className="text-red-600 hover:text-red-900"
                                            >
                                                Eliminar
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {/* Escritorio: tabla */}
                            <div className="hidden overflow-hidden bg-white shadow-sm sm:block sm:rounded-lg">
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    ID
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Nombre
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Pares
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Dirección
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Lotaje
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Estado
                                                </th>
                                                <th className="px-6 py-3" />
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200 bg-white">
                                            {bots.map((bot) => (
                                                <tr key={bot.id}>
                                                    <td className="whitespace-nowrap px-6 py-4 font-mono text-sm text-gray-500">
                                                        #{bot.id}
                                                    </td>
                                                    <td className="whitespace-nowrap px-6 py-4 font-medium text-gray-900">
                                                        {bot.name}
                                                    </td>
                                                    <td className="px-6 py-4 text-sm text-gray-600">
                                                        {(bot.symbols ?? []).join(', ') || '—'}
                                                    </td>
                                                    <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                                        {bot.direction}
                                                    </td>
                                                    <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                                        {bot.lot_size}
                                                    </td>
                                                    <td className="whitespace-nowrap px-6 py-4">
                                                        <StatusBadge bot={bot} />
                                                    </td>
                                                    <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                                                        <Link
                                                            href={route('bots.edit', bot.id)}
                                                            className="text-indigo-600 hover:text-indigo-900"
                                                        >
                                                            Editar
                                                        </Link>
                                                        <button
                                                            onClick={() => destroy(bot)}
                                                            className="ms-4 text-red-600 hover:text-red-900"
                                                        >
                                                            Eliminar
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </>
                    )}

                    {/* Avisos del worker: operaciones abiertas / rechazadas */}
                    {reports.length > 0 && (
                        <div className="overflow-hidden rounded-lg bg-white shadow-sm">
                            <div className="border-b border-gray-100 px-6 py-4">
                                <h3 className="font-semibold text-gray-800">
                                    Últimas operaciones y avisos
                                </h3>
                                <p className="mt-1 text-xs text-gray-500">
                                    Reportes que envía el bot al intentar abrir cada operación.
                                </p>
                            </div>
                            <div className="divide-y divide-gray-100">
                                {reports.map((r) => (
                                    <div
                                        key={r.id}
                                        className="flex flex-wrap items-center justify-between gap-2 px-6 py-3 text-sm"
                                    >
                                        <div className="flex flex-wrap items-center gap-3">
                                            <span className="font-medium text-gray-900">
                                                {r.bot?.name ?? `Bot #${r.bot_id}`}
                                            </span>
                                            <span className="text-gray-700">{r.symbol}</span>
                                            {r.direction && (
                                                <span className={`rounded px-2 py-0.5 text-xs font-semibold ${r.direction === 'buy' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                                    {r.direction.toUpperCase()}
                                                </span>
                                            )}
                                            {r.error && (
                                                <span className="text-xs text-gray-500">{r.error}</span>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${REPORT_STYLES[r.status] ?? 'bg-gray-100 text-gray-600'}`}>
                                                {REPORT_LABELS[r.status] ?? r.status}
                                            </span>
                                            <span className="text-xs text-gray-400">
                                                {new Date(r.created_at).toLocaleString()}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
