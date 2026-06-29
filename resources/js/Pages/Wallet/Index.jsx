import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';

const TYPE_LABELS = {
    deposit: 'Recarga',
    withdrawal: 'Retiro',
    charge: 'Cobro',
    payout: 'Ingreso',
    fee: 'Comisión',
};

export default function Index({ wallet, transactions }) {
    const flash = usePage().props.flash ?? {};
    const { data, setData, post, processing, errors, reset } = useForm({ amount: '50' });

    const deposit = (e) => {
        e.preventDefault();
        post(route('wallet.deposit'), { preserveScroll: true, onSuccess: () => reset('amount') });
    };

    const fmt = (n) =>
        new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">Billetera</h2>
            }
        >
            <Head title="Billetera" />

            <div className="py-6 sm:py-12">
                <div className="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800">
                            {flash.success}
                        </div>
                    )}

                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <div className="text-sm font-medium text-gray-500">Saldo disponible</div>
                        <div className="mt-1 text-4xl font-bold text-gray-900">
                            ${fmt(wallet.balance)} <span className="text-base font-normal text-gray-400">{wallet.currency}</span>
                        </div>

                        <form onSubmit={deposit} className="mt-4 flex flex-wrap items-end gap-3 border-t border-gray-100 pt-4">
                            <div>
                                <label className="text-xs font-medium text-gray-600">Recargar (USD)</label>
                                <TextInput
                                    type="number"
                                    step="1"
                                    min="1"
                                    className="mt-1 block w-40"
                                    value={data.amount}
                                    onChange={(e) => setData('amount', e.target.value)}
                                />
                                <InputError className="mt-1" message={errors.amount} />
                            </div>
                            <PrimaryButton disabled={processing}>Recargar</PrimaryButton>
                            <p className="w-full text-xs text-gray-400">
                                Recarga manual de prueba. En producción se hará vía Stripe.
                            </p>
                        </form>
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <div className="mb-3 text-sm font-medium text-gray-700">Movimientos</div>
                        {transactions.length === 0 ? (
                            <p className="text-sm text-gray-500">Aún no hay movimientos.</p>
                        ) : (
                            <div className="divide-y divide-gray-100">
                                {transactions.map((t) => (
                                    <div key={t.id} className="flex items-center justify-between py-2 text-sm">
                                        <div>
                                            <div className="font-medium text-gray-800">
                                                {TYPE_LABELS[t.type] ?? t.type}
                                            </div>
                                            <div className="text-xs text-gray-500">{t.description}</div>
                                        </div>
                                        <div className="text-right">
                                            <div className={Number(t.amount) >= 0 ? 'font-semibold text-green-600' : 'font-semibold text-red-600'}>
                                                {Number(t.amount) >= 0 ? '+' : ''}{fmt(t.amount)}
                                            </div>
                                            <div className="text-xs text-gray-400">Saldo: ${fmt(t.balance_after)}</div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
