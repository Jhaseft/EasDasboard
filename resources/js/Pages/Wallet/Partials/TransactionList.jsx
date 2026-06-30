import { formatMoney } from '@/lib/format';

const TYPE_LABELS = {
    deposit: 'Recarga',
    withdrawal: 'Retiro',
    charge: 'Cobro',
    payout: 'Ingreso',
    fee: 'Comisión',
};

function TransactionRow({ transaction: t }) {
    const positive = Number(t.amount) >= 0;

    return (
        <div className="flex items-center justify-between py-2 text-sm">
            <div>
                <div className="font-medium text-gray-800">{TYPE_LABELS[t.type] ?? t.type}</div>
                <div className="text-xs text-gray-500">{t.description}</div>
            </div>
            <div className="text-right">
                <div className={positive ? 'font-semibold text-green-600' : 'font-semibold text-red-600'}>
                    {positive ? '+' : ''}
                    {formatMoney(t.amount)}
                </div>
                <div className="text-xs text-gray-400">Saldo: ${formatMoney(t.balance_after)}</div>
            </div>
        </div>
    );
}

export default function TransactionList({ transactions }) {
    return (
        <div className="rounded-lg bg-white p-6 shadow-sm">
            <div className="mb-3 text-sm font-medium text-gray-700">Movimientos</div>
            {transactions.length === 0 ? (
                <p className="text-sm text-gray-500">Aún no hay movimientos.</p>
            ) : (
                <div className="divide-y divide-gray-100">
                    {transactions.map((t) => (
                        <TransactionRow key={t.id} transaction={t} />
                    ))}
                </div>
            )}
        </div>
    );
}
