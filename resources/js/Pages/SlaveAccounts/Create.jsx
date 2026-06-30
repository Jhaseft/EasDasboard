import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ masters, regions }) {
    const { data, setData, post, processing, errors } = useForm({
        master_account_id: masters[0]?.id ?? '',
        name: '',
        platform: 'mt5',
        login: '',
        server: '',
        password: '',
        region: regions[0] ?? 'new-york',
        lot_multiplier: '1.0',
        auto_copy: true,
        copy_mode: 'multiplier',
        fixed_lot: '0.01',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('slave-accounts.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Conectar cuenta esclava
                </h2>
            }
        >
            <Head title="Conectar cuenta esclava" />

            <div className="py-4">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded-lg bg-white p-4 shadow-sm sm:p-6">
                        <p className="mb-6 text-sm text-gray-600">
                            La cuenta esclava recibirá copias de las operaciones
                            de la cuenta maestra seleccionada. El lote se calcula
                            multiplicando el lote de la maestra por el{' '}
                            <strong>multiplicador</strong> que configures.
                        </p>

                        <form onSubmit={submit} className="space-y-5">
                            <div>
                                <InputLabel htmlFor="master_account_id" value="Cuenta maestra" />
                                <select
                                    id="master_account_id"
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={data.master_account_id}
                                    onChange={(e) => setData('master_account_id', e.target.value)}
                                >
                                    {masters.map((m) => (
                                        <option key={m.id} value={m.id}>
                                            {m.name} ({m.platform.toUpperCase()})
                                        </option>
                                    ))}
                                </select>
                                <InputError className="mt-2" message={errors.master_account_id} />
                            </div>

                            <div>
                                <InputLabel htmlFor="name" value="Nombre (alias)" />
                                <TextInput
                                    id="name"
                                    className="mt-1 block w-full"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Cuenta alumno 1"
                                />
                                <InputError className="mt-2" message={errors.name} />
                            </div>

                            <div>
                                <InputLabel htmlFor="platform" value="Plataforma" />
                                <select
                                    id="platform"
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={data.platform}
                                    onChange={(e) => setData('platform', e.target.value)}
                                >
                                    <option value="mt5">MetaTrader 5</option>
                                    <option value="mt4">MetaTrader 4</option>
                                </select>
                                <InputError className="mt-2" message={errors.platform} />
                            </div>

                            <div>
                                <InputLabel htmlFor="login" value="Login (número de cuenta)" />
                                <TextInput
                                    id="login"
                                    className="mt-1 block w-full"
                                    value={data.login}
                                    onChange={(e) => setData('login', e.target.value)}
                                    placeholder="12345678"
                                />
                                <InputError className="mt-2" message={errors.login} />
                            </div>

                            <div>
                                <InputLabel htmlFor="server" value="Servidor del broker" />
                                <TextInput
                                    id="server"
                                    className="mt-1 block w-full"
                                    value={data.server}
                                    onChange={(e) => setData('server', e.target.value)}
                                    placeholder="ICMarkets-Live01"
                                />
                                <InputError className="mt-2" message={errors.server} />
                            </div>

                            <div>
                                <InputLabel htmlFor="password" value="Contraseña (no se guarda)" />
                                <TextInput
                                    id="password"
                                    type="password"
                                    className="mt-1 block w-full"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                />
                                <InputError className="mt-2" message={errors.password} />
                            </div>

                            <div>
                                <InputLabel htmlFor="region" value="Región del servidor cloud" />
                                <select
                                    id="region"
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={data.region}
                                    onChange={(e) => setData('region', e.target.value)}
                                >
                                    {regions.map((r) => (
                                        <option key={r} value={r}>{r}</option>
                                    ))}
                                </select>
                                <InputError className="mt-2" message={errors.region} />
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
                                    Si está activo, el sistema replica automáticamente las
                                    aperturas y cierres de la maestra. Si lo desactivas,
                                    la cuenta solo se copia manualmente.
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
                                    <p className="mt-1 text-xs text-gray-500">
                                        Ignora el tamaño de la maestra y abre siempre este lote.
                                    </p>
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
                                        Ej: 0.5 abre la mitad del lote de la maestra · 2.0 abre el doble
                                    </p>
                                    <InputError className="mt-2" message={errors.lot_multiplier} />
                                </div>
                            )}

                            <div className="flex items-center justify-end">
                                <PrimaryButton disabled={processing}>
                                    Conectar cuenta esclava
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
