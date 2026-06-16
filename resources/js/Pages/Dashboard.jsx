import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Dashboard({ stats }) {
    const total = stats?.total ?? 0;
    const active = stats?.active ?? 0;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="text-sm font-medium text-gray-500">
                                Bots totales
                            </div>
                            <div className="mt-2 text-3xl font-bold text-gray-900">
                                {total}
                            </div>
                        </div>
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="text-sm font-medium text-gray-500">
                                Bots activos
                            </div>
                            <div className="mt-2 text-3xl font-bold text-green-600">
                                {active}
                            </div>
                        </div>
                    </div>

                    <div className="rounded-lg bg-white p-6 text-gray-700 shadow-sm">
                        Gestiona la configuración de tus bots desde la sección{' '}
                        <Link
                            href={route('bots.index')}
                            className="font-medium text-indigo-600 hover:text-indigo-900"
                        >
                            Bots
                        </Link>
                        . El runner externo consulta la configuración activa vía la API.
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
