import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { formatMoney } from '@/lib/format';
import { useForm } from '@inertiajs/react';

export default function WalletBalanceCard({ wallet, exchangeRate = 6.9 }) {
    const { data, setData, post, processing, errors } = useForm({ amount: '50' });

    const submit = (e) => {
        e.preventDefault();
        post(route('wallet.qr.store')); // redirige a la página de pago del QR
    };

    return (
        <div className="rounded-lg bg-white p-6 shadow-sm">
            <div className="text-sm font-medium text-gray-500">Saldo disponible</div>
            <div className="mt-1 text-4xl font-bold text-gray-900">
                ${formatMoney(wallet.balance)}{' '}
                <span className="text-base font-normal text-gray-400">{wallet.currency}</span>
            </div>

            <form onSubmit={submit} className="mt-4 flex flex-wrap items-end gap-3 border-t border-gray-100 pt-4">
                <div>
                    <label className="text-xs font-medium text-gray-600">Recargar con QR (USD)</label>
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
                <PrimaryButton disabled={processing}>
                    {processing ? 'Generando…' : 'Pagar con QR'}
                </PrimaryButton>
                {Number(data.amount) > 0 && (
                    <p className="w-full text-xs text-gray-400">
                        Pagarás aprox.{' '}
                        <span className="font-semibold text-gray-600">
                            Bs {formatMoney(Number(data.amount) * exchangeRate)}
                        </span>{' '}
                        con tu app bancaria.
                    </p>
                )}
            </form>
        </div>
    );
}
