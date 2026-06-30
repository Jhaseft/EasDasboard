import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { formatMoney } from '@/lib/format';
import { useForm } from '@inertiajs/react';

export default function BinanceDepositCard({ networks = [], defaultNetwork = 'TRX' }) {
    const { data, setData, post, processing, errors } = useForm({
        amount: '50',
        network: defaultNetwork,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('wallet.binance.store')); // redirige a la página de depósito
    };

    return (
        <div className="rounded-lg bg-white p-6 shadow-sm">
            <div className="flex items-center gap-2">
                <div className="text-sm font-medium text-gray-700">Recargar con USDT</div>
                <span className="rounded bg-yellow-50 px-1.5 py-0.5 text-xs font-medium text-yellow-700">
                    Binance
                </span>
            </div>
            <p className="mt-1 text-xs text-gray-400">Paga con USDT desde tu Binance o wallet. 1 USDT = 1 USD.</p>

            <form onSubmit={submit} className="mt-4 space-y-4">
                <div className="flex flex-wrap items-end gap-3">
                    <div>
                        <label className="text-xs font-medium text-gray-600">Monto (USD)</label>
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
                        {processing ? 'Generando…' : 'Recargar con USDT'}
                    </PrimaryButton>
                </div>

                {networks.length > 0 && (
                    <div>
                        <label className="text-xs font-medium text-gray-600">Red</label>
                        <div className="mt-1 flex flex-wrap gap-2">
                            {networks.map((n) => (
                                <button
                                    key={n.network}
                                    type="button"
                                    onClick={() => setData('network', n.network)}
                                    className={
                                        'rounded-md border px-3 py-1.5 text-sm transition ' +
                                        (data.network === n.network
                                            ? 'border-yellow-500 bg-yellow-50 font-medium text-yellow-700'
                                            : 'border-gray-200 text-gray-600 hover:bg-gray-50')
                                    }
                                >
                                    {n.label}
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                {Number(data.amount) > 0 && (
                    <p className="text-xs text-gray-400">
                        Enviarás{' '}
                        <span className="font-semibold text-gray-600">{formatMoney(data.amount)} USDT</span> y
                        recibirás <span className="font-semibold text-gray-600">${formatMoney(data.amount)}</span>.
                    </p>
                )}
            </form>
        </div>
    );
}
