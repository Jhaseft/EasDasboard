import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function Index({ bots }) {
    const flash = usePage().props.flash ?? {};

    const toggle = (bot) => {
        router.patch(route('bots.toggle', bot.id), {}, { preserveScroll: true });
    };

    const destroy = (bot) => {
        if (confirm(`¿Eliminar el bot "${bot.name}"?`)) {
            router.delete(route('bots.destroy', bot.id), { preserveScroll: true });
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Bots
                    </h2>
                    <Link href={route('bots.create')}>
                        <PrimaryButton>Nuevo bot</PrimaryButton>
                    </Link>
                </div>
            }
        >
            <Head title="Bots" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800">
                            {flash.success}
                        </div>
                    )}

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        {bots.length === 0 ? (
                            <div className="p-6 text-gray-600">
                                Aún no tienes bots. Crea el primero con el botón
                                «Nuevo bot».
                            </div>
                        ) : (
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
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
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
