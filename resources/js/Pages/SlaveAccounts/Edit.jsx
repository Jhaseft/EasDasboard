import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Edit({ slave }) {
    const { data, setData, put, processing, errors } = useForm({
        name: slave.name ?? '',
        lot_multiplier: slave.lot_multiplier ?? '1.0',
        auto_copy: !!slave.auto_copy,
        copy_mode: slave.copy_mode ?? 'multiplier',
        fixed_lot: slave.fixed_lot ?? '0.01',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('slave-accounts.update', slave.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Editar cuenta esclava
                </h2>
            }
        >
            <Head title="Editar cuenta esclava" />

            <div className="py-4">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded-lg bg-white p-4 shadow-sm sm:p-6">
                        <div className="mb-6 text-sm text-gray-600">
                            <p>
                                Editando los ajustes de copia de{' '}
                                <strong>{slave.platform?.toUpperCase()} · {slave.login}</strong>
                                {slave.master?.name && (
                                    <> (copia de <strong>{slave.master.name}</strong>)</>
                                )}.
                            </p>
                            <p className="mt-1 text-xs text-gray-500">
                                Para cambiar login, servidor o contraseña debes desconectar
                                y volver a conectar la cuenta (eso re-provisiona en MetaApi).
                            </p>
                        </div>

                        <form onSubmit={submit} className="space-y-5">
                            <div>
                                <InputLabel htmlFor="name" value="Nombre (alias)" />
                                <TextInput
                                    id="name"
                                    className="mt-1 block w-full"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                />
                                <InputError className="mt-2" message={errors.name} />
                            </div>

                            <div className="rounded-md border border-gray-200 bg-gray-50 p-3">
                                <label className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        checked={data.auto_copy}
                                        onChange={(e) => setData('auto_copy', e.target.checked)}
                                    />
                                    <span className="text-sm font-medium text-gray-800">
                                        Copia automática (tiempo real)
                                    </span>
                                </label>
                                <p className="mt-1 text-xs text-gray-500">
                                    Si está activo, replica automáticamente aperturas y cierres
                                    de la maestra.
                                </p>
                            </div>

                            <div>
                                <InputLabel htmlFor="copy_mode" value="Modo de lote" />
                                <select
                                    id="copy_mode"
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={data.copy_mode}
                                    onChange={(e) => setData('copy_mode', e.target.value)}
                                >
                                    <option value="multiplier">Proporcional (multiplicador)</option>
                                    <option value="fixed">Lote fijo</option>
                                </select>
                                <InputError className="mt-2" message={errors.copy_mode} />
                            </div>

                            {data.copy_mode === 'fixed' ? (
                                <div>
                                    <InputLabel htmlFor="fixed_lot" value="Lote fijo" />
                                    <TextInput
                                        id="fixed_lot"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        className="mt-1 block w-full"
                                        value={data.fixed_lot}
                                        onChange={(e) => setData('fixed_lot', e.target.value)}
                                    />
                                    <InputError className="mt-2" message={errors.fixed_lot} />
                                </div>
                            ) : (
                                <div>
                                    <InputLabel htmlFor="lot_multiplier" value="Multiplicador de lote" />
                                    <TextInput
                                        id="lot_multiplier"
                                        type="number"
                                        step="0.0001"
                                        min="0.0001"
                                        className="mt-1 block w-full"
                                        value={data.lot_multiplier}
                                        onChange={(e) => setData('lot_multiplier', e.target.value)}
                                    />
                                    <p className="mt-1 text-xs text-gray-500">
                                        Ej: 0.5 abre la mitad del lote de la maestra · 2.0 el doble
                                    </p>
                                    <InputError className="mt-2" message={errors.lot_multiplier} />
                                </div>
                            )}

                            <div className="flex items-center justify-between">
                                <Link
                                    href={route('slave-accounts.index')}
                                    className="text-sm text-gray-500 hover:text-gray-800"
                                >
                                    Cancelar
                                </Link>
                                <PrimaryButton disabled={processing}>
                                    Guardar cambios
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
