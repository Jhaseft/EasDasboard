import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ regions }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        platform: 'mt5',
        login: '',
        server: '',
        password: '',
        region: regions[0] ?? 'new-york',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('broker-accounts.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Conectar cuenta de broker
                </h2>
            }
        >
            <Head title="Conectar broker" />

            <div className="py-6 sm:py-12">
                <div className="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded-lg bg-white p-4 shadow-sm sm:p-6">
                        <p className="mb-6 text-sm text-gray-600">
                            Tus credenciales se envían directamente a MetaApi, que
                            opera el terminal en la nube. La contraseña{' '}
                            <strong>no se guarda</strong> en nuestra base de datos.
                            Usa preferiblemente la contraseña de inversor/maestra
                            según el acceso que quieras dar.
                        </p>

                        <form onSubmit={submit} className="space-y-5">
                            <div>
                                <InputLabel htmlFor="name" value="Nombre (alias)" />
                                <TextInput
                                    id="name"
                                    className="mt-1 block w-full"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Mi cuenta FTMO"
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
                                    placeholder="FTMO-Demo"
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
                                        <option key={r} value={r}>
                                            {r}
                                        </option>
                                    ))}
                                </select>
                                <InputError className="mt-2" message={errors.region} />
                            </div>

                            <div className="flex items-center justify-end">
                                <PrimaryButton disabled={processing}>
                                    Conectar cuenta
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
